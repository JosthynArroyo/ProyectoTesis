<?php

namespace App\Http\Controllers;

use App\Jobs\EnviarConfirmacionCitaJob;
use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Mail\CuentaCreadaDesdeChat;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ChatBotController extends Controller
{
    public function index()
    {
        return view('chatbot.index');
    }

    public function especialidades()
    {
        return response()->json(
            Especialidad::orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    public function doctoresPorEspecialidad(Especialidad $especialidad)
    {
        $doctores = User::doctors()
            ->onlyActive()
            ->whereHas('especialidades', fn($q) => $q->where('especialidades.id', $especialidad->id))
            ->orderBy('name')
            ->get(['id', 'name', 'precio_consulta', 'moneda']);

        if ($doctores->isEmpty()) {
            return response()->json([
                'ok'      => false,
                'message' => 'No contamos con medicos activos para esta especialidad de momento.',
            ], 404);
        }

        return response()->json([
            'ok'           => true,
            'especialidad' => $especialidad->nombre,
            'doctores'     => $doctores->map(function ($d) {
                $precioDefinido = !is_null($d->precio_consulta);
                $moneda = $d->moneda ?: 'USD';

                return [
                    'id'            => $d->id,
                    'nombre'        => $d->name,
                    'precio'        => $precioDefinido ? (float) $d->precio_consulta : null,
                    'moneda'        => $moneda,
                    'precio_format' => $precioDefinido
                        ? sprintf('$%s %s', number_format((float) $d->precio_consulta, 2), $moneda)
                        : 'Tarifa no disponible',
                ];
            })->values(),
        ]);
    }

    public function fechasDisponibles(User $doctor)
    {
        abort_unless($doctor->hasRole('doctor'), 404);

        $hoy = Carbon::today();
        $horarios = Horario::where('doctor_id', $doctor->id)
            ->whereDate('fecha', '>=', $hoy)
            ->orderBy('fecha')
            ->limit(30)
            ->get()
            ->groupBy(fn($h) => Carbon::parse($h->fecha)->toDateString());

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
                'ok'      => false,
                'message' => 'Este medico no tiene horarios libres proximamente.',
            ], 404);
        }

        return response()->json([
            'ok'     => true,
            'doctor' => ['id' => $doctor->id, 'nombre' => $doctor->name],
            'fechas' => $fechas,
        ]);
    }

    public function agendar(Request $request)
    {
        $data = $request->validate([
            'nombre'          => ['required','string','max:255'],
            'cedula'          => ['required','digits:10'],
            'email'           => ['nullable','email','max:255'],
            'telefono'        => ['nullable','digits:10'],
            'especialidad_id' => ['required','exists:especialidades,id'],
            'doctor_id'       => ['required','integer','exists:users,id'],
            'fecha'           => ['required','date_format:Y-m-d','after_or_equal:today'],
            'hora'            => ['required','date_format:H:i'],
            'motivo'          => ['nullable','string','max:500'],
        ]);

        $doctor = User::doctors()->onlyActive()->findOrFail($data['doctor_id']);

        if (! $doctor->especialidades()->where('especialidad_id', $data['especialidad_id'])->exists()) {
            return response()->json([
                'ok'      => false,
                'message' => 'El doctor seleccionado no pertenece a esa especialidad.',
            ], 422);
        }

        $fecha = Carbon::parse($data['fecha'])->toDateString();
        if (! $this->slotDisponible($doctor->id, $fecha, $data['hora'])) {
            return response()->json([
                'ok'      => false,
                'message' => 'El horario escogido ya no esta disponible.',
            ], 422);
        }

        $passwordPlano = null;
        $user = User::where('dni', $data['cedula'])->first();

        if ($user) {
            if (!empty($data['email'])) {
                if (!empty($user->email) && strcasecmp($user->email, $data['email']) !== 0) {
                    return response()->json([
                        'ok'      => false,
                        'message' => 'El correo no coincide con el paciente registrado para esa cedula.',
                    ], 422);
                }

                if (empty($user->email)) {
                    $user->email = $data['email'];
                }
            }
        } else {
            if (!empty($data['email'])) {
                $userPorEmail = User::where('email', $data['email'])->first();
                if ($userPorEmail) {
                    if (!empty($userPorEmail->dni) && $userPorEmail->dni !== $data['cedula']) {
                        return response()->json([
                            'ok'      => false,
                            'message' => 'Este correo ya esta vinculado a otro numero de cedula.',
                        ], 422);
                    }

                    $userPorEmail->dni = $userPorEmail->dni ?: $data['cedula'];
                    $user = $userPorEmail;
                }
            }
        }

        if ($user) {
            if (empty($user->email) && !empty($data['email'])) {
                $user->email = $data['email'];
            }

            $telefonoAsignado = $data['telefono'] ?: $user->telefono;
            if (empty($telefonoAsignado)) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Necesitamos un numero de telefono de 10 digitos para contactarte.',
                ], 422);
            }

            $user->telefono = $telefonoAsignado;
            $user->dni = $user->dni ?: $data['cedula'];
            $user->save();
        } else {
            if (empty($data['email'])) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Necesitamos un correo electronico valido para crear tu cuenta.',
                ], 422);
            }

            if (empty($data['telefono'])) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Ingresa un numero de telefono de 10 digitos.',
                ], 422);
            }

            $passwordPlano = Str::random(10);

            $user = User::create([
                'name'     => $data['nombre'],
                'email'    => $data['email'],
                'password' => bcrypt($passwordPlano),
                'dni'      => $data['cedula'],
                'telefono' => $data['telefono'],
                'status'   => 'active',
            ]);

            if ($rol = Role::where('name', 'paciente')->first()) {
                $user->roles()->syncWithoutDetaching([$rol->id]);
            }

            Mail::to($user->email)->queue(new CuentaCreadaDesdeChat($user, $passwordPlano));
        }

        try {
            $cita = Cita::create([
                'paciente_id'     => $user->id,
                'doctor_id'       => $doctor->id,
                'especialidad_id' => $data['especialidad_id'],
                'fecha'           => $fecha,
                'hora'            => $data['hora'],
                'estado'          => Cita::ESTADO_PENDIENTE,
                'activo'          => true,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE')) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Mientras completabas el proceso, ese horario fue tomado por otro paciente. Por favor elige una nueva hora.',
                ], 422);
            }

            throw $e;
        }

        EnviarConfirmacionCitaJob::dispatch($cita);

        return response()->json([
            'ok'   => true,
            'message' => 'Tu cita ha sido agendada correctamente.',
            'cita' => [
                'id'            => $cita->id,
                'fecha'         => $cita->fecha->format('d/m/Y'),
                'hora'          => substr($cita->hora, 0, 5),
                'estado'        => $cita->estado,
                'doctor'        => $doctor->name,
                'especialidad'  => $doctor->especialidades()->where('especialidad_id', $data['especialidad_id'])->value('nombre'),
            ],
            'usuario_creado' => (bool) $passwordPlano,
        ]);
    }

    public function buscarCitas(Request $request)
    {
        $data = $request->validate([
            'cedula'        => ['nullable','string','max:20'],
            'email'         => ['nullable','email','max:255'],
            'estado'        => ['nullable', Rule::in(Cita::ESTADOS)],
            'incluir_todas' => ['nullable','boolean'],
        ]);

        if (empty($data['cedula']) && empty($data['email'])) {
            return response()->json([
                'ok'      => false,
                'message' => 'Debes proporcionar tu cedula o tu correo.',
                'citas'   => [],
            ], 422);
        }

        $userQuery = User::query();

        if (!empty($data['email'])) {
            $userQuery->where('email', $data['email']);
        } elseif (!empty($data['cedula'])) {
            $userQuery->where('dni', $data['cedula']);
        }

        $user = $userQuery->first();

        if (! $user) {
            return response()->json([
                'ok'      => false,
                'message' => 'No encontramos pacientes con esos datos.',
                'citas'   => [],
            ]);
        }

        $hoy = Carbon::today();
        $modoHistorico = (bool) ($data['incluir_todas'] ?? false);

        $citas = Cita::with(['doctor:id,name', 'especialidad:id,nombre'])
            ->where('paciente_id', $user->id)
            ->when(!empty($data['estado']), function ($q) use ($data) {
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
                'id'              => $cita->id,
                'doctor_id'       => $cita->doctor_id,
                'especialidad_id' => $cita->especialidad_id,
                'fecha'           => $cita->fecha instanceof Carbon ? $cita->fecha->format('d/m/Y') : Carbon::parse($cita->fecha)->format('d/m/Y'),
                'hora'            => substr($cita->hora, 0, 5),
                'estado'          => $cita->estado,
                'doctor'          => optional($cita->doctor)->name,
                'especialidad'    => optional($cita->especialidad)->nombre,
            ];
        });

        if ($respuesta->isEmpty()) {
            return response()->json([
                'ok'       => false,
                'message'  => 'No se encontraron citas con los filtros seleccionados.',
                'citas'    => [],
                'paciente' => $user->name,
            ]);
        }

        return response()->json([
            'ok'       => true,
            'message'  => 'Citas encontradas.',
            'paciente' => $user->name,
            'estado_consultado' => $data['estado'] ?? null,
            'citas'    => $respuesta,
        ]);
    }

    public function cancelar(Request $request)
    {
        $data = $request->validate([
            'cita_id' => ['required','integer','exists:citas_medicas,id'],
        ]);

        $cita = Cita::findOrFail($data['cita_id']);

        if (Carbon::parse($cita->fecha)->isPast()) {
            return response()->json([
                'ok'      => false,
                'message' => 'No es posible cancelar citas pasadas.',
            ], 422);
        }

        $cita->estado = Cita::ESTADO_CANCELADA;
        $cita->activo = false;
        $cita->save();

        NotificarCambioEstadoCitaJob::dispatch($cita, 'cancelada', 'paciente');

        return response()->json([
            'ok'      => true,
            'message' => 'Tu cita fue cancelada correctamente.',
        ]);
    }

    public function reagendar(Request $request)
    {
        $data = $request->validate([
            'cita_id' => ['required','integer','exists:citas_medicas,id'],
            'fecha'   => ['required','date_format:Y-m-d','after_or_equal:today'],
            'hora'    => ['required','date_format:H:i'],
        ]);

        $cita = Cita::findOrFail($data['cita_id']);

        if (Carbon::parse($cita->fecha)->isPast()) {
            return response()->json([
                'ok'      => false,
                'message' => 'No se pueden reprogramar citas pasadas.',
            ], 422);
        }

        $nuevaFecha = Carbon::parse($data['fecha'])->toDateString();

        if (! $this->slotDisponible($cita->doctor_id, $nuevaFecha, $data['hora'])) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ese horario ya no esta disponible.',
            ], 422);
        }

        $cita->fecha  = $nuevaFecha;
        $cita->hora   = $data['hora'];
        $cita->estado = Cita::ESTADO_PENDIENTE;
        $cita->activo = true;
        $cita->save();

        NotificarCambioEstadoCitaJob::dispatch($cita, 'reagendada', 'paciente');

        return response()->json([
            'ok'      => true,
            'message' => 'La cita fue reprogramada con exito.',
        ]);
    }

    public function verificarPaciente(Request $request)
    {
        $data = $request->validate([
            'cedula' => ['required','digits:10'],
            'email'  => ['nullable','email','max:255'],
        ]);

        $paciente = User::where('dni', $data['cedula'])->first();

        if (! $paciente) {
            return response()->json([
                'ok'      => true,
                'existe'  => false,
                'message' => 'No encontramos pacientes registrados con esta cedula.',
            ]);
        }

        if (!empty($data['email']) && !empty($paciente->email) && strcasecmp($paciente->email, $data['email']) !== 0) {
            return response()->json([
                'ok'      => false,
                'existe'  => true,
                'message' => 'Este correo no coincide con el paciente registrado para esa cedula.',
                'paciente'=> [
                    'nombre' => $paciente->name,
                    'email'  => $paciente->email,
                ],
            ], 422);
        }

        return response()->json([
            'ok'      => true,
            'existe'  => true,
            'message' => 'Paciente identificado correctamente.',
            'paciente'=> [
                'id'       => $paciente->id,
                'nombre'   => $paciente->name,
                'email'    => $paciente->email,
                'telefono' => $paciente->telefono,
            ],
        ]);
    }

    public function enviarCodigoVerificacion(Request $request)
    {
        $data = $request->validate([
            'cedula' => ['required','digits:10'],
            'email'  => ['required','email','max:255'],
        ]);

        $paciente = User::where('dni', $data['cedula'])->first();

        if ($paciente && !empty($paciente->email) && strcasecmp($paciente->email, $data['email']) !== 0) {
            return response()->json([
                'ok'      => false,
                'message' => 'El correo no coincide con el paciente registrado para esa cedula.',
            ], 422);
        }

        $codigo = (string) random_int(100000, 999999);
        $cacheKey = $this->codigoCacheKey($data['cedula'], $data['email']);
        Cache::put($cacheKey, $codigo, now()->addMinutes(10));

        Mail::raw("Tu codigo de verificacion es {$codigo}. Vence en 10 minutos.", function ($message) use ($data) {
            $message->to($data['email'])
                ->subject('Codigo de verificacion - Clinica');
        });

        return response()->json([
            'ok'      => true,
            'message' => 'Hemos enviado un codigo de verificacion a tu correo.',
        ]);
    }

    public function verificarCodigo(Request $request)
    {
        $data = $request->validate([
            'cedula' => ['required','digits:10'],
            'email'  => ['required','email','max:255'],
            'codigo' => ['required','digits:6'],
        ]);

        $cacheKey = $this->codigoCacheKey($data['cedula'], $data['email']);
        $codigoGuardado = Cache::get($cacheKey);

        if (! $codigoGuardado || $codigoGuardado !== $data['codigo']) {
            return response()->json([
                'ok'      => false,
                'message' => 'El codigo ingresado no es correcto o ya vencio.',
            ], 422);
        }

        Cache::forget($cacheKey);

        $paciente = User::where('dni', $data['cedula'])->first();

        return response()->json([
            'ok'      => true,
            'message' => 'Codigo validado.',
            'paciente'=> $paciente ? [
                'id'       => $paciente->id,
                'nombre'   => $paciente->name,
                'email'    => $paciente->email,
                'telefono' => $paciente->telefono,
            ] : null,
        ]);
    }

    public function perfil(Request $request)
    {
        $data = $request->validate([
            'cedula'  => ['required','digits:10'],
            'email'   => ['required','email','max:255'],
            'user_id' => ['nullable','integer','exists:users,id'],
        ]);

        $user = User::query()
            ->when(!empty($data['user_id']), fn($q) => $q->where('id', $data['user_id']))
            ->where(function ($q) use ($data) {
                $q->where('dni', $data['cedula'])
                    ->orWhere('email', $data['email']);
            })
            ->first();

        if (! $user) {
            return response()->json([
                'ok'      => false,
                'message' => 'No encontramos un paciente con esos datos verificados.',
            ], 404);
        }

        if (!empty($user->dni) && $user->dni !== $data['cedula']) {
            return response()->json([
                'ok'      => false,
                'message' => 'La cedula validada no coincide con el paciente.',
            ], 422);
        }

        if (!empty($user->email) && strcasecmp($user->email, $data['email']) !== 0) {
            return response()->json([
                'ok'      => false,
                'message' => 'El correo validado no coincide con el paciente.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'perfil' => [
                'id'               => $user->id,
                'nombre'           => $user->name,
                'email'            => $user->email,
                'telefono'         => $user->telefono,
                'dni'              => $user->dni,
                'direccion'        => $user->direccion,
                'fecha_nacimiento' => $user->fecha_nacimiento ? $user->fecha_nacimiento->toDateString() : null,
                'sexo'             => $user->sexo,
            ],
            'sexos' => ['Masculino','Femenino','Otro'],
        ]);
    }

    public function actualizarPerfil(Request $request)
    {
        $identidad = $request->validate([
            'cedula'          => ['required','digits:10'],
            'email_identidad' => ['required','email','max:255'],
            'user_id'         => ['nullable','integer','exists:users,id'],
        ]);

        $user = User::query()
            ->when(!empty($identidad['user_id']), fn($q) => $q->where('id', $identidad['user_id']))
            ->where(function ($q) use ($identidad) {
                $q->where('dni', $identidad['cedula'])
                    ->orWhere('email', $identidad['email_identidad']);
            })
            ->first();

        if (! $user) {
            return response()->json([
                'ok'      => false,
                'message' => 'No encontramos un paciente con esos datos verificados.',
            ], 404);
        }

        if (!empty($user->dni) && $user->dni !== $identidad['cedula']) {
            return response()->json([
                'ok'      => false,
                'message' => 'La cedula validada no coincide con el paciente.',
            ], 422);
        }

        if (!empty($user->email) && strcasecmp($user->email, $identidad['email_identidad']) !== 0) {
            return response()->json([
                'ok'      => false,
                'message' => 'El correo validado no coincide con el paciente.',
            ], 422);
        }

        $rules = [
            'nombre'            => ['required','string','max:255'],
            'email'             => ['required','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'telefono'          => ['nullable','regex:/^\\d{10}$/'],
            'dni'               => ['required','digits:10', Rule::unique('users','dni')->ignore($user->id)],
            'direccion'         => ['nullable','string','max:255'],
            'fecha_nacimiento'  => ['required','date','before:today'],
            'sexo'              => ['nullable', Rule::in(['Masculino','Femenino','Otro'])],
            'current_password'  => ['nullable','string'],
            'password'          => [
                'nullable','string','min:8','confirmed','different:current_password',
                'regex:/^(?=.*[A-Za-z])(?=.*\\d)(?=.*[^A-Za-z0-9]).{8,}$/'
            ],
        ];

        $messages = [
            'telefono.regex' => 'El telefono debe tener exactamente 10 digitos.',
            'password.regex' => 'La contraseña debe incluir letras, numeros y al menos un caracter especial.',
        ];

        validator($request->all(), $rules, $messages)->validate();

        $user->fill([
            'name'             => $request->input('nombre'),
            'email'            => $request->input('email'),
            'telefono'         => $request->input('telefono'),
            'dni'              => $request->input('dni'),
            'direccion'        => $request->input('direccion'),
            'fecha_nacimiento' => $request->input('fecha_nacimiento'),
            'sexo'             => $request->input('sexo'),
        ]);

        $passwordChanged = false;
        if ($request->filled('password')) {
            if (! $request->filled('current_password') || ! Hash::check($request->input('current_password'), $user->password)) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'La contraseña actual no es correcta.',
                    'field'   => 'current_password',
                ], 422);
            }

            $user->password = Hash::make($request->input('password'));
            $user->setRememberToken(Str::random(60));
            $passwordChanged = true;
        }

        $user->save();

        return response()->json([
            'ok'      => true,
            'message' => 'Datos actualizados correctamente.',
            'perfil'  => [
                'id'               => $user->id,
                'nombre'           => $user->name,
                'email'            => $user->email,
                'telefono'         => $user->telefono,
                'dni'              => $user->dni,
                'direccion'        => $user->direccion,
                'fecha_nacimiento' => $user->fecha_nacimiento ? $user->fecha_nacimiento->toDateString() : null,
                'sexo'             => $user->sexo,
            ],
            'password_actualizado' => $passwordChanged,
        ]);
    }

    protected function codigoCacheKey(string $cedula, string $email): string
    {
        return 'chatbot:codigo:'.sha1($cedula.'|'.strtolower($email));
    }

    protected function slotDisponible(int $doctorId, string $fecha, string $hora): bool
    {
        $libres = $this->calcularSlotsDisponibles($doctorId, $fecha);
        return in_array($hora, $libres, true);
    }

    protected function calcularSlotsDisponibles(int $doctorId, string $fecha, $bloques = null): array
    {
        $bloques = $bloques ?: Horario::where('doctor_id', $doctorId)
            ->whereDate('fecha', $fecha)
            ->get();

        if ($bloques->isEmpty()) {
            return [];
        }

        $ocupadas = Cita::where('doctor_id', $doctorId)
            ->whereDate('fecha', $fecha)
            ->where('activo', true)
            ->pluck('hora')
            ->map(fn($hora) => substr($hora, 0, 5))
            ->toArray();

        $slots = [];
        foreach ($bloques as $horario) {
            $step = property_exists($horario, 'intervalo_minutos') && $horario->intervalo_minutos
                ? (int) $horario->intervalo_minutos
                : 30;

            $inicio = Carbon::parse("{$fecha} {$horario->hora_inicio}");
            $fin    = Carbon::parse("{$fecha} {$horario->hora_fin}");

            for ($cursor = $inicio->copy(); $cursor->lt($fin); $cursor->addMinutes($step)) {
                $hhmm = $cursor->format('H:i');
                if (! in_array($hhmm, $ocupadas, true)) {
                    $slots[] = $hhmm;
                }
            }
        }

        sort($slots);
        return array_values(array_unique($slots));
    }
}
