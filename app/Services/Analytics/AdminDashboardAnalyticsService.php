<?php

namespace App\Services\Analytics;

use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\LaboratorioOrden;
use App\Models\LabOrder;
use App\Models\PedidoLaboratorio;
use App\Models\Receta;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminDashboardAnalyticsService
{
    public function __construct(
        protected DashboardPeriodResolver $periodResolver,
        protected DashboardChartBuilder $chartBuilder
    ) {}

    public function buildSuperadminDashboard(User $user, array $filters = [], ?string $timezone = null): array
    {
        $tz = $timezone ?: config('app.timezone', 'America/Guayaquil');
        $now = Carbon::now($tz);
        $range = $this->periodResolver->resolveRange($filters, 'month', $now, $tz);

        $activeUsers = (int) User::onlyActive()->count();
        $usersByRole = $this->countUsersByRoles(['paciente', 'doctor', 'administrador', 'laboratorio']);
        $citasPeriodo = $this->countCitasInRange(null, $range['start'], $range['end']);
        $citasHoy = $this->countCitasInRange(null, $range['today_start'], $range['today_end']);
        $labPendientes = $this->countPendingLabOrders();
        $documentos = $this->countDocumentsInRange($range['start'], $range['end']);

        $citasByState = $this->chartBuilder->buildStateSeries(
            $this->citasQuery(),
            Cita::ESTADOS,
            fn (string $state): string => match ($state) {
                Cita::ESTADO_PENDIENTE => 'Pendiente',
                Cita::ESTADO_CONFIRMADA => 'Confirmada',
                Cita::ESTADO_CANCELADA => 'Cancelada',
                Cita::ESTADO_REALIZADA => 'Atendida',
                Cita::ESTADO_NO_SE_PRESENTO => 'No se presento',
                default => Str::headline($state),
            },
            'estado',
            null,
            $range['start'],
            $range['end']
        );
        $citasByState['links'] = $this->periodResolver->buildCitaStateLinks('admin.cambios-citas.index', $range);

        $citasTimeline = $this->chartBuilder->buildTimelineSeries(
            $this->citasQuery(),
            'citas_medicas.fecha',
            $range['timeline_start'],
            $range['timeline_end'],
            $range['grouping'],
            'Citas',
            $now,
            $this->periodResolver,
            $tz
        );
        $citasTimeline['colors'] = ['#0d9488'];

        $usersTimeline = $this->chartBuilder->buildTimelineSeries(
            User::query(),
            'users.created_at',
            $range['timeline_start'],
            $range['timeline_end'],
            $range['grouping'],
            'Usuarios',
            $now,
            $this->periodResolver,
            $tz
        );
        $usersTimeline['colors'] = ['#3b82f6'];

        $specialtySeries = $this->buildSpecialtySeries($range['start'], $range['end']);
        $doctorSeries = $this->buildDoctorSeries($range['start'], $range['end'], $range);
        $documentSeries = $this->buildDocumentSeries($range['start'], $range['end']);

        return [
            'role' => 'superadmin',
            'filters' => $range,
            'metrics' => [
                'users_active' => $this->chartBuilder->metric($activeUsers),
                'patients_total' => $this->chartBuilder->metric((int) ($usersByRole['paciente'] ?? 0)),
                'doctors_total' => $this->chartBuilder->metric((int) ($usersByRole['doctor'] ?? 0)),
                'admins_total' => $this->chartBuilder->metric((int) ($usersByRole['administrador'] ?? 0)),
                'laboratory_total' => $this->chartBuilder->metric((int) ($usersByRole['laboratorio'] ?? 0)),
                'appointments_period' => $this->chartBuilder->metric($citasPeriodo),
                'appointments_today' => $this->chartBuilder->metric($citasHoy),
                'lab_pending' => $this->chartBuilder->metric($labPendientes),
                'documents_total' => $this->chartBuilder->metric(array_sum(array_column($documentos, 'value'))),
            ],
            'charts' => [
                'citas_estado' => $citasByState,
                'citas_timeline' => $citasTimeline,
                'usuarios_roles' => $this->buildRoleSeries($usersByRole, $range),
                'usuarios_timeline' => $usersTimeline,
                'citas_especialidad' => $specialtySeries,
                'citas_doctor' => $doctorSeries,
                'documentos_tipo' => $documentSeries,
            ],
            'lists' => [],
        ];
    }

    public function buildAdminDashboard(User $user, array $filters = [], ?string $timezone = null): array
    {
        $tz = $timezone ?: config('app.timezone', 'America/Guayaquil');
        $now = Carbon::now($tz);
        $range = $this->periodResolver->resolveRange($filters, 'month', $now, $tz);
        $doctorCount = $this->countUsersByRoles(['doctor']);
        $patientCount = $this->countUsersByRoles(['paciente']);

        $citasPeriodo = $this->countCitasInRange(null, $range['start'], $range['end']);
        $citasHoy = $this->countCitasInRange(null, $range['today_start'], $range['today_end']);
        $stateCounts = $this->countCitasByState(null, $range['start'], $range['end']);
        $labPending = $this->countPendingLabOrders();

        $metrics = [
            'appointments_today' => $this->chartBuilder->metric($citasHoy),
            'appointments_pending' => $this->chartBuilder->metric((int) ($stateCounts[Cita::ESTADO_PENDIENTE] ?? 0)),
            'appointments_confirmed' => $this->chartBuilder->metric((int) ($stateCounts[Cita::ESTADO_CONFIRMADA] ?? 0)),
            'appointments_completed' => $this->chartBuilder->metric((int) ($stateCounts[Cita::ESTADO_REALIZADA] ?? 0)),
            'appointments_cancelled' => $this->chartBuilder->metric((int) ($stateCounts[Cita::ESTADO_CANCELADA] ?? 0)),
            'patients_total' => $this->chartBuilder->metric((int) ($patientCount['paciente'] ?? 0)),
            'doctors_active' => $this->chartBuilder->metric($this->countActiveDoctors()),
            'appointments_period' => $this->chartBuilder->metric($citasPeriodo),
            'lab_pending' => $this->chartBuilder->metric($labPending),
        ];

        return [
            'role' => 'admin',
            'filters' => $range,
            'metrics' => $metrics,
            'charts' => [
                'citas_estado' => $this->chartBuilder->buildStateSeries(
                    $this->citasQuery(),
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
                ) + ['links' => $this->periodResolver->buildCitaStateLinks('admin.cambios-citas.index', $range)],
                'citas_por_dia_estado' => $this->chartBuilder->buildStateTimelineSeries(
                    $this->citasQuery(),
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
                'citas_doctor' => $this->buildDoctorAppointmentsStacked($range['start'], $range['end'], $range),
                'pacientes_nuevos_atendidos' => $this->buildPatientsNewVsAttended($range['timeline_start'], $range['timeline_end'], $range['grouping'], $now, $tz),
            ],
            'lists' => [
                'recent_appointments' => $this->buildRecentAppointments($range['start'], $range['end']),
            ],
        ];
    }

    public function buildRoleSeries(array $usersByRole, ?array $range = null): array
    {
        $rows = Role::query()
            ->leftJoin('role_user', 'roles.id', '=', 'role_user.role_id')
            ->select('roles.name', DB::raw('COUNT(DISTINCT role_user.user_id) as total'))
            ->groupBy('roles.id', 'roles.name')
            ->get();

        $counts = $rows->pluck('total', 'name')->all();
        $order = ['paciente', 'doctor', 'administrador', 'laboratorio', 'superadmin'];

        $labels = [];
        $values = [];
        $links = [];
        foreach ($order as $role) {
            $total = (int) ($counts[$role] ?? 0);
            if ($total <= 0) {
                continue;
            }

            $labels[] = $this->chartBuilder->labelForRole($role);
            $values[] = $total;
            $links[] = in_array($role, ['paciente', 'doctor', 'administrador', 'laboratorio'], true)
                ? route('superadmin.users.index', array_merge($this->periodResolver->rangeQueryParams($range), ['role' => $role]))
                : null;
        }

        return [
            'type' => 'donut',
            'labels' => $labels,
            'series' => $values,
            'links' => $links,
            'colors' => $this->chartBuilder->stateColors(count($labels)),
            'centerLabel' => 'Usuarios',
        ];
    }

    public function buildSpecialtySeries(?Carbon $start, ?Carbon $end): array
    {
        $query = Cita::query()
            ->with('especialidad:id,nombre')
            ->select('especialidad_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('especialidad_id')
            ->groupBy('especialidad_id')
            ->orderByDesc('total')
            ->limit(8);

        if ($start !== null && $end !== null) {
            $query->whereBetween('fecha', [$start->toDateString(), $end->toDateString()]);
        }

        $rows = $query->get();

        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $labels[] = optional($row->especialidad)->nombre ?? 'Sin especialidad';
            $values[] = (int) $row->total;
        }

        return [
            'type' => 'bar',
            'labels' => $labels,
            'series' => [
                [
                    'name' => 'Citas',
                    'data' => $values,
                ],
            ],
            'colors' => ['#0ea5e9'],
            'horizontal' => true,
        ];
    }

    public function buildDoctorSeries(?Carbon $start, ?Carbon $end, ?array $range = null): array
    {
        $query = Cita::query()
            ->select('doctor_id', DB::raw('COUNT(*) as total'))
            ->groupBy('doctor_id')
            ->orderByDesc('total')
            ->limit(8)
            ->with('doctor:id,name');

        if ($start !== null && $end !== null) {
            $query->whereBetween('fecha', [$start->toDateString(), $end->toDateString()]);
        }

        $rows = $query->get();

        $labels = [];
        $values = [];
        $links = [];
        foreach ($rows as $row) {
            $labels[] = $row->doctor?->name ?? 'Sin doctor';
            $values[] = (int) $row->total;
            $links[] = route('admin.cambios-citas.index', array_merge(
                $this->periodResolver->rangeQueryParams($range),
                ['doctor_id' => $row->doctor_id]
            ));
        }

        return [
            'type' => 'bar',
            'labels' => $labels,
            'series' => [
                [
                    'name' => 'Citas',
                    'data' => $values,
                ],
            ],
            'links' => $links,
            'colors' => ['#8b5cf6'],
            'horizontal' => true,
        ];
    }

    public function buildDocumentSeries(?Carbon $start, ?Carbon $end): array
    {
        $documents = $this->countDocumentsInRange($start, $end);

        return [
            'type' => 'donut',
            'labels' => array_column($documents, 'label'),
            'series' => array_column($documents, 'value'),
            'colors' => ['#0f766e', '#3b82f6', '#f59e0b'],
        ];
    }

    public function buildDoctorAppointmentsStacked(?Carbon $start, ?Carbon $end, ?array $range = null): array
    {
        $docIdsQuery = Cita::query();
        if ($start !== null && $end !== null) {
            $docIdsQuery->whereBetween('citas_medicas.fecha', [$start->toDateString(), $end->toDateString()]);
        }
        $doctorIds = $docIdsQuery->groupBy('citas_medicas.doctor_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(8)
            ->pluck('citas_medicas.doctor_id')
            ->all();

        $doctorNames = User::whereIn('id', array_filter($doctorIds))->pluck('name', 'id')->all();

        $matrixQuery = Cita::query();
        if ($start !== null && $end !== null) {
            $matrixQuery->whereBetween('citas_medicas.fecha', [$start->toDateString(), $end->toDateString()]);
        }
        $counts = $matrixQuery->whereIn('citas_medicas.doctor_id', $doctorIds)
            ->select('citas_medicas.doctor_id', 'citas_medicas.estado', DB::raw('COUNT(*) as total'))
            ->groupBy('citas_medicas.doctor_id', 'citas_medicas.estado')
            ->get();

        $matrix = [];
        foreach ($doctorIds as $docId) {
            $name = 'Sin asignar';
            if ($docId !== null) {
                $name = $doctorNames[$docId] ?? 'Doctor sin nombre';
            }
            $matrix[$docId ?? 'null'] = [
                'name' => $name,
                'values' => array_fill_keys(Cita::ESTADOS, 0),
            ];
        }

        foreach ($counts as $row) {
            $key = $row->doctor_id ?? 'null';
            if (isset($matrix[$key])) {
                $matrix[$key]['values'][$row->estado] = (int) $row->total;
            }
        }

        $labels = array_map(function ($doc) {
            $name = trim($doc['name'] ?? '');

            return $name !== '' ? $name : 'Doctor sin nombre';
        }, array_values($matrix));

        $series = [];
        foreach (Cita::ESTADOS as $state) {
            $series[] = [
                'name' => match ($state) {
                    Cita::ESTADO_PENDIENTE => 'Pendiente',
                    Cita::ESTADO_CONFIRMADA => 'Confirmada',
                    Cita::ESTADO_REALIZADA => 'Realizada',
                    Cita::ESTADO_CANCELADA => 'Cancelada',
                    Cita::ESTADO_NO_SE_PRESENTO => 'No se presentó',
                    default => ucfirst($state),
                },
                'data' => array_map(fn ($doc) => (int) ($doc['values'][$state] ?? 0), array_values($matrix)),
            ];
        }

        $links = [];
        foreach (array_keys($matrix) as $docIdKey) {
            $docId = $docIdKey === 'null' ? null : $docIdKey;
            $links[] = route('admin.cambios-citas.index', array_merge(
                $this->periodResolver->rangeQueryParams($range),
                $docId !== null ? ['doctor_id' => $docId] : []
            ));
        }

        return [
            'type' => 'bar',
            'stacked' => true,
            'labels' => $labels,
            'series' => $series,
            'links' => $links,
            'colors' => $this->chartBuilder->stateColors(count(Cita::ESTADOS)),
            'horizontal' => true,
        ];
    }

    public function buildPatientsNewVsAttended(Carbon $start, ?Carbon $end, string $grouping, ?Carbon $now = null, ?string $timezone = null): array
    {
        $tz = $timezone ?: config('app.timezone', 'America/Guayaquil');
        if (! $now) {
            $now = Carbon::now($tz);
        }

        $minUser = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'paciente'))->min('created_at');
        $minCita = Cita::query()->where('estado', Cita::ESTADO_REALIZADA)->min('fecha');

        $overallMin = $minUser;
        if ($minCita) {
            $overallMin = $overallMin ? $this->periodResolver->earlierDate(Carbon::parse($overallMin), Carbon::parse($minCita))->toDateString() : $minCita;
        }

        $maxUser = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'paciente'))->max('created_at');
        $maxCita = Cita::query()->where('estado', Cita::ESTADO_REALIZADA)->max('fecha');

        $overallMax = $maxUser;
        if ($maxCita) {
            $overallMax = $overallMax ? $this->periodResolver->laterDate(Carbon::parse($overallMax), Carbon::parse($maxCita))->toDateString() : $maxCita;
        }

        $t_start = $this->periodResolver->getVisualStartDate($start, $overallMin, $tz);
        $t_end = $this->periodResolver->getVisualEndDate($end, $overallMax, $now, $tz);

        $newUsersDaily = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'paciente'))
            ->whereBetween('users.created_at', [$t_start->copy()->startOfDay(), $t_end->copy()->endOfDay()])
            ->selectRaw('DATE(users.created_at) as bucket, COUNT(*) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn ($v) => (int) $v)
            ->all();

        $attendedDaily = Cita::query()
            ->where('citas_medicas.estado', Cita::ESTADO_REALIZADA)
            ->whereBetween('citas_medicas.fecha', [$t_start->toDateString(), $t_end->toDateString()])
            ->selectRaw('DATE(citas_medicas.fecha) as bucket, COUNT(DISTINCT CASE WHEN dependiente_id IS NULL THEN CONCAT("P-", paciente_id) ELSE CONCAT("D-", dependiente_id) END) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn ($v) => (int) $v)
            ->all();

        $buckets = [];
        $crossYear = $t_start->year !== $t_end->year;
        foreach (CarbonPeriod::create($t_start->copy()->startOfDay(), '1 day', $t_end->copy()->endOfDay()) as $date) {
            $bucketKey = $this->chartBuilder->timelineBucketKey($date, $grouping);

            if (! isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = [
                    'label' => $this->chartBuilder->timelineLabel($date, $grouping, $crossYear),
                    'new' => 0,
                    'attended' => 0,
                ];
            }

            $buckets[$bucketKey]['new'] += (int) ($newUsersDaily[$date->toDateString()] ?? 0);
            $buckets[$bucketKey]['attended'] += (int) ($attendedDaily[$date->toDateString()] ?? 0);
        }

        $labels = array_values(array_map(fn ($bucket) => $bucket['label'], $buckets));

        return [
            'type' => 'bar',
            'labels' => $labels,
            'series' => [
                [
                    'name' => 'Pacientes nuevos',
                    'data' => array_values(array_map(fn ($bucket) => (int) $bucket['new'], $buckets)),
                ],
                [
                    'name' => 'Pacientes atendidos',
                    'data' => array_values(array_map(fn ($bucket) => (int) $bucket['attended'], $buckets)),
                ],
            ],
            'colors' => ['#3b82f6', '#14b8a6'],
        ];
    }

    public function countUsersByRoles(array $roles): array
    {
        return Role::query()
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('roles.name', $roles)
            ->select('roles.name', DB::raw('COUNT(DISTINCT role_user.user_id) as total'))
            ->groupBy('roles.name')
            ->pluck('total', 'roles.name')
            ->all();
    }

    public function countActiveDoctors(): int
    {
        return (int) User::query()
            ->onlyActive()
            ->whereHas('roles', fn ($query) => $query->where('name', 'doctor'))
            ->count();
    }

    public function countCitasInRange(?int $doctorId, ?Carbon $start, ?Carbon $end): int
    {
        $query = $this->citasQuery()
            ->when($doctorId, fn ($q) => $q->where('citas_medicas.doctor_id', $doctorId));

        if ($start !== null && $end !== null) {
            $query->whereBetween('citas_medicas.fecha', [$start->toDateString(), $end->toDateString()]);
        }

        return (int) $query->count();
    }

    public function countCitasByState(?int $doctorId, ?Carbon $start, ?Carbon $end): array
    {
        $query = $this->citasQuery()
            ->when($doctorId, fn ($q) => $q->where('citas_medicas.doctor_id', $doctorId));

        if ($start !== null && $end !== null) {
            $query->whereBetween('citas_medicas.fecha', [$start->toDateString(), $end->toDateString()]);
        }

        return $query
            ->select('citas_medicas.estado', DB::raw('COUNT(*) as total'))
            ->groupBy('citas_medicas.estado')
            ->pluck('total', 'citas_medicas.estado')
            ->all();
    }

    public function countPendingLabOrders(): int
    {
        $pedidoLaboratorio = $this->chartBuilder->tableExists((new PedidoLaboratorio)->getTable())
            ? (int) PedidoLaboratorio::query()
                ->where('estado', 'pendiente_toma')
                ->count()
            : 0;

        $legacy = $this->chartBuilder->tableExists((new LaboratorioOrden)->getTable())
            ? (int) LaboratorioOrden::query()
                ->whereIn('estado', [LaboratorioOrden::ESTADO_ORDEN_CREADA, LaboratorioOrden::ESTADO_CITA_PROGRAMADA, LaboratorioOrden::ESTADO_MUESTRA_TOMADA])
                ->count()
            : 0;

        $labOrders = $this->chartBuilder->tableExists((new LabOrder)->getTable())
            ? (int) LabOrder::query()
                ->whereIn('status', [LabOrder::STATUS_PENDIENTE_TOMA, LabOrder::STATUS_MUESTRA_TOMADA, LabOrder::STATUS_EN_ANALISIS])
                ->count()
            : 0;

        return (int) $pedidoLaboratorio + (int) $legacy + (int) $labOrders;
    }

    public function countDocumentsInRange(?Carbon $start, ?Carbon $end): array
    {
        $recetaQuery = Receta::query();
        $certificadoQuery = CertificadoMedico::query();
        $pedidoQuery = PedidoLaboratorio::query();

        if ($start !== null && $end !== null) {
            $recetaQuery->whereBetween('recetas.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            $certificadoQuery->whereBetween('certificados_medicos.fecha_emision', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            if ($this->chartBuilder->tableExists((new PedidoLaboratorio)->getTable())) {
                $pedidoQuery->whereBetween('pedidos_laboratorio.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            }
        }

        return [
            [
                'label' => 'Recetas',
                'value' => (int) $recetaQuery->count(),
            ],
            [
                'label' => 'Certificados',
                'value' => (int) $certificadoQuery->count(),
            ],
            [
                'label' => 'Pedidos de laboratorio',
                'value' => $this->chartBuilder->tableExists((new PedidoLaboratorio)->getTable())
                    ? (int) $pedidoQuery->count()
                    : 0,
            ],
        ];
    }

    public function buildRecentAppointments(?Carbon $start, ?Carbon $end): array
    {
        $q = Cita::query()
            ->with(['paciente:id,name', 'doctor:id,name', 'especialidad:id,nombre']);

        if ($start !== null && $end !== null) {
            $q->whereBetween('citas_medicas.fecha', [$start->toDateString(), $end->toDateString()]);
        }

        return $q->orderByDesc('citas_medicas.fecha')
            ->orderByDesc('citas_medicas.hora')
            ->limit(5)
            ->get(['citas_medicas.id', 'citas_medicas.paciente_id', 'citas_medicas.doctor_id', 'citas_medicas.especialidad_id', 'citas_medicas.fecha', 'citas_medicas.hora', 'citas_medicas.estado'])
            ->map(fn (Cita $cita) => [
                'id' => $cita->id,
                'paciente' => $cita->nombrePacienteReal(),
                'doctor' => $cita->doctor?->name ?? 'Sin doctor',
                'especialidad' => $cita->especialidad?->nombre ?? 'Consulta',
                'when' => optional($cita->fecha)->format('d/m/Y').' '.$cita->hora,
                'status' => $cita->estado,
                'tone' => $this->chartBuilder->toneForState($cita->estado),
                'url' => route('admin.cambios-citas.index', ['cita_id' => $cita->id]),
            ])
            ->all();
    }

    public function citasQuery()
    {
        return Cita::query();
    }

    public function laboratoryOrdersQuery()
    {
        return LabOrder::query();
    }
}
