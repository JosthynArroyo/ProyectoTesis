<?php

namespace App\Services;

use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\LabOrder;
use App\Models\LaboratorioOrden;
use App\Models\NotaSoap;
use App\Models\NotaSoapDiagnostico;
use App\Models\PedidoLaboratorio;
use App\Models\Receta;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DashboardAnalyticsService
{
    protected array $tableExistsCache = [];

    public function __construct(
        private string $timezone = 'America/Guayaquil'
    ) {
        $this->timezone = config('app.timezone', $this->timezone);
    }

    public function buildSuperadminDashboard(User $user, array $filters = []): array
    {
        $this->timezone = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::now($this->timezone);
        $range = $this->resolveRange($filters, 'month', $now);

        $activeUsers = (int) User::onlyActive()->count();
        $usersByRole = $this->countUsersByRoles(['paciente', 'doctor', 'administrador', 'laboratorio']);
        $citasPeriodo = $this->countCitasInRange(null, $range['start'], $range['end']);
        $citasHoy = $this->countCitasInRange(null, $range['today_start'], $range['today_end']);
        $labPendientes = $this->countPendingLabOrders();
        $documentos = $this->countDocumentsInRange($range['start'], $range['end']);

        $citasByState = $this->buildStateSeries(
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
        $citasByState['links'] = $this->buildCitaStateLinks('admin.cambios-citas.index', $range);

        $citasTimeline = $this->buildTimelineSeries(
            $this->citasQuery(),
            'citas_medicas.fecha',
            $range['timeline_start'],
            $range['timeline_end'],
            $range['grouping'],
            'Citas',
            $now
        );

        $usersTimeline = $this->buildTimelineSeries(
            User::query(),
            'users.created_at',
            $range['timeline_start'],
            $range['timeline_end'],
            $range['grouping'],
            'Usuarios',
            $now
        );

        $specialtySeries = $this->buildSpecialtySeries($range['start'], $range['end']);
        $doctorSeries = $this->buildDoctorSeries($range['start'], $range['end'], $range);
        $documentSeries = $this->buildDocumentSeries($range['start'], $range['end']);

        return [
            'role' => 'superadmin',
            'filters' => $range,
            'metrics' => [
                'users_active' => $this->metric($activeUsers),
                'patients_total' => $this->metric((int) ($usersByRole['paciente'] ?? 0)),
                'doctors_total' => $this->metric((int) ($usersByRole['doctor'] ?? 0)),
                'admins_total' => $this->metric((int) ($usersByRole['administrador'] ?? 0)),
                'laboratory_total' => $this->metric((int) ($usersByRole['laboratorio'] ?? 0)),
                'appointments_period' => $this->metric($citasPeriodo),
                'appointments_today' => $this->metric($citasHoy),
                'lab_pending' => $this->metric($labPendientes),
                'documents_total' => $this->metric(array_sum(array_column($documentos, 'value'))),
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

    public function buildAdminDashboard(User $user, array $filters = []): array
    {
        $this->timezone = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::now($this->timezone);
        $range = $this->resolveRange($filters, 'month', $now);
        $doctorCount = $this->countUsersByRoles(['doctor']);
        $patientCount = $this->countUsersByRoles(['paciente']);

        $citasPeriodo = $this->countCitasInRange(null, $range['start'], $range['end']);
        $citasHoy = $this->countCitasInRange(null, $range['today_start'], $range['today_end']);
        $stateCounts = $this->countCitasByState(null, $range['start'], $range['end']);
        $labPending = $this->countPendingLabOrders();

        $metrics = [
            'appointments_today' => $this->metric($citasHoy),
            'appointments_pending' => $this->metric((int) ($stateCounts[Cita::ESTADO_PENDIENTE] ?? 0)),
            'appointments_confirmed' => $this->metric((int) ($stateCounts[Cita::ESTADO_CONFIRMADA] ?? 0)),
            'appointments_completed' => $this->metric((int) ($stateCounts[Cita::ESTADO_REALIZADA] ?? 0)),
            'appointments_cancelled' => $this->metric((int) ($stateCounts[Cita::ESTADO_CANCELADA] ?? 0)),
            'patients_total' => $this->metric((int) ($patientCount['paciente'] ?? 0)),
            'doctors_active' => $this->metric($this->countActiveDoctors()),
            'appointments_period' => $this->metric($citasPeriodo),
            'lab_pending' => $this->metric($labPending),
        ];

        return [
            'role' => 'admin',
            'filters' => $range,
            'metrics' => $metrics,
            'charts' => [
                'citas_estado' => $this->buildStateSeries(
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
                ) + ['links' => $this->buildCitaStateLinks('admin.cambios-citas.index', $range)],
                'citas_por_dia_estado' => $this->buildStateTimelineSeries(
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
                    $now
                ),
                'citas_doctor' => $this->buildDoctorAppointmentsStacked($range['start'], $range['end'], $range),
                'pacientes_nuevos_atendidos' => $this->buildPatientsNewVsAttended($range['timeline_start'], $range['timeline_end'], $range['grouping'], $now),
            ],
            'lists' => [
                'recent_appointments' => $this->buildRecentAppointments($range['start'], $range['end']),
            ],
        ];
    }

    public function buildDoctorDashboard(User $user, array $filters = []): array
    {
        $this->timezone = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::now($this->timezone);
        $range = $this->resolveRange($filters, 'month', $now);
        $doctorId = $user->id;

        $appointmentsQuery = $this->citasQuery()->where('citas_medicas.doctor_id', $doctorId);

        $citasHoy = $this->countCitasInRange($doctorId, $range['today_start'], $range['today_end']);
        $nextAppointment = $this->nextAppointmentForDoctor($doctorId, $now);
        $pending = $this->countAppointmentsForDoctor($doctorId, [Cita::ESTADO_PENDIENTE]);
        $completed = $this->countAppointmentsForDoctor($doctorId, [Cita::ESTADO_REALIZADA]);
        $futureControls = $this->countFutureControls($doctorId, $now);
        $draftNotes = $this->countDraftNotes($doctorId);
        $pendingLabOrders = $this->countDoctorLabOrders($doctorId, false);
        $patientsAttended = $this->countPatientsAttendedInRange($doctorId, $range['start'], $range['end']);
        $documents = $this->countDoctorDocuments($doctorId, $range['start'], $range['end']);

        return [
            'role' => 'doctor',
            'filters' => $range,
            'metrics' => [
                'appointments_today' => $this->metric($citasHoy),
                'next_appointment' => $this->metric($nextAppointment ? 1 : 0, $nextAppointment ? $this->formatAppointmentSummary($nextAppointment) : 'Sin cita próxima'),
                'appointments_pending' => $this->metric($pending),
                'appointments_completed' => $this->metric($completed),
                'future_controls' => $this->metric($futureControls),
                'draft_notes' => $this->metric($draftNotes),
                'lab_orders_related' => $this->metric($pendingLabOrders),
                'patients_attended' => $this->metric($patientsAttended),
                'documents_total' => $this->metric(array_sum(array_column($documents, 'value'))),
            ],
            'charts' => [
                'appointments_status' => $this->buildStateSeries(
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
                ) + ['links' => $this->buildCitaStateLinks('doctor.citas', $range)],
                'appointments_status_timeline' => $this->buildStateTimelineSeries(
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
                    $now
                ),
                'patients_timeline' => $this->buildPatientTimelineSeries($range['timeline_start'], $range['timeline_end'], $range['grouping'], $doctorId, $now),
                'patients_recurrent' => $this->buildNewVsRecurrentSeries($doctorId, $range['start'], $range['end']),
                'controls_status' => $this->buildFollowUpSeries($doctorId, $range['start'], $range['end']),
                'documents_type' => $this->buildDoctorDocumentSeries($doctorId, $range['start'], $range['end']),
            ],
            'lists' => [
                'next_appointments' => $this->buildDoctorUpcomingAppointments($doctorId, 5, $now),
                'follow_up_controls' => $this->buildDoctorUpcomingControls($doctorId, 5, $now),
            ],
        ];
    }

    public function buildLaboratorioDashboard(User $user, array $filters = []): array
    {
        $this->timezone = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::now($this->timezone);
        $range = $this->resolveRange($filters, 'month', $now);
        $labUserId = $user->id;

        $citasToday = $this->countLabOrdersCreatedToday($range['today_start'], $range['today_end']);
        $statusSeries = $this->buildLabStateSeries($range['start'], $range['end']);
        $timeline = $this->buildLabTimelineSeries($range['timeline_start'], $range['timeline_end'], $range['grouping'], $now);
        $topExams = $this->buildLabExamSeries($range['start'], $range['end']);
        $doctorSeries = $this->buildLabDoctorSeries($range['start'], $range['end']);

        $metrics = [
            'received_today' => $this->metric($citasToday),
            'pending' => $this->metric((int) ($statusSeries['totals']['pending'] ?? 0)),
            'in_process' => $this->metric((int) ($statusSeries['totals']['in_process'] ?? 0)),
            'completed' => $this->metric((int) ($statusSeries['totals']['completed'] ?? 0)),
            'delivered' => $this->metric((int) ($statusSeries['totals']['delivered'] ?? 0)),
            'cancelled' => $this->metric((int) ($statusSeries['totals']['cancelled'] ?? 0)),
        ];

        return [
            'role' => 'laboratorio',
            'filters' => $range,
            'metrics' => $metrics,
            'charts' => [
                'orders_status' => $statusSeries['chart'],
                'orders_timeline' => $timeline,
                'orders_status_timeline' => $this->buildStateTimelineSeries(
                    $this->laboratoryOrdersQuery(),
                    'lab_orders.created_at',
                    null,
                    'status',
                    [
                        LabOrder::STATUS_PENDIENTE_TOMA,
                        LabOrder::STATUS_MUESTRA_TOMADA,
                        LabOrder::STATUS_EN_ANALISIS,
                        LabOrder::STATUS_RESULTADO_LISTO,
                        LabOrder::STATUS_CANCELADO,
                    ],
                    fn (string $state): string => match ($state) {
                        LabOrder::STATUS_PENDIENTE_TOMA => 'Pendiente',
                        LabOrder::STATUS_MUESTRA_TOMADA => 'Muestra tomada',
                        LabOrder::STATUS_EN_ANALISIS => 'En análisis',
                        LabOrder::STATUS_RESULTADO_LISTO => 'Completado',
                        LabOrder::STATUS_CANCELADO => 'Cancelado',
                        default => Str::headline($state),
                    },
                    $range['timeline_start'],
                    $range['timeline_end'],
                    $range['grouping'],
                    null,
                    $now
                ),
                'top_exams' => $topExams,
                'orders_by_doctor' => $doctorSeries,
            ],
            'lists' => [
                'recent_orders' => $this->buildLaboratoryRecentOrders($labUserId, 6),
            ],
        ];
    }

    public function resolveRange(array $filters, string $defaultPeriod = 'month', ?Carbon $now = null): array
    {
        $this->timezone = config('app.timezone', 'America/Guayaquil');
        if (! $now) {
            $now = Carbon::now($this->timezone);
        }

        $period = strtolower(trim((string) ($filters['period'] ?? $defaultPeriod)));
        $allowed = ['all', '7d', '30d', 'month', 'year'];
        if (! in_array($period, $allowed, true)) {
            $period = 'month';
        }

        $todayEnd = $now->copy()->endOfDay();
        $systemStart = $this->getSystemStartDate($now);

        return match ($period) {
            'all' => $this->buildAllRange($systemStart, $now),
            '7d' => $this->buildResolvedRange($period, $this->laterDate($now->copy()->subDays(6)->startOfDay(), $systemStart), $todayEnd, 'day', $now),
            '30d' => $this->buildResolvedRange($period, $this->laterDate($now->copy()->subDays(29)->startOfDay(), $systemStart), $todayEnd, 'day', $now),
            'month' => $this->buildResolvedRange($period, $this->laterDate($now->copy()->startOfMonth()->startOfDay(), $systemStart), $now->copy()->endOfMonth()->endOfDay(), 'day', $now),
            'year' => $this->buildResolvedRange($period, $this->laterDate($now->copy()->startOfYear()->startOfDay(), $systemStart), $now->copy()->endOfYear()->endOfDay(), 'month', $now),
        };
    }

    public function periodOptions(): array
    {
        return [
            'all' => 'Todos',
            '7d' => 'Últimos 7 días',
            '30d' => 'Últimos 30 días',
            'month' => 'Este mes',
            'year' => 'Este año',
        ];
    }

    protected function getSystemStartDate(Carbon $now): Carbon
    {
        $firstUser = User::query()
            ->orderBy('created_at')
            ->orderBy('id')
            ->first(['created_at']);
        return $firstUser ? $firstUser->created_at->copy()->tz($this->timezone)->startOfDay() : $now->copy()->startOfDay();
    }

    protected function laterDate(Carbon $first, Carbon $second): Carbon
    {
        return $first->greaterThan($second) ? $first->copy() : $second->copy();
    }

    protected function earlierDate(Carbon $first, Carbon $second): Carbon
    {
        return $first->lessThan($second) ? $first->copy() : $second->copy();
    }

    protected function getVisualStartDate(Carbon $systemStart, ?string $minRecordDate): Carbon
    {
        if (! $minRecordDate) {
            return $systemStart->copy();
        }
        $recordCarbon = Carbon::parse($minRecordDate, $this->timezone)->startOfDay();
        return $this->earlierDate($systemStart, $recordCarbon);
    }

    protected function getVisualEndDate(?Carbon $rangeEnd, ?string $maxRecordDate, Carbon $now): Carbon
    {
        if ($rangeEnd !== null) {
            return $rangeEnd->copy()->endOfDay();
        }
        if (! $maxRecordDate) {
            return $now->copy()->endOfDay();
        }
        $recordCarbon = Carbon::parse($maxRecordDate, $this->timezone)->endOfDay();
        return $this->laterDate($now, $recordCarbon);
    }

    protected function buildAllRange(Carbon $systemStart, Carbon $now): array
    {
        $maxCitaFecha = Cita::max('fecha');
        $maxCitaCarbon = $maxCitaFecha ? Carbon::parse($maxCitaFecha, $this->timezone)->endOfDay() : null;
        $endReal = $maxCitaCarbon && $maxCitaCarbon->greaterThan($now) ? $maxCitaCarbon : $now->copy()->endOfDay();

        $grouping = $endReal->greaterThan($systemStart->copy()->addYears(2)) ? 'year' : 'month';

        return [
            'period' => 'all',
            'label' => 'Todos',
            'start' => null,
            'end' => null,
            'timeline_start' => $systemStart,
            'timeline_end' => $endReal,
            'today_start' => $now->copy()->startOfDay(),
            'today_end' => $now->copy()->endOfDay(),
            'grouping' => $grouping,
            'from' => null,
            'to' => null,
        ];
    }

    protected function buildResolvedRange(string $period, Carbon $start, Carbon $end, string $grouping, Carbon $now): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        return [
            'period' => $period,
            'label' => $this->periodLabel($period),
            'start' => $start,
            'end' => $end,
            'timeline_start' => $start,
            'timeline_end' => $end,
            'today_start' => $now->copy()->startOfDay(),
            'today_end' => $now->copy()->endOfDay(),
            'grouping' => $grouping,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
        ];
    }

    protected function periodLabel(string $period): string
    {
        return $this->periodOptions()[$period] ?? 'Este mes';
    }

    protected function rangeQueryParams(?array $range): array
    {
        if (! $range) {
            return [];
        }
        return [
            'period' => $range['period'] ?? 'month',
        ];
    }

    protected function buildCitaStateLinks(string $routeName, array $range): array
    {
        return [
            route($routeName, array_merge($this->rangeQueryParams($range), ['estado' => Cita::ESTADO_PENDIENTE])),
            route($routeName, array_merge($this->rangeQueryParams($range), ['estado' => Cita::ESTADO_CONFIRMADA])),
            route($routeName, array_merge($this->rangeQueryParams($range), ['estado' => Cita::ESTADO_CANCELADA])),
            route($routeName, array_merge($this->rangeQueryParams($range), ['estado' => Cita::ESTADO_REALIZADA])),
            route($routeName, array_merge($this->rangeQueryParams($range), ['estado' => Cita::ESTADO_NO_SE_PRESENTO])),
        ];
    }

    protected function citasQuery()
    {
        return Cita::query();
    }

    protected function laboratoryOrdersQuery()
    {
        return LabOrder::query();
    }

    protected function countUsersByRoles(array $roles): array
    {
        $rows = Role::query()
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('roles.name', $roles)
            ->select('roles.name', DB::raw('COUNT(DISTINCT role_user.user_id) as total'))
            ->groupBy('roles.name')
            ->pluck('total', 'roles.name')
            ->all();

        return $rows;
    }

    protected function countActiveDoctors(): int
    {
        return (int) User::query()
            ->onlyActive()
            ->whereHas('roles', fn ($query) => $query->where('name', 'doctor'))
            ->count();
    }

    protected function countCitasInRange(?int $doctorId, ?Carbon $start, ?Carbon $end): int
    {
        $query = $this->citasQuery()
            ->when($doctorId, fn ($q) => $q->where('citas_medicas.doctor_id', $doctorId));

        if ($start !== null && $end !== null) {
            $query->whereBetween('citas_medicas.fecha', [$start->toDateString(), $end->toDateString()]);
        }

        return (int) $query->count();
    }

    protected function countCitasByState(?int $doctorId, ?Carbon $start, ?Carbon $end): array
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

    protected function countPendingLabOrders(): int
    {
        $pedidoLaboratorio = $this->tableExists((new PedidoLaboratorio())->getTable())
            ? (int) PedidoLaboratorio::query()
                ->where('estado', 'pendiente_toma')
                ->count()
            : 0;

        $legacy = $this->tableExists((new LaboratorioOrden())->getTable())
            ? (int) LaboratorioOrden::query()
                ->whereIn('estado', [LaboratorioOrden::ESTADO_ORDEN_CREADA, LaboratorioOrden::ESTADO_CITA_PROGRAMADA, LaboratorioOrden::ESTADO_MUESTRA_TOMADA])
                ->count()
            : 0;

        $labOrders = $this->tableExists((new LabOrder())->getTable())
            ? (int) LabOrder::query()
                ->whereIn('status', [LabOrder::STATUS_PENDIENTE_TOMA, LabOrder::STATUS_MUESTRA_TOMADA, LabOrder::STATUS_EN_ANALISIS])
                ->count()
            : 0;

        return (int) $pedidoLaboratorio + (int) $legacy + (int) $labOrders;
    }

    protected function countDocumentsInRange(?Carbon $start, ?Carbon $end): array
    {
        $recetaQuery = Receta::query();
        $certificadoQuery = CertificadoMedico::query();
        $pedidoQuery = PedidoLaboratorio::query();

        if ($start !== null && $end !== null) {
            $recetaQuery->whereBetween('recetas.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            $certificadoQuery->whereBetween('certificados_medicos.fecha_emision', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            if ($this->tableExists((new PedidoLaboratorio())->getTable())) {
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
                'value' => $this->tableExists((new PedidoLaboratorio())->getTable())
                    ? (int) $pedidoQuery->count()
                    : 0,
            ],
        ];
    }

    protected function buildStateSeries($baseQuery, array $states, callable $labelResolver, string $field, ?int $doctorId, ?Carbon $start, ?Carbon $end): array
    {
        $query = clone $baseQuery;
        if ($doctorId) {
            $query->where('citas_medicas.doctor_id', $doctorId);
        }

        if ($start !== null && $end !== null) {
            $query->whereBetween('citas_medicas.fecha', [$start->toDateString(), $end->toDateString()]);
        }

        $counts = $query
            ->select($field, DB::raw('COUNT(*) as total'))
            ->groupBy($field)
            ->pluck('total', $field)
            ->all();

        $labels = [];
        $series = [];
        foreach ($states as $state) {
            $labels[] = $labelResolver($state);
            $series[] = (int) ($counts[$state] ?? 0);
        }

        return [
            'type' => 'donut',
            'title' => '',
            'labels' => $labels,
            'series' => $series,
            'colors' => $this->stateColors(count($labels)),
        ];
    }

    protected function buildTimelineSeries($baseQuery, string $column, Carbon $start, ?Carbon $end, string $grouping, string $seriesName, Carbon $now): array
    {
        if ($grouping === 'hour') {
            return $this->buildHourlyTimelineSeries($baseQuery, $column, $start, $seriesName);
        }

        $minVal = (clone $baseQuery)->min($column);
        $maxVal = (clone $baseQuery)->max($column);

        $t_start = $this->getVisualStartDate($start, $minVal);
        $t_end = $this->getVisualEndDate($end, $maxVal, $now);

        $query = clone $baseQuery;
        $query->whereBetween($column, [$t_start->copy()->startOfDay(), $t_end->copy()->endOfDay()]);

        $daily = $query
            ->selectRaw('DATE('.$column.') as bucket, COUNT(*) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn ($value) => (int) $value)
            ->all();

        [$labels, $values] = $this->aggregateTimeline($daily, $t_start, $t_end, $grouping);

        return [
            'type' => 'line',
            'labels' => $labels,
            'series' => [
                [
                    'name' => $seriesName,
                    'data' => $values,
                ],
            ],
            'colors' => ['#0f766e'],
        ];
    }

    protected function buildHourlyTimelineSeries($baseQuery, string $column, Carbon $start, string $seriesName): array
    {
        $dayStart = $start->copy()->startOfDay();
        $dayEnd = $start->copy()->endOfDay();

        $query = clone $baseQuery;
        $query->whereBetween($column, [$dayStart, $dayEnd]);

        $counts = $query
            ->selectRaw("SUBSTR($column, 12, 2) as bucket, COUNT(*) as total")
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn ($value) => (int) $value)
            ->all();

        $labels = [];
        $values = [];
        foreach (range(0, 23) as $hour) {
            $key = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
            $labels[] = $key.':00';
            $values[] = (int) ($counts[$key] ?? 0);
        }

        return [
            'type' => 'line',
            'labels' => $labels,
            'series' => [
                [
                    'name' => $seriesName,
                    'data' => $values,
                ],
            ],
            'colors' => ['#0f766e'],
        ];
    }

    protected function buildStateTimelineSeries(
        $baseQuery,
        string $dateColumn,
        ?string $timeColumn,
        string $stateColumn,
        array $states,
        callable $labelResolver,
        Carbon $start,
        ?Carbon $end,
        string $grouping,
        ?int $doctorId = null,
        ?Carbon $now = null
    ): array {
        if (! $now) {
            $now = Carbon::now($this->timezone);
        }

        $query = clone $baseQuery;
        if ($doctorId !== null) {
            $query->where('citas_medicas.doctor_id', $doctorId);
        }

        if ($grouping === 'hour') {
            $bucketExpression = $timeColumn ? "SUBSTR($timeColumn, 1, 2)" : "SUBSTR($dateColumn, 12, 2)";

            $rows = $query
                ->whereDate($dateColumn, $start->toDateString())
                ->selectRaw("$bucketExpression as bucket, $stateColumn as state, COUNT(*) as total")
                ->groupBy('bucket', $stateColumn)
                ->get();

            $seriesMap = [];
            foreach ($states as $state) {
                $seriesMap[$state] = array_fill(0, 24, 0);
            }

            foreach ($rows as $row) {
                $hour = (int) $row->bucket;
                if ($hour < 0 || $hour > 23 || ! array_key_exists($row->state, $seriesMap)) {
                    continue;
                }

                $seriesMap[$row->state][$hour] = (int) $row->total;
            }

            $labels = [];
            foreach (range(0, 23) as $hour) {
                $labels[] = str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00';
            }

            $series = [];
            foreach ($states as $state) {
                $series[] = [
                    'name' => $labelResolver($state),
                    'data' => $seriesMap[$state] ?? array_fill(0, 24, 0),
                ];
            }

            return [
                'type' => 'bar',
                'stacked' => true,
                'labels' => $labels,
                'series' => $series,
                'colors' => $this->stateColors(count($states)),
            ];
        }

        $minVal = (clone $baseQuery)->min($dateColumn);
        $maxVal = (clone $baseQuery)->max($dateColumn);

        $t_start = $this->getVisualStartDate($start, $minVal);
        $t_end = $this->getVisualEndDate($end, $maxVal, $now);

        $rows = $query
            ->whereBetween($dateColumn, [$t_start->copy()->startOfDay(), $t_end->copy()->endOfDay()])
            ->selectRaw('DATE('.$dateColumn.') as bucket, '.$stateColumn.' as state, COUNT(*) as total')
            ->groupBy('bucket', $stateColumn)
            ->get();

        $daily = [];
        foreach ($rows as $row) {
            $daily[$row->bucket][$row->state] = (int) $row->total;
        }

        $buckets = [];
        $crossYear = $t_start->year !== $t_end->year;
        foreach (CarbonPeriod::create($t_start->copy()->startOfDay(), '1 day', $t_end->copy()->endOfDay()) as $date) {
            $bucketKey = $this->timelineBucketKey($date, $grouping);

            if (! isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = [
                    'label' => $this->timelineLabel($date, $grouping, $crossYear),
                    'values' => array_fill_keys($states, 0),
                ];
            }

            $dayCounts = $daily[$date->toDateString()] ?? [];
            foreach ($states as $state) {
                $buckets[$bucketKey]['values'][$state] += (int) ($dayCounts[$state] ?? 0);
            }
        }

        $labels = array_values(array_map(fn ($bucket) => $bucket['label'], $buckets));
        $series = [];
        foreach ($states as $state) {
            $series[] = [
                'name' => $labelResolver($state),
                'data' => array_values(array_map(fn ($bucket) => (int) ($bucket['values'][$state] ?? 0), $buckets)),
            ];
        }

        return [
            'type' => 'bar',
            'stacked' => true,
            'labels' => $labels,
            'series' => $series,
            'colors' => $this->stateColors(count($states)),
        ];
    }

    protected function aggregateTimeline(array $dailyCounts, Carbon $start, Carbon $end, string $grouping): array
    {
        $points = [];
        $crossYear = $start->year !== $end->year;
        foreach (CarbonPeriod::create($start->copy()->startOfDay(), '1 day', $end->copy()->endOfDay()) as $date) {
            $key = $this->timelineBucketKey($date, $grouping);

            if (! isset($points[$key])) {
                $points[$key] = [
                    'label' => $this->timelineLabel($date, $grouping, $crossYear),
                    'value' => 0,
                ];
            }

            $points[$key]['value'] += (int) ($dailyCounts[$date->toDateString()] ?? 0);
        }

        return [
            array_values(array_map(fn ($point) => $point['label'], $points)),
            array_values(array_map(fn ($point) => $point['value'], $points)),
        ];
    }

    protected function timelineBucketKey(Carbon $date, string $grouping): string
    {
        return match ($grouping) {
            'hour' => $date->format('Y-m-d H'),
            'month' => $date->format('Y-m'),
            'week' => $date->isoWeekYear.'-W'.str_pad((string) $date->isoWeek(), 2, '0', STR_PAD_LEFT),
            'year' => $date->format('Y'),
            default => $date->toDateString(),
        };
    }

    protected function timelineLabel(Carbon $date, string $grouping, bool $crossYear = false): string
    {
        return match ($grouping) {
            'hour' => $date->format('H').':00',
            'month' => ucfirst(str_replace('.', '', $date->locale('es')->translatedFormat('M Y'))),
            'week' => 'Semana '.$date->isoWeek().' / '.$date->isoWeekYear,
            'year' => $date->format('Y'),
            default => $crossYear ? $date->format('d/m/Y') : $date->format('d/m'),
        };
    }

    protected function buildRoleSeries(array $usersByRole, ?array $range = null): array
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

            $labels[] = $this->labelForRole($role);
            $values[] = $total;
            $links[] = in_array($role, ['paciente', 'doctor', 'administrador', 'laboratorio'], true)
                ? route('superadmin.users.index', array_merge($this->rangeQueryParams($range), ['role' => $role]))
                : null;
        }

        return [
            'type' => 'donut',
            'labels' => $labels,
            'series' => $values,
            'links' => $links,
            'colors' => $this->stateColors(count($labels)),
            'centerLabel' => 'Usuarios',
        ];
    }

    protected function buildSpecialtySeries(Carbon $start, Carbon $end): array
    {
        $rows = Cita::query()
            ->select('especialidad_id', DB::raw('COUNT(*) as total'))
            ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('especialidad_id')
            ->groupBy('especialidad_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

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

    protected function buildDoctorSeries(Carbon $start, Carbon $end, ?array $range = null): array
    {
        $rows = Cita::query()
            ->select('doctor_id', DB::raw('COUNT(*) as total'))
            ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->groupBy('doctor_id')
            ->orderByDesc('total')
            ->limit(8)
            ->with('doctor:id,name')
            ->get();

        $labels = [];
        $values = [];
        $links = [];
        foreach ($rows as $row) {
            $labels[] = $row->doctor?->name ?? 'Sin doctor';
            $values[] = (int) $row->total;
            $links[] = route('admin.cambios-citas.index', array_merge(
                $this->rangeQueryParams($range),
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

    protected function buildDocumentSeries(Carbon $start, Carbon $end): array
    {
        $documents = $this->countDocumentsInRange($start, $end);

        return [
            'type' => 'donut',
            'labels' => array_column($documents, 'label'),
            'series' => array_column($documents, 'value'),
            'colors' => ['#0f766e', '#3b82f6', '#f59e0b'],
        ];
    }

    protected function buildPatientTimelineSeries(Carbon $start, ?Carbon $end, string $grouping, ?int $doctorId = null, ?Carbon $now = null): array
    {
        if (! $now) {
            $now = Carbon::now($this->timezone);
        }

        $baseQuery = Cita::query()
            ->whereIn('citas_medicas.estado', [Cita::ESTADO_REALIZADA]);

        if ($doctorId) {
            $baseQuery->where('citas_medicas.doctor_id', $doctorId);
        }

        $minVal = (clone $baseQuery)->min('citas_medicas.fecha');
        $maxVal = (clone $baseQuery)->max('citas_medicas.fecha');

        $t_start = $this->getVisualStartDate($start, $minVal);
        $t_end = $this->getVisualEndDate($end, $maxVal, $now);

        $query = clone $baseQuery;
        $query->whereBetween('citas_medicas.fecha', [$t_start->toDateString(), $t_end->toDateString()]);

        $daily = $query
            ->selectRaw('DATE(citas_medicas.fecha) as bucket, COUNT(DISTINCT CASE WHEN dependiente_id IS NULL THEN CONCAT("P-", paciente_id) ELSE CONCAT("D-", dependiente_id) END) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn ($value) => (int) $value)
            ->all();

        [$labels, $values] = $this->aggregateTimeline($daily, $t_start, $t_end, $grouping);

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

    protected function buildNewVsRecurrentSeries(int $doctorId, ?Carbon $start, ?Carbon $end): array
    {
        $attended = Cita::query()
            ->where('citas_medicas.doctor_id', $doctorId)
            ->where('citas_medicas.estado', Cita::ESTADO_REALIZADA);

        if ($start !== null && $end !== null) {
            $attended->whereBetween('citas_medicas.fecha', [$start->toDateString(), $end->toDateString()]);
        }

        $attended->selectRaw("CASE WHEN dependiente_id IS NULL THEN CONCAT('P-', paciente_id) ELSE CONCAT('D-', dependiente_id) END as subject_key, MIN(citas_medicas.fecha) as first_date")
            ->groupBy('subject_key');

        $newQuery = DB::query()->fromSub($attended, 'attended');
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

    protected function buildFollowUpSeries(int $doctorId, ?Carbon $start, ?Carbon $end): array
    {
        $now = Carbon::now($this->timezone);

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
                $citaDateTime = Carbon::parse($fechaStr . ' ' . $horaStr, $this->timezone);

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

    protected function buildDoctorDocumentSeries(int $doctorId, Carbon $start, Carbon $end): array
    {
        $labCount = 0;
        if ($this->tableExists((new PedidoLaboratorio())->getTable())) {
            $labCount += (int) PedidoLaboratorio::query()
                ->where('doctor_id', $doctorId)
                ->whereBetween('created_at', [$start, $end])
                ->count();
        }
        if ($this->tableExists((new LabOrder())->getTable())) {
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

    protected function buildDoctorUpcomingAppointments(int $doctorId, int $limit = 5): array
    {
        $now = Carbon::now($this->timezone);

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
                'tone' => $this->toneForState($cita->estado),
                'url' => route('doctor.citas.soap', $cita),
            ])
            ->values()
            ->all();
    }

    protected function buildDoctorUpcomingControls(int $doctorId, int $limit = 5): array
    {
        return NotaSoap::query()
            ->with(['cita.paciente', 'cita.dependiente', 'followUpCita.doctor', 'followUpCita.especialidad'])
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
                        'tone' => $this->toneForState($followUp->estado),
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

    protected function countPatientsAttendedInRange(int $doctorId, Carbon $start, Carbon $end): int
    {
        return (int) Cita::query()
            ->where('doctor_id', $doctorId)
            ->where('estado', Cita::ESTADO_REALIZADA)
            ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("COUNT(DISTINCT CASE WHEN dependiente_id IS NULL THEN CONCAT('P-', paciente_id) ELSE CONCAT('D-', dependiente_id) END) as total")
            ->value('total');
    }

    protected function countDraftNotes(int $doctorId): int
    {
        return (int) NotaSoap::query()
            ->join('citas_medicas', 'notas_soap.cita_id', '=', 'citas_medicas.id')
            ->where('citas_medicas.doctor_id', $doctorId)
            ->where('notas_soap.estado', NotaSoap::ESTADO_BORRADOR)
            ->count();
    }

    protected function countFutureControls(int $doctorId): int
    {
        $now = Carbon::now($this->timezone);

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

    protected function countDoctorLabOrders(int $doctorId, bool $onlyCompleted): int
    {
        $pendingCount = 0;

        if ($this->tableExists((new PedidoLaboratorio())->getTable())) {
            $pending = PedidoLaboratorio::query()->where('doctor_id', $doctorId);
            if ($onlyCompleted) {
                return (int) $pending->where('estado', 'resultado_listo')->count();
            }

            $pendingCount = (int) $pending->where('estado', 'pendiente_toma')->count();
        }

        $labOrderCount = $this->tableExists((new LabOrder())->getTable())
            ? (int) LabOrder::query()->where('doctor_id', $doctorId)->whereIn('status', [LabOrder::STATUS_PENDIENTE_TOMA, LabOrder::STATUS_MUESTRA_TOMADA, LabOrder::STATUS_EN_ANALISIS])->count()
            : 0;

        return $pendingCount + $labOrderCount;
    }

    protected function countAppointmentsForDoctor(int $doctorId, array $states): int
    {
        return (int) Cita::query()
            ->where('doctor_id', $doctorId)
            ->whereIn('estado', $states)
            ->count();
    }

    protected function nextAppointmentForDoctor(int $doctorId): ?Cita
    {
        $now = Carbon::now($this->timezone);

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

    protected function formatAppointmentSummary(Cita $cita): string
    {
        return trim(sprintf(
            '%s · %s %s · %s',
            $cita->nombrePacienteReal(),
            $cita->fecha instanceof Carbon ? $cita->fecha->format('d/m/Y') : (string) $cita->fecha,
            substr((string) $cita->hora, 0, 5),
            $cita->especialidad?->nombre ?? 'Consulta'
        ));
    }

    protected function countLabOrdersCreatedToday(Carbon $start, Carbon $end): int
    {
        return ($this->tableExists((new PedidoLaboratorio())->getTable())
                ? (int) PedidoLaboratorio::query()->whereBetween('created_at', [$start, $end])->count()
                : 0)
            + ($this->tableExists((new LaboratorioOrden())->getTable())
                ? (int) LaboratorioOrden::query()->whereBetween('created_at', [$start, $end])->count()
                : 0)
            + ($this->tableExists((new LabOrder())->getTable())
                ? (int) LabOrder::query()->whereBetween('created_at', [$start, $end])->count()
                : 0);
    }

    protected function buildLabStateSeries(?Carbon $start, ?Carbon $end): array
    {
        $counts = [
            'pending' => 0,
            'in_process' => 0,
            'completed' => 0,
            'delivered' => 0,
            'cancelled' => 0,
        ];

        if ($this->tableExists((new PedidoLaboratorio())->getTable())) {
            $q = PedidoLaboratorio::query();
            if ($start !== null && $end !== null) {
                $q->whereBetween('pedidos_laboratorio.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            }
            $counts['pending'] += (int) (clone $q)->where('estado', 'pendiente_toma')->count();
            $counts['completed'] += (int) (clone $q)->where('estado', 'resultado_listo')->count();
            $counts['delivered'] += (int) (clone $q)->whereNotNull('resultado_enviado_at')->count();
            $counts['cancelled'] += (int) (clone $q)->where('estado', 'cancelado')->count();
        }

        if ($this->tableExists((new LaboratorioOrden())->getTable())) {
            $q = LaboratorioOrden::query();
            if ($start !== null && $end !== null) {
                $q->whereBetween('laboratorio_ordenes.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            }
            $counts['pending'] += (int) (clone $q)->whereIn('estado', [LaboratorioOrden::ESTADO_ORDEN_CREADA, LaboratorioOrden::ESTADO_CITA_PROGRAMADA])->count();
            $counts['in_process'] += (int) (clone $q)->where('estado', LaboratorioOrden::ESTADO_MUESTRA_TOMADA)->count();
            $counts['completed'] += (int) (clone $q)->where('estado', LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE)->count();
            $counts['delivered'] += (int) (clone $q)->whereNotNull('resultado_enviado_at')->count();
        }

        if ($this->tableExists((new LabOrder())->getTable())) {
            $q = LabOrder::query();
            if ($start !== null && $end !== null) {
                $q->whereBetween('lab_orders.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            }
            $counts['pending'] += (int) (clone $q)->where('status', LabOrder::STATUS_PENDIENTE_TOMA)->count();
            $counts['in_process'] += (int) (clone $q)->whereIn('status', [LabOrder::STATUS_MUESTRA_TOMADA, LabOrder::STATUS_EN_ANALISIS])->count();
            $counts['completed'] += (int) (clone $q)->where('status', LabOrder::STATUS_RESULTADO_LISTO)->count();
            $counts['delivered'] += (int) (clone $q)->whereNotNull('resultado_enviado_at')->count();
            $counts['cancelled'] += (int) (clone $q)->where('status', LabOrder::STATUS_CANCELADO)->count();
        }

        return [
            'totals' => $counts,
            'chart' => [
                'type' => 'donut',
                'labels' => ['Pendientes', 'En proceso', 'Completados', 'Entregados', 'Cancelados'],
                'series' => array_values($counts),
                'colors' => ['#f59e0b', '#3b82f6', '#0f766e', '#14b8a6', '#ef4444'],
            ],
        ];
    }

    protected function buildLabTimelineSeries(Carbon $start, ?Carbon $end, string $grouping, Carbon $now): array
    {
        $minCreatedAt = null;
        $maxCreatedAt = null;
        if ($this->tableExists((new PedidoLaboratorio())->getTable())) {
            $minP = PedidoLaboratorio::min('created_at');
            $maxP = PedidoLaboratorio::max('created_at');
            if ($minP) {
                $minCreatedAt = $minCreatedAt ? $this->earlierDate($minCreatedAt, Carbon::parse($minP)) : Carbon::parse($minP);
            }
            if ($maxP) {
                $maxCreatedAt = $maxCreatedAt ? $this->laterDate($maxCreatedAt, Carbon::parse($maxP)) : Carbon::parse($maxP);
            }
        }
        if ($this->tableExists((new LaboratorioOrden())->getTable())) {
            $minL = LaboratorioOrden::min('created_at');
            $maxL = LaboratorioOrden::max('created_at');
            if ($minL) {
                $minCreatedAt = $minCreatedAt ? $this->earlierDate($minCreatedAt, Carbon::parse($minL)) : Carbon::parse($minL);
            }
            if ($maxL) {
                $maxCreatedAt = $maxCreatedAt ? $this->laterDate($maxCreatedAt, Carbon::parse($maxL)) : Carbon::parse($maxL);
            }
        }
        if ($this->tableExists((new LabOrder())->getTable())) {
            $minO = LabOrder::min('created_at');
            $maxO = LabOrder::max('created_at');
            if ($minO) {
                $minCreatedAt = $minCreatedAt ? $this->earlierDate($minCreatedAt, Carbon::parse($minO)) : Carbon::parse($minO);
            }
            if ($maxO) {
                $maxCreatedAt = $maxCreatedAt ? $this->laterDate($maxCreatedAt, Carbon::parse($maxO)) : Carbon::parse($maxO);
            }
        }

        $t_start = $this->getVisualStartDate($start, $minCreatedAt ? $minCreatedAt->toDateString() : null);
        $t_end = $this->getVisualEndDate($end, $maxCreatedAt ? $maxCreatedAt->toDateString() : null, $now);

        $daily = [];

        if ($this->tableExists((new PedidoLaboratorio())->getTable())) {
            $daily = $this->mergeDailyCounts($daily, PedidoLaboratorio::query()
                ->whereBetween('pedidos_laboratorio.created_at', [$t_start->copy()->startOfDay(), $t_end->copy()->endOfDay()])
                ->selectRaw('DATE(pedidos_laboratorio.created_at) as bucket, COUNT(*) as total')
                ->groupBy('bucket')
                ->pluck('total', 'bucket')
                ->map(fn ($value) => (int) $value)
                ->all());
        }

        if ($this->tableExists((new LaboratorioOrden())->getTable())) {
            $daily = $this->mergeDailyCounts($daily, LaboratorioOrden::query()
                ->whereBetween('laboratorio_ordenes.created_at', [$t_start->copy()->startOfDay(), $t_end->copy()->endOfDay()])
                ->selectRaw('DATE(laboratorio_ordenes.created_at) as bucket, COUNT(*) as total')
                ->groupBy('bucket')
                ->pluck('total', 'bucket')
                ->map(fn ($value) => (int) $value)
                ->all());
        }

        if ($this->tableExists((new LabOrder())->getTable())) {
            $daily = $this->mergeDailyCounts($daily, LabOrder::query()
                ->whereBetween('lab_orders.created_at', [$t_start->copy()->startOfDay(), $t_end->copy()->endOfDay()])
                ->selectRaw('DATE(lab_orders.created_at) as bucket, COUNT(*) as total')
                ->groupBy('bucket')
                ->pluck('total', 'bucket')
                ->map(fn ($value) => (int) $value)
                ->all());
        }

        [$labels, $values] = $this->aggregateTimeline($daily, $t_start, $t_end, $grouping);

        return [
            'type' => 'line',
            'labels' => $labels,
            'series' => [
                [
                    'name' => 'Pedidos recibidos',
                    'data' => $values,
                ],
            ],
            'colors' => ['#0f766e'],
        ];
    }

    protected function buildLabExamSeries(?Carbon $start, ?Carbon $end): array
    {
        $counts = [];

        if ($this->tableExists((new PedidoLaboratorio())->getTable())) {
            $q = PedidoLaboratorio::query();
            if ($start !== null && $end !== null) {
                $q->whereBetween('pedidos_laboratorio.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            }
            $q->get(['examenes'])
                ->each(function (PedidoLaboratorio $pedido) use (&$counts) {
                    foreach ((array) $pedido->examenes as $exam) {
                        $label = Str::headline((string) $exam);
                        $counts[$label] = ($counts[$label] ?? 0) + 1;
                    }
                });
        }

        if ($this->tableExists((new LaboratorioOrden())->getTable())) {
            $q = LaboratorioOrden::query();
            if ($start !== null && $end !== null) {
                $q->whereBetween('laboratorio_ordenes.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            }
            $q->get(['tipo_examen'])
                ->pluck('tipo_examen')
                ->filter()
                ->each(function ($exam) use (&$counts) {
                    $label = trim((string) $exam);
                    $counts[$label] = ($counts[$label] ?? 0) + 1;
                });
        }

        if ($this->tableExists((new LabOrder())->getTable())) {
            $q = LabOrder::query();
            if ($start !== null && $end !== null) {
                $q->whereBetween('lab_orders.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            }
            $q->with('items.test:id,nombre')
                ->get()
                ->each(function (LabOrder $order) use (&$counts) {
                    foreach ($order->items as $item) {
                        $label = $item->test?->nombre ?: 'Examen';
                        $counts[$label] = ($counts[$label] ?? 0) + 1;
                    }
                });
        }

        arsort($counts);
        $counts = array_slice($counts, 0, 10, true);

        return [
            'type' => 'bar',
            'labels' => array_keys($counts),
            'series' => [
                [
                    'name' => 'Solicitudes',
                    'data' => array_values($counts),
                ],
            ],
            'colors' => ['#8b5cf6'],
            'horizontal' => true,
        ];
    }

    protected function buildLabDoctorSeries(?Carbon $start, ?Carbon $end): array
    {
        $counts = [];

        if ($this->tableExists((new PedidoLaboratorio())->getTable())) {
            $q = PedidoLaboratorio::query()->with('doctor:id,name');
            if ($start !== null && $end !== null) {
                $q->whereBetween('pedidos_laboratorio.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            }
            $counts = $this->mergeNamedCounts(
                $counts,
                $q->select('doctor_id', DB::raw('COUNT(*) as total'))
                    ->groupBy('doctor_id')
                    ->get()
                    ->mapWithKeys(fn ($row) => [$row->doctor?->name ?? 'Sin doctor' => (int) $row->total])
                    ->all()
            );
        }

        if ($this->tableExists((new LaboratorioOrden())->getTable())) {
            $q = LaboratorioOrden::query()->with('cita.doctor:id,name');
            if ($start !== null && $end !== null) {
                $q->whereBetween('laboratorio_ordenes.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            }
            $legacy = $q->select('cita_id', DB::raw('COUNT(*) as total'))
                ->groupBy('cita_id')
                ->get()
                ->mapWithKeys(fn ($row) => [optional($row->cita)->doctor?->name ?? 'Sin doctor' => (int) $row->total])
                ->all();

            $counts = $this->mergeNamedCounts($counts, $legacy);
        }

        if ($this->tableExists((new LabOrder())->getTable())) {
            $q = LabOrder::query()->with('doctor:id,name');
            if ($start !== null && $end !== null) {
                $q->whereBetween('lab_orders.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            }
            $self = $q->select('doctor_id', DB::raw('COUNT(*) as total'))
                ->groupBy('doctor_id')
                ->get()
                ->mapWithKeys(fn ($row) => [$row->doctor?->name ?? 'Sin doctor' => (int) $row->total])
                ->all();

            $counts = $this->mergeNamedCounts($counts, $self);
        }

        arsort($counts);

        return [
            'type' => 'bar',
            'labels' => array_slice(array_keys($counts), 0, 8),
            'series' => [
                [
                    'name' => 'Pedidos',
                    'data' => array_slice(array_values($counts), 0, 8),
                ],
            ],
            'colors' => ['#0ea5e9'],
            'horizontal' => true,
        ];
    }

    protected function buildRecentAppointments(?Carbon $start, ?Carbon $end): array
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
                'tone' => $this->toneForState($cita->estado),
                'url' => route('admin.cambios-citas.index', ['cita_id' => $cita->id]),
            ])
            ->all();
    }

    protected function buildLaboratoryRecentOrders(int $labUserId, int $limit = 6): array
    {
        return LabOrder::query()
            ->with(['patient:id,name', 'doctor:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (LabOrder $order) => [
                'id' => $order->id,
                'paciente' => $order->patient?->name ?? 'Paciente',
                'doctor' => $order->doctor?->name ?? 'Sin doctor',
                'when' => $order->created_at->format('d/m/Y H:i'),
                'status' => $order->status,
                'status_label' => match ($order->status) {
                    LabOrder::STATUS_PENDIENTE_TOMA => 'Pendiente',
                    LabOrder::STATUS_MUESTRA_TOMADA => 'Muestra tomada',
                    LabOrder::STATUS_EN_ANALISIS => 'En análisis',
                    LabOrder::STATUS_RESULTADO_LISTO => 'Completado',
                    LabOrder::STATUS_CANCELADO => 'Cancelado',
                    default => Str::headline($order->status),
                },
                'tone' => $this->toneForLabOrderState($order->status),
                'url' => route('laboratorio.ordenes.index').'#lab-order-'.$order->id,
            ])
            ->all();
    }

    protected function countDoctorDocuments(int $doctorId, Carbon $start, Carbon $end): array
    {
        $labCount = 0;
        if ($this->tableExists((new PedidoLaboratorio())->getTable())) {
            $labCount += (int) PedidoLaboratorio::query()
                ->where('doctor_id', $doctorId)
                ->whereBetween('created_at', [$start, $end])
                ->count();
        }
        if ($this->tableExists((new LabOrder())->getTable())) {
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
                'value' => (int) CertificadoMedico::query()
                    ->where('doctor_id', $doctorId)
                    ->whereBetween('fecha_emision', [$start, $end])
                    ->count(),
            ],
            [
                'label' => 'Pedidos de laboratorio',
                'value' => $labCount,
            ],
        ];
    }

    protected function tableExists(string $table): bool
    {
        if (isset($this->tableExistsCache[$table])) {
            return $this->tableExistsCache[$table];
        }

        $this->tableExistsCache[$table] = Schema::hasTable($table);

        return $this->tableExistsCache[$table];
    }

    protected function mergeDailyCounts(array $current, array $incoming): array
    {
        foreach ($incoming as $bucket => $total) {
            $current[$bucket] = ($current[$bucket] ?? 0) + (int) $total;
        }

        return $current;
    }

    protected function mergeNamedCounts(array $current, array $incoming): array
    {
        foreach ($incoming as $label => $total) {
            $current[$label] = ($current[$label] ?? 0) + (int) $total;
        }

        return $current;
    }

    protected function metric(int $value, ?string $subtitle = null): array
    {
        return [
            'value' => $value,
            'subtitle' => $subtitle,
        ];
    }

    protected function labelForRole(string $role): string
    {
        return match ($role) {
            'superadmin' => 'Superadmin',
            'administrador' => 'Administradores',
            'doctor' => 'Doctores',
            'laboratorio' => 'Laboratorio',
            'paciente' => 'Pacientes',
            default => Str::headline($role),
        };
    }

    protected function toneForState(string $state): string
    {
        return match ($state) {
            Cita::ESTADO_CONFIRMADA => 'info',
            Cita::ESTADO_REALIZADA => 'success',
            Cita::ESTADO_CANCELADA, Cita::ESTADO_NO_SE_PRESENTO => 'danger',
            default => 'warning',
        };
    }

    protected function toneForLabState(string $state): string
    {
        return match ($state) {
            LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE => 'success',
            LaboratorioOrden::ESTADO_MUESTRA_TOMADA => 'info',
            default => 'warning',
        };
    }

    protected function toneForLabOrderState(string $state): string
    {
        return match ($state) {
            LabOrder::STATUS_RESULTADO_LISTO => 'success',
            LabOrder::STATUS_EN_ANALISIS, LabOrder::STATUS_MUESTRA_TOMADA => 'info',
            LabOrder::STATUS_CANCELADO => 'danger',
            default => 'warning',
        };
    }

    protected function buildDoctorAppointmentsStacked(?Carbon $start, ?Carbon $end, ?array $range = null): array
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
                $this->rangeQueryParams($range),
                $docId !== null ? ['doctor_id' => $docId] : []
            ));
        }

        return [
            'type' => 'bar',
            'stacked' => true,
            'labels' => $labels,
            'series' => $series,
            'links' => $links,
            'colors' => $this->stateColors(count(Cita::ESTADOS)),
            'horizontal' => true,
        ];
    }

    protected function buildPatientsNewVsAttended(Carbon $start, ?Carbon $end, string $grouping, ?Carbon $now = null): array
    {
        if (! $now) {
            $now = Carbon::now($this->timezone);
        }

        $minUser = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'paciente'))->min('created_at');
        $minCita = Cita::query()->where('estado', Cita::ESTADO_REALIZADA)->min('fecha');

        $overallMin = $minUser;
        if ($minCita) {
            $overallMin = $overallMin ? $this->earlierDate(Carbon::parse($overallMin), Carbon::parse($minCita))->toDateString() : $minCita;
        }

        $maxUser = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'paciente'))->max('created_at');
        $maxCita = Cita::query()->where('estado', Cita::ESTADO_REALIZADA)->max('fecha');

        $overallMax = $maxUser;
        if ($maxCita) {
            $overallMax = $overallMax ? $this->laterDate(Carbon::parse($overallMax), Carbon::parse($maxCita))->toDateString() : $maxCita;
        }

        $t_start = $this->getVisualStartDate($start, $overallMin);
        $t_end = $this->getVisualEndDate($end, $overallMax, $now);

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
            $bucketKey = $this->timelineBucketKey($date, $grouping);

            if (! isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = [
                    'label' => $this->timelineLabel($date, $grouping, $crossYear),
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

    protected function stateColors(int $count): array
    {
        $base = ['#0f766e', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6'];

        return array_slice($base, 0, max(1, $count));
    }
}
