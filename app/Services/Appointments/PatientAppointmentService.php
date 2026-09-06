<?php

namespace App\Services\Appointments;

use App\Events\CitaAgendada;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\LaboratorioOrden;
use App\Models\User;
use App\Services\CitaNoShowService;
use App\Services\PagoService;
use App\Services\PriorityEvaluator;
use App\Services\ProfessionalScheduleService;
use App\Services\SlotHoldService;
use App\Support\ValidationRules;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientAppointmentService
{
    public function __construct(
        protected CitaNoShowService $noShowService,
        protected PagoService $pagoService,
        protected PriorityEvaluator $priorityEvaluator,
        protected ProfessionalScheduleService $scheduleService,
        protected SlotHoldService $slotHoldService
    ) {}

    public function index(Request $request, int $userId): array
    {
        $this->noShowService->marcarVencidas();
        $q = $this->normalizeSearchTerm($request->get('q', ''));
        $estado = (string) $request->get('estado', '');
        if ($estado === 'all') {
            $estado = '';
        }
        $validStates = ['pendiente', 'confirmada', 'cancelada', 'realizada', 'no_se_presento'];
        $estadoLabel = match ($estado) {
            Cita::ESTADO_PENDIENTE => 'En revision',
            Cita::ESTADO_NO_SE_PRESENTO => 'No se presento',
            default => ucfirst($estado),
        };
        $qNorm = mb_strtolower($q);

        $totalesPorEstado = Cita::where('paciente_id', $userId)
            ->select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $filteredBase = Cita::with(['doctor:id,name', 'especialidad:id,nombre'])
            ->where('paciente_id', $userId)
            ->when($q !== '', function ($query) use ($qNorm) {
                $query->where(function ($qq) use ($qNorm) {
                    $qq->whereHas('doctor', function ($dq) use ($qNorm) {
                        $dq->whereRaw('LOWER(name) LIKE ?', ['%'.$qNorm.'%']);
                    })->orWhereHas('especialidad', function ($eq) use ($qNorm) {
                        $eq->whereRaw('LOWER(nombre) LIKE ?', ['%'.$qNorm.'%']);
                    });
                });
            })
            ->when(in_array($estado, $validStates, true), function ($query) use ($estado) {
                $query->where('estado', $estado);
            });

        $citas = (clone $filteredBase)
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->paginate(10)
            ->withQueryString();

        $emptyMessage = null;

        if ($citas->count() === 0) {
            if ($q !== '' && in_array($estado, $validStates, true)) {
                $doctorExists = User::whereHas('roles', fn ($r) => $r->where('name', 'doctor'))
                    ->whereRaw('LOWER(name) LIKE ?', ['%'.$qNorm.'%'])->exists();
                $especialidadExists = Especialidad::whereRaw('LOWER(nombre) LIKE ?', ['%'.$qNorm.'%'])->exists();

                if ($especialidadExists && ! $doctorExists) {
                    $emptyMessage = 'No tienes cita en la especialidad "'.$q.'" con estado '.$estadoLabel.'.';
                } elseif ($doctorExists && ! $especialidadExists) {
                    $emptyMessage = 'No tienes cita con el doctor "'.$q.'" en estado '.$estadoLabel.'.';
                } elseif (! $doctorExists && ! $especialidadExists) {
                    $emptyMessage = 'No existe la especialidad "'.$q.'" ni un doctor con ese nombre en estado '.$estadoLabel.'.';
                } else {
                    $emptyMessage = 'No hay coincidencias para "'.$q.'" en estado '.$estadoLabel.'.';
                }
            } elseif ($q !== '') {
                $doctorExists = User::whereHas('roles', fn ($r) => $r->where('name', 'doctor'))
                    ->whereRaw('LOWER(name) LIKE ?', ['%'.$qNorm.'%'])->exists();
                $especialidadExists = Especialidad::whereRaw('LOWER(nombre) LIKE ?', ['%'.$qNorm.'%'])->exists();

                if ($especialidadExists && ! $doctorExists) {
                    $emptyMessage = 'No tienes cita agendada en la especialidad "'.$q.'".';
                } elseif ($doctorExists && ! $especialidadExists) {
                    $emptyMessage = 'No tienes cita agendada con el doctor "'.$q.'".';
                } elseif (! $doctorExists && ! $especialidadExists) {
                    $emptyMessage = 'No existe la especialidad "'.$q.'" ni un doctor con ese nombre.';
                } else {
                    $emptyMessage = 'No tienes citas que coincidan con "'.$q.'".';
                }
            } elseif (in_array($estado, $validStates, true)) {
                $emptyMessage = 'No tienes citas en estado '.$estadoLabel.'.';
            } else {
                $emptyMessage = 'No tienes citas registradas.';
            }
        }

        $bloqueoPagosPendientes = $this->pagoService->pacienteTieneBloqueo($userId);

        return compact(
            'citas',
            'emptyMessage',
            'totalesPorEstado',
            'bloqueoPagosPendientes'
        );
    }

    public function create(Request $request, User $user): array
    {
        $doctores = User::query()
            ->onlyActive()
            ->whereHas('roles', fn ($q) => $q->where('name', 'doctor'))
            ->orderBy('name')
            ->get(['id', 'name']);
        $especialidades = Especialidad::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
        $prefEspecialidad = $request->get('especialidad');
        $laboratorioId = $this->laboratorioEspecialidadId();
        $labExamenes = $this->laboratorioCatalogoExamenes();
        $dependientes = $user->dependientes;

        return compact('doctores', 'especialidades', 'prefEspecialidad', 'laboratorioId', 'labExamenes', 'dependientes');
    }

    public function store(Request $request, User $user): array
    {
        if ($this->pagoService->pacienteTieneBloqueo((int) $user->id)) {
            return ['ok' => false, 'type' => 'errors', 'errors' => ['error' => PagoService::MENSAJE_BLOQUEO]];
        }

        $request->validate(
            [
                'doctor_id' => 'required|exists:users,id',
                'especialidad_id' => 'required|exists:especialidades,id',
                'fecha' => 'required|date',
                'hora' => 'required|date_format:H:i',
                'hold_token' => 'nullable|string|max:80',
                'motivo_consulta' => ValidationRules::motivoConsulta(),
                'dependiente_id' => 'nullable|exists:dependientes,id',
            ],
            [
                'doctor_id.required' => 'Seleccione un doctor.',
                'doctor_id.exists' => 'El doctor seleccionado no existe.',
                'especialidad_id.required' => 'Seleccione una especialidad.',
                'especialidad_id.exists' => 'La especialidad seleccionada no existe.',
                'fecha.required' => 'Seleccione una fecha.',
                'fecha.date' => 'La fecha no es válida.',
                'hora.required' => 'Ingrese una hora.',
                'hora.date_format' => 'Formato de hora inválido. Use HH:MM.',
                'motivo_consulta.required' => 'El motivo de consulta es obligatorio.',
                'motivo_consulta.min' => 'El motivo debe tener al menos 3 caracteres.',
                'motivo_consulta.max' => 'El motivo no puede superar 80 caracteres.',
                'motivo_consulta.regex' => 'El motivo debe ir en una sola linea.',
                'dependiente_id.exists' => 'El dependiente seleccionado no existe.',
            ]
        );

        $isDependienteSelected = $request->input('tipo_paciente') === 'dependiente' || $request->filled('dependiente_id');

        if ($isDependienteSelected && $request->input('tipo_paciente') !== 'titular') {
            if (! $request->filled('dependiente_id')) {
                return ['ok' => false, 'type' => 'errors', 'errors' => ['dependiente_id' => 'Selecciona el familiar para quien deseas agendar la cita.']];
            }

            $belongs = $user->dependientes()->where('id', $request->dependiente_id)->exists();
            if (! $belongs) {
                return ['ok' => false, 'type' => 'errors', 'errors' => ['dependiente_id' => 'El dependiente seleccionado no pertenece a tu cuenta.']];
            }
        } else {
            $request->merge(['dependiente_id' => null]);
        }

        $motivoConsulta = $this->priorityEvaluator->sanitizeMotivo($request->input('motivo_consulta'));

        $labId = $this->laboratorioEspecialidadId();
        $isLab = $labId && (int) $request->especialidad_id === (int) $labId;
        $preparacion = null;
        $prioridad = null;

        if ($isLab) {
            $request->validate(
                [
                    'tipo_examen' => 'required|string|max:255',
                    'prioridad' => 'required|in:normal,urgente',
                    'indicaciones' => 'prohibited',
                    'preparacion' => 'prohibited',
                ],
                [
                    'tipo_examen.required' => 'Indica el tipo de examen.',
                ]
            );

            $preparacion = $this->laboratorioPreparacionPorTipo($request->tipo_examen)
                ?: $this->formatearPreparacion($this->laboratorioPreparacionGenerica());

            $prioridad = $request->prioridad ?: 'normal';
        }

        $professional = $this->activeProfessionalForSpecialty(
            (int) $request->doctor_id,
            (int) $request->especialidad_id,
            (bool) $isLab
        );

        if (! $professional) {
            return ['ok' => false, 'type' => 'errors', 'errors' => ['doctor_id' => $this->activeProfessionalError((bool) $isLab)]];
        }

        $slot = Carbon::createFromFormat('H:i', $request->hora);
        $validationError = $this->scheduleService->validateBookingSlot(
            professionalId: (int) $request->doctor_id,
            date: (string) $request->fecha,
            slot: $slot,
            messages: [
                'missing_schedule' => 'No hay horario configurado para ese profesional en ese dia y hora.',
                'missing_schedule_field' => 'error',
                'misaligned' => 'La hora seleccionada no coincide con un bloque disponible del horario configurado.',
                'lead_time' => 'Debes agendar con al menos 1 hora de anticipacion.',
                'conflict' => 'El profesional ya tiene una cita en ese horario o en un bloque inmediato del horario configurado.',
            ],
            exceptHoldToken: $request->input('hold_token')
        );

        if ($validationError) {
            return ['ok' => false, 'type' => 'errors', 'errors' => [$validationError['field'] => $validationError['message']]];
        }

        try {
            $cita = DB::transaction(function () use (
                $request, $user, $isLab, $prioridad, $preparacion, $motivoConsulta
            ) {
                User::query()->whereKey((int) $request->doctor_id)->lockForUpdate()->firstOrFail();

                $slot = Carbon::createFromFormat('H:i', $request->hora);
                $conflict = $this->scheduleService->hasConflict(
                    professionalId: (int) $request->doctor_id,
                    date: (string) $request->fecha,
                    slot: $slot,
                    interval: 30,
                    exceptHoldToken: $request->input('hold_token')
                );

                if ($conflict) {
                    throw new \DomainException(
                        ($isLab
                            ? 'El laboratorio ya tiene una cita en ese horario.'
                            : 'Ese horario acaba de ser reservado por otro paciente. Selecciona otro horario disponible.')
                    );
                }

                $cita = Cita::create([
                    'paciente_id' => $user->id,
                    'dependiente_id' => $request->dependiente_id,
                    'doctor_id' => $request->doctor_id,
                    'especialidad_id' => $request->especialidad_id,
                    'fecha' => $request->fecha,
                    'hora' => $slot->format('H:i:00'),
                    'motivo_consulta' => $motivoConsulta,
                    'estado' => Cita::ESTADO_PENDIENTE,
                    'activo' => true,
                ]);

                $this->priorityEvaluator->apply($cita);
                $cita->save();

                if ($isLab) {
                    LaboratorioOrden::create([
                        'cita_id' => $cita->id,
                        'solicitante_id' => $user->id,
                        'origen' => 'paciente',
                        'prioridad' => $prioridad,
                        'tipo_examen' => $request->tipo_examen,
                        'indicaciones' => null,
                        'preparacion' => $preparacion,
                        'estado' => LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
                    ]);
                }

                $this->slotHoldService->completeByToken(
                    token: $request->input('hold_token'),
                    professionalId: (int) $request->doctor_id,
                    date: (string) $request->fecha,
                    time: $slot->format('H:i:00')
                );

                return $cita;
            });
        } catch (\DomainException $e) {
            $this->slotHoldService->releaseByToken(
                token: $request->input('hold_token'),
                professionalId: (int) $request->doctor_id,
                date: (string) $request->fecha,
                time: $slot->format('H:i:00')
            );

            return ['ok' => false, 'type' => 'errors', 'errors' => ['error' => $e->getMessage()]];
        } catch (QueryException $e) {
            $this->slotHoldService->releaseByToken(
                token: $request->input('hold_token'),
                professionalId: (int) $request->doctor_id,
                date: (string) $request->fecha,
                time: $slot->format('H:i:00')
            );
            $msg = $isLab
                ? 'El laboratorio ya tiene una cita exactamente a esa hora.'
                : 'El doctor ya tiene una cita exactamente a esa hora.';

            return ['ok' => false, 'type' => 'errors', 'errors' => ['error' => $msg]];
        }

        try {
            event(new CitaAgendada($cita));
            if (! app(\App\Services\ApplicationModeService::class)->isDemo()) {
                EnviarConfirmacionCitaJob::dispatchAfterResponse($cita);
            }
        } catch (\Throwable $e) {
            Log::error('Error al notificar cita agendada en store(): '.$e->getMessage(), [
                'cita_id' => $cita->id,
                'exception' => $e,
            ]);
        }

        return ['ok' => true, 'cita' => $cita];
    }

    public function cancelar(int $id, int $userId): array
    {
        $transition = DB::transaction(function () use ($id, $userId) {
            $cita = Cita::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($cita->paciente_id != $userId) {
                return ['ok' => false, 'message' => 'No puedes cancelar esta cita.', 'cita' => $cita];
            }

            if ($this->noShowService->marcarSiVencio($cita)) {
                return ['ok' => false, 'message' => 'La cita ya vencio y se marco como no se presento.', 'cita' => $cita->refresh()];
            }

            if (in_array($cita->estado, [Cita::ESTADO_CANCELADA, Cita::ESTADO_REALIZADA, Cita::ESTADO_NO_SE_PRESENTO])) {
                return ['ok' => false, 'message' => 'Esta cita ya no puede ser cancelada.', 'cita' => $cita];
            }

            $cita->estado = Cita::ESTADO_CANCELADA;
            $cita->activo = false;
            $cita->save();

            return ['ok' => true, 'message' => null, 'cita' => $cita->refresh()];
        });

        if (! $transition['ok']) {
            return ['ok' => false, 'message' => $transition['message']];
        }

        $cita = $transition['cita'];
        NotificarCambioEstadoCitaJob::dispatchAfterResponse($cita, 'cancelada', 'paciente');

        return ['ok' => true, 'cita' => $cita];
    }

    public function edit(int $id, int $userId): array
    {
        $cita = Cita::with(['doctor', 'especialidad'])->findOrFail($id);

        if ($cita->paciente_id != $userId) {
            return ['ok' => false, 'message' => 'No puedes editar esta cita.'];
        }

        if ($this->noShowService->marcarSiVencio($cita)) {
            return ['ok' => false, 'message' => 'La cita ya vencio y se marco como no se presento.'];
        }

        if (! $cita->esReprogramable()) {
            return ['ok' => false, 'message' => 'Esta cita no puede ser modificada.'];
        }

        return ['ok' => true, 'cita' => $cita];
    }

    public function actualizar(Request $request, int $id, int $userId): array
    {
        $cita = Cita::findOrFail($id);

        if ($cita->paciente_id != $userId) {
            return ['ok' => false, 'type' => 'flash', 'message' => 'No puedes modificar esta cita.'];
        }

        if ($this->noShowService->marcarSiVencio($cita)) {
            return ['ok' => false, 'type' => 'flash', 'message' => 'La cita ya vencio y se marco como no se presento.'];
        }

        if (! $cita->esReprogramable()) {
            return ['ok' => false, 'type' => 'flash', 'message' => 'Esta cita no puede ser modificada.'];
        }

        $request->validate(
            [
                'fecha' => 'required|date',
                'hora' => 'required|date_format:H:i',
                'motivo_consulta' => ValidationRules::motivoConsulta(),
            ],
            [
                'fecha.required' => 'Seleccione una fecha.',
                'fecha.date' => 'La fecha no es válida.',
                'hora.required' => 'Ingrese una hora.',
                'hora.date_format' => 'Formato de hora inválido. Use HH:MM.',
                'motivo_consulta.required' => 'El motivo de consulta es obligatorio.',
                'motivo_consulta.min' => 'El motivo debe tener al menos 3 caracteres.',
                'motivo_consulta.max' => 'El motivo no puede superar 80 caracteres.',
                'motivo_consulta.regex' => 'El motivo debe ir en una sola linea.',
            ]
        );

        $motivoConsulta = $this->priorityEvaluator->sanitizeMotivo($request->input('motivo_consulta'));
        $labId = $this->laboratorioEspecialidadId();
        $isLab = $labId && (int) $cita->especialidad_id === (int) $labId;

        $professional = $this->activeProfessionalForSpecialty(
            (int) $cita->doctor_id,
            (int) $cita->especialidad_id,
            (bool) $isLab
        );

        if (! $professional) {
            return ['ok' => false, 'type' => 'errors', 'errors' => ['error' => 'El profesional asignado a esta cita esta inactivo. Contacta a la clinica para reagendar con otro profesional.']];
        }

        $slot = Carbon::createFromFormat('H:i', $request->hora);
        $validationError = $this->scheduleService->validateBookingSlot(
            professionalId: (int) $cita->doctor_id,
            date: (string) $request->fecha,
            slot: $slot,
            messages: [
                'missing_schedule' => 'No hay horario configurado para ese profesional en ese dia y hora.',
                'missing_schedule_field' => 'error',
                'misaligned' => 'La hora seleccionada no coincide con un bloque disponible del horario configurado.',
                'lead_time' => 'Debes reagendar con al menos 1 hora de anticipacion.',
                'conflict' => 'El profesional ya tiene una cita en ese horario o en un bloque inmediato del horario configurado.',
            ],
            exceptCitaId: (int) $cita->id
        );

        if ($validationError) {
            return ['ok' => false, 'type' => 'errors', 'errors' => [$validationError['field'] => $validationError['message']]];
        }

        $citaId = (int) $cita->id;
        $updatedCita = null;
        try {
            DB::transaction(function () use (&$updatedCita, $citaId, $userId, $request, $slot, $motivoConsulta, $isLab) {
                $freshCita = Cita::query()->whereKey($citaId)->lockForUpdate()->firstOrFail();

                if ($freshCita->paciente_id != $userId) {
                    throw new \DomainException('No puedes modificar esta cita.');
                }

                if ($this->noShowService->marcarSiVencio($freshCita)) {
                    throw new \DomainException('La cita ya vencio y se marco como no se presento.');
                }

                if (! $freshCita->esReprogramable()) {
                    throw new \DomainException('Esta cita no puede ser modificada.');
                }

                User::query()->whereKey((int) $freshCita->doctor_id)->lockForUpdate()->firstOrFail();

                $conflict = $this->scheduleService->hasConflict(
                    professionalId: (int) $freshCita->doctor_id,
                    date: (string) $request->fecha,
                    slot: $slot,
                    interval: 30,
                    exceptCitaId: (int) $freshCita->id
                );

                if ($conflict) {
                    throw new \DomainException(
                        $isLab
                            ? 'El laboratorio ya tiene una cita en ese horario.'
                            : 'Ese horario acaba de ser reservado por otro paciente. Selecciona otro horario disponible.'
                    );
                }

                $freshCita->update([
                    'fecha' => $request->fecha,
                    'hora' => $slot->format('H:i:00'),
                    'motivo_consulta' => $motivoConsulta,
                    'estado' => Cita::ESTADO_PENDIENTE,
                    'activo' => true,
                ]);

                $this->priorityEvaluator->apply($freshCita);
                $freshCita->save();

                $updatedCita = $freshCita;
            });
        } catch (\DomainException $e) {
            return ['ok' => false, 'type' => 'errors', 'errors' => ['error' => $e->getMessage()]];
        } catch (QueryException $e) {
            $msg = $isLab
                ? 'El laboratorio ya tiene una cita exactamente a esa hora.'
                : 'El doctor ya tiene una cita exactamente a esa hora.';

            return ['ok' => false, 'type' => 'errors', 'errors' => ['error' => $msg]];
        }

        NotificarCambioEstadoCitaJob::dispatchAfterResponse($updatedCita, 'reagendada', 'paciente');

        return ['ok' => true, 'cita' => $updatedCita];
    }

    public function laboratorioEspecialidadId(): ?int
    {
        return Especialidad::laboratorioClinicoId();
    }

    public function activeProfessionalForSpecialty(int $professionalId, int $especialidadId, bool $isLab): ?User
    {
        $expectedRole = $isLab ? 'laboratorio' : 'doctor';

        return User::query()
            ->onlyActive()
            ->whereKey($professionalId)
            ->whereHas('roles', fn ($q) => $q->where('name', $expectedRole))
            ->whereHas('especialidades', fn ($q) => $q->where('especialidad_id', $especialidadId))
            ->first();
    }

    public function activeProfessionalError(bool $isLab): string
    {
        return $isLab
            ? 'Selecciona un laboratorio valido y activo para esa especialidad.'
            : 'Selecciona un doctor valido y activo para esa especialidad.';
    }

    public function laboratorioPreparacionGenerica(): array
    {
        return [
            'ayuno' => 'Confirmar en la orden medica.',
            'agua' => 'Agua permitida salvo indicacion.',
            'horario' => 'Preferible en la mañana.',
        ];
    }

    public function laboratorioCatalogoExamenes(): array
    {
        return [
            [
                'value' => 'Hemograma completo',
                'label' => 'Hemograma completo',
                'prep' => [
                    'ayuno' => 'No requiere ayuno.',
                    'agua' => 'Agua permitida.',
                    'horario' => 'Preferible en la mañana.',
                ],
            ],
            [
                'value' => 'Perfil lipidico',
                'label' => 'Perfil lipidico',
                'prep' => [
                    'ayuno' => 'Ayuno de 9 a 12 horas.',
                    'agua' => 'Agua permitida.',
                    'horario' => 'Ideal en la mañana.',
                ],
            ],
            [
                'value' => 'Glucosa en ayunas',
                'label' => 'Glucosa en ayunas',
                'prep' => [
                    'ayuno' => 'Ayuno de 8 horas.',
                    'agua' => 'Agua permitida.',
                    'horario' => 'Preferible antes de las 09:00.',
                ],
            ],
            [
                'value' => 'Uroanalisis',
                'label' => 'Uroanalisis',
                'prep' => [
                    'ayuno' => 'No requiere ayuno.',
                    'agua' => 'Evitar exceso de liquidos antes de la toma.',
                    'horario' => 'Primera orina de la mañana.',
                ],
            ],
            [
                'value' => 'Otro examen',
                'label' => 'Otro examen (segun orden medica)',
                'prep' => $this->laboratorioPreparacionGenerica(),
            ],
        ];
    }

    public function laboratorioPreparacionPorTipo(string $tipoExamen): ?string
    {
        foreach ($this->laboratorioCatalogoExamenes() as $examen) {
            if ($examen['value'] === $tipoExamen) {
                return $this->formatearPreparacion($examen['prep'] ?? []);
            }
        }

        return null;
    }

    public function formatearPreparacion(array $prep): string
    {
        $partes = [];
        if (! empty($prep['ayuno'])) {
            $partes[] = 'Ayuno: '.$prep['ayuno'];
        }
        if (! empty($prep['agua'])) {
            $partes[] = 'Agua: '.$prep['agua'];
        }
        if (! empty($prep['horario'])) {
            $partes[] = 'Horario recomendado: '.$prep['horario'];
        }

        return implode(' | ', $partes);
    }

    public function normalizeSearchTerm(mixed $value, int $maxLength = 100): string
    {
        return trim(mb_substr((string) $value, 0, $maxLength));
    }
}
