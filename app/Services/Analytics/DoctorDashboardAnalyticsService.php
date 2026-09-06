<?php

namespace App\Services\Analytics;

use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\LabOrder;
use App\Models\NotaSoap;
use App\Models\PedidoLaboratorio;
use App\Models\Receta;
use App\Models\User;
use Carbon\Carbon;

class DoctorDashboardAnalyticsService
{
    public function __construct(
        protected DashboardPeriodResolver $periodResolver,
        protected DashboardChartBuilder $chartBuilder
    ) {}

    public function buildDoctorDashboard(User $user, array $filters = [], ?string $timezone = null): array
    {
        $tz = $timezone ?: config('app.timezone', 'America/Guayaquil');
        $now = Carbon::now($tz);
        $range = $this->periodResolver->resolveRange($filters, 'month', $now, $tz);
        $doctorId = $user->id;

        $appointmentsQuery = Cita::query()->where('citas_medicas.doctor_id', $doctorId);

        $citasHoy = $this->countCitasInRange($doctorId, $range['today_start'], $range['today_end']);
        $nextAppointment = $this->nextAppointmentForDoctor($doctorId, $now, $tz);
        $pending = $this->countAppointmentsForDoctor($doctorId, [Cita::ESTADO_PENDIENTE]);
        $completed = $this->countAppointmentsForDoctor($doctorId, [Cita::ESTADO_REALIZADA]);
        $futureControls = $this->countFutureControls($doctorId, $now, $tz);
        $draftNotes = $this->countDraftNotes($doctorId);
        $pendingLabOrders = $this->countDoctorLabOrders($doctorId, false);
        $patientsAttended = $this->countPatientsAttendedInRange($doctorId, $range['start'], $range['end']);
        $documents = $this->countDoctorDocuments($doctorId, $range['start'], $range['end']);

        return [
            'role' => 'doctor',
            'filters' => $range,
            'metrics' => [
                'appointments_today' => $this->chartBuilder->metric($citasHoy),
                'next_appointment' => $this->chartBuilder->metric($nextAppointment ? 1 : 0, $nextAppointment ? $this->formatAppointmentSummary($nextAppointment) : 'Sin cita próxima'),
                'appointments_pending' => $this->chartBuilder->metric($pending),
                'appointments_completed' => $this->chartBuilder->metric($completed),
                'future_controls' => $this->chartBuilder->metric($futureControls),
                'draft_notes' => $this->chartBuilder->metric($draftNotes),
                'lab_orders_related' => $this->chartBuilder->metric($pendingLabOrders),
                'patients_attended' => $this->chartBuilder->metric($patientsAttended),
                'documents_total' => $this->chartBuilder->metric(array_sum(array_column($documents, 'value'))),
            ],
            'charts' => [
                'appointments_status' => $this->chartBuilder->buildStateSeries(
                    $appointmentsQuery,
                    Cita::ESTADOS,
                    fn (string $state): string => match ($state) {
                        Cita::ESTADO_PENDIENTE => 'Pendiente',
                        Cita::ESTADO_CONFIRMADA => 'Confirmada',
                        Cita::ESTADO_CANCELADA => 'Cancelada',
                        Cita::ESTADO_REALIZADA => 'Realizada',
                        Cita::ESTADO_NO_SE_PRESENTO => 'No se presentó',
                        default => ucfirst($state),
                    },
                    'estado',
                    null,
                    $range['start'],
                    $range['end']
                ) + ['links' => $this->periodResolver->buildCitaStateLinks('doctor.citas', $range)],
                'appointments_status_timeline' => $this->chartBuilder->buildStateTimelineSeries(
                    $appointmentsQuery,
                    'citas_medicas.fecha',
                    null,
                    'estado',
                    Cita::ESTADOS,
                    fn (string $state): string => match ($state) {
                        Cita::ESTADO_PENDIENTE => 'Pendiente',
                        Cita::ESTADO_CONFIRMADA => 'Confirmada',
                        Cita::ESTADO_CANCELADA => 'Cancelada',
                        Cita::ESTADO_REALIZADA => 'Realizada',
                        Cita::ESTADO_NO_SE_PRESENTO => 'No se presentó',
                        default => ucfirst($state),
                    },
                    $range['timeline_start'],
                    $range['timeline_end'],
                    $range['grouping'],
                    null,
                    $now,
                    $this->periodResolver,
                    $tz
                ),
                'patients_timeline' => $this->buildPatientTimelineSeries($range['timeline_start'], $range['timeline_end'], $range['grouping'], $doctorId, $now, $tz),
                'patients_recurrent' => $this->buildNewVsRecurrentSeries($doctorId, $range['start'], $range['end']),
                'controls_status' => $this->buildFollowUpSeries($doctorId, $range['start'], $range['end'], $tz),
                'documents_type' => $this->buildDoctorDocumentSeries($doctorId, $range['start'], $range['end']),
            ],
            'lists' => [
                'next_appointments' => $this->buildDoctorUpcomingAppointments($doctorId, 5, $now),
                'follow_up_controls' => $this->buildDoctorUpcomingControls($doctorId, 5, $now),
            ],
        ];
    }

    public function buildPatientTimelineSeries(Carbon $start, ?Carbon $end, string $grouping, ?int $doctorId = null, ?Carbon $now = null, ?string $timezone = null): array
    {
        $tz = $timezone ?: config('app.timezone', 'America/Guayaquil');
        if (! $now) {
            $now = Carbon::now($tz);
        }

        $baseQuery = Cita::query()
            ->whereIn('citas_medicas.estado', [Cita::ESTADO_REALIZADA]);

        if ($doctorId) {
            $baseQuery->where('citas_medicas.doctor_id', $doctorId);
        }

        $minVal = (clone $baseQuery)->min('citas_medicas.fecha');
        $maxVal = (clone $baseQuery)->max('citas_medicas.fecha');

        $t_start = $this->periodResolver->getVisualStartDate($start, $minVal, $tz);
        $t_end = $this->periodResolver->getVisualEndDate($end, $maxVal, $now, $tz);

        $query = clone $baseQuery;
        $query->whereBetween('citas_medicas.fecha', [$t_start->toDateString(), $t_end->toDateString()]);

        $daily = $query
            ->selectRaw('DATE(citas_medicas.fecha) as bucket, COUNT(DISTINCT CASE WHEN dependiente_id IS NULL THEN CONCAT("P-", paciente_id) ELSE CONCAT("D-", dependiente_id) END) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn ($value) => (int) $value)
            ->all();

        [$labels, $values] = $this->chartBuilder->aggregateTimeline($daily, $t_start, $t_end, $grouping);

        return [
            'type' => 'line',
            'labels' => $labels,
            'series' => [
                [
                    'name' => 'Pacientes atendidos',
                    'data' => $values,
                ],
            ],
            'colors' => ['#14b8a6'],
        ];
    }

    public function buildNewVsRecurrentSeries(int $doctorId, ?Carbon $start, ?Carbon $end): array
    {
        $attended = Cita::query()
            ->where('citas_medicas.doctor_id', $doctorId)
            ->where('citas_medicas.estado', Cita::ESTADO_REALIZADA);

        if ($start !== null && $end !== null) {
            $attended->whereBetween('citas_medicas.fecha', [$start->toDateString(), $end->toDateString()]);
        }

        $attended->selectRaw("CASE WHEN dependiente_id IS NULL THEN CONCAT('P-', paciente_id) ELSE CONCAT('D-', dependiente_id) END as subject_key, MIN(citas_medicas.fecha) as first_date")
            ->groupBy('subject_key');

        $newQuery = \Illuminate\Support\Facades\DB::query()->fromSub($attended, 'attended');
        if ($start !== null && $end !== null) {
            $newQuery->whereBetween('first_date', [$start->toDateString(), $end->toDateString()]);
        }
        $new = $newQuery->count();

        $totalQuery = Cita::query()
            ->where('citas_medicas.doctor_id', $doctorId)
            ->where('citas_medicas.estado', Cita::ESTADO_REALIZADA);

        if ($start !== null && $end !== null) {
            $totalQuery->whereBetween('citas_medicas.fecha', [$start->toDateString(), $end->toDateString()]);
        }

        $total = $totalQuery->selectRaw("COUNT(DISTINCT CASE WHEN dependiente_id IS NULL THEN CONCAT('P-', paciente_id) ELSE CONCAT('D-', dependiente_id) END) as total")
            ->value('total') ?? 0;

        $recurrent = max(0, (int) $total - (int) $new);

        return [
            'type' => 'donut',
            'labels' => ['Nuevos', 'Recurrentes'],
            'series' => [(int) $new, $recurrent],
            'colors' => ['#3b82f6', '#0f766e'],
        ];
    }

    public function buildFollowUpSeries(int $doctorId, ?Carbon $start, ?Carbon $end, ?string $timezone = null): array
    {
        $tz = $timezone ?: config('app.timezone', 'America/Guayaquil');
        $now = Carbon::now($tz);

        $query = Cita::query()
            ->where('citas_medicas.doctor_id', $doctorId)
            ->whereIn('citas_medicas.id', function ($query) {
                $query->select('follow_up_cita_id')
                    ->from('notas_soap')
                    ->whereNotNull('follow_up_cita_id');
            });

        if ($start !== null && $end !== null) {
            $query->whereBetween('citas_medicas.fecha', [$start->toDateString(), $end->toDateString()]);
        }

        $controls = $query->get(['citas_medicas.id', 'citas_medicas.fecha', 'citas_medicas.hora', 'citas_medicas.estado']);

        $proximos = 0;
        $realizados = 0;
        $cancelados = 0;
        $noPresentados = 0;
        $vencidos = 0;

        foreach ($controls as $control) {
            $estado = $control->estado;

            if (in_array($estado, [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA], true)) {
                $fechaStr = optional($control->fecha)->toDateString();
                $horaStr = $control->hora ?? '00:00:00';
                $citaDateTime = Carbon::parse($fechaStr.' '.$horaStr, $tz);

                if ($citaDateTime->isPast()) {
                    $vencidos++;
                } else {
                    $proximos++;
                }
            } elseif ($estado === Cita::ESTADO_REALIZADA) {
                $realizados++;
            } elseif ($estado === Cita::ESTADO_CANCELADA) {
                $cancelados++;
            } elseif ($estado === Cita::ESTADO_NO_SE_PRESENTO) {
                $noPresentados++;
            }
        }

        return [
            'type' => 'donut',
            'labels' => ['Próximos', 'Realizados', 'Cancelados', 'No presentados', 'Pendientes vencidos'],
            'series' => [$proximos, $realizados, $cancelados, $noPresentados, $vencidos],
            'colors' => ['#0ea5e9', '#10b981', '#f43f5e', '#64748b', '#f59e0b'],
        ];
    }

    public function buildDoctorDocumentSeries(int $doctorId, Carbon $start, Carbon $end): array
    {
        $labCount = 0;
        if ($this->chartBuilder->tableExists((new PedidoLaboratorio)->getTable())) {
            $labCount += (int) PedidoLaboratorio::query()
                ->where('doctor_id', $doctorId)
                ->whereBetween('created_at', [$start, $end])
                ->count();
        }
        if ($this->chartBuilder->tableExists((new LabOrder)->getTable())) {
            $labCount += (int) LabOrder::query()
                ->where('doctor_id', $doctorId)
                ->whereBetween('created_at', [$start, $end])
                ->count();
        }

        $counts = [
            'Recetas' => (int) Receta::query()
                ->whereHas('cita', fn ($q) => $q->where('doctor_id', $doctorId))
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'Certificados' => (int) CertificadoMedico::query()->where('doctor_id', $doctorId)
                ->whereBetween('fecha_emision', [$start, $end])
                ->count(),
            'Pedidos de laboratorio' => $labCount,
        ];

        return [
            'type' => 'bar',
            'labels' => array_keys($counts),
            'series' => [
                [
                    'name' => 'Documentos',
                    'data' => array_values($counts),
                ],
            ],
            'colors' => ['#0f766e', '#3b82f6', '#f59e0b'],
        ];
    }

    public function buildDoctorUpcomingAppointments(int $doctorId, int $limit = 5, ?Carbon $now = null): array
    {
        $now = $now ?: Carbon::now(config('app.timezone', 'America/Guayaquil'));

        return Cita::query()
            ->with(['paciente:id,name', 'dependiente:id,nombre', 'especialidad:id,nombre'])
            ->where('doctor_id', $doctorId)
            ->whereIn('estado', [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA])
            ->where(function ($query) use ($now) {
                $query->whereDate('fecha', '>', $now->toDateString())
                    ->orWhere(function ($sameDay) use ($now) {
                        $sameDay->whereDate('fecha', $now->toDateString())
                            ->whereTime('hora', '>=', $now->format('H:i:s'));
                    });
            })
            ->orderBy('fecha')
            ->orderBy('hora')
            ->limit($limit)
            ->get(['id', 'paciente_id', 'dependiente_id', 'especialidad_id', 'fecha', 'hora', 'estado'])
            ->map(fn (Cita $cita) => [
                'label' => $cita->nombrePacienteReal(),
                'meta' => trim(($cita->especialidad?->nombre ?? 'Consulta').' · '.$cita->estadoComprobante()),
                'when' => $cita->fecha->format('d/m/Y').' '.$cita->hora,
                'tone' => $this->chartBuilder->toneForState($cita->estado),
                'url' => route('doctor.citas.soap', $cita),
            ])
            ->values()
            ->all();
    }

    public function buildDoctorUpcomingControls(int $doctorId, int $limit = 5, ?Carbon $now = null): array
    {
        return NotaSoap::query()
            ->with([
                'cita.paciente:id,name',
                'cita.dependiente:id,nombre',
                'followUpCita.doctor:id,name',
                'followUpCita.especialidad:id,nombre',
                'followUpCita.paciente:id,name',
                'followUpCita.dependiente:id,nombre',
            ])
            ->join('citas_medicas', 'notas_soap.cita_id', '=', 'citas_medicas.id')
            ->where('citas_medicas.doctor_id', $doctorId)
            ->where('notas_soap.estado', NotaSoap::ESTADO_FIRMADA)
            ->whereNotNull('notas_soap.follow_up_date')
            ->orderBy('notas_soap.follow_up_date')
            ->limit($limit)
            ->get(['notas_soap.id', 'notas_soap.cita_id', 'notas_soap.follow_up_date', 'notas_soap.follow_up_cita_id'])
            ->map(function (NotaSoap $note) {
                $followUp = $note->followUpCita;
                if ($followUp) {
                    return [
                        'label' => $followUp->nombrePacienteReal(),
                        'meta' => trim(($followUp->especialidad?->nombre ?? 'Seguimiento').' · '.$followUp->estadoComprobante()),
                        'when' => $followUp->fecha->format('d/m/Y').' '.$followUp->hora,
                        'tone' => $this->chartBuilder->toneForState($followUp->estado),
                        'url' => route('doctor.citas.soap', $note->cita_id).'#plan-control-box',
                    ];
                }

                return [
                    'label' => $note->cita?->nombrePacienteReal() ?? 'Paciente',
                    'meta' => 'Control sugerido',
                    'when' => optional($note->follow_up_date)->format('d/m/Y') ?? 'Sin fecha',
                    'tone' => 'warning',
                    'url' => route('doctor.citas.soap', $note->cita_id).'#plan-control-box',
                ];
            })
            ->values()
            ->all();
    }

    public function countPatientsAttendedInRange(int $doctorId, ?Carbon $start, ?Carbon $end): int
    {
        if ($start === null || $end === null) {
            return (int) Cita::query()
                ->where('doctor_id', $doctorId)
                ->where('estado', Cita::ESTADO_REALIZADA)
                ->selectRaw("COUNT(DISTINCT CASE WHEN dependiente_id IS NULL THEN CONCAT('P-', paciente_id) ELSE CONCAT('D-', dependiente_id) END) as total")
                ->value('total');
        }

        return (int) Cita::query()
            ->where('doctor_id', $doctorId)
            ->where('estado', Cita::ESTADO_REALIZADA)
            ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("COUNT(DISTINCT CASE WHEN dependiente_id IS NULL THEN CONCAT('P-', paciente_id) ELSE CONCAT('D-', dependiente_id) END) as total")
            ->value('total');
    }

    public function countDraftNotes(int $doctorId): int
    {
        return (int) NotaSoap::query()
            ->join('citas_medicas', 'notas_soap.cita_id', '=', 'citas_medicas.id')
            ->where('citas_medicas.doctor_id', $doctorId)
            ->where('notas_soap.estado', NotaSoap::ESTADO_BORRADOR)
            ->count();
    }

    public function countFutureControls(int $doctorId, ?Carbon $now = null, ?string $timezone = null): int
    {
        $tz = $timezone ?: config('app.timezone', 'America/Guayaquil');
        $now = $now ?: Carbon::now($tz);

        return (int) NotaSoap::query()
            ->join('citas_medicas', 'notas_soap.cita_id', '=', 'citas_medicas.id')
            ->where('citas_medicas.doctor_id', $doctorId)
            ->where('notas_soap.estado', NotaSoap::ESTADO_FIRMADA)
            ->whereNotNull('notas_soap.follow_up_date')
            ->where(function ($query) use ($now) {
                $query->whereNotNull('notas_soap.follow_up_cita_id')
                    ->orWhereDate('notas_soap.follow_up_date', '>=', $now->toDateString());
            })
            ->count();
    }

    public function countDoctorLabOrders(int $doctorId, bool $onlyCompleted): int
    {
        $pendingCount = 0;

        if ($this->chartBuilder->tableExists((new PedidoLaboratorio)->getTable())) {
            $pending = PedidoLaboratorio::query()->where('doctor_id', $doctorId);
            if ($onlyCompleted) {
                return (int) $pending->where('estado', 'resultado_listo')->count();
            }

            $pendingCount = (int) $pending->where('estado', 'pendiente_toma')->count();
        }

        $labOrderCount = $this->chartBuilder->tableExists((new LabOrder)->getTable())
            ? (int) LabOrder::query()->where('doctor_id', $doctorId)->whereIn('status', [LabOrder::STATUS_PENDIENTE_TOMA, LabOrder::STATUS_MUESTRA_TOMADA, LabOrder::STATUS_EN_ANALISIS])->count()
            : 0;

        return $pendingCount + $labOrderCount;
    }

    public function countAppointmentsForDoctor(int $doctorId, array $states): int
    {
        return (int) Cita::query()
            ->where('doctor_id', $doctorId)
            ->whereIn('estado', $states)
            ->count();
    }

    public function nextAppointmentForDoctor(int $doctorId, ?Carbon $now = null, ?string $timezone = null): ?Cita
    {
        $tz = $timezone ?: config('app.timezone', 'America/Guayaquil');
        $now = $now ?: Carbon::now($tz);

        return Cita::query()
            ->with(['paciente:id,name', 'dependiente:id,nombre', 'especialidad:id,nombre'])
            ->where('doctor_id', $doctorId)
            ->whereIn('estado', [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA])
            ->where(function ($query) use ($now) {
                $query->whereDate('fecha', '>', $now->toDateString())
                    ->orWhere(function ($sameDay) use ($now) {
                        $sameDay->whereDate('fecha', $now->toDateString())
                            ->whereTime('hora', '>=', $now->format('H:i:s'));
                    });
            })
            ->orderBy('fecha')
            ->orderBy('hora')
            ->first();
    }

    public function formatAppointmentSummary(Cita $cita): string
    {
        return trim(sprintf(
            '%s · %s %s · %s',
            $cita->nombrePacienteReal(),
            $cita->fecha instanceof Carbon ? $cita->fecha->format('d/m/Y') : (string) $cita->fecha,
            substr((string) $cita->hora, 0, 5),
            $cita->especialidad?->nombre ?? 'Consulta'
        ));
    }

    public function countCitasInRange(?int $doctorId, ?Carbon $start, ?Carbon $end): int
    {
        $query = Cita::query();
        if ($doctorId) {
            $query->where('citas_medicas.doctor_id', $doctorId);
        }

        if ($start !== null && $end !== null) {
            $query->whereBetween('citas_medicas.fecha', [$start->toDateString(), $end->toDateString()]);
        }

        return (int) $query->count();
    }

    public function countDoctorDocuments(int $doctorId, ?Carbon $start, ?Carbon $end): array
    {
        if ($start === null || $end === null) {
            $start = Carbon::create(1970, 1, 1);
            $end = Carbon::create(2099, 12, 31);
        }

        $labCount = 0;
        if ($this->chartBuilder->tableExists((new PedidoLaboratorio)->getTable())) {
            $labCount += (int) PedidoLaboratorio::query()
                ->where('doctor_id', $doctorId)
                ->whereBetween('created_at', [$start, $end])
                ->count();
        }
        if ($this->chartBuilder->tableExists((new LabOrder)->getTable())) {
            $labCount += (int) LabOrder::query()
                ->where('doctor_id', $doctorId)
                ->whereBetween('created_at', [$start, $end])
                ->count();
        }

        return [
            [
                'label' => 'Recetas',
                'value' => (int) Receta::query()
                    ->whereHas('cita', fn ($q) => $q->where('doctor_id', $doctorId))
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
            ],
            [
                'label' => 'Certificados',
                'value' => (int) CertificadoMedico::query()->where('doctor_id', $doctorId)
                    ->whereBetween('fecha_emision', [$start, $end])
                    ->count(),
            ],
            [
                'label' => 'Pedidos de laboratorio',
                'value' => $labCount,
            ],
        ];
    }
}
