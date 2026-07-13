<?php

namespace App\Http\Controllers;

use App\Events\CitaAgendada;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Mail\CuentaCreadaDesdeChat;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use App\Services\CitaComprobanteService;
use App\Services\PagoService;
use App\Services\PriorityEvaluator;
use App\Services\ProfessionalScheduleService;
use App\Services\SlotHoldService;
use App\Support\ValidationRules;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ChatBotController extends Controller
{
    public function especialidades()
    {
        return response()->json(
            Especialidad::orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    public function doctoresPorEspecialidad(Especialidad $especialidad)
    {
        $rol = $especialidad->isLaboratorioClinico()
            ? 'laboratorio'
            : 'doctor';

        $doctores = User::query()
            ->role($rol)
            ->onlyActive()
            ->whereHas('especialidades', fn ($q) => $q->where('especialidades.id', $especialidad->id))
            ->orderBy('name')
            ->get(['id', 'name', 'precio_consulta', 'moneda']);

        if ($doctores->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'No contamos con médicos activos para esta especialidad de momento.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'especialidad' => $especialidad->nombre,
            'doctores' => $doctores->map(function ($d) {
                $precioDefinido = ! is_null($d->precio_consulta);
                $moneda = $d->moneda ?? 'USD';

                return [
                    'id' => $d->id,
                    'nombre' => $d->name,
                    'precio' => $precioDefinido ? (float) $d->precio_consulta : null,
                    'moneda' => $moneda,
                    'precio_format' => $precioDefinido
                        ? sprintf('$%s %s', number_format((float) $d->precio_consulta, 2), $moneda)
                        : 'Tarifa no disponible',
                ];
            })->values(),
        ]);
    }

    public function fechasDisponibles(User $doctor, ProfessionalScheduleService $scheduleService)
    {
        try {
            abort_unless($doctor->isActive() && ($doctor->hasRole('doctor') || $doctor->hasRole('laboratorio')), 404);

            $tz = config('app.timezone', 'America/Guayaquil');
            $hoy = Carbon::today($tz);
            if ($doctor->hasRole('laboratorio')) {
                $fechas = [];

                for ($i = 0; $i < 30; $i++) {
                    $fecha = $hoy->copy()->addDays($i)->toDateString();
                    $slotsLibres = $this->calcularSlotsDisponibles($doctor->id, $fecha);
                    if (empty($slotsLibres)) {
                        continue;
                    }

                    $fechas[] = [
                        'value' => $fecha,
                        'label' => Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM'),
                    ];

                    if (count($fechas) >= 7) {
                        break;
                    }
                }

                if (empty($fechas)) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Este médico no tiene horarios libres próximamente.',
                    ], 404);
                }

                return response()->json([
                    'ok' => true,
                    'doctor' => ['id' => $doctor->id, 'nombre' => $doctor->name],
                    'fechas' => $fechas,
                ]);
            }

            $horarios = Horario::where('doctor_id', $doctor->id)
                ->whereDate('fecha', '>=', $hoy)
                ->orderBy('fecha')
                ->limit(30)
                ->get()
                ->groupBy(fn ($h) => Carbon::parse($h->fecha)->toDateString());

            $fechas = [];
            foreach ($horarios as $fecha => $bloques) {
                $slotsLibres = $this->calcularSlotsDisponibles($doctor->id, $fecha, $bloques);
                if (empty($slotsLibres)) {
                    continue;
                }

                $fechas[] = [
                    'value' => $fecha,
                    'label' => Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM'),
                ];

                if (count($fechas) >= 7) {
                    break;
                }
            }

            if (empty($fechas)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Este médico no tiene horarios libres próximamente.',
                ], 404);
            }

            return response()->json([
                'ok' => true,
                'doctor' => ['id' => $doctor->id, 'nombre' => $doctor->name],
                'fechas' => $fechas,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en Chatbot al consultar fechasDisponibles', [
                'doctor_id' => $doctor->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Ocurrió un error inesperado al cargar las fechas disponibles.',
            ], 500);
        }
    }

    public function agendar(
        Request $request,
        PriorityEvaluator $priorityEvaluator,
        ProfessionalScheduleService $scheduleService,
        SlotHoldService $slotHoldService
    )
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'cedula' => ['required', 'digits:10'],
            'email' => ['required', 'email', 'max:255'],
            'telefono' => ['required', 'digits:10'],
            'paciente_id' => ['nullable', 'integer', 'exists:users,id'],
            'especialidad_id' => ['required', 'exists:especialidades,id'],
            'doctor_id' => ['required', 'integer', 'exists:users,id'],
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora' => ['required', 'date_format:H:i'],
            'hold_token' => ['nullable', 'string', 'max:80'],
            'motivo_consulta' => ValidationRules::motivoConsulta(false),
            'motivo' => array_merge(['required_without:motivo_consulta'], ValidationRules::motivoConsulta(false)),
            'crear_usuario' => ['required', 'boolean'],
        ]);

        $motivoConsulta = $priorityEvaluator->sanitizeMotivo($data['motivo_consulta'] ?? $data['motivo'] ?? null);

        $crearUsuario = array_key_exists('crear_usuario', $data) ? (bool) $data['crear_usuario'] : null;
        $identifiedPatientId = filled($data['paciente_id'] ?? null) ? (int) $data['paciente_id'] : null;
        $user = $request->user();
        $usuarioCreado = false;
        $credencialesEnviadas = false;
        $credencialesError = null;
        $passwordPlanoCredenciales = null;

        if ($user) {
            if (! empty($user->dni) && $user->dni !== $data['cedula']) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La cédula no coincide con tu perfil.',
                ], 403);
            }

            if (! empty($data['email']) && ! empty($user->email) && strcasecmp($user->email, $data['email']) !== 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El correo no coincide con tu perfil.',
                ], 403);
            }

            if (empty($user->email) && ! empty($data['email'])) {
                $user->email = $data['email'];
            }
            if (empty($user->dni)) {
                $user->dni = $data['cedula'];
            }

            if (! empty($data['telefono'])) {
                if (! empty($user->telefono) && $user->telefono !== $data['telefono']) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'El teléfono no coincide con tu perfil.',
                    ], 403);
                }
                if (empty($user->telefono)) {
                    $user->telefono = $data['telefono'];
                }
            }

            if (empty($user->name)) {
                $user->name = $data['nombre'];
            }

            if ($user->isDirty()) {
                $user->save();
            }
        } else {
            if ($identifiedPatientId) {
                $user = User::query()
                    ->role('paciente')
                    ->whereKey($identifiedPatientId)
                    ->first();

                if (! $user) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'No encontramos el paciente identificado para este agendamiento.',
                    ], 404);
                }
            } elseif ($crearUsuario === false) {
                $duplicatePatient = User::query()
                    ->role('paciente')
                    ->where(function ($query) use ($data) {
                        $query->where('dni', $data['cedula'])
                            ->orWhere('email', $data['email']);
                    })
                    ->first();

                if ($duplicatePatient) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Ya existe un paciente registrado con esos datos. Continúa con el flujo de paciente existente o crea tu usuario.',
                    ], 409);
                }

                $user = null;
            } else {
                $user = User::query()
                    ->role('paciente')
                    ->where('dni', $data['cedula'])
                    ->first();

                if (! $user && ! empty($data['email'])) {
                    $user = User::query()
                        ->role('paciente')
                        ->where('email', $data['email'])
                        ->first();
                }
            }

            if ($user) {
                if (! empty($data['cedula']) && ! empty($user->dni) && $user->dni !== $data['cedula']) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'La cédula no coincide con tu perfil.',
                    ], 403);
                }

                if (! empty($data['email']) && ! empty($user->email) && strcasecmp($user->email, $data['email']) !== 0) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'El correo no coincide con tu perfil.',
                    ], 403);
                }

                if (empty($user->email) && ! empty($data['email'])) {
                    $user->email = $data['email'];
                }
                if (empty($user->dni)) {
                    $user->dni = $data['cedula'];
                }

                if (! empty($data['telefono'])) {
                    if (! empty($user->telefono) && $user->telefono !== $data['telefono']) {
                        return response()->json([
                            'ok' => false,
                            'message' => 'El teléfono no coincide con tu perfil.',
                        ], 403);
                    }
                    if (empty($user->telefono)) {
                        $user->telefono = $data['telefono'];
                    }
                }

                if (empty($user->name)) {
                    $user->name = $data['nombre'];
                }

                if ($user->isDirty()) {
                    $user->save();
                }
            } else {
                if (empty($data['email'])) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Necesitamos un correo válido para registrar tu cita.',
                    ], 422);
                }

                $passwordPlano = $crearUsuario !== false
                    ? $data['cedula']
                    : Str::random(10);
                $user = User::create([
                    'name' => $data['nombre'],
                    'email' => $data['email'],
                    'password' => Hash::make($passwordPlano),
                    'telefono' => $data['telefono'],
                    'dni' => $data['cedula'],
                ]);

                $role = Role::where('name', 'paciente')->first();
                if ($role) {
                    $user->roles()->attach($role->id);
                }

                $usuarioCreado = true;
                $passwordPlanoCredenciales = $crearUsuario !== false ? $passwordPlano : null;
            }
        }

        if (empty($user->telefono)) {
            return response()->json([
                'ok' => false,
                'message' => 'Necesitamos un número de teléfono de 10 dígitos para contactarte.',
            ], 422);
        }

        if (app(PagoService::class)->pacienteTieneBloqueo((int) $user->id)) {
            return response()->json([
                'ok' => false,
                'message' => PagoService::MENSAJE_BLOQUEO,
            ], 423);
        }

        $doctor = User::query()
            ->onlyActive()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['doctor', 'laboratorio']))
            ->findOrFail($data['doctor_id']);

        if (! $doctor->especialidades()->where('especialidad_id', $data['especialidad_id'])->exists()) {
            return response()->json([
                'ok' => false,
                'message' => 'El doctor seleccionado no pertenece a esa especialidad.',
            ], 422);
        }

        $fecha = Carbon::parse($data['fecha'])->toDateString();
        $slot = Carbon::createFromFormat('H:i', $data['hora']);
        $validationError = $scheduleService->validateBookingSlot(
            professionalId: (int) $doctor->id,
            date: $fecha,
            slot: $slot,
            messages: [
                'missing_schedule' => 'No existe disponibilidad configurada para ese horario.',
                'misaligned' => 'La hora seleccionada no coincide con un bloque disponible.',
                'lead_time' => 'Debes agendar con al menos 1 hora de anticipacion.',
                'lead_time_field' => 'hora',
                'conflict' => 'El horario escogido ya no esta disponible.',
                'conflict_field' => 'hora',
            ],
            exceptHoldToken: $data['hold_token'] ?? null,
            timezone: config('app.timezone', 'America/Guayaquil')
        );

        if ($validationError) {
            return response()->json([
                'ok' => false,
                'message' => 'El horario escogido ya no está disponible.',
            ], 422);
        }

        try {
            $cita = DB::transaction(function () use (
                $user,
                $doctor,
                $data,
                $fecha,
                $slot,
                $motivoConsulta,
                $priorityEvaluator,
                $slotHoldService
            ) {
                $cita = Cita::create([
                    'paciente_id' => $user->id,
                    'doctor_id' => $doctor->id,
                    'especialidad_id' => $data['especialidad_id'],
                    'fecha' => $fecha,
                    'hora' => $slot->format('H:i:00'),
                    'motivo_consulta' => $motivoConsulta,
                    'estado' => Cita::ESTADO_PENDIENTE,
                    'activo' => true,
                ]);
                $priorityEvaluator->apply($cita);
                $cita->save();

                $slotHoldService->completeByToken(
                    token: $data['hold_token'] ?? null,
                    professionalId: (int) $doctor->id,
                    date: $fecha,
                    time: $slot->format('H:i:00')
                );

                return $cita->refresh();
            });
        } catch (QueryException $e) {
            $slotHoldService->releaseByToken(
                token: $data['hold_token'] ?? null,
                professionalId: (int) $doctor->id,
                date: $fecha,
                time: $slot->format('H:i:00')
            );

            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Mientras completabas el proceso, ese horario fue tomado por otro paciente. Por favor elige una nueva hora.',
                ], 422);
            }

            throw $e;
        }

        try {
            event(new CitaAgendada($cita));
            $cita->refresh();
            EnviarConfirmacionCitaJob::dispatch($cita);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error al notificar cita agendada en chatbot: ' . $e->getMessage(), [
                'cita_id' => $cita->id,
                'exception' => $e
            ]);
        }

        if ($passwordPlanoCredenciales && $user->email) {
            ['sent' => $credencialesEnviadas, 'error' => $credencialesError] = $this->enviarCredencialesChatbot($user, $passwordPlanoCredenciales);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Tu cita ha sido agendada correctamente. Conserva el comprobante para validarla en recepción.',
            'cita' => [
                'id' => $cita->id,
                'folio_cita' => $cita->folio_cita,
                'token_validacion' => $cita->token_validacion,
                'fecha' => $cita->fecha->format('d/m/Y'),
                'hora' => substr($cita->hora, 0, 5),
                'estado' => $cita->estado,
                'doctor' => $doctor->name,
                'especialidad' => $doctor->especialidades()->where('especialidad_id', $data['especialidad_id'])->value('nombre'),
            ],
            'usuario_creado' => $usuarioCreado,
            'credenciales_enviadas' => $credencialesEnviadas,
            'credenciales_error' => $credencialesError,
        ]);
    }

    public function registrarUsuario(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'cedula' => ['required', 'digits:10'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::query()
            ->role('paciente')
            ->where('dni', $data['cedula'])
            ->first();

        if (! $user) {
            $user = User::query()
                ->role('paciente')
                ->where('email', $data['email'])
                ->first();
        }

        $usuarioCreado = false;
        $credencialesEnviadas = false;
        $credencialesError = null;

        if ($user) {
            if (! empty($user->dni) && $user->dni !== $data['cedula']) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La cedula no coincide con el paciente registrado.',
                ], 409);
            }

            if (! empty($user->email) && strcasecmp((string) $user->email, $data['email']) !== 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El correo no coincide con el paciente registrado.',
                ], 409);
            }

            if (empty($user->name)) {
                $user->name = $data['nombre'];
            }
            if (empty($user->dni)) {
                $user->dni = $data['cedula'];
            }
            if (empty($user->email)) {
                $user->email = $data['email'];
            }

            if ($user->isDirty()) {
                $user->save();
            }
        } else {
            $user = User::create([
                'name' => $data['nombre'],
                'email' => $data['email'],
                'password' => Hash::make($data['cedula']),
                'dni' => $data['cedula'],
            ]);

            $role = Role::where('name', 'paciente')->first();
            if ($role) {
                $user->roles()->attach($role->id);
            }

            $usuarioCreado = true;

            ['sent' => $credencialesEnviadas, 'error' => $credencialesError] = $this->enviarCredencialesChatbot(
                $user,
                $data['cedula'],
                'Registramos tu usuario, pero no pudimos enviar el correo con tus credenciales.'
            );
        }

        return response()->json([
            'ok' => true,
            'message' => $usuarioCreado
                ? 'Usuario registrado correctamente.'
                : 'El usuario ya estaba registrado.',
            'usuario_creado' => $usuarioCreado,
            'credenciales_enviadas' => $credencialesEnviadas,
            'credenciales_error' => $credencialesError,
            'paciente' => [
                'id' => $user->id,
                'nombre' => $user->name,
                'email' => $user->email,
                'telefono' => $user->telefono,
            ],
        ]);
    }

    public function buscarCitas(Request $request)
    {
        $data = $request->validate([
            'cedula' => ['required', 'digits:10'],
            'email' => ['required', 'email', 'max:255'],
            'estado' => ['required', Rule::in(array_merge(Cita::ESTADOS, ['all']))],
            'incluir_todas' => ['required', 'boolean'],
        ]);

        if (($data['estado'] ?? null) === 'all') {
            $data['estado'] = null;
        }

        $user = $request->user();
        if ($user) {
            if (! empty($data['cedula']) && ! empty($user->dni) && $user->dni !== $data['cedula']) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La cédula no coincide con tu perfil.',
                    'citas' => [],
                ], 403);
            }

            if (! empty($data['email']) && ! empty($user->email) && strcasecmp($user->email, $data['email']) !== 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El correo no coincide con tu perfil.',
                    'citas' => [],
                ], 403);
            }
        } else {
            $user = User::query()
                ->role('paciente')
                ->where('dni', $data['cedula'])
                ->first();

            if (! $user && ! empty($data['email'])) {
                $user = User::query()
                    ->role('paciente')
                    ->where('email', $data['email'])
                    ->first();
            }

            if (! $user) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No encontramos un paciente con esos datos.',
                    'citas' => [],
                ], 404);
            }

            if (! empty($data['cedula']) && ! empty($user->dni) && $user->dni !== $data['cedula']) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La cédula no coincide con el paciente registrado.',
                    'citas' => [],
                ], 403);
            }

            if (! empty($data['email']) && ! empty($user->email) && strcasecmp($user->email, $data['email']) !== 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El correo no coincide con el paciente registrado.',
                    'citas' => [],
                ], 403);
            }
        }

        $hoy = Carbon::today(config('app.timezone', 'America/Guayaquil'));
        $modoHistorico = (bool) ($data['incluir_todas'] ?? false);

        $citas = Cita::with(['doctor:id,name', 'especialidad:id,nombre'])
            ->where('paciente_id', $user->id)
            ->when(! empty($data['estado']), function ($q) use ($data) {
                $q->where('estado', $data['estado']);
            });

        if (! $modoHistorico) {
            $citas->whereDate('fecha', '>=', $hoy)
                ->where('estado', '!=', Cita::ESTADO_REALIZADA)
                ->where('activo', true);
        }

        $citas = $citas->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        $respuesta = $citas->map(function ($cita) {
            return [
                'id' => $cita->id,
                'doctor_id' => $cita->doctor_id,
                'especialidad_id' => $cita->especialidad_id,
                'fecha' => $cita->fecha instanceof Carbon ? $cita->fecha->format('d/m/Y') : Carbon::parse($cita->fecha)->format('d/m/Y'),
                'hora' => substr($cita->hora, 0, 5),
                'estado' => $cita->estado,
                'doctor' => optional($cita->doctor)->name,
                'especialidad' => optional($cita->especialidad)->nombre,
            ];
        });

        if ($respuesta->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontraron citas con los filtros seleccionados.',
                'citas' => [],
                'paciente' => $user->name,
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Citas encontradas.',
            'paciente' => $user->name,
            'estado_consultado' => $data['estado'] ?? null,
            'citas' => $respuesta,
        ]);
    }

    public function cancelar(Request $request)
    {
        $isGuest = ! $request->user();
        $data = $request->validate([
            'cita_id' => ['required', 'integer', 'exists:citas_medicas,id'],
            'cedula' => [Rule::requiredIf($isGuest), 'digits:10'],
            'email' => [Rule::requiredIf($isGuest), 'email', 'max:255'],
        ]);

        $cita = Cita::with('paciente')->findOrFail($data['cita_id']);
        $user = $request->user();
        if ($user) {
            if ($cita->paciente_id !== $user->id) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No tienes permisos para cancelar esta cita.',
                ], 403);
            }
        } else {
            if (empty($data['cedula']) && empty($data['email'])) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Debes proporcionar tu cédula o correo para cancelar la cita.',
                ], 422);
            }

            $paciente = $cita->paciente;
            if (! $paciente) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No encontramos al paciente asociado a esta cita.',
                ], 404);
            }

            if (! empty($data['cedula']) && ! empty($paciente->dni) && $paciente->dni !== $data['cedula']) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La cédula no coincide con el paciente de la cita.',
                ], 403);
            }

            if (! empty($data['email']) && ! empty($paciente->email) && strcasecmp($paciente->email, $data['email']) !== 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El correo no coincide con el paciente de la cita.',
                ], 403);
            }
        }

        $transition = DB::transaction(function () use ($cita) {
            $cita = Cita::query()->whereKey($cita->id)->lockForUpdate()->firstOrFail();

            if (Carbon::parse($cita->fecha)->isPast()) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'No es posible cancelar citas pasadas.',
                    'cita' => $cita,
                ];
            }

            if (in_array($cita->estado, [Cita::ESTADO_CANCELADA, Cita::ESTADO_REALIZADA, Cita::ESTADO_NO_SE_PRESENTO], true)) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Esta cita ya no puede ser cancelada.',
                    'cita' => $cita,
                ];
            }

            $cita->estado = Cita::ESTADO_CANCELADA;
            $cita->activo = false;
            $cita->save();

            return ['ok' => true, 'status' => 200, 'message' => null, 'cita' => $cita->refresh()];
        });

        if (! $transition['ok']) {
            return response()->json([
                'ok' => false,
                'message' => $transition['message'],
            ], $transition['status']);
        }

        $cita = $transition['cita'];
        app(CitaComprobanteService::class)->sincronizarComprobante($cita);

        NotificarCambioEstadoCitaJob::dispatch($cita, 'cancelada', 'paciente');

        return response()->json([
            'ok' => true,
            'message' => 'Tu cita fue cancelada correctamente.',
        ]);
    }

    public function reagendar(
        Request $request,
        PriorityEvaluator $priorityEvaluator,
        ProfessionalScheduleService $scheduleService
    )
    {
        $isGuest = ! $request->user();
        $data = $request->validate([
            'cita_id' => ['required', 'integer', 'exists:citas_medicas,id'],
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora' => ['required', 'date_format:H:i'],
            'motivo_consulta' => ValidationRules::motivoConsulta(false),
            'cedula' => [Rule::requiredIf($isGuest), 'digits:10'],
            'email' => [Rule::requiredIf($isGuest), 'email', 'max:255'],
        ]);

        $cita = Cita::with('paciente')->findOrFail($data['cita_id']);
        $user = $request->user();
        if ($user) {
            if ($cita->paciente_id !== $user->id) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No tienes permisos para reprogramar esta cita.',
                ], 403);
            }
        } else {
            if (empty($data['cedula']) && empty($data['email'])) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Debes proporcionar tu cédula o correo para reprogramar la cita.',
                ], 422);
            }

            $paciente = $cita->paciente;
            if (! $paciente) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No encontramos al paciente asociado a esta cita.',
                ], 404);
            }

            if (! empty($data['cedula']) && ! empty($paciente->dni) && $paciente->dni !== $data['cedula']) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La cédula no coincide con el paciente de la cita.',
                ], 403);
            }

            if (! empty($data['email']) && ! empty($paciente->email) && strcasecmp($paciente->email, $data['email']) !== 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El correo no coincide con el paciente de la cita.',
                ], 403);
            }
        }

        if (Carbon::parse($cita->fecha)->isPast()) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pueden reprogramar citas pasadas.',
            ], 422);
        }

        $doctorActivo = User::query()
            ->onlyActive()
            ->whereKey($cita->doctor_id)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['doctor', 'laboratorio']))
            ->exists();

        if (! $doctorActivo) {
            return response()->json([
                'ok' => false,
                'message' => 'El profesional asignado a esta cita ya no esta activo. Comunicate con la clinica para reagendar con otro profesional.',
            ], 422);
        }

        $nuevaFecha = Carbon::parse($data['fecha'])->toDateString();

        $slot = Carbon::createFromFormat('H:i', $data['hora']);
        $validationError = $scheduleService->validateBookingSlot(
            professionalId: (int) $cita->doctor_id,
            date: $nuevaFecha,
            slot: $slot,
            messages: [
                'missing_schedule' => 'No existe disponibilidad configurada para ese horario.',
                'misaligned' => 'La hora seleccionada no coincide con un bloque disponible.',
                'lead_time' => 'Debes reagendar con al menos 1 hora de anticipacion.',
                'lead_time_field' => 'hora',
                'conflict' => 'Ese horario ya no esta disponible.',
                'conflict_field' => 'hora',
            ],
            exceptCitaId: (int) $cita->id,
            timezone: config('app.timezone', 'America/Guayaquil')
        );

        if ($validationError) {
            return response()->json([
                'ok' => false,
                'message' => 'Ese horario ya no está disponible.',
            ], 422);
        }

        $cita = DB::transaction(function () use ($cita, $nuevaFecha, $slot, $request, $priorityEvaluator) {
            $cita = Cita::query()->whereKey($cita->id)->lockForUpdate()->firstOrFail();

            $cita->fecha = $nuevaFecha;
            $cita->hora = $slot->format('H:i:00');
            $cita->estado = Cita::ESTADO_PENDIENTE;
            $cita->activo = true;

            if ($request->filled('motivo_consulta')) {
                $cita->motivo_consulta = $priorityEvaluator->sanitizeMotivo($request->input('motivo_consulta'));
            }

            $priorityEvaluator->apply($cita);
            $cita->save();

            return $cita->refresh();
        });
        app(CitaComprobanteService::class)->sincronizarComprobante($cita);

        NotificarCambioEstadoCitaJob::dispatch($cita, 'reagendada', 'paciente');

        return response()->json([
            'ok' => true,
            'message' => 'La cita fue reprogramada con éxito.',
        ]);
    }

    public function verificarPaciente(Request $request)
    {
        $cedula = preg_replace('/\s+/', '', (string) $request->input('cedula', ''));
        $emailInput = $request->input('email');
        $email = is_null($emailInput) ? null : trim((string) $emailInput);

        $data = validator(
            [
                'cedula' => $cedula,
                'email' => $email,
            ],
            [
                'cedula' => ['required', 'digits:10'],
                'email' => ['nullable', 'email', 'max:255'],
            ]
        )->validate();

        $emailProporcionado = filled($data['email'] ?? null);

        $user = $request->user();
        if ($user) {
            if (! empty($user->dni) && $user->dni !== $data['cedula']) {
                return response()->json([
                    'ok' => false,
                    'existe' => true,
                    'message' => 'La cédula no coincide con tu perfil.',
                ], 403);
            }

            if ($emailProporcionado && ! empty($user->email) && strcasecmp((string) $user->email, $data['email']) !== 0) {
                return response()->json([
                    'ok' => false,
                    'existe' => true,
                    'message' => 'El correo no coincide con tu perfil.',
                ], 403);
            }

            if ($emailProporcionado && empty($user->email)) {
                $user->email = $data['email'];
                $user->save();
            }
        } else {
            $user = User::query()
                ->role('paciente')
                ->where('dni', $data['cedula'])
                ->first();

            if (! $user) {
                return response()->json([
                    'ok' => false,
                    'existe' => false,
                    'message' => 'No encontramos pacientes registrados con esta cédula.',
                ], 404);
            }

            if ($emailProporcionado && ! empty($user->email) && strcasecmp((string) $user->email, $data['email']) !== 0) {
                return response()->json([
                    'ok' => false,
                    'existe' => true,
                    'message' => 'El correo no coincide con el paciente registrado.',
                ], 403);
            }

            if ($emailProporcionado && empty($user->email)) {
                $user->email = $data['email'];
                $user->save();
            }
        }

        return response()->json([
            'ok' => true,
            'existe' => true,
            'message' => 'Paciente identificado correctamente.',
            'paciente' => [
                'id' => $user->id,
                'nombre' => $user->name,
                'email' => $user->email,
                'telefono' => $user->telefono,
            ],
        ]);
    }

    public function enviarCodigoVerificacion(Request $request)
    {
        $data = $request->validate([
            'cedula' => ['required', 'digits:10'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = $request->user();
        if ($user) {
            if (! empty($user->dni) && $user->dni !== $data['cedula']) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La cédula no coincide con tu perfil.',
                ], 403);
            }

            if (empty($user->email)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Tu perfil no tiene un correo registrado.',
                ], 422);
            }

            if (strcasecmp((string) $user->email, $data['email']) !== 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El correo no coincide con tu perfil.',
                ], 403);
            }
        } else {
            $user = User::query()
                ->role('paciente')
                ->where('dni', $data['cedula'])
                ->first();

            if (! $user) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No encontramos un paciente con esos datos.',
                ], 404);
            }

            if (! empty($user->email) && strcasecmp((string) $user->email, $data['email']) !== 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El correo no coincide con el paciente registrado.',
                ], 403);
            }

            if (empty($user->email)) {
                $user->email = $data['email'];
                $user->save();
            }
        }

        $codigo = (string) random_int(100000, 999999);
        $cacheKey = $this->codigoCacheKey($user->dni ?? $data['cedula'], $user->email ?? $data['email']);
        Cache::put($cacheKey, $codigo, now()->addMinutes(10));

        Mail::raw("Tu código de verificación es {$codigo}. Vence en 10 minutos.", function ($message) use ($data) {
            $message->to($data['email'])
                ->subject('Código de verificación - Clínica');
        });

        return response()->json([
            'ok' => true,
            'message' => 'Hemos enviado un código de verificación a tu correo.',
        ]);
    }

    public function verificarCodigo(Request $request)
    {
        $data = $request->validate([
            'cedula' => ['required', 'digits:10'],
            'email' => ['required', 'email', 'max:255'],
            'codigo' => ['required', 'digits:6'],
        ]);

        $user = $request->user();
        if ($user) {
            if (! empty($user->dni) && $user->dni !== $data['cedula']) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La cédula no coincide con tu perfil.',
                ], 403);
            }

            if (empty($user->email) || strcasecmp((string) $user->email, $data['email']) !== 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El correo no coincide con tu perfil.',
                ], 403);
            }
        } else {
            $user = User::query()
                ->role('paciente')
                ->where('dni', $data['cedula'])
                ->first();

            if (! $user) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No encontramos un paciente con esos datos.',
                ], 404);
            }

            if (! empty($user->email) && strcasecmp((string) $user->email, $data['email']) !== 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El correo no coincide con el paciente registrado.',
                ], 403);
            }
        }

        $cacheKey = $this->codigoCacheKey($user->dni ?? $data['cedula'], $user->email ?? $data['email']);
        $codigoGuardado = Cache::get($cacheKey);

        if (is_null($codigoGuardado)) {
            return response()->json([
                'ok' => false,
                'message' => 'El código ya venció. Solicita uno nuevo.',
                'error' => 'codigo_vencido',
                'allow_resend' => true,
            ], 422);
        }

        if ($codigoGuardado !== $data['codigo']) {
            return response()->json([
                'ok' => false,
                'message' => 'El código ingresado no es correcto.',
                'error' => 'codigo_incorrecto',
            ], 422);
        }

        Cache::forget($cacheKey);

        return response()->json([
            'ok' => true,
            'message' => 'Código validado.',
            'paciente' => [
                'id' => $user->id,
                'nombre' => $user->name,
                'email' => $user->email,
                'telefono' => $user->telefono,
            ],
        ]);
    }

    public function perfil(Request $request)
    {
        $data = $request->validate([
            'cedula' => ['required', 'digits:10'],
            'email' => ['required', 'email', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        if ($user) {
            if (! empty($user->dni) && $user->dni !== $data['cedula']) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La cédula validada no coincide con tu perfil.',
                ], 403);
            }

            if (! empty($user->email) && strcasecmp($user->email, $data['email']) !== 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El correo validado no coincide con tu perfil.',
                ], 403);
            }
        } else {
            $user = User::query()
                ->role('paciente')
                ->where('dni', $data['cedula'])
                ->first();

            if (! $user) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No encontramos un paciente con esos datos.',
                ], 404);
            }

            if (! empty($user->email) && strcasecmp($user->email, $data['email']) !== 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El correo validado no coincide con el paciente registrado.',
                ], 403);
            }

            if (! empty($data['user_id']) && (int) $data['user_id'] !== (int) $user->id) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El perfil no coincide con el paciente validado.',
                ], 403);
            }
        }

        return response()->json([
            'ok' => true,
            'perfil' => [
                'id' => $user->id,
                'nombre' => $user->name,
                'email' => $user->email,
                'telefono' => $user->telefono,
                'dni' => $user->dni,
                'direccion' => $user->direccion,
                'fecha_nacimiento' => $user->fecha_nacimiento ? $user->fecha_nacimiento->toDateString() : null,
                'sexo' => $user->sexo,
            ],
            'sexos' => ['Masculino', 'Femenino', 'Otro'],
        ]);
    }

    public function actualizarPerfil(Request $request)
    {
        $identidad = $request->validate([
            'cedula' => ['required', 'digits:10'],
            'email_identidad' => ['required', 'email', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        if ($user) {
            if (! empty($user->dni) && $user->dni !== $identidad['cedula']) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La cédula validada no coincide con tu perfil.',
                ], 403);
            }

            if (! empty($user->email) && strcasecmp($user->email, $identidad['email_identidad']) !== 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El correo validado no coincide con tu perfil.',
                ], 403);
            }
        } else {
            $user = User::query()
                ->role('paciente')
                ->where('dni', $identidad['cedula'])
                ->first();

            if (! $user) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No encontramos un paciente con esos datos.',
                ], 404);
            }

            if (! empty($user->email) && strcasecmp($user->email, $identidad['email_identidad']) !== 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El correo validado no coincide con el paciente registrado.',
                ], 403);
            }

            if (! empty($identidad['user_id']) && (int) $identidad['user_id'] !== (int) $user->id) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El perfil no coincide con el paciente validado.',
                ], 403);
            }
        }

        $rules = [
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'telefono' => ValidationRules::telefono(),
            'dni' => ['required', 'digits:10', Rule::unique('users', 'dni')->ignore($user->id)],
            'direccion' => ['required', 'string', 'max:255'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'sexo' => ['required', Rule::in(['Masculino', 'Femenino', 'Otro'])],
            'current_password' => ['nullable', 'string'],
            'password' => array_merge(
                ValidationRules::passwordOptional(),
                ['different:current_password']
            ),
        ];

        $messages = [
            'telefono.digits' => 'El teléfono debe tener exactamente 10 dígitos.',
            'password.regex' => 'La contraseña debe incluir letras, números y al menos un carácter especial.',
        ];

        validator($request->all(), $rules, $messages)->validate();

        $user->fill([
            'name' => $request->input('nombre'),
            'email' => $request->input('email'),
            'telefono' => $request->input('telefono'),
            'dni' => $request->input('dni'),
            'direccion' => $request->input('direccion'),
            'fecha_nacimiento' => $request->input('fecha_nacimiento'),
            'sexo' => $request->input('sexo'),
        ]);

        $passwordChanged = false;
        if ($request->filled('password')) {
            if (! $request->filled('current_password') || ! Hash::check($request->input('current_password'), $user->password)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La contraseña actual no es correcta.',
                    'field' => 'current_password',
                ], 422);
            }

            $user->password = Hash::make($request->input('password'));
            $user->setRememberToken(Str::random(60));
            $passwordChanged = true;
        }

        $user->save();

        return response()->json([
            'ok' => true,
            'message' => 'Datos actualizados correctamente.',
            'perfil' => [
                'id' => $user->id,
                'nombre' => $user->name,
                'email' => $user->email,
                'telefono' => $user->telefono,
                'dni' => $user->dni,
                'direccion' => $user->direccion,
                'fecha_nacimiento' => $user->fecha_nacimiento ? $user->fecha_nacimiento->toDateString() : null,
                'sexo' => $user->sexo,
            ],
            'password_actualizado' => $passwordChanged,
        ]);
    }

    protected function enviarCredencialesChatbot(
        User $user,
        string $passwordPlano,
        string $errorMessage = 'La cita fue registrada, pero no pudimos enviar el correo con tus credenciales.'
    ): array
    {
        try {
            Mail::to($user->email)->send(new CuentaCreadaDesdeChat($user, $passwordPlano));

            return [
                'sent' => true,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            Log::error('Chatbot: no se pudo enviar el correo de credenciales.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'exception' => $exception,
            ]);

            return [
                'sent' => false,
                'error' => $errorMessage,
            ];
        }
    }

    protected function codigoCacheKey(string $cedula, string $email): string
    {
        return 'chatbot:codigo:'.sha1($cedula.'|'.strtolower($email));
    }

    protected function calcularSlotsDisponibles(int $doctorId, string $fecha, $bloques = null): array
    {
        return collect(app(ProfessionalScheduleService::class)->buildSlotsForDate(
            $doctorId,
            $fecha,
            config('app.timezone', 'America/Guayaquil')
        ))
            ->filter(fn ($slot) => ($slot['estado'] ?? null) === 'libre')
            ->pluck('hora')
            ->values()
            ->all();
    }
}
