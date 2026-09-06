<?php

namespace App\Services\Appointments;

use App\Events\CitaAgendada;
use App\Events\CitaAtendida;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Models\Cita;
use App\Models\CitaEvento;
use App\Models\Horario;
use App\Models\NotaSoap;
use App\Models\User;
use App\Services\CitaNoShowService;
use App\Services\PagoService;
use App\Services\PriorityEvaluator;
use App\Services\ProfessionalScheduleService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DoctorAppointmentService
{
    public function __construct(
        protected CitaNoShowService $noShowService,
        protected PagoService $pagoService,
        protected PriorityEvaluator $priorityEvaluator,
        protected ProfessionalScheduleService $scheduleService
    ) {}

    public function indexDoctor(Request $request, int $doctorId): array
    {
        $this->noShowService->marcarVencidas();
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
                'certificadoMedico',
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
                [$H, $M] = array_map('intval', explode(':', $hhmm));
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

        $todasPorPar = Cita::query()
            ->where('doctor_id', $doctorId)
            ->whereIn('paciente_id', $pacientesConRealizadas)
            ->get(['id', 'paciente_id', 'dependiente_id', 'doctor_id', 'fecha', 'hora', 'estado', 'activo'])
            ->sortBy(fn ($c) => $dt($c->fecha, $c->hora)->format('Y-m-d H:i:s'))
            ->groupBy(fn ($c) => $pairKey($c->paciente_id, $c->dependiente_id, $c->doctor_id));

        $mapProxima = [];

        foreach ($todasPorPar as $lista) {
            $count = $lista->count();
            if ($count < 2) {
                continue;
            }

            $fechas = [];
            foreach ($lista as $i => $c) {
                $fechas[$i] = $dt($c->fecha, $c->hora);
            }

            foreach ($lista as $i => $c) {
                if ($c->estado !== Cita::ESTADO_REALIZADA) {
                    continue;
                }

                $nextIdx = null;
                for ($j = $i + 1; $j < $count; $j++) {
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

        foreach ($citasPagina as $c) {
            $c->proxima_cita = $mapProxima[$c->id] ?? null;
        }

        return compact('citas', 'estado', 'prioridad');
    }

    public function aceptar(int $id, int $doctorId): Cita
    {
        $transition = DB::transaction(function () use ($id, $doctorId) {
            $cita = Cita::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($cita->doctor_id != $doctorId) {
                return ['ok' => false, 'message' => 'No puedes aceptar esta cita.', 'cita' => $cita];
            }

            if ($this->noShowService->marcarSiVencio($cita)) {
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
            throw new \DomainException($transition['message']);
        }

        $cita = $transition['cita'];
        NotificarCambioEstadoCitaJob::dispatchAfterResponse($cita, 'aceptada', 'doctor');

        return $cita;
    }

    public function rechazar(int $id, int $doctorId): Cita
    {
        $transition = DB::transaction(function () use ($id, $doctorId) {
            $cita = Cita::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($cita->doctor_id != $doctorId) {
                return ['ok' => false, 'message' => 'No puedes rechazar esta cita.', 'cita' => $cita];
            }

            if ($this->noShowService->marcarSiVencio($cita)) {
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
            throw new \DomainException($transition['message']);
        }

        $cita = $transition['cita'];
        NotificarCambioEstadoCitaJob::dispatchAfterResponse($cita, 'cancelada', 'doctor');

        return $cita;
    }

    public function realizar(int $id, int $doctorId): array
    {
        $transition = DB::transaction(function () use ($id, $doctorId) {
            $cita = Cita::query()->with('notaSoap')->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($cita->doctor_id != $doctorId) {
                return ['ok' => false, 'message' => 'No puedes marcar esta cita.', 'redirect' => null, 'cita' => $cita];
            }

            if ($this->noShowService->marcarSiVencio($cita)) {
                return ['ok' => false, 'message' => 'La cita ya vencio y se marco como no se presento.', 'redirect' => null, 'cita' => $cita->refresh()];
            }

            if ($cita->estado !== Cita::ESTADO_CONFIRMADA) {
                return ['ok' => false, 'message' => 'Solo puedes marcar como realizada citas confirmadas.', 'redirect' => null, 'cita' => $cita];
            }

            if (! $cita->notaSoap || $cita->notaSoap->estado !== NotaSoap::ESTADO_FIRMADA) {
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
            return $transition;
        }

        $cita = $transition['cita'];
        event(new CitaAtendida($cita));

        return ['ok' => true, 'cita' => $cita];
    }

    public function exportarDoctorExcel(Request $request, int $doctorId): StreamedResponse
    {
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

    public function exportarDoctorPdf(Request $request, int $doctorId, User $doctor): Response
    {
        $estado = $this->obtenerEstadoFiltroDoctor($request);
        $prioridad = $this->obtenerPrioridadFiltroDoctor($request);

        $citas = $this->queryDoctorCitas($doctorId, $estado, $prioridad)
            ->with(['paciente:id,name', 'especialidad:id,nombre'])
            ->get();

        $html = view('doctor.citas-export-pdf', [
            'citas' => $citas,
            'doctor' => $doctor,
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

    public function checkDisponibilidad(Request $r, int $doctorId): array
    {
        $ref = $r->date('fecha_preferida') ?? now('America/Guayaquil');

        $tiene = Horario::where('doctor_id', $doctorId)
            ->whereBetween('fecha', [
                Carbon::parse($ref)->startOfWeek()->toDateString(),
                Carbon::parse($ref)->endOfWeek()->toDateString(),
            ])->exists();

        return $tiene
            ? ['ok' => true]
            : ['ok' => false, 'reason' => 'sin_horario'];
    }

    public function cancelarControlPlanificado(Cita $cita, Cita $control, int $doctorId): Cita
    {
        if ($cita->doctor_id !== $doctorId) {
            abort(403);
        }

        if (! $this->controlPerteneceACita($cita, $control)) {
            abort(404);
        }

        $transition = DB::transaction(function () use ($control) {
            $control = Cita::query()->whereKey($control->id)->lockForUpdate()->firstOrFail();

            if ($this->noShowService->marcarSiVencio($control)) {
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
            throw new \DomainException($transition['message']);
        }

        $control = $transition['control'];
        NotificarCambioEstadoCitaJob::dispatchAfterResponse($control, 'cancelada', 'doctor');

        return $control;
    }

    public function proximaPlanificada(Request $request, Cita $cita, int $doctorId): array
    {
        if ($cita->doctor_id !== $doctorId) {
            abort(403);
        }

        if ($this->pagoService->pacienteTieneBloqueo((int) $cita->paciente_id)) {
            return [
                'status' => 423,
                'payload' => [
                    'ok' => false,
                    'msg' => PagoService::MENSAJE_BLOQUEO,
                ],
            ];
        }

        $request->validate(
            ['fecha' => 'required|date', 'hora' => 'required|date_format:H:i'],
            ['fecha.required' => 'Seleccione una fecha.', 'hora.required' => 'Seleccione una hora.']
        );

        $slot = Carbon::createFromFormat('H:i', $request->hora);
        if ($slot->minute % 30 !== 0) {
            return [
                'status' => 422,
                'payload' => ['ok' => false, 'msg' => 'La hora debe ser 00 o 30 minutos.'],
            ];
        }

        $nota = NotaSoap::query()
            ->with(['followUpCita'])
            ->where('cita_id', $cita->id)
            ->first();

        if (! $nota || ! $nota->isSigned()) {
            return [
                'status' => 422,
                'payload' => ['ok' => false, 'msg' => 'Debes firmar la nota clinica antes de agendar el control.'],
            ];
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
        if ($controlExistente && ! $this->controlPerteneceACita($cita, $controlExistente)) {
            $controlExistente = null;
        }

        $esReagendamiento = ($controlExistente !== null);
        $fechaAnterior = null;
        $horaAnterior = null;
        if ($esReagendamiento) {
            $fechaAnterior = $controlExistente->fecha ? Carbon::parse($controlExistente->fecha)->format('d/m/Y') : null;
            $horaAnterior = $controlExistente->hora ? Carbon::parse($controlExistente->hora)->format('H:i') : null;
        }

        $validationError = $this->scheduleService->validateBookingSlot(
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
            return [
                'status' => 422,
                'payload' => ['ok' => false, 'msg' => $validationError['message']],
            ];
        }

        try {
            $result = DB::transaction(function () use ($cita, $request, $slot, $nota, $doctorId) {
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
                if ($controlExistente && ! $this->controlPerteneceACita($cita, $controlExistente)) {
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

                $hasConflict = $this->scheduleService->hasConflict(
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

                $this->priorityEvaluator->apply($control);
                $control->save();

                $nota->forceFill([
                    'follow_up_cita_id' => $control->id,
                    'follow_up_date' => $request->fecha,
                ])->saveQuietly();

                CitaEvento::create([
                    'cita_id' => $control->id,
                    'user_id' => $doctorId,
                    'tipo' => $esNuevo ? 'control_agendado' : 'control_reagendado',
                ]);

                return [
                    'control' => $control->refresh(),
                    'existed' => ! $esNuevo,
                ];
            });
        } catch (\DomainException $e) {
            return [
                'status' => 422,
                'payload' => ['ok' => false, 'msg' => $e->getMessage()],
            ];
        } catch (\Throwable $e) {
            Log::error('Error en proximaPlanificada: '.$e->getMessage(), [
                'exception' => $e,
                'cita_id' => $cita->id,
            ]);

            return [
                'status' => 500,
                'payload' => ['ok' => false, 'msg' => 'Ocurrió un error inesperado al procesar el control.'],
            ];
        }

        if (! $esReagendamiento) {
            try {
                event(new CitaAgendada($result['control']));
                EnviarConfirmacionCitaJob::dispatchAfterResponse($result['control']);
            } catch (\Throwable $e) {
                Log::error('Error al notificar cita de control agendada: '.$e->getMessage(), [
                    'cita_id' => $result['control']->id,
                    'exception' => $e,
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

        return [
            'status' => 200,
            'payload' => [
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
            ],
        ];
    }

    public function obtenerEstadoFiltroDoctor(Request $request): string
    {
        $estado = trim((string) $request->get('estado', ''));
        if ($estado === 'all') {
            return '';
        }
        $validStates = ['pendiente', 'confirmada', 'cancelada', 'realizada', 'no_se_presento'];

        return in_array($estado, $validStates, true) ? $estado : '';
    }

    public function obtenerPrioridadFiltroDoctor(Request $request): string
    {
        $prioridad = strtoupper(trim((string) $request->get('prioridad', '')));
        if ($prioridad === 'ALL') {
            return '';
        }

        return in_array($prioridad, Cita::PRIORIDAD_NIVELES, true) ? $prioridad : '';
    }

    public function normalizeSearchTerm(mixed $value, int $maxLength = 100): string
    {
        return trim(mb_substr((string) $value, 0, $maxLength));
    }

    public function etiquetaEstadoCita(string $estado): string
    {
        return $estado === Cita::ESTADO_NO_SE_PRESENTO ? 'No se presento' : ucfirst($estado);
    }

    public function queryDoctorCitas(int $doctorId, string $estado = '', string $prioridad = '')
    {
        return Cita::where('doctor_id', $doctorId)
            ->when($estado !== '', fn ($query) => $query->where('estado', $estado))
            ->when($prioridad !== '', fn ($query) => $query->where('prioridad_nivel', $prioridad))
            ->orderByRaw(Cita::prioridadOrderSql())
            ->orderBy('fecha', 'asc')
            ->orderBy('hora', 'asc');
    }

    public function fechaHoraCita(Cita $cita): Carbon
    {
        return Carbon::parse(
            Carbon::parse($cita->fecha)->toDateString().' '.substr((string) $cita->hora, 0, 8),
            'America/Guayaquil'
        );
    }

    public function controlPosteriorActivo(Cita $cita): ?Cita
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

    public function controlPerteneceACita(Cita $cita, Cita $control): bool
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
}
