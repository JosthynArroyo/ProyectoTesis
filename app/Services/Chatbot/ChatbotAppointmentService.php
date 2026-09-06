<?php

namespace App\Services\Chatbot;

use App\Events\CitaAgendada;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Models\Cita;
use App\Models\Dependiente;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ChatbotAppointmentService
{
    public function __construct(
        protected PriorityEvaluator $priorityEvaluator,
        protected ProfessionalScheduleService $scheduleService,
        protected SlotHoldService $slotHoldService,
        protected PagoService $pagoService,
        protected CitaComprobanteService $comprobanteService,
        protected ChatbotAuthService $authService
    ) {}

    public function agendar(Request $request): array
    {
        // ── Resolve patient from verified OTP session ───────────────────────
        $chatbotUserId = $request->session()->get(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID);
        $otpVerified = (bool) $request->session()->get(ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED, false);

        if (! $chatbotUserId || ! $otpVerified) {
            return [
                'status' => 401,
                'payload' => [
                    'ok' => false,
                    'message' => 'Sesión de chatbot no identificada. Por favor, verifica tu identidad mediante OTP.',
                    'error' => 'identity_required',
                ],
            ];
        }

        $user = User::query()->role('paciente')->find($chatbotUserId);
        if (! $user || ! $user->isActive()) {
            $this->authService->clearChatbotIdentitySession($request);

            return [
                'status' => 403,
                'payload' => [
                    'ok' => false,
                    'message' => 'Tu cuenta de paciente se encuentra inactiva o bloqueada.',
                    'error' => 'user_inactive',
                ],
            ];
        }

        // The client cannot decide or spoof patient identity
        if ($request->filled('paciente_id') && (int) $request->input('paciente_id') !== (int) $user->id) {
            return [
                'status' => 403,
                'payload' => [
                    'ok' => false,
                    'message' => 'No estás autorizado para agendar en nombre de otro paciente.',
                ],
            ];
        }

        if ($request->filled('email') && strcasecmp(trim((string) $request->input('email')), (string) $user->email) !== 0) {
            return [
                'status' => 403,
                'payload' => [
                    'ok' => false,
                    'message' => 'El correo no coincide con tu perfil verificado.',
                ],
            ];
        }

        if ($request->filled('cedula') && trim((string) $request->input('cedula')) !== (string) $user->dni) {
            return [
                'status' => 403,
                'payload' => [
                    'ok' => false,
                    'message' => 'La cédula no coincide con tu perfil verificado.',
                ],
            ];
        }

        // Update phone if provided by this request and not yet stored
        if ($request->filled('telefono') && ! $user->telefono) {
            $user->forceFill(['telefono' => $request->input('telefono')])->saveQuietly();
            $user->refresh();
        }

        // Accept both 'motivo' (guest/test compat) and 'motivo_consulta' (widget flow)
        if ($request->has('motivo') && ! $request->has('motivo_consulta')) {
            $request->merge(['motivo_consulta' => $request->input('motivo')]);
        }

        $motivoRules = ValidationRules::motivoConsulta(false);

        $data = $request->validate([
            'especialidad_id' => ['required', 'exists:especialidades,id'],
            'doctor_id' => ['required', 'integer', 'exists:users,id'],
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora' => ['required', 'date_format:H:i'],
            'hold_token' => ['nullable', 'string', 'max:80'],
            'motivo_consulta' => $motivoRules,
            'motivo' => $request->has('motivo') ? $motivoRules : ['sometimes', 'nullable'],
            'dependiente_id' => ['nullable', 'integer', 'exists:dependientes,id'],
        ]);

        if ($this->pagoService->pacienteTieneBloqueo($user->id)) {
            return [
                'status' => 423,
                'payload' => [
                    'ok' => false,
                    'message' => PagoService::MENSAJE_BLOQUEO,
                ],
            ];
        }

        $dependienteId = null;
        if ($request->filled('dependiente_id')) {
            $dep = Dependiente::where('id', $request->dependiente_id)->where('user_id', $user->id)->first();
            if (! $dep) {
                return [
                    'status' => 403,
                    'payload' => [
                        'ok' => false,
                        'message' => 'El dependiente seleccionado no pertenece a tu cuenta.',
                    ],
                ];
            }
            if (! $dep->activo) {
                return [
                    'status' => 403,
                    'payload' => [
                        'ok' => false,
                        'message' => 'El dependiente seleccionado está inactivo.',
                    ],
                ];
            }
            $dependienteId = $dep->id;
        }

        $doctor = User::query()
            ->onlyActive()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['doctor', 'laboratorio']))
            ->findOrFail($data['doctor_id']);

        if (! $doctor->especialidades()->where('especialidad_id', $data['especialidad_id'])->exists()) {
            return [
                'status' => 422,
                'payload' => [
                    'ok' => false,
                    'message' => 'El doctor seleccionado no pertenece a esa especialidad.',
                ],
            ];
        }

        $fecha = Carbon::parse($data['fecha'])->toDateString();
        $slot = Carbon::createFromFormat('H:i', $data['hora']);

        $validationError = $this->scheduleService->validateBookingSlot(
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
            return [
                'status' => 422,
                'payload' => [
                    'ok' => false,
                    'message' => 'El horario escogido ya no está disponible.',
                ],
            ];
        }

        $motivoConsulta = $this->priorityEvaluator->sanitizeMotivo($data['motivo_consulta'] ?? null);

        try {
            $cita = DB::transaction(function () use (
                $user,
                $doctor,
                $data,
                $fecha,
                $slot,
                $motivoConsulta,
                $dependienteId
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

                $this->priorityEvaluator->apply($cita);
                $cita->save();

                $this->slotHoldService->completeByToken(
                    token: $data['hold_token'] ?? null,
                    professionalId: (int) $doctor->id,
                    date: $fecha,
                    time: $slot->format('H:i:00')
                );

                return $cita->refresh();
            });
        } catch (QueryException $e) {
            $this->slotHoldService->releaseByToken(
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
                return [
                    'status' => 422,
                    'payload' => [
                        'ok' => false,
                        'message' => 'Ese horario ya no está disponible. Por favor elige otro.',
                    ],
                ];
            }

            throw $e;
        }

        try {
            event(new CitaAgendada($cita));
            $cita->refresh();
            EnviarConfirmacionCitaJob::dispatch($cita);
        } catch (\Throwable $e) {
            Log::error('Error al notificar cita agendada en chatbot: '.$e->getMessage());
        }

        return [
            'status' => 200,
            'payload' => [
                'ok' => true,
                'message' => 'Tu cita ha sido agendada correctamente. Conserva el comprobante para validarla en recepción.',
                'usuario_creado' => false,
                'credenciales_enviadas' => false,
                'cita' => [
                    'id' => $cita->id,
                    'folio_cita' => $cita->folio_cita,
                    'token_validacion' => $cita->token_validacion,
                    'fecha' => $cita->fecha->format('d/m/Y'),
                    'hora' => substr($cita->hora, 0, 5),
                    'estado' => $cita->estado,
                    'doctor' => $doctor->name,
                    'especialidad' => $doctor->especialidades()->where('especialidad_id', $data['especialidad_id'])->value('nombre'),
                    'paciente' => $cita->nombrePacienteReal(),
                ],
            ],
        ];
    }

    public function buscarCitas(Request $request): array
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
            $dep = Dependiente::where('id', (int) $dependienteFilter)->where('user_id', $user->id)->first();
            if (! $dep) {
                return [
                    'status' => 403,
                    'payload' => [
                        'ok' => false,
                        'message' => 'El dependiente seleccionado no pertenece a tu cuenta.',
                    ],
                ];
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
            return [
                'status' => 200,
                'payload' => [
                    'ok' => false,
                    'message' => 'No se encontraron citas con los filtros seleccionados.',
                    'citas' => [],
                    'paciente' => $user->name,
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'ok' => true,
                'message' => 'Citas encontradas.',
                'paciente' => $user->name,
                'citas' => $respuesta,
            ],
        ];
    }

    public function cancelar(Request $request): array
    {
        $chatbotUserId = $request->session()->get(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID);
        $user = User::findOrFail($chatbotUserId);

        $data = $request->validate([
            'cita_id' => ['required', 'integer', 'exists:citas_medicas,id'],
        ]);

        $cita = Cita::findOrFail($data['cita_id']);

        if ($cita->paciente_id !== $user->id) {
            return [
                'status' => 403,
                'payload' => [
                    'ok' => false,
                    'message' => 'No tienes permisos para cancelar esta cita.',
                ],
            ];
        }

        if ($cita->dependiente_id) {
            $belongs = Dependiente::where('id', $cita->dependiente_id)->where('user_id', $user->id)->exists();
            if (! $belongs) {
                return [
                    'status' => 403,
                    'payload' => [
                        'ok' => false,
                        'message' => 'La cita seleccionada no pertenece a un dependiente válido.',
                    ],
                ];
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
            return [
                'status' => $transition['status'],
                'payload' => [
                    'ok' => false,
                    'message' => $transition['message'],
                ],
            ];
        }

        $cita = $transition['cita'];
        $this->comprobanteService->sincronizarComprobante($cita);
        NotificarCambioEstadoCitaJob::dispatch($cita, 'cancelada', 'paciente');

        return [
            'status' => 200,
            'payload' => [
                'ok' => true,
                'message' => 'Tu cita fue cancelada correctamente.',
            ],
        ];
    }

    public function reagendar(Request $request): array
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
            return [
                'status' => 403,
                'payload' => [
                    'ok' => false,
                    'message' => 'No tienes permisos para reprogramar esta cita.',
                ],
            ];
        }

        if ($cita->dependiente_id) {
            $dep = Dependiente::where('id', $cita->dependiente_id)->where('user_id', $user->id)->first();
            if (! $dep) {
                return [
                    'status' => 403,
                    'payload' => [
                        'ok' => false,
                        'message' => 'El dependiente asociado a esta cita no es válido.',
                    ],
                ];
            }
            if (! $dep->activo) {
                return [
                    'status' => 422,
                    'payload' => [
                        'ok' => false,
                        'message' => 'No se puede reprogramar la cita porque el paciente está inactivo.',
                    ],
                ];
            }
        }

        if (Carbon::parse($cita->fecha)->isPast()) {
            return [
                'status' => 422,
                'payload' => [
                    'ok' => false,
                    'message' => 'No se pueden reprogramar citas pasadas.',
                ],
            ];
        }

        if (! $cita->esReprogramable()) {
            return [
                'status' => 422,
                'payload' => [
                    'ok' => false,
                    'message' => 'Esta cita ya no puede ser reprogramada.',
                ],
            ];
        }

        $doctorActivo = User::query()
            ->onlyActive()
            ->whereKey($cita->doctor_id)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['doctor', 'laboratorio']))
            ->exists();

        if (! $doctorActivo) {
            return [
                'status' => 422,
                'payload' => [
                    'ok' => false,
                    'message' => 'El profesional asignado a esta cita ya no esta activo. Comunicate con la clinica para reagendar con otro profesional.',
                ],
            ];
        }

        $nuevaFecha = Carbon::parse($data['fecha'])->toDateString();
        $slot = Carbon::createFromFormat('H:i', $data['hora']);

        $validationError = $this->scheduleService->validateBookingSlot(
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
            return [
                'status' => 422,
                'payload' => [
                    'ok' => false,
                    'message' => 'Ese horario ya no está disponible.',
                ],
            ];
        }

        $citaId = (int) $cita->id;
        $transition = null;

        try {
            $transition = DB::transaction(function () use ($citaId, $user, $nuevaFecha, $slot, $request) {
                $cita = Cita::query()->whereKey($citaId)->lockForUpdate()->firstOrFail();

                if ($cita->paciente_id !== $user->id) {
                    return [
                        'ok' => false,
                        'status' => 403,
                        'message' => 'No tienes permisos para reprogramar esta cita.',
                    ];
                }

                if ($cita->dependiente_id) {
                    $dep = Dependiente::where('id', $cita->dependiente_id)->where('user_id', $user->id)->first();
                    if (! $dep) {
                        return [
                            'ok' => false,
                            'status' => 403,
                            'message' => 'El dependiente asociado a esta cita no es válido.',
                        ];
                    }
                    if (! $dep->activo) {
                        return [
                            'ok' => false,
                            'status' => 422,
                            'message' => 'No se puede reprogramar la cita porque el paciente está inactivo.',
                        ];
                    }
                }

                if (Carbon::parse($cita->fecha)->isPast()) {
                    return [
                        'ok' => false,
                        'status' => 422,
                        'message' => 'No se pueden reprogramar citas pasadas.',
                    ];
                }

                if (! $cita->esReprogramable()) {
                    return [
                        'ok' => false,
                        'status' => 422,
                        'message' => 'Esta cita ya no puede ser reprogramada.',
                    ];
                }

                User::query()->whereKey((int) $cita->doctor_id)->lockForUpdate()->firstOrFail();

                $conflict = $this->scheduleService->hasConflict(
                    professionalId: (int) $cita->doctor_id,
                    date: $nuevaFecha,
                    slot: $slot,
                    interval: 30,
                    exceptCitaId: (int) $cita->id
                );

                if ($conflict) {
                    return [
                        'ok' => false,
                        'status' => 422,
                        'message' => 'Ese horario ya no está disponible.',
                    ];
                }

                $cita->fecha = $nuevaFecha;
                $cita->hora = $slot->format('H:i:00');
                $cita->estado = Cita::ESTADO_PENDIENTE;
                $cita->activo = true;

                if ($request->filled('motivo_consulta')) {
                    $cita->motivo_consulta = $this->priorityEvaluator->sanitizeMotivo($request->input('motivo_consulta'));
                }

                $this->priorityEvaluator->apply($cita);
                $cita->save();

                return [
                    'ok' => true,
                    'status' => 200,
                    'cita' => $cita->refresh(),
                ];
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
                return [
                    'status' => 422,
                    'payload' => [
                        'ok' => false,
                        'message' => 'Ese horario ya no está disponible. Por favor elige otro.',
                    ],
                ];
            }

            throw $e;
        }

        if (! $transition['ok']) {
            return [
                'status' => $transition['status'],
                'payload' => [
                    'ok' => false,
                    'message' => $transition['message'],
                ],
            ];
        }

        $cita = $transition['cita'];
        $this->comprobanteService->sincronizarComprobante($cita);
        NotificarCambioEstadoCitaJob::dispatch($cita, 'reagendada', 'paciente');

        return [
            'status' => 200,
            'payload' => [
                'ok' => true,
                'message' => 'La cita fue reprogramada con éxito.',
            ],
        ];
    }

    public function finalizar(Request $request): array
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

        return [
            'status' => 200,
            'payload' => [
                'ok' => true,
                'message' => 'Sesión del chatbot finalizada con éxito.',
            ],
        ];
    }
}
