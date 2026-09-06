<?php

namespace App\Services\Chatbot;

use App\Mail\CuentaCreadaDesdeChat;
use App\Models\Dependiente;
use App\Models\Role;
use App\Models\User;
use App\Support\ChatbotSessionKeys;
use App\Support\ValidationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ChatbotAuthService
{
    public function verificarPaciente(Request $request): array
    {
        $cedula = preg_replace('/\s+/', '', (string) $request->input('cedula', ''));
        $email = strlen(trim((string) $request->input('email', ''))) > 0
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
            return [
                'status' => 404,
                'payload' => [
                    'ok' => false,
                    'existe' => false,
                    'message' => 'No encontramos pacientes registrados con estos datos.',
                ],
            ];
        }

        if (! $user->isActive()) {
            return [
                'status' => 403,
                'payload' => [
                    'ok' => false,
                    'existe' => true,
                    'message' => 'Tu cuenta de paciente se encuentra inactiva o bloqueada. Por favor, comunícate con la clínica.',
                    'error' => 'user_inactive',
                ],
            ];
        }

        // Validate email match only when email was provided
        if ($email !== null && ! empty($user->email) && strcasecmp((string) $user->email, $email) !== 0) {
            return [
                'status' => 403,
                'payload' => [
                    'ok' => false,
                    'existe' => true,
                    'message' => 'El correo no coincide con el paciente registrado.',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'ok' => true,
                'existe' => true,
                'message' => 'Paciente identificado. Por favor verifica tu identidad mediante OTP.',
                'paciente' => [
                    'id' => $user->id,
                    'nombre' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ];
    }

    public function enviarCodigoVerificacion(Request $request): array
    {
        $data = $request->validate([
            'cedula' => ['required', 'digits:10'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::query()
            ->role('paciente')
            ->where('dni', $data['cedula'])
            ->first();

        $pendingKey = $this->pendingRegistrationCacheKey($data['cedula'], $data['email']);
        $pending = Cache::get($pendingKey);

        if (! $user && ! $pending) {
            return [
                'status' => 404,
                'payload' => [
                    'ok' => false,
                    'existe' => false,
                    'message' => 'No encontramos un paciente registrado con esos datos.',
                ],
            ];
        }

        if ($user && ! $user->isActive()) {
            return [
                'status' => 403,
                'payload' => [
                    'ok' => false,
                    'existe' => true,
                    'message' => 'Tu cuenta de paciente se encuentra inactiva o bloqueada.',
                    'error' => 'user_inactive',
                ],
            ];
        }

        $targetEmail = $user ? $user->email : $pending['email'];
        $targetDni = $user ? $user->dni : $pending['cedula'];

        if (! empty($targetEmail) && strcasecmp((string) $targetEmail, $data['email']) !== 0) {
            return [
                'status' => 403,
                'payload' => [
                    'ok' => false,
                    'existe' => true,
                    'message' => 'El correo no coincide con el paciente registrado.',
                ],
            ];
        }

        $cooldownKey = $this->otpSendCooldownKey($request, $targetEmail);
        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            $retryAfter = max(1, RateLimiter::availableIn($cooldownKey));

            return [
                'status' => 429,
                'payload' => [
                    'ok' => false,
                    'message' => "Has solicitado varios codigos. Intenta nuevamente en {$retryAfter} segundos.",
                    'retry_after' => $retryAfter,
                ],
                'headers' => ['Retry-After' => (string) $retryAfter],
            ];
        }

        $quotaKey = $this->otpSendQuotaKey($request, $targetEmail);
        if (RateLimiter::tooManyAttempts($quotaKey, 3)) {
            $retryAfter = max(1, RateLimiter::availableIn($quotaKey));

            return [
                'status' => 429,
                'payload' => [
                    'ok' => false,
                    'message' => "Has solicitado varios codigos. Intenta nuevamente en {$retryAfter} segundos.",
                    'retry_after' => $retryAfter,
                ],
                'headers' => ['Retry-After' => (string) $retryAfter],
            ];
        }

        $codigo = (string) random_int(100000, 999999);
        $cacheKey = $this->codigoCacheKey($targetDni, $targetEmail);

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

            return [
                'status' => 500,
                'payload' => [
                    'ok' => false,
                    'message' => 'No pudimos enviar el correo con el codigo de verificacion.',
                ],
            ];
        }

        Cache::put($cacheKey, $codigo, now()->addMinutes(10));

        $request->session()->put(ChatbotSessionKeys::SESSION_OTP_LAST_SENT_AT, now()->timestamp);
        $request->session()->forget(ChatbotSessionKeys::SESSION_OTP_VERIFY_ATTEMPTS);

        RateLimiter::hit($cooldownKey, 60);
        RateLimiter::hit($quotaKey, 600);

        return [
            'status' => 200,
            'payload' => [
                'ok' => true,
                'message' => 'Hemos enviado un codigo de verificacion a tu correo.',
            ],
        ];
    }

    public function verificarCodigo(Request $request): array
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

            return [
                'status' => 422,
                'payload' => [
                    'ok' => false,
                    'message' => 'Has superado el límite de intentos (3). El código fue invalidado. Por favor solicita uno nuevo.',
                    'error' => 'otp_invalidated',
                ],
            ];
        }

        $user = User::query()
            ->role('paciente')
            ->where('dni', $data['cedula'])
            ->where('email', $data['email'])
            ->first();

        $pendingKey = $this->pendingRegistrationCacheKey($data['cedula'], $data['email']);
        $pending = Cache::get($pendingKey);

        if (! $user && ! $pending) {
            return [
                'status' => 404,
                'payload' => [
                    'ok' => false,
                    'message' => 'No encontramos un paciente con esos datos.',
                ],
            ];
        }

        if ($user && ! $user->isActive()) {
            $this->clearChatbotIdentitySession($request);

            return [
                'status' => 403,
                'payload' => [
                    'ok' => false,
                    'message' => 'Tu cuenta de paciente se encuentra inactiva o bloqueada.',
                    'error' => 'user_inactive',
                ],
            ];
        }

        $targetDni = $user ? $user->dni : $pending['cedula'];
        $targetEmail = $user ? $user->email : $pending['email'];

        $cacheKey = $this->codigoCacheKey($targetDni, $targetEmail);
        $codigoGuardado = Cache::get($cacheKey);

        if (is_null($codigoGuardado)) {
            return [
                'status' => 422,
                'payload' => [
                    'ok' => false,
                    'message' => 'El código ya venció o no existe. Solicita uno nuevo.',
                    'error' => 'codigo_vencido',
                    'allow_resend' => true,
                ],
            ];
        }

        if ($codigoGuardado !== $data['codigo']) {
            $attempts++;
            $request->session()->put(ChatbotSessionKeys::SESSION_OTP_VERIFY_ATTEMPTS, $attempts);

            if ($attempts >= 3) {
                Cache::forget($cacheKey);
                $request->session()->forget(ChatbotSessionKeys::SESSION_OTP_VERIFY_ATTEMPTS);

                return [
                    'status' => 422,
                    'payload' => [
                        'ok' => false,
                        'message' => 'Has superado el límite de intentos (3). El código fue invalidado. Por favor solicita uno nuevo.',
                        'error' => 'otp_invalidated',
                    ],
                ];
            }

            return [
                'status' => 422,
                'payload' => [
                    'ok' => false,
                    'message' => 'El código ingresado no es correcto.',
                    'error' => 'codigo_incorrecto',
                    'attempts' => $attempts,
                    'remaining_attempts' => 3 - $attempts,
                ],
            ];
        }

        Cache::forget($cacheKey);
        $request->session()->forget(ChatbotSessionKeys::SESSION_OTP_VERIFY_ATTEMPTS);

        $usuarioCreado = false;
        $credencialesEnviadas = false;

        if (! $user && $pending) {
            $passwordPlano = Str::password(12, true, true, false);

            $user = User::create([
                'name' => $pending['nombre'],
                'email' => $pending['email'],
                'password' => Hash::make($passwordPlano),
                'telefono' => $pending['telefono'] ?? null,
                'dni' => $pending['cedula'],
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => true,
            ]);

            $user->forceFill(['email_verified_at' => now()])->saveQuietly();

            $role = Role::where('name', 'paciente')->first();
            if ($role) {
                $user->roles()->attach($role->id);
            }

            $credResult = $this->enviarCredencialesChatbot($user, $passwordPlano);
            $credencialesEnviadas = $credResult['sent'];
            $usuarioCreado = true;

            Cache::forget($pendingKey);
        }

        $request->session()->put(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID, $user->id);
        $request->session()->put(ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED, true);

        // Retrieve dependents
        $dependientes = Dependiente::where('user_id', $user->id)->orderBy('nombre')->get();

        return [
            'status' => 200,
            'payload' => [
                'ok' => true,
                'message' => 'Código validado.',
                'usuario_creado' => $usuarioCreado,
                'credenciales_enviadas' => $credencialesEnviadas,
                'paciente' => [
                    'id' => $user->id,
                    'nombre' => $user->name,
                    'email' => $user->email,
                    'telefono' => $user->telefono,
                ],
                'dependientes' => $dependientes->map(fn ($d) => [
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
            ],
        ];
    }

    public function registrarUsuario(Request $request): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'cedula' => ValidationRules::cedulaUnique(User::class, null, 'registrar_usuario_chatbot', 'chatbot'),
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'telefono' => ['nullable', 'digits:10'],
        ]);

        $codigo = (string) random_int(100000, 999999);
        $pendingKey = $this->pendingRegistrationCacheKey($data['cedula'], $data['email']);
        $otpKey = $this->codigoCacheKey($data['cedula'], $data['email']);

        Cache::put($pendingKey, [
            'nombre' => $data['nombre'],
            'cedula' => $data['cedula'],
            'email' => $data['email'],
            'telefono' => $data['telefono'] ?? null,
        ], now()->addMinutes(10));

        Cache::put($otpKey, $codigo, now()->addMinutes(10));

        $request->session()->put(ChatbotSessionKeys::SESSION_OTP_LAST_SENT_AT, now()->timestamp);
        $request->session()->forget(ChatbotSessionKeys::SESSION_OTP_VERIFY_ATTEMPTS);

        try {
            Mail::raw("Tu código de verificación es {$codigo}. Vence en 10 minutos.", function ($message) use ($data) {
                $message->to($data['email'])
                    ->subject('Código de verificación - Clínica');
            });
        } catch (\Throwable $e) {
            Log::error('Chatbot: No se pudo enviar el correo del codigo OTP al registrar usuario.', [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'payload' => [
                    'ok' => false,
                    'message' => 'No pudimos enviar el correo con el código de verificación.',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'ok' => true,
                'usuario_creado' => false,
                'requiere_otp' => true,
                'message' => 'Hemos enviado un código de verificación de 6 dígitos a tu correo.',
                'paciente' => [
                    'nombre' => $data['nombre'],
                    'email' => $data['email'],
                    'telefono' => $data['telefono'] ?? null,
                ],
                'dependientes' => [],
            ],
        ];
    }

    public function enviarCredencialesChatbot(User $user, string $passwordPlano): array
    {
        try {
            Mail::to($user->email)->send(new CuentaCreadaDesdeChat($user, $passwordPlano));

            return ['sent' => true, 'error' => null];
        } catch (\Throwable $exception) {
            Log::error('Chatbot: no se pudo enviar el correo de credenciales: '.$exception->getMessage());

            return ['sent' => false, 'error' => 'No pudimos enviar el correo con tus credenciales.'];
        }
    }

    public function codigoCacheKey(string $cedula, string $email): string
    {
        $emailHash = hash('sha256', strtolower(trim($email)));

        return 'chatbot:codigo:'.sha1($cedula.'|'.$emailHash);
    }

    public function pendingRegistrationCacheKey(string $cedula, string $email): string
    {
        $emailHash = hash('sha256', strtolower(trim($email)));

        return 'chatbot:pending_reg:'.sha1($cedula.'|'.$emailHash);
    }

    public function otpSendCooldownKey(Request $request, string $email): string
    {
        $sessionBucket = $this->otpSendSessionBucket($request);
        $emailHash = hash('sha256', strtolower(trim($email)));

        return 'chatbot:otp:send:cooldown:'.sha1($sessionBucket.'|'.$emailHash);
    }

    public function otpSendQuotaKey(Request $request, string $email): string
    {
        $sessionBucket = $this->otpSendSessionBucket($request);
        $emailHash = hash('sha256', strtolower(trim($email)));

        return 'chatbot:otp:send:quota:'.sha1($sessionBucket.'|'.$emailHash);
    }

    public function otpSendSessionBucket(Request $request): string
    {
        $bucket = (string) $request->session()->get('chatbot_otp_send_bucket', '');

        if ($bucket === '') {
            $bucket = (string) Str::uuid();
            $request->session()->put('chatbot_otp_send_bucket', $bucket);
        }

        return $bucket;
    }

    public function clearChatbotIdentitySession(Request $request): void
    {
        $request->session()->forget([
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED,
        ]);
    }
}
