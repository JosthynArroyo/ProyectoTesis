<?php

namespace App\Http\Controllers;

use App\Events\CitaAgendada;
use App\Events\CitaAtendida;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Models\Cita;
use App\Models\CitaEvento;
use App\Models\Especialidad;
use App\Models\NotaSoap;
use App\Models\Horario;
use App\Models\LaboratorioOrden;
use App\Models\User;
use App\Services\CitaNoShowService;
use App\Services\PagoService;
use App\Services\PriorityEvaluator;
use App\Services\ProfessionalScheduleService;
use App\Services\SlotHoldService;
use App\Support\ValidationRules;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CitaController extends Controller
{
    /** ======================= PACIENTE: LISTADO ======================= */
    public function index(Request $request)
    {
        app(CitaNoShowService::class)->marcarVencidas();
        $userId = Auth::id();
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
                    // Keep raw fragments parameterized; never interpolate request text into SQL.
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

        $bloqueoPagosPendientes = app(PagoService::class)->pacienteTieneBloqueo($userId);

        return view('paciente.citas', compact(
            'citas',
            'emptyMessage',
            'totalesPorEstado',
            'bloqueoPagosPendientes'
        ));
    }

    /** ======================= PACIENTE: CREAR ======================= */
    protected function laboratorioEspecialidadId(): ?int
    {
        return Especialidad::laboratorioClinicoId();
    }

    protected function activeProfessionalForSpecialty(int $professionalId, int $especialidadId, bool $isLab): ?User
    {
        $expectedRole = $isLab ? 'laboratorio' : 'doctor';

        return User::query()
            ->onlyActive()
            ->whereKey($professionalId)
            ->whereHas('roles', fn ($q) => $q->where('name', $expectedRole))
            ->whereHas('especialidades', fn ($q) => $q->where('especialidad_id', $especialidadId))
            ->first();
    }

    protected function activeProfessionalError(bool $isLab): string
    {
        return $isLab
            ? 'Selecciona un laboratorio valido y activo para esa especialidad.'
            : 'Selecciona un doctor valido y activo para esa especialidad.';
    }

    protected function laboratorioPreparacionGenerica(): array
    {
        return [
            'ayuno' => 'Confirmar en la orden medica.',
            'agua' => 'Agua permitida salvo indicacion.',
            'horario' => 'Preferible en la mañana.',
        ];
    }

    protected function laboratorioCatalogoExamenes(): array
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

    protected function laboratorioPreparacionPorTipo(string $tipoExamen): ?string
    {
        foreach ($this->laboratorioCatalogoExamenes() as $examen) {
            if ($examen['value'] === $tipoExamen) {
                return $this->formatearPreparacion($examen['prep'] ?? []);
            }
        }

        return null;
    }

    protected function formatearPreparacion(array $prep): string
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

    public function create(Request $request)
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
        $dependientes = Auth::user()->dependientes;

        return view('paciente.crear-cita', compact('doctores', 'especialidades', 'prefEspecialidad', 'laboratorioId', 'labExamenes', 'dependientes'));
    }

    public function store(
        Request $request,
        PriorityEvaluator $priorityEvaluator,
        ProfessionalScheduleService $scheduleService,
        SlotHoldService $slotHoldService
    ) {
        if (app(PagoService::class)->pacienteTieneBloqueo((int) Auth::id())) {
            return back()->withErrors(['error' => PagoService::MENSAJE_BLOQUEO])->withInput();
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

        if ($request->filled('dependiente_id')) {
            $belongs = Auth::user()->dependientes()->where('id', $request->dependiente_id)->exists();
            if (!$belongs) {
                return back()->withErrors(['dependiente_id' => 'El dependiente seleccionado no pertenece a tu cuenta.'])->withInput();
            }
        }

        $motivoConsulta = $priorityEvaluator->sanitizeMotivo($request->input('motivo_consulta'));

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
            return back()
                ->withErrors(['doctor_id' => $this->activeProfessionalError((bool) $isLab)])
                ->withInput();
        }

        $slot = Carbon::createFromFormat('H:i', $request->hora);
        $validationError = $scheduleService->validateBookingSlot(
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
            return back()->withErrors([$validationError['field'] => $validationError['message']])->withInput();
        }

        $citaResult = null;
        try {
            $citaResult = DB::transaction(function () use (
                $request, $slot, $priorityEvaluator, $scheduleService, $slotHoldService,
                $isLab, $prioridad, $preparacion, $motivoConsulta
            ) {
                // Acquire a stable single-row lock on the doctor before any reads/writes.
                // This guarantees mutual exclusion even when there are no existing appointments
                // for that day yet (an empty row-set lock would not block concurrent transactions).
                User::query()->whereKey((int) $request->doctor_id)->lockForUpdate()->firstOrFail();

                // Re-verify availability inside the serialised transaction.
                // IMPORTANT: pass exceptHoldToken so the patient's own active hold is not
                // counted as a conflict against themselves.
                $slot = Carbon::createFromFormat('H:i', $request->hora);
                $conflict = $scheduleService->hasConflict(
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
                    'paciente_id' => Auth::id(),
                    'dependiente_id' => $request->dependiente_id,
                    'doctor_id' => $request->doctor_id,
                    'especialidad_id' => $request->especialidad_id,
                    'fecha' => $request->fecha,
                    'hora' => $slot->format('H:i:00'),
                    'motivo_consulta' => $motivoConsulta,
                    'estado' => Cita::ESTADO_PENDIENTE,
                    'activo' => true,
                ]);

                $priorityEvaluator->apply($cita);
                $cita->save();

                if ($isLab) {
                    LaboratorioOrden::create([
                        'cita_id' => $cita->id,
                        'solicitante_id' => Auth::id(),
                        'origen' => 'paciente',
                        'prioridad' => $prioridad,
                        'tipo_examen' => $request->tipo_examen,
                        'indicaciones' => null,
                        'preparacion' => $preparacion,
                        'estado' => LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
                    ]);
                }

                $slotHoldService->completeByToken(
                    token: $request->input('hold_token'),
                    professionalId: (int) $request->doctor_id,
                    date: (string) $request->fecha,
                    time: $slot->format('H:i:00')
                );

                return $cita;
            });
        } catch (\DomainException $e) {
            $slotHoldService->releaseByToken(
                token: $request->input('hold_token'),
                professionalId: (int) $request->doctor_id,
                date: (string) $request->fecha,
                time: $slot->format('H:i:00')
            );
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        } catch (QueryException $e) {
            $slotHoldService->releaseByToken(
                token: $request->input('hold_token'),
                professionalId: (int) $request->doctor_id,
                date: (string) $request->fecha,
                time: $slot->format('H:i:00')
            );
            $msg = $isLab
                ? 'El laboratorio ya tiene una cita exactamente a esa hora.'
                : 'El doctor ya tiene una cita exactamente a esa hora.';

            return back()->withErrors(['error' => $msg])->withInput();
        }

        $cita = $citaResult;

        // Dispatch jobs AFTER the transaction has committed.
        try {
            event(new CitaAgendada($cita));
            EnviarConfirmacionCitaJob::dispatchAfterResponse($cita);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error al notificar cita agendada en store(): ' . $e->getMessage(), [
                'cita_id' => $cita->id,
                'exception' => $e
            ]);
        }

        return redirect()->route('paciente.citas')
            ->with('success', 'Cita creada con éxito. Confirmación enviada y doctor notificado.')
            ->with('success_action_url', route('paciente.citas'))
            ->with('success_action_label', 'Ver mis citas')
            ->with('highlight_cita', $cita->id);
    }

    public function cancelar($id)
    {
        $transition = DB::transaction(function () use ($id) {
            $cita = Cita::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($cita->paciente_id != Auth::id()) {
                return ['ok' => false, 'message' => 'No puedes cancelar esta cita.', 'cita' => $cita];
            }

            if (app(CitaNoShowService::class)->marcarSiVencio($cita)) {
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
            return back()->with('error', $transition['message']);
        }

        $cita = $transition['cita'];
        NotificarCambioEstadoCitaJob::dispatchAfterResponse($cita, 'cancelada', 'paciente');

        return back()
            ->with('success', 'Cita cancelada.')
            ->with('success_action_url', route('paciente.citas'))
            ->with('success_action_label', 'Ver mis citas')
            ->with('highlight_cita', $cita->id);
    }

    /** ======================= PACIENTE: EDITAR/ACTUALIZAR ======================= */
    public function edit($id)
    {
        $cita = Cita::with(['doctor', 'especialidad'])->findOrFail($id);

        if ($cita->paciente_id != Auth::id()) {
            return back()->with('error', 'No puedes editar esta cita.');
        }

        if (app(CitaNoShowService::class)->marcarSiVencio($cita)) {
            return back()->with('error', 'La cita ya vencio y se marco como no se presento.');
        }

        if (in_array($cita->estado, [Cita::ESTADO_CANCELADA, Cita::ESTADO_REALIZADA, Cita::ESTADO_NO_SE_PRESENTO])) {
            return back()->with('error', 'Esta cita no puede ser modificada.');
        }

        return view('paciente.editar-cita', compact('cita'));
    }

    public function actualizar(Request $request, $id, PriorityEvaluator $priorityEvaluator, ProfessionalScheduleService $scheduleService)
    {
        $cita = Cita::findOrFail($id);

        if ($cita->paciente_id != Auth::id()) {
            return back()->with('error', 'No puedes modificar esta cita.');
        }

        if (app(CitaNoShowService::class)->marcarSiVencio($cita)) {
            return back()->with('error', 'La cita ya vencio y se marco como no se presento.');
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

        $motivoConsulta = $priorityEvaluator->sanitizeMotivo($request->input('motivo_consulta'));
        $labId = $this->laboratorioEspecialidadId();
        $isLab = $labId && (int) $cita->especialidad_id === (int) $labId;

        $professional = $this->activeProfessionalForSpecialty(
            (int) $cita->doctor_id,
            (int) $cita->especialidad_id,
            (bool) $isLab
        );

        if (! $professional) {
            return back()
                ->withErrors(['error' => 'El profesional asignado a esta cita esta inactivo. Contacta a la clinica para reagendar con otro profesional.'])
                ->withInput();
        }

        $slot = Carbon::createFromFormat('H:i', $request->hora);
        $validationError = $scheduleService->validateBookingSlot(
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
            return back()->withErrors([$validationError['field'] => $validationError['message']])->withInput();
        }

        $citaId = $cita->id;
        try {
            DB::transaction(function () use (&$cita, $request, $slot, $priorityEvaluator, $scheduleService, $motivoConsulta, $isLab) {
                // Acquire stable single-row lock on the doctor before re-checking availability.
                User::query()->whereKey((int) $cita->doctor_id)->lockForUpdate()->firstOrFail();

                $conflict = $scheduleService->hasConflict(
                    professionalId: (int) $cita->doctor_id,
                    date: (string) $request->fecha,
                    slot: $slot,
                    interval: 30,
                    exceptCitaId: (int) $cita->id
                    // No hold_token on reschedule: holds are only issued on initial slot selection
                );

                if ($conflict) {
                    throw new \DomainException(
                        $isLab
                            ? 'El laboratorio ya tiene una cita en ese horario.'
                            : 'Ese horario acaba de ser reservado por otro paciente. Selecciona otro horario disponible.'
                    );
                }

                $cita->update([
                    'fecha' => $request->fecha,
                    'hora' => $slot->format('H:i:00'),
                    'motivo_consulta' => $motivoConsulta,
                    'estado' => Cita::ESTADO_PENDIENTE,
                    'activo' => true,
                ]);

                $priorityEvaluator->apply($cita);
                $cita->save();
            });
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        } catch (QueryException $e) {
            $msg = $isLab
                ? 'El laboratorio ya tiene una cita exactamente a esa hora.'
                : 'El doctor ya tiene una cita exactamente a esa hora.';

            return back()->withErrors(['error' => $msg])->withInput();
        }

        // Dispatch AFTER transaction commits.
        NotificarCambioEstadoCitaJob::dispatchAfterResponse($cita, 'reagendada', 'paciente');

        return redirect()->route('paciente.citas')
            ->with('success', 'Cita reagendada.')
            ->with('success_action_url', route('paciente.citas'))
            ->with('success_action_label', 'Ver mis citas')
            ->with('highlight_cita', $cita->id);
    }

    /** ======================= DOCTOR: LISTADO ======================= */
    public function indexDoctor(Request $request)
    {
        app(CitaNoShowService::class)->marcarVencidas();
        $doctorId = Auth::id();
        $estado = $this->obtenerEstadoFiltroDoctor($request);
        $prioridad = $this->obtenerPrioridadFiltroDoctor($request);

        $citas = Cita::query()
            ->where('doctor_id', $doctorId)
            ->when($estado !== '', fn ($query) => $query->where('estado', $estado))
            ->when($prioridad !== '', fn ($query) => $query->where('prioridad_nivel', $prioridad))
            ->with([
                'paciente:id,name',
                'dependiente:id,nombre,dni,parentesco',
                'especialidad:id,nombre',
                'receta:id,cita_id,created_at,updated_at',
                'notaSoap:id,cita_id,estado',
                'certificadoMedico:id,cita_id,codigo,pdf_path',
            ])
            ->orderByRaw(Cita::prioridadOrderSql())
            ->orderBy('fecha', 'asc')
            ->orderBy('hora', 'asc')
            ->paginate(25, [
                'id',
                'paciente_id',
                'dependiente_id',
                'doctor_id',
                'especialidad_id',
                'fecha',
                'hora',
                'estado',
                'activo',
                'prioridad_nivel',
                'prioridad_red_flag',
            ])
            ->withQueryString();

        $citasPagina = $citas->getCollection();

        $dt = function ($fecha, $hora) {
            $d = Carbon::parse($fecha, 'America/Guayaquil');
            if (! empty($hora)) {
                $hhmm = substr($hora, 0, 5);
                [$H,$M] = array_map('intval', explode(':', $hhmm));
                $d->setTime($H, $M, 0);
            }

            return $d;
        };
        $pairKey = fn ($p, $dep, $d) => $p.'|'.($dep ?? '0').'|'.$d;
        $pacientesConRealizadas = $citasPagina
            ->where('estado', Cita::ESTADO_REALIZADA)
            ->pluck('paciente_id')
            ->filter()
            ->unique()
            ->values();

        // Todas las citas del doctor, ordenadas por fecha+hora, agrupadas por (paciente,doctor)
        $todasPorPar = Cita::query()
            ->where('doctor_id', $doctorId)
            ->whereIn('paciente_id', $pacientesConRealizadas)
            ->get(['id', 'paciente_id', 'dependiente_id', 'doctor_id', 'fecha', 'hora', 'estado', 'activo'])
            ->sortBy(fn ($c) => $dt($c->fecha, $c->hora)->format('Y-m-d H:i:s'))
            ->groupBy(fn ($c) => $pairKey($c->paciente_id, $c->dependiente_id, $c->doctor_id));

        // id_de_realizada -> cita inmediatamente posterior (independiente del estado actual de esa posterior)
        $mapProxima = [];

        foreach ($todasPorPar as $lista) {
            $count = $lista->count();
            if ($count < 2) {
                continue;
            }

            // Precalcular datetimes
            $fechas = [];
            foreach ($lista as $i => $c) {
                $fechas[$i] = $dt($c->fecha, $c->hora);
            }

            // Para cada realizada R en posición i, buscar el primer j>i
            foreach ($lista as $i => $c) {
                if ($c->estado !== Cita::ESTADO_REALIZADA) {
                    continue;
                }

                $nextIdx = null;
                for ($j = $i + 1; $j < $count; $j++) {
                    // primera posterior estricta
                    if ($fechas[$j]->gt($fechas[$i])) {
                        $nextIdx = $j;
                        break;
                    }
                }
                if ($nextIdx !== null) {
                    $mapProxima[$c->id] = $lista[$nextIdx];
                }
            }
        }

        // Adjuntar en filas de la tabla
        foreach ($citasPagina as $c) {
            $c->proxima_cita = $mapProxima[$c->id] ?? null;
        }

        return view('doctor.citas', compact('citas', 'estado', 'prioridad'));
    }

    protected function obtenerEstadoFiltroDoctor(Request $request): string
    {
        $estado = trim((string) $request->get('estado', ''));
        if ($estado === 'all') {
            return '';
        }
        $validStates = ['pendiente', 'confirmada', 'cancelada', 'realizada', 'no_se_presento'];

        return in_array($estado, $validStates, true) ? $estado : '';
    }

    protected function obtenerPrioridadFiltroDoctor(Request $request): string
    {
        $prioridad = strtoupper(trim((string) $request->get('prioridad', '')));
        if ($prioridad === 'ALL') {
            return '';
        }

        return in_array($prioridad, Cita::PRIORIDAD_NIVELES, true) ? $prioridad : '';
    }

    protected function normalizeSearchTerm(mixed $value, int $maxLength = 100): string
    {
        return trim(mb_substr((string) $value, 0, $maxLength));
    }

    protected function etiquetaEstadoCita(string $estado): string
    {
        return $estado === \App\Models\Cita::ESTADO_NO_SE_PRESENTO ? 'No se presento' : ucfirst($estado);
    }

    protected function queryDoctorCitas(int $doctorId, string $estado = '', string $prioridad = '')
    {
        return \App\Models\Cita::where('doctor_id', $doctorId)
            ->when($estado !== '', fn ($query) => $query->where('estado', $estado))
            ->when($prioridad !== '', fn ($query) => $query->where('prioridad_nivel', $prioridad))
            ->orderByRaw(Cita::prioridadOrderSql())
            ->orderBy('fecha', 'asc')
            ->orderBy('hora', 'asc');
    }

    public function aceptar($id)
    {
        $transition = DB::transaction(function () use ($id) {
            $cita = Cita::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($cita->doctor_id != Auth::id()) {
                return ['ok' => false, 'message' => 'No puedes aceptar esta cita.', 'cita' => $cita];
            }

            if (app(CitaNoShowService::class)->marcarSiVencio($cita)) {
                return ['ok' => false, 'message' => 'La cita ya vencio y se marco como no se presento.', 'cita' => $cita->refresh()];
            }

            if ($cita->estado !== Cita::ESTADO_PENDIENTE) {
                return ['ok' => false, 'message' => 'Solo puedes aceptar citas pendientes.', 'cita' => $cita];
            }

            $cita->estado = Cita::ESTADO_CONFIRMADA;
            $cita->activo = true;
            $cita->save();

            return ['ok' => true, 'message' => null, 'cita' => $cita->refresh()];
        });

        if (! $transition['ok']) {
            return back()->with('error', $transition['message']);
        }

        $cita = $transition['cita'];
        NotificarCambioEstadoCitaJob::dispatchAfterResponse($cita, 'aceptada', 'doctor');

        return back()->with('success', 'Cita confirmada.');
    }

    public function rechazar($id)
    {
        $transition = DB::transaction(function () use ($id) {
            $cita = Cita::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($cita->doctor_id != Auth::id()) {
                return ['ok' => false, 'message' => 'No puedes rechazar esta cita.', 'cita' => $cita];
            }

            if (app(CitaNoShowService::class)->marcarSiVencio($cita)) {
                return ['ok' => false, 'message' => 'La cita ya vencio y se marco como no se presento.', 'cita' => $cita->refresh()];
            }

            if ($cita->estado !== Cita::ESTADO_PENDIENTE) {
                return ['ok' => false, 'message' => 'Solo puedes rechazar citas pendientes.', 'cita' => $cita];
            }

            $cita->estado = Cita::ESTADO_CANCELADA;
            $cita->activo = false;
            $cita->save();

            return ['ok' => true, 'message' => null, 'cita' => $cita->refresh()];
        });

        if (! $transition['ok']) {
            return back()->with('error', $transition['message']);
        }

        $cita = $transition['cita'];
        NotificarCambioEstadoCitaJob::dispatchAfterResponse($cita, 'cancelada', 'doctor');

        return back()->with('success', 'Cita rechazada.');
    }

    public function realizar($id)
    {
        $transition = DB::transaction(function () use ($id) {
            $cita = Cita::query()->with('notaSoap')->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($cita->doctor_id != Auth::id()) {
                return ['ok' => false, 'message' => 'No puedes marcar esta cita.', 'redirect' => null, 'cita' => $cita];
            }

            if (app(CitaNoShowService::class)->marcarSiVencio($cita)) {
                return ['ok' => false, 'message' => 'La cita ya vencio y se marco como no se presento.', 'redirect' => null, 'cita' => $cita->refresh()];
            }

            if ($cita->estado !== Cita::ESTADO_CONFIRMADA) {
                return ['ok' => false, 'message' => 'Solo puedes marcar como realizada citas confirmadas.', 'redirect' => null, 'cita' => $cita];
            }

            if (! $cita->notaSoap || $cita->notaSoap->estado !== \App\Models\NotaSoap::ESTADO_FIRMADA) {
                return [
                    'ok' => false,
                    'message' => 'Debes firmar la nota clinica antes de marcar la cita como realizada.',
                    'redirect' => route('doctor.citas.soap', $cita->id),
                    'cita' => $cita,
                ];
            }

            $cita->estado = Cita::ESTADO_REALIZADA;
            $cita->activo = true;
            $cita->save();

            return ['ok' => true, 'message' => null, 'redirect' => null, 'cita' => $cita->refresh()];
        });

        if (! $transition['ok']) {
            if ($transition['redirect']) {
                return redirect($transition['redirect'])->with('error', $transition['message']);
            }

            return back()->with('error', $transition['message']);
        }

        $cita = $transition['cita'];
        event(new CitaAtendida($cita));

        return back()->with('success', 'Cita marcada como realizada.');
    }

    public function exportarDoctorExcel(Request $request)
    {
        $doctorId = Auth::id();
        $estado = $this->obtenerEstadoFiltroDoctor($request);
        $prioridad = $this->obtenerPrioridadFiltroDoctor($request);

        $rows = $this->queryDoctorCitas($doctorId, $estado, $prioridad)
            ->with(['paciente:id,name', 'especialidad:id,nombre'])
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Paciente', 'Especialidad', 'Fecha', 'Hora', 'Estado', 'Prioridad']], null, 'A1', true);

        $fila = 2;
        foreach ($rows as $cita) {
            $sheet->setCellValue("A{$fila}", $cita->paciente?->name ?? 'Sin paciente');
            $sheet->setCellValue("B{$fila}", $cita->especialidad?->nombre ?? 'Sin especialidad');
            $sheet->setCellValue("C{$fila}", $cita->fecha);
            $sheet->setCellValue("D{$fila}", substr((string) $cita->hora, 0, 5));
            $sheet->setCellValue("E{$fila}", $this->etiquetaEstadoCita($cita->estado));
            $sheet->setCellValue("F{$fila}", ($cita->prioridad_nivel ?? Cita::PRIORIDAD_BAJA).($cita->prioridad_red_flag ? ' (Red flag)' : ''));
            $fila++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'citas_doctor_'.$doctorId.'_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            if (ob_get_length()) {
                ob_end_clean();
            }
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function exportarDoctorPdf(Request $request)
    {
        $doctorId = Auth::id();
        $estado = $this->obtenerEstadoFiltroDoctor($request);
        $prioridad = $this->obtenerPrioridadFiltroDoctor($request);

        $citas = $this->queryDoctorCitas($doctorId, $estado, $prioridad)
            ->with(['paciente:id,name', 'especialidad:id,nombre'])
            ->get();

        $html = view('doctor.citas-export-pdf', [
            'citas' => $citas,
            'doctor' => Auth::user(),
            'estado' => $estado,
            'prioridad' => $prioridad,
        ])->render();

        $opt = new Options;
        $opt->set('isRemoteEnabled', true);
        $opt->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($opt);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $fileName = 'citas_doctor_'.$doctorId.'_'.now()->format('Ymd_His').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    /** ======================= DOCTOR: DISPONIBILIDAD + PROXIMA CITA (TOAST) ======================= */

    /** GET /doctor/disponibilidad/check  */
    public function checkDisponibilidad(Request $r)
    {
        $doctorId = (int) Auth::id();
        $ref = $r->date('fecha_preferida') ?? now('America/Guayaquil');

        $tiene = Horario::where('doctor_id', $doctorId)
            ->whereBetween('fecha', [
                Carbon::parse($ref)->startOfWeek()->toDateString(),
                Carbon::parse($ref)->endOfWeek()->toDateString(),
            ])->exists();

        return $tiene
            ? response()->json(['ok' => true])
            : response()->json(['ok' => false, 'reason' => 'sin_horario']);
    }

    protected function fechaHoraCita(Cita $cita): Carbon
    {
        return Carbon::parse(
            Carbon::parse($cita->fecha)->toDateString().' '.substr((string) $cita->hora, 0, 8),
            'America/Guayaquil'
        );
    }

    protected function controlPosteriorActivo(Cita $cita): ?Cita
    {
        $inicio = $this->fechaHoraCita($cita);

        return Cita::query()
            ->where('id', '<>', $cita->id)
            ->when(
                $cita->dependiente_id,
                fn ($query) => $query->where('dependiente_id', $cita->dependiente_id),
                fn ($query) => $query
                    ->where('paciente_id', $cita->paciente_id)
                    ->whereNull('dependiente_id')
            )
            ->where('doctor_id', $cita->doctor_id)
            ->where('especialidad_id', $cita->especialidad_id)
            ->where('activo', true)
            ->whereNotIn('estado', [Cita::ESTADO_CANCELADA, Cita::ESTADO_REALIZADA, Cita::ESTADO_NO_SE_PRESENTO])
            ->where(function ($query) use ($inicio) {
                $query->whereDate('fecha', '>', $inicio->toDateString())
                    ->orWhere(function ($sameDay) use ($inicio) {
                        $sameDay->whereDate('fecha', $inicio->toDateString())
                            ->whereTime('hora', '>', $inicio->format('H:i:s'));
                    });
            })
            ->orderBy('fecha')
            ->orderBy('hora')
            ->first();
    }

    protected function controlPerteneceACita(Cita $cita, Cita $control): bool
    {
        if ($control->id === $cita->id) {
            return false;
        }

        if (
            (int) $control->paciente_id !== (int) $cita->paciente_id
            || (int) ($control->dependiente_id ?? 0) !== (int) ($cita->dependiente_id ?? 0)
            || (int) $control->doctor_id !== (int) $cita->doctor_id
            || (int) $control->especialidad_id !== (int) $cita->especialidad_id
        ) {
            return false;
        }

        return $this->fechaHoraCita($control)->gt($this->fechaHoraCita($cita));
    }

    public function cancelarControlPlanificado(Cita $cita, Cita $control)
    {
        if ($cita->doctor_id !== Auth::id()) {
            abort(403);
        }

        if (! $this->controlPerteneceACita($cita, $control)) {
            abort(404);
        }

        $transition = DB::transaction(function () use ($control) {
            $control = Cita::query()->whereKey($control->id)->lockForUpdate()->firstOrFail();

            if (app(CitaNoShowService::class)->marcarSiVencio($control)) {
                return ['ok' => false, 'message' => 'El control ya vencio y se marco como no se presento.', 'control' => $control->refresh()];
            }

            if (in_array($control->estado, [Cita::ESTADO_CANCELADA, Cita::ESTADO_REALIZADA, Cita::ESTADO_NO_SE_PRESENTO], true)) {
                return ['ok' => false, 'message' => 'Este control ya no puede cancelarse.', 'control' => $control];
            }

            $control->estado = Cita::ESTADO_CANCELADA;
            $control->activo = false;
            $control->save();

            return ['ok' => true, 'message' => null, 'control' => $control->refresh()];
        });

        if (! $transition['ok']) {
            return back()->with('error', $transition['message']);
        }

        $control = $transition['control'];
        NotificarCambioEstadoCitaJob::dispatchAfterResponse($control, 'cancelada', 'doctor');

        return redirect()->route('doctor.citas.soap', $cita)
            ->with('success', 'Control cancelado correctamente.');
    }

    /**
     * POST /doctor/citas/{cita}/proxima/planificar
     * Crea la próxima cita con fecha/hora elegidas por el doctor.
     */
    public function proximaPlanificada(Request $request, Cita $cita, PriorityEvaluator $priorityEvaluator, ProfessionalScheduleService $scheduleService)
    {
        if ($cita->doctor_id !== Auth::id()) {
            abort(403);
        }

        if (app(PagoService::class)->pacienteTieneBloqueo((int) $cita->paciente_id)) {
            return response()->json([
                'ok' => false,
                'msg' => PagoService::MENSAJE_BLOQUEO,
            ], 423);
        }

        $request->validate(
            ['fecha' => 'required|date', 'hora' => 'required|date_format:H:i'],
            ['fecha.required' => 'Seleccione una fecha.', 'hora.required' => 'Seleccione una hora.']
        );

        $slot = Carbon::createFromFormat('H:i', $request->hora);
        if ($slot->minute % 30 !== 0) {
            return response()->json(['ok' => false, 'msg' => 'La hora debe ser 00 o 30 minutos.'], 422);
        }

        $nota = NotaSoap::query()
            ->with(['followUpCita'])
            ->where('cita_id', $cita->id)
            ->first();

        if (! $nota || ! $nota->isSigned()) {
            return response()->json(['ok' => false, 'msg' => 'Debes firmar la nota clinica antes de agendar el control.'], 422);
        }

        $controlExistente = null;
        if ($nota->follow_up_cita_id) {
            $controlExistente = Cita::find($nota->follow_up_cita_id);
        } else {
            $controlExistente = Cita::query()
                ->where('source_nota_soap_id', $nota->id)
                ->where('activo', true)
                ->first();
        }
        if ($controlExistente && !$this->controlPerteneceACita($cita, $controlExistente)) {
            $controlExistente = null;
        }

        $esReagendamiento = ($controlExistente !== null);
        $fechaAnterior = null;
        $horaAnterior = null;
        if ($esReagendamiento) {
            $fechaAnterior = $controlExistente->fecha ? Carbon::parse($controlExistente->fecha)->format('d/m/Y') : null;
            $horaAnterior = $controlExistente->hora ? Carbon::parse($controlExistente->hora)->format('H:i') : null;
        }

        $validationError = $scheduleService->validateBookingSlot(
            professionalId: (int) $cita->doctor_id,
            date: (string) $request->fecha,
            slot: $slot,
            messages: [
                'missing_schedule' => 'No hay horario para ese dia/hora.',
                'missing_schedule_field' => 'fecha',
                'misaligned' => 'La hora no coincide con un bloque disponible del horario configurado.',
                'misaligned_field' => 'hora',
                'lead_time' => 'Debe ser al menos 1 hora despues de la hora actual.',
                'lead_time_field' => 'fecha',
                'conflict' => 'Choque con otra cita en un bloque inmediato del horario configurado.',
                'conflict_field' => 'hora',
            ],
            exceptCitaId: $controlExistente?->id
        );

        if ($validationError) {
            return response()->json(['ok' => false, 'msg' => $validationError['message']], 422);
        }

        try {
            $result = DB::transaction(function () use ($cita, $request, $slot, $priorityEvaluator, $nota, $scheduleService) {
                // Acquire stable single-row lock on the doctor FIRST.
                // A lock on existing appointment rows would not protect us when there are
                // zero appointments for that day, allowing two concurrent transactions to
                // both read an empty set and proceed to insert the same slot.
                User::query()->whereKey((int) $cita->doctor_id)->lockForUpdate()->firstOrFail();

                $cita = Cita::query()->whereKey($cita->id)->lockForUpdate()->firstOrFail();

                $nota = NotaSoap::query()
                    ->whereKey($nota->id)
                    ->with(['followUpCita'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $controlExistente = null;
                if ($nota->follow_up_cita_id) {
                    $controlExistente = Cita::query()->whereKey($nota->follow_up_cita_id)->lockForUpdate()->first();
                } else {
                    $controlExistente = Cita::query()
                        ->where('source_nota_soap_id', $nota->id)
                        ->where('activo', true)
                        ->lockForUpdate()
                        ->first();
                }
                if ($controlExistente && !$this->controlPerteneceACita($cita, $controlExistente)) {
                    $controlExistente = null;
                }

                $control = $controlExistente
                    ? Cita::query()->whereKey($controlExistente->id)->lockForUpdate()->firstOrFail()
                    : new Cita([
                        'paciente_id' => $cita->paciente_id,
                        'dependiente_id' => $cita->dependiente_id,
                        'doctor_id' => $cita->doctor_id,
                        'especialidad_id' => $cita->especialidad_id,
                        'motivo_consulta' => 'Consulta médica de control',
                        'source_nota_soap_id' => $nota->id,
                     ]);

                // Re-verify availability inside the serialised critical section.
                $hasConflict = $scheduleService->hasConflict(
                    professionalId: (int) $cita->doctor_id,
                    date: (string) $request->fecha,
                    slot: $slot,
                    interval: 30,
                    exceptCitaId: $control->exists ? $control->id : null
                );

                if ($hasConflict) {
                    throw new \DomainException('Ese horario acaba de ser reservado por otro paciente. Selecciona otro horario disponible.');
                }

                $esNuevo = ! $control->exists;
                $control->fill([
                    'fecha' => $request->fecha,
                    'hora' => $slot->format('H:i:00'),
                    'estado' => Cita::ESTADO_PENDIENTE,
                    'activo' => true,
                    'source_nota_soap_id' => $nota->id,
                ]);

                $priorityEvaluator->apply($control);
                $control->save();

                // Keep follow_up_date on NotaSoap in sync with the control appointment date.
                $nota->forceFill([
                    'follow_up_cita_id' => $control->id,
                    'follow_up_date' => $request->fecha,
                ])->saveQuietly();

                CitaEvento::create([
                    'cita_id' => $control->id,
                    'user_id' => Auth::id(),
                    'tipo' => $esNuevo ? 'control_agendado' : 'control_reagendado',
                ]);

                return [
                    'control' => $control->refresh(),
                    'existed' => ! $esNuevo,
                ];
            });
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error en proximaPlanificada: ' . $e->getMessage(), [
                'exception' => $e,
                'cita_id' => $cita->id,
            ]);
            return response()->json(['ok' => false, 'msg' => 'Ocurrió un error inesperado al procesar el control.'], 500);
        }

        // Dispatch notification jobs AFTER the transaction has committed.
        if (! $esReagendamiento) {
            try {
                event(new CitaAgendada($result['control']));
                EnviarConfirmacionCitaJob::dispatchAfterResponse($result['control']);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Error al notificar cita de control agendada: ' . $e->getMessage(), [
                    'cita_id' => $result['control']->id,
                    'exception' => $e
                ]);
            }
        } else {
            NotificarCambioEstadoCitaJob::dispatchAfterResponse(
                $result['control'],
                'reagendada',
                'doctor',
                $fechaAnterior,
                $horaAnterior
            );
        }

        $control = $result['control'];

        return response()->json([
            'ok' => true,
            'msg' => $result['existed'] ? 'Control reagendado correctamente.' : 'Control agendado correctamente.',
            'redirect' => route('doctor.citas.soap', $cita).'#plan-control-box',
            'cita' => [
                'id' => $control->id,
                'fecha' => Carbon::parse($control->fecha)->format('d/m/Y'),
                'hora' => Carbon::parse($control->hora)->format('H:i'),
                'estado' => $control->estado,
                'especialidad' => $control->especialidad?->nombre,
                'doctor' => $control->doctor?->name,
            ],
        ]);
    }
}
