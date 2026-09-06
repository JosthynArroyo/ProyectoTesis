<?php

namespace App\Services\Analytics;

use App\Models\LaboratorioOrden;
use App\Models\LabOrder;
use App\Models\PedidoLaboratorio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LaboratoryDashboardAnalyticsService
{
    public function __construct(
        protected DashboardPeriodResolver $periodResolver,
        protected DashboardChartBuilder $chartBuilder
    ) {}

    public function buildLaboratorioDashboard(User $user, array $filters = [], ?string $timezone = null): array
    {
        $tz = $timezone ?: config('app.timezone', 'America/Guayaquil');
        $now = Carbon::now($tz);
        $range = $this->periodResolver->resolveRange($filters, 'month', $now, $tz);
        $labUserId = $user->id;

        $citasToday = $this->countLabOrdersCreatedToday($range['today_start'], $range['today_end']);
        $statusSeries = $this->buildLabStateSeries($range['start'], $range['end']);
        $timeline = $this->buildLabTimelineSeries($range['timeline_start'], $range['timeline_end'], $range['grouping'], $now, $tz);
        $topExams = $this->buildLabExamSeries($range['start'], $range['end']);
        $doctorSeries = $this->buildLabDoctorSeries($range['start'], $range['end']);

        $metrics = [
            'received_today' => $this->chartBuilder->metric($citasToday),
            'pending' => $this->chartBuilder->metric((int) ($statusSeries['totals']['pending'] ?? 0)),
            'in_process' => $this->chartBuilder->metric((int) ($statusSeries['totals']['in_process'] ?? 0)),
            'completed' => $this->chartBuilder->metric((int) ($statusSeries['totals']['completed'] ?? 0)),
            'delivered' => $this->chartBuilder->metric((int) ($statusSeries['totals']['delivered'] ?? 0)),
            'cancelled' => $this->chartBuilder->metric((int) ($statusSeries['totals']['cancelled'] ?? 0)),
        ];

        return [
            'role' => 'laboratorio',
            'filters' => $range,
            'metrics' => $metrics,
            'charts' => [
                'orders_status' => $statusSeries['chart'],
                'orders_timeline' => $timeline,
                'orders_status_timeline' => $this->chartBuilder->buildStateTimelineSeries(
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
                    $now,
                    $this->periodResolver,
                    $tz
                ),
                'top_exams' => $topExams,
                'orders_by_doctor' => $doctorSeries,
            ],
            'lists' => [
                'recent_orders' => $this->buildLaboratoryRecentOrders($labUserId, 6),
            ],
        ];
    }

    public function countLabOrdersCreatedToday(Carbon $start, Carbon $end): int
    {
        return ($this->chartBuilder->tableExists((new PedidoLaboratorio)->getTable())
                ? (int) PedidoLaboratorio::query()->whereBetween('created_at', [$start, $end])->count()
                : 0)
            + ($this->chartBuilder->tableExists((new LaboratorioOrden)->getTable())
                ? (int) LaboratorioOrden::query()->whereBetween('created_at', [$start, $end])->count()
                : 0)
            + ($this->chartBuilder->tableExists((new LabOrder)->getTable())
                ? (int) LabOrder::query()->whereBetween('created_at', [$start, $end])->count()
                : 0);
    }

    public function buildLabStateSeries(?Carbon $start, ?Carbon $end): array
    {
        $counts = [
            'pending' => 0,
            'in_process' => 0,
            'completed' => 0,
            'delivered' => 0,
            'cancelled' => 0,
        ];

        if ($this->chartBuilder->tableExists((new PedidoLaboratorio)->getTable())) {
            $q = PedidoLaboratorio::query();
            if ($start !== null && $end !== null) {
                $q->whereBetween('pedidos_laboratorio.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            }
            $pCounts = $q->selectRaw('estado, COUNT(*) as total, SUM(CASE WHEN resultado_enviado_at IS NOT NULL THEN 1 ELSE 0 END) as delivered')
                ->groupBy('estado')
                ->get();
            foreach ($pCounts as $row) {
                if ($row->estado === 'pendiente_toma') {
                    $counts['pending'] += (int) $row->total;
                } elseif ($row->estado === 'resultado_listo') {
                    $counts['completed'] += (int) $row->total;
                } elseif ($row->estado === 'cancelado') {
                    $counts['cancelled'] += (int) $row->total;
                }
                $counts['delivered'] += (int) ($row->delivered ?? 0);
            }
        }

        if ($this->chartBuilder->tableExists((new LaboratorioOrden)->getTable())) {
            $q = LaboratorioOrden::query();
            if ($start !== null && $end !== null) {
                $q->whereBetween('laboratorio_ordenes.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            }
            $lCounts = $q->selectRaw('estado, COUNT(*) as total, SUM(CASE WHEN resultado_enviado_at IS NOT NULL THEN 1 ELSE 0 END) as delivered')
                ->groupBy('estado')
                ->get();
            foreach ($lCounts as $row) {
                if (in_array($row->estado, [LaboratorioOrden::ESTADO_ORDEN_CREADA, LaboratorioOrden::ESTADO_CITA_PROGRAMADA], true)) {
                    $counts['pending'] += (int) $row->total;
                } elseif ($row->estado === LaboratorioOrden::ESTADO_MUESTRA_TOMADA) {
                    $counts['in_process'] += (int) $row->total;
                } elseif ($row->estado === LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE) {
                    $counts['completed'] += (int) $row->total;
                }
                $counts['delivered'] += (int) ($row->delivered ?? 0);
            }
        }

        if ($this->chartBuilder->tableExists((new LabOrder)->getTable())) {
            $q = LabOrder::query();
            if ($start !== null && $end !== null) {
                $q->whereBetween('lab_orders.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            }
            $loCounts = $q->selectRaw('status, COUNT(*) as total, SUM(CASE WHEN resultado_enviado_at IS NOT NULL THEN 1 ELSE 0 END) as delivered')
                ->groupBy('status')
                ->get();
            foreach ($loCounts as $row) {
                if ($row->status === LabOrder::STATUS_PENDIENTE_TOMA) {
                    $counts['pending'] += (int) $row->total;
                } elseif (in_array($row->status, [LabOrder::STATUS_MUESTRA_TOMADA, LabOrder::STATUS_EN_ANALISIS], true)) {
                    $counts['in_process'] += (int) $row->total;
                } elseif ($row->status === LabOrder::STATUS_RESULTADO_LISTO) {
                    $counts['completed'] += (int) $row->total;
                } elseif ($row->status === LabOrder::STATUS_CANCELADO) {
                    $counts['cancelled'] += (int) $row->total;
                }
                $counts['delivered'] += (int) ($row->delivered ?? 0);
            }
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

    public function buildLabTimelineSeries(Carbon $start, ?Carbon $end, string $grouping, Carbon $now, ?string $timezone = null): array
    {
        $tz = $timezone ?: config('app.timezone', 'America/Guayaquil');
        $minCreatedAt = null;
        $maxCreatedAt = null;
        if ($this->chartBuilder->tableExists((new PedidoLaboratorio)->getTable())) {
            $rangeP = PedidoLaboratorio::selectRaw('MIN(created_at) as min_val, MAX(created_at) as max_val')->first();
            if ($rangeP?->min_val) {
                $minCreatedAt = $minCreatedAt ? $this->periodResolver->earlierDate($minCreatedAt, Carbon::parse($rangeP->min_val)) : Carbon::parse($rangeP->min_val);
            }
            if ($rangeP?->max_val) {
                $maxCreatedAt = $maxCreatedAt ? $this->periodResolver->laterDate($maxCreatedAt, Carbon::parse($rangeP->max_val)) : Carbon::parse($rangeP->max_val);
            }
        }
        if ($this->chartBuilder->tableExists((new LaboratorioOrden)->getTable())) {
            $rangeL = LaboratorioOrden::selectRaw('MIN(created_at) as min_val, MAX(created_at) as max_val')->first();
            if ($rangeL?->min_val) {
                $minCreatedAt = $minCreatedAt ? $this->periodResolver->earlierDate($minCreatedAt, Carbon::parse($rangeL->min_val)) : Carbon::parse($rangeL->min_val);
            }
            if ($rangeL?->max_val) {
                $maxCreatedAt = $maxCreatedAt ? $this->periodResolver->laterDate($maxCreatedAt, Carbon::parse($rangeL->max_val)) : Carbon::parse($rangeL->max_val);
            }
        }
        if ($this->chartBuilder->tableExists((new LabOrder)->getTable())) {
            $rangeO = LabOrder::selectRaw('MIN(created_at) as min_val, MAX(created_at) as max_val')->first();
            if ($rangeO?->min_val) {
                $minCreatedAt = $minCreatedAt ? $this->periodResolver->earlierDate($minCreatedAt, Carbon::parse($rangeO->min_val)) : Carbon::parse($rangeO->min_val);
            }
            if ($rangeO?->max_val) {
                $maxCreatedAt = $maxCreatedAt ? $this->periodResolver->laterDate($maxCreatedAt, Carbon::parse($rangeO->max_val)) : Carbon::parse($rangeO->max_val);
            }
        }

        $t_start = $this->periodResolver->getVisualStartDate($start, $minCreatedAt ? $minCreatedAt->toDateString() : null, $tz);
        $t_end = $this->periodResolver->getVisualEndDate($end, $maxCreatedAt ? $maxCreatedAt->toDateString() : null, $now, $tz);

        $daily = [];

        if ($this->chartBuilder->tableExists((new PedidoLaboratorio)->getTable())) {
            $daily = $this->chartBuilder->mergeDailyCounts($daily, PedidoLaboratorio::query()
                ->whereBetween('pedidos_laboratorio.created_at', [$t_start->copy()->startOfDay(), $t_end->copy()->endOfDay()])
                ->selectRaw('DATE(pedidos_laboratorio.created_at) as bucket, COUNT(*) as total')
                ->groupBy('bucket')
                ->pluck('total', 'bucket')
                ->map(fn ($value) => (int) $value)
                ->all());
        }

        if ($this->chartBuilder->tableExists((new LaboratorioOrden)->getTable())) {
            $daily = $this->chartBuilder->mergeDailyCounts($daily, LaboratorioOrden::query()
                ->whereBetween('laboratorio_ordenes.created_at', [$t_start->copy()->startOfDay(), $t_end->copy()->endOfDay()])
                ->selectRaw('DATE(laboratorio_ordenes.created_at) as bucket, COUNT(*) as total')
                ->groupBy('bucket')
                ->pluck('total', 'bucket')
                ->map(fn ($value) => (int) $value)
                ->all());
        }

        if ($this->chartBuilder->tableExists((new LabOrder)->getTable())) {
            $daily = $this->chartBuilder->mergeDailyCounts($daily, LabOrder::query()
                ->whereBetween('lab_orders.created_at', [$t_start->copy()->startOfDay(), $t_end->copy()->endOfDay()])
                ->selectRaw('DATE(lab_orders.created_at) as bucket, COUNT(*) as total')
                ->groupBy('bucket')
                ->pluck('total', 'bucket')
                ->map(fn ($value) => (int) $value)
                ->all());
        }

        [$labels, $values] = $this->chartBuilder->aggregateTimeline($daily, $t_start, $t_end, $grouping);

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

    public function buildLabExamSeries(?Carbon $start, ?Carbon $end): array
    {
        $counts = [];

        if ($this->chartBuilder->tableExists((new PedidoLaboratorio)->getTable())) {
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

        if ($this->chartBuilder->tableExists((new LaboratorioOrden)->getTable())) {
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

        if ($this->chartBuilder->tableExists((new LabOrder)->getTable())) {
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

    public function buildLabDoctorSeries(?Carbon $start, ?Carbon $end): array
    {
        $counts = [];

        if ($this->chartBuilder->tableExists((new PedidoLaboratorio)->getTable())) {
            $q = PedidoLaboratorio::query()->with('doctor:id,name');
            if ($start !== null && $end !== null) {
                $q->whereBetween('pedidos_laboratorio.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            }
            $counts = $this->chartBuilder->mergeNamedCounts(
                $counts,
                $q->select('doctor_id', DB::raw('COUNT(*) as total'))
                    ->groupBy('doctor_id')
                    ->get()
                    ->mapWithKeys(fn ($row) => [$row->doctor?->name ?? 'Sin doctor' => (int) $row->total])
                    ->all()
            );
        }

        if ($this->chartBuilder->tableExists((new LaboratorioOrden)->getTable())) {
            $q = LaboratorioOrden::query()->with('cita.doctor:id,name');
            if ($start !== null && $end !== null) {
                $q->whereBetween('laboratorio_ordenes.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            }
            $legacy = $q->select('cita_id', DB::raw('COUNT(*) as total'))
                ->groupBy('cita_id')
                ->get()
                ->mapWithKeys(fn ($row) => [optional($row->cita)->doctor?->name ?? 'Sin doctor' => (int) $row->total])
                ->all();

            $counts = $this->chartBuilder->mergeNamedCounts($counts, $legacy);
        }

        if ($this->chartBuilder->tableExists((new LabOrder)->getTable())) {
            $q = LabOrder::query()->with('doctor:id,name');
            if ($start !== null && $end !== null) {
                $q->whereBetween('lab_orders.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            }
            $self = $q->select('doctor_id', DB::raw('COUNT(*) as total'))
                ->groupBy('doctor_id')
                ->get()
                ->mapWithKeys(fn ($row) => [$row->doctor?->name ?? 'Sin doctor' => (int) $row->total])
                ->all();

            $counts = $this->chartBuilder->mergeNamedCounts($counts, $self);
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

    public function buildLaboratoryRecentOrders(int $labUserId, int $limit = 6): array
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
                'tone' => $this->chartBuilder->toneForLabOrderState($order->status),
                'url' => route('laboratorio.ordenes.index').'#lab-order-'.$order->id,
            ])
            ->all();
    }

    public function laboratoryOrdersQuery()
    {
        return LabOrder::query();
    }
}
