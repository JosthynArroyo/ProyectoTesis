<?php

namespace App\Http\Controllers;

use App\Events\CitaAgendada;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Mail\CuentaCreadaDesdeChat;
use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use App\Services\CitaComprobanteService;
use App\Services\PagoService;
use App\Services\PriorityEvaluator;
use App\Services\ProfessionalScheduleService;
use App\Services\SlotHoldService;
use App\Support\ChatbotSessionKeys;
use App\Support\ValidationRules;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ChatBotController extends Controller
{
    public function especialidades(): JsonResponse
    {
        return response()->json(
            Especialidad::orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    public function doctoresPorEspecialidad(Especialidad $especialidad): JsonResponse
    {
        $rol = $especialidad->isLaboratorioClinico() ? 'laboratorio' : 'doctor';

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

    public function fechasDisponibles(User $doctor, ProfessionalScheduleService $scheduleService): JsonResponse
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
            Log::error('Error en Chatbot al consultar fechasDisponibles: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Ocurrió un error inesperado al cargar las fechas disponibles.',
            ], 500);
        }
    }

    public function verificarPaciente(Request $request): JsonResponse
    {
        $cedula = preg_replace('/\s+/', '', (string) $request->input('cedula', ''));
        $email  = strlen(trim((string) $request->input('email', ''))) > 0
            ? trim((string) $request->input('email', ''))
            : null;

        $rules = ['cedula' => ['required', 'digits:10']];
        if ($email !== null) {
            $rules['email'] = ['required', 'email', 'max:255'];
        }

        $data = validator(
            ['cedula' => $cedula, 'email' => $email],
            $rules
        )->validate();

        // Find patient by cedula first
        $user = User::query()
            ->role('paciente')
            ->where('dni', $data['cedula'])
            ->first();

        // If not found by cedula, try email (only when email was provided)
        if (! $user && $email !== null) {
            $user = User::query()
                ->role('paciente')
                ->where('email', $email)
                ->first();
        }

        if (! $user) {
            return response()->json([
                'ok'      => false,
                'existe'  => false,
                'message' => 'No encontramos pacientes registrados con estos datos.',
            ], 404);
        }

        // Validate email match only when email was provided
        if ($email !== null && ! empty($user->email) && strcasecmp((string) $user->email, $email) !== 0) {
            return response()->json([
                'ok'     => false,
                'existe' => true,
                'message' => 'El correo no coincide con el paciente registrado.',
            ], 403);
        }

        return response()->json([
            'ok'      => true,
            'existe'  => true,
            'message' => 'Paciente identificado. Por favor verifica tu identidad mediante OTP.',
            'paciente' => [
                'id'     => $user->id,
                'nombre' => $user->name,
                'email'  => $user->email,
            ],
        ]);
    }


    public function enviarCodigoVerificacion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cedula' => ['required', 'digits:10'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::query()
            ->role('paciente')
            ->where('dni', $data['cedula'])
            ->first();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'existe' => false,
                'message' => 'No encontramos un paciente registrado con esos datos.',
            ], 404);
        }

        if (! empty($user->email) && strcasecmp((string) $user->email, $data['email']) !== 0) {
            return response()->json([
                'ok' => false,
                'existe' => true,
                'message' => 'El correo no coincide con el paciente registrado.',
            ], 403);
        }

        $cooldownKey = $this->otpSendCooldownKey($request, $user->email);
        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            $retryAfter = max(1, RateLimiter::availableIn($cooldownKey));

            return response()->json([
                'ok' => false,
                'message' => "Has solicitado varios codigos. Intenta nuevamente en {$retryAfter} segundos.",
                'retry_after' => $retryAfter,
            ], 429)->header('Retry-After', (string) $retryAfter);
        }

        $quotaKey = $this->otpSendQuotaKey($request, $user->email);
        if (RateLimiter::tooManyAttempts($quotaKey, 3)) {
            $retryAfter = max(1, RateLimiter::availableIn($quotaKey));

            return response()->json([
                'ok' => false,
                'message' => "Has solicitado varios codigos. Intenta nuevamente en {$retryAfter} segundos.",
                'retry_after' => $retryAfter,
            ], 429)->header('Retry-After', (string) $retryAfter);
        }

        $codigo = (string) random_int(100000, 999999);
        $cacheKey = $this->codigoCacheKey($user->dni, $user->email);

        try {
            Mail::raw("Tu código de verificación es {$codigo}. Vence en 10 minutos.", function ($message) use ($data) {
                $message->to($data['email'])
                    ->subject('Código de verificación - Clínica');
            });
        } catch (\Throwable $e) {
            Log::error('Chatbot: No se pudo enviar el correo del codigo OTP.', [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'ok' => false,
                'message' => 'No pudimos enviar el correo con el codigo de verificacion.',
            ], 500);
        }

        Cache::put($cacheKey, $codigo, now()->addMinutes(10));

        $request->session()->put(ChatbotSessionKeys::SESSION_OTP_LAST_SENT_AT, now()->timestamp);
        $request->session()->forget(ChatbotSessionKeys::SESSION_OTP_VERIFY_ATTEMPTS);

        RateLimiter::hit($cooldownKey, 60);
        RateLimiter::hit($quotaKey, 600);

        return response()->json([
            'ok' => true,
            'message' => 'Hemos enviado un codigo de verificacion a tu correo.',
        ]);
    }

    public function verificarCodigo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cedula' => ['required', 'digits:10'],
            'email' => ['required', 'email', 'max:255'],
            'codigo' => ['required', 'digits:6'],
        ]);

        $attempts = (int) $request->session()->get(ChatbotSessionKeys::SESSION_OTP_VERIFY_ATTEMPTS, 0);
        if ($attempts >= 3) {
            $cacheKey = $this->codigoCacheKey($data['cedula'], $data['email']);
            Cache::forget($cacheKey);
            $request->session()->forget(ChatbotSessionKeys::SESSION_OTP_VERIFY_ATTEMPTS);
            return response()->json([
                'ok' => false,
                'message' => 'Has superado el límite de intentos (3). El código fue invalidado. Por favor solicita uno nuevo.',
                'error' => 'otp_invalidated',
            ], 422);
        }

        $user = User::query()
            ->role('paciente')
            ->where('dni', $data['cedula'])
            ->where('email', $data['email'])
            ->first();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'message' => 'No encontramos un paciente con esos datos.',
            ], 404);
        }

        $cacheKey = $this->codigoCacheKey($user->dni, $user->email);
        $codigoGuardado = Cache::get($cacheKey);

        if (is_null($codigoGuardado)) {
            return response()->json([
                'ok' => false,
                'message' => 'El código ya venció o no existe. Solicita uno nuevo.',
                'error' => 'codigo_vencido',
                'allow_resend' => true,
            ], 422);
        }

        if ($codigoGuardado !== $data['codigo']) {
            $attempts++;
            $request->session()->put(ChatbotSessionKeys::SESSION_OTP_VERIFY_ATTEMPTS, $attempts);

            if ($attempts >= 3) {
                Cache::forget($cacheKey);
                $request->session()->forget(ChatbotSessionKeys::SESSION_OTP_VERIFY_ATTEMPTS);
                return response()->json([
                    'ok' => false,
                    'message' => 'Has superado el límite de intentos (3). El código fue invalidado. Por favor solicita uno nuevo.',
                    'error' => 'otp_invalidated',
                ], 422);
            }

            return response()->json([
                'ok' => false,
                'message' => 'El código ingresado no es correcto.',
                'error' => 'codigo_incorrecto',
                'attempts' => $attempts,
                'remaining_attempts' => 3 - $attempts,
            ], 422);
        }

        Cache::forget($cacheKey);
        $request->session()->forget(ChatbotSessionKeys::SESSION_OTP_VERIFY_ATTEMPTS);

        $request->session()->put(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID, $user->id);
        $request->session()->put(ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED, true);

        // Retrieve dependents (even inactive, but we filter in JS or handle them)
        // Actually, retrieve all of them so JS has them, but only show active ones for booking
        $dependientes = Dependiente::where('user_id', $user->id)->orderBy('nombre')->get();

        return response()->json([
            'ok' => true,
            'message' => 'Código validado.',
            'paciente' => [
                'id' => $user->id,
                'nombre' => $user->name,
                'email' => $user->email,
                'telefono' => $user->telefono,
            ],
            'dependientes' => $dependientes->map(fn($d) => [
                'id' => $d->id,
                'nombre' => $d->nombre,
                'nombre_completo' => $d->nombreConParentesco(),
                'dni' => $d->dni,
                'fecha_nacimiento' => $d->fecha_nacimiento->toDateString(),
                'sexo' => $d->sexo,
                'parentesco' => $d->parentesco,
                'telefono_emergencia' => $d->telefono_emergencia,
                'notas' => $d->notas,
                'activo' => (bool) $d->activo,
            ]),
        ]);
    }

    public function registrarUsuario(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre'   => ['required', 'string', 'max:255'],
            'cedula'   => ValidationRules::cedulaUnique(User::class, null, 'registrar_usuario_chatbot', 'chatbot'),
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'telefono' => ['nullable', 'digits:10'],
        ]);

        $passwordPlano = $data['cedula'];

        $user = User::create([
            'name'     => $data['nombre'],
            'email'    => $data['email'],
            'password' => Hash::make($passwordPlano),
            'telefono' => $data['telefono'] ?? null,
            'dni'      => $data['cedula'],
            'status'   => User::STATUS_ACTIVE,
        ]);

        $role = Role::where('name', 'paciente')->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        $credResult = $this->enviarCredencialesChatbot($user, $passwordPlano);

        $request->session()->put(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID, $user->id);
        $request->session()->put(ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED, true);

        $response = [
            'ok'                   => true,
            'usuario_creado'       => true,
            'credenciales_enviadas' => $credResult['sent'],
            'message'              => 'Usuario registrado e identificado correctamente.',
            'paciente'             => [
                'id'       => $user->id,
                'nombre'   => $user->name,
                'email'    => $user->email,
                'telefono' => $user->telefono,
            ],
            'dependientes' => [],
        ];

        if (! $credResult['sent']) {
            $response['credenciales_error'] = 'Registramos tu usuario, pero no pudimos enviar el correo con tus credenciales.';
        }

        return response()->json($response);
    }

    public function agendar(
        Request $request,
        PriorityEvaluator $priorityEvaluator,
        ProfessionalScheduleService $scheduleService,
        SlotHoldService $slotHoldService
    ): JsonResponse
    {
        // ── Resolve patient ──────────────────────────────────────────────────
        // Priority 1: Identified session (post-OTP flow)
        $chatbotUserId = $request->session()->get(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID);

        // Priority 2: Guest flow – datos de identidad vienen en el body
        $usuarioCreado        = false;
        $credencialesEnviadas  = false;
        $credencialesError     = null;

        if ($chatbotUserId) {
            $user = User::find($chatbotUserId);
            if (! $user) {
                return response()->json(['ok' => false, 'message' => 'Sesión inválida.'], 401);
            }
            // Update phone if provided by this request and not yet stored
            if ($request->filled('telefono') && ! $user->telefono) {
                $user->forceFill(['telefono' => $request->input('telefono')])->saveQuietly();
                $user->refresh();
            }
        } else {
            // ── Validate guest identity fields ───────────────────────────────
            $guestRules = [
                'nombre'   => ['required', 'string', 'max:255'],
                'cedula'   => ['required', 'digits:10'],
                'email'    => ['required', 'email', 'max:255'],
                'telefono' => ['nullable', 'digits:10'],
                'motivo'   => ValidationRules::motivoConsulta(false),
                'crear_usuario' => ['nullable', 'boolean'],
                'paciente_id'   => ['nullable', 'integer'],
            ];
            $guestData = $request->validate($guestRules);

            $crearUsuario = (bool) ($guestData['crear_usuario'] ?? false);
            $pacienteId   = $guestData['paciente_id'] ?? null;

            // If paciente_id provided, verify email matches (identified guest)
            if ($pacienteId) {
                $user = User::find($pacienteId);
                if (! $user) {
                    return response()->json(['ok' => false, 'message' => 'Paciente no encontrado.'], 404);
                }
                if (strtolower(trim($user->email)) !== strtolower(trim($guestData['email']))) {
                    return response()->json([
                        'ok'      => false,
                        'message' => 'El correo no coincide con tu perfil.',
                    ], 403);
                }
            } else {
                $normalizedCedula = \App\Services\IdentityDocumentService::normalize($guestData['cedula']);
                $byEmail  = User::where('email', $guestData['email'])->first();
                $byCedula = User::where('dni', $normalizedCedula)->first();
                $byCedulaDep = Dependiente::where('dni', $normalizedCedula)->first();

                if ($byEmail && $byCedula && $byEmail->id === $byCedula->id && ! $byCedulaDep) {
                    // Existing user – reuse without creating, no credentials email
                    $user = $byEmail;
                    // Update phone if given and missing
                    if (isset($guestData['telefono']) && ! $user->telefono) {
                        $user->forceFill(['telefono' => $guestData['telefono']])->saveQuietly();
                    }
                } elseif ($byEmail || $byCedula || $byCedulaDep) {
                    // Partial match – email or cedula belongs to a different user/dependiente
                    return response()->json([
                        'ok'      => false,
                        'message' => 'Este número de cédula ya está registrado para otra persona.',
                    ], 409);
                } else {
                    // Brand-new user – create
                    $passwordPlano = $guestData['cedula'];
                    $password      = $crearUsuario ? Hash::make($passwordPlano) : Hash::make(Str::random(32));

                    $role = Role::where('name', 'paciente')->first();

                    $user = User::create([
                        'name'     => $guestData['nombre'],
                        'email'    => $guestData['email'],
                        'password' => $password,
                        'telefono' => $guestData['telefono'] ?? null,
                        'dni'      => $guestData['cedula'],
                        'status'   => User::STATUS_ACTIVE,
                    ]);

                    if ($role) {
                        $user->roles()->attach($role->id);
                    }

                    $usuarioCreado = true;

                    if ($crearUsuario) {
                        $credResult           = $this->enviarCredencialesChatbot($user, $passwordPlano);
                        $credencialesEnviadas  = $credResult['sent'];
                        $credencialesError     = $credResult['error'] ?? null;
                    }
                }
            }
        }

        // Accept both 'motivo' (guest/test compat) and 'motivo_consulta' (widget flow)
        // Normalize to motivo_consulta so booking logic works uniformly
        if ($request->has('motivo') && ! $request->has('motivo_consulta')) {
            $request->merge(['motivo_consulta' => $request->input('motivo')]);
        }

        $motivoRules = ValidationRules::motivoConsulta(false);

        $data = $request->validate([
            'especialidad_id' => ['required', 'exists:especialidades,id'],
            'doctor_id'       => ['required', 'integer', 'exists:users,id'],
            'fecha'           => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora'            => ['required', 'date_format:H:i'],
            'hold_token'      => ['nullable', 'string', 'max:80'],
            'motivo_consulta' => $motivoRules,
            // Also validate under 'motivo' alias so errors appear on the right field
            'motivo'          => $request->has('motivo') ? $motivoRules : ['sometimes', 'nullable'],
            'dependiente_id'  => ['nullable', 'integer', 'exists:dependientes,id'],
        ]);

        if (app(PagoService::class)->pacienteTieneBloqueo($user->id)) {
            return response()->json([
                'ok' => false,
                'message' => PagoService::MENSAJE_BLOQUEO,
            ], 423);
        }

        $dependienteId = null;
        if ($request->filled('dependiente_id')) {
            // Verify ownership bypassing user->dependientes() scope
            $dep = Dependiente::where('id', $request->dependiente_id)->where('user_id', $user->id)->first();
            if (!$dep) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El dependiente seleccionado no pertenece a tu cuenta.',
                ], 403);
            }
            if (!$dep->activo) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El dependiente seleccionado está inactivo.',
                ], 403);
            }
            $dependienteId = $dep->id;
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

        $motivoConsulta = $priorityEvaluator->sanitizeMotivo($data['motivo_consulta'] ?? null);

        try {
            $cita = DB::transaction(function () use (
                $user,
                $doctor,
                $data,
                $fecha,
                $slot,
                $motivoConsulta,
                $dependienteId,
                $priorityEvaluator,
                $slotHoldService
            ) {
                $cita = Cita::create([
                    'paciente_id' => $user->id,
                    'dependiente_id' => $dependienteId,
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

            $isDuplicate = false;
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                $isDuplicate = ($e->errorInfo[1] ?? 0) === 1062 && str_contains($e->getMessage(), 'citas_unq_doctor_fecha_hora_activo');
            } else {
                $isDuplicate = $e->getCode() === '23000' && (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'citas_unq_doctor_fecha_hora_activo'));
            }

            if ($isDuplicate) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Ese horario ya no está disponible. Por favor elige otro.',
                ], 422);
            }

            throw $e;
        }

        try {
            event(new CitaAgendada($cita));
            $cita->refresh();
            EnviarConfirmacionCitaJob::dispatch($cita);
        } catch (\Throwable $e) {
            Log::error('Error al notificar cita agendada en chatbot: ' . $e->getMessage());
        }

        $agendarResponse = [
            'ok'                   => true,
            'message'              => 'Tu cita ha sido agendada correctamente. Conserva el comprobante para validarla en recepción.',
            'usuario_creado'       => $usuarioCreado,
            'credenciales_enviadas' => $credencialesEnviadas,
            'cita'                 => [
                'id'               => $cita->id,
                'folio_cita'       => $cita->folio_cita,
                'token_validacion' => $cita->token_validacion,
                'fecha'            => $cita->fecha->format('d/m/Y'),
                'hora'             => substr($cita->hora, 0, 5),
                'estado'           => $cita->estado,
                'doctor'           => $doctor->name,
                'especialidad'     => $doctor->especialidades()->where('especialidad_id', $data['especialidad_id'])->value('nombre'),
                'paciente'         => $cita->nombrePacienteReal(),
            ],
        ];

        if ($credencialesError) {
            $agendarResponse['credenciales_error'] = 'La cita fue registrada, pero no pudimos enviar el correo con tus credenciales.';
        }

        return response()->json($agendarResponse);
    }


    public function buscarCitas(Request $request): JsonResponse
    {
        $chatbotUserId = $request->session()->get(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID);
        $user = User::findOrFail($chatbotUserId);

        $data = $request->validate([
            'estado' => ['required', Rule::in(array_merge(Cita::ESTADOS, ['all']))],
            'incluir_todas' => ['required', 'boolean'],
            'dependiente_id' => ['nullable', 'string'],
        ]);

        if ($data['estado'] === 'all') {
            $data['estado'] = null;
        }

        $dependienteFilter = $data['dependiente_id'] ?? 'all';
        $dependienteId = null;

        if (is_numeric($dependienteFilter)) {
            // Verify ownership bypassing user->dependientes() scope
            $dep = Dependiente::where('id', (int) $dependienteFilter)->where('user_id', $user->id)->first();
            if (!$dep) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El dependiente seleccionado no pertenece a tu cuenta.',
                ], 403);
            }
            $dependienteId = $dep->id;
        }

        $hoy = Carbon::today(config('app.timezone', 'America/Guayaquil'));
        $modoHistorico = (bool) $data['incluir_todas'];

        $citas = Cita::with(['doctor:id,name', 'especialidad:id,nombre'])
            ->where('paciente_id', $user->id)
            ->when($dependienteFilter === 'titular', function ($q) {
                $q->whereNull('dependiente_id');
            })
            ->when(is_numeric($dependienteFilter), function ($q) use ($dependienteId) {
                $q->where('dependiente_id', $dependienteId);
            })
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
                'dependiente_id' => $cita->dependiente_id,
                'paciente_real' => $cita->nombrePacienteReal(),
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
            'citas' => $respuesta,
        ]);
    }

    public function cancelar(Request $request): JsonResponse
    {
        $chatbotUserId = $request->session()->get(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID);
        $user = User::findOrFail($chatbotUserId);

        $data = $request->validate([
            'cita_id' => ['required', 'integer', 'exists:citas_medicas,id'],
        ]);

        $cita = Cita::findOrFail($data['cita_id']);

        if ($cita->paciente_id !== $user->id) {
            return response()->json([
                'ok' => false,
                'message' => 'No tienes permisos para cancelar esta cita.',
            ], 403);
        }

        if ($cita->dependiente_id) {
            // Verify ownership bypassing user->dependientes() scope
            $belongs = Dependiente::where('id', $cita->dependiente_id)->where('user_id', $user->id)->exists();
            if (!$belongs) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La cita seleccionada no pertenece a un dependiente válido.',
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
                ];
            }

            if (in_array($cita->estado, [Cita::ESTADO_CANCELADA, Cita::ESTADO_REALIZADA, Cita::ESTADO_NO_SE_PRESENTO], true)) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Esta cita ya no puede ser cancelada.',
                ];
            }

            $cita->estado = Cita::ESTADO_CANCELADA;
            $cita->activo = false;
            $cita->save();

            return ['ok' => true, 'status' => 200, 'cita' => $cita->refresh()];
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
    ): JsonResponse
    {
        $chatbotUserId = $request->session()->get(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID);
        $user = User::findOrFail($chatbotUserId);

        $data = $request->validate([
            'cita_id' => ['required', 'integer', 'exists:citas_medicas,id'],
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora' => ['required', 'date_format:H:i'],
            'motivo_consulta' => ValidationRules::motivoConsulta(false),
        ]);

        $cita = Cita::findOrFail($data['cita_id']);

        if ($cita->paciente_id !== $user->id) {
            return response()->json([
                'ok' => false,
                'message' => 'No tienes permisos para reprogramar esta cita.',
            ], 403);
        }

        if ($cita->dependiente_id) {
            // Verify ownership bypassing user->dependientes() scope
            $dep = Dependiente::where('id', $cita->dependiente_id)->where('user_id', $user->id)->first();
            if (!$dep) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El dependiente asociado a esta cita no es válido.',
                ], 403);
            }
            if (!$dep->activo) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No se puede reprogramar la cita porque el paciente está inactivo.',
                ], 422);
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

        try {
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
        } catch (QueryException $e) {
            $isDuplicate = false;
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                $isDuplicate = ($e->errorInfo[1] ?? 0) === 1062 && str_contains($e->getMessage(), 'citas_unq_doctor_fecha_hora_activo');
            } else {
                $isDuplicate = $e->getCode() === '23000' && (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'citas_unq_doctor_fecha_hora_activo'));
            }

            if ($isDuplicate) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Ese horario ya no está disponible. Por favor elige otro.',
                ], 422);
            }

            throw $e;
        }

        app(CitaComprobanteService::class)->sincronizarComprobante($cita);
        NotificarCambioEstadoCitaJob::dispatch($cita, 'reagendada', 'paciente');

        return response()->json([
            'ok' => true,
            'message' => 'La cita fue reprogramada con éxito.',
        ]);
    }

    public function perfil(Request $request): JsonResponse
    {
        $chatbotUserId = $request->session()->get(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID);
        $user = User::findOrFail($chatbotUserId);

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
            'dependientes' => Dependiente::where('user_id', $user->id)->activos()->orderBy('nombre')->get()->map(fn($d) => [
                'id' => $d->id,
                'nombre' => $d->nombre,
                'nombre_completo' => $d->nombreConParentesco(),
                'dni' => $d->dni,
                'fecha_nacimiento' => $d->fecha_nacimiento->toDateString(),
                'sexo' => $d->sexo,
                'parentesco' => $d->parentesco,
                'telefono_emergencia' => $d->telefono_emergencia,
                'notas' => $d->notas,
            ]),
        ]);
    }

    public function actualizarPerfil(Request $request): JsonResponse
    {
        $chatbotUserId = $request->session()->get(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID);
        $user = User::findOrFail($chatbotUserId);

        if ($request->filled('dependiente_id')) {
            // Verify ownership bypassing user->dependientes() scope
            $dep = Dependiente::where('id', $request->input('dependiente_id'))->where('user_id', $user->id)->first();
            if (!$dep) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El dependiente seleccionado no es válido.',
                ], 403);
            }
            if (!$dep->activo) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El dependiente seleccionado está inactivo.',
                ], 403);
            }

            $rules = ValidationRules::dependiente(true, $dep->id);
            $validated = $request->validate($rules);

            $dep->update([
                'nombre' => $validated['nombre'],
                'dni' => $validated['dni'],
                'fecha_nacimiento' => $validated['fecha_nacimiento'],
                'sexo' => $validated['sexo'] ?? null,
                'parentesco' => $validated['parentesco'],
                'telefono_emergencia' => $validated['telefono_emergencia'] ?? null,
                'notas' => $validated['notas'] ?? null,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Datos del dependiente actualizados correctamente.',
            ]);
        }

        $rules = [
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ValidationRules::telefono(),
            'direccion' => ['required', 'string', 'max:255'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'sexo' => ['required', Rule::in(['Masculino', 'Femenino', 'Otro'])],
        ];

        $messages = [
            'telefono.digits' => 'El teléfono debe tener exactamente 10 dígitos.',
        ];

        $validated = $request->validate($rules, $messages);

        $user->update([
            'name' => $validated['nombre'],
            'telefono' => $validated['telefono'],
            'direccion' => $validated['direccion'],
            'fecha_nacimiento' => $validated['fecha_nacimiento'],
            'sexo' => $validated['sexo'],
        ]);

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
        ]);
    }

    public function finalizar(Request $request): JsonResponse
    {
        $request->session()->forget([
            ChatbotSessionKeys::SESSION_VERIFIED,
            ChatbotSessionKeys::SESSION_VERIFIED_AT,
            ChatbotSessionKeys::SESSION_CHALLENGE_ID,
            ChatbotSessionKeys::SESSION_CHALLENGE_TOKEN,
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED,
            ChatbotSessionKeys::SESSION_OTP_LAST_SENT_AT,
            ChatbotSessionKeys::SESSION_OTP_VERIFY_ATTEMPTS,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Sesión del chatbot finalizada con éxito.',
        ]);
    }

    protected function enviarCredencialesChatbot(User $user, string $passwordPlano): array
    {
        try {
            Mail::to($user->email)->send(new CuentaCreadaDesdeChat($user, $passwordPlano));

            return ['sent' => true, 'error' => null];
        } catch (\Throwable $exception) {
            Log::error('Chatbot: no se pudo enviar el correo de credenciales: ' . $exception->getMessage());

            return ['sent' => false, 'error' => 'No pudimos enviar el correo con tus credenciales.'];
        }
    }

    protected function codigoCacheKey(string $cedula, string $email): string
    {
        $emailHash = hash('sha256', strtolower(trim($email)));
        return 'chatbot:codigo:' . sha1($cedula . '|' . $emailHash);
    }

    protected function otpSendCooldownKey(Request $request, string $email): string
    {
        $sessionBucket = $this->otpSendSessionBucket($request);
        $emailHash = hash('sha256', strtolower(trim($email)));

        return 'chatbot:otp:send:cooldown:' . sha1($sessionBucket . '|' . $emailHash);
    }

    protected function otpSendQuotaKey(Request $request, string $email): string
    {
        $sessionBucket = $this->otpSendSessionBucket($request);
        $emailHash = hash('sha256', strtolower(trim($email)));

        return 'chatbot:otp:send:quota:' . sha1($sessionBucket . '|' . $emailHash);
    }

    protected function otpSendSessionBucket(Request $request): string
    {
        $bucket = (string) $request->session()->get('chatbot_otp_send_bucket', '');

        if ($bucket === '') {
            $bucket = (string) Str::uuid();
            $request->session()->put('chatbot_otp_send_bucket', $bucket);
        }

        return $bucket;
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
