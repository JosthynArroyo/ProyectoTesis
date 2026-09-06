<?php

namespace App\Services\Analytics;

use App\Models\Cita;
use App\Models\LaboratorioOrden;
use App\Models\LabOrder;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DashboardChartBuilder
{
    protected static array $tableExistsCache = [];

    public function buildStateSeries($baseQuery, array $states, callable $labelResolver, string $field, ?int $doctorId, ?Carbon $start, ?Carbon $end): array
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

    public function buildTimelineSeries(
        $baseQuery,
        string $column,
        Carbon $start,
        ?Carbon $end,
        string $grouping,
        string $seriesName,
        Carbon $now,
        DashboardPeriodResolver $periodResolver,
        string $timezone
    ): array {
        if ($grouping === 'hour') {
            return $this->buildHourlyTimelineSeries($baseQuery, $column, $start, $seriesName);
        }

        $minVal = (clone $baseQuery)->min($column);
        $maxVal = (clone $baseQuery)->max($column);

        $t_start = $periodResolver->getVisualStartDate($start, $minVal, $timezone);
        $t_end = $periodResolver->getVisualEndDate($end, $maxVal, $now, $timezone);

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

    public function buildHourlyTimelineSeries($baseQuery, string $column, Carbon $start, string $seriesName): array
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

    public function buildStateTimelineSeries(
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
        ?Carbon $now = null,
        ?DashboardPeriodResolver $periodResolver = null,
        ?string $timezone = null
    ): array {
        $tz = $timezone ?: config('app.timezone', 'America/Guayaquil');
        if (! $now) {
            $now = Carbon::now($tz);
        }
        $resolver = $periodResolver ?: new DashboardPeriodResolver;

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

        $t_start = $resolver->getVisualStartDate($start, $minVal, $tz);
        $t_end = $resolver->getVisualEndDate($end, $maxVal, $now, $tz);

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

    public function aggregateTimeline(array $dailyCounts, Carbon $start, Carbon $end, string $grouping): array
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

    public function timelineBucketKey(Carbon $date, string $grouping): string
    {
        return match ($grouping) {
            'hour' => $date->format('Y-m-d H'),
            'month' => $date->format('Y-m'),
            'week' => $date->isoWeekYear.'-W'.str_pad((string) $date->isoWeek(), 2, '0', STR_PAD_LEFT),
            'year' => $date->format('Y'),
            default => $date->toDateString(),
        };
    }

    public function timelineLabel(Carbon $date, string $grouping, bool $crossYear = false): string
    {
        return match ($grouping) {
            'hour' => $date->format('H').':00',
            'month' => ucfirst(str_replace('.', '', $date->locale('es')->translatedFormat('M Y'))),
            'week' => 'Semana '.$date->isoWeek().' / '.$date->isoWeekYear,
            'year' => $date->format('Y'),
            default => $crossYear ? $date->format('d/m/Y') : $date->format('d/m'),
        };
    }

    public function tableExists(string $table): bool
    {
        if (isset(self::$tableExistsCache[$table])) {
            return self::$tableExistsCache[$table];
        }

        self::$tableExistsCache[$table] = Schema::hasTable($table);

        return self::$tableExistsCache[$table];
    }

    public function mergeDailyCounts(array $current, array $incoming): array
    {
        foreach ($incoming as $bucket => $total) {
            $current[$bucket] = ($current[$bucket] ?? 0) + (int) $total;
        }

        return $current;
    }

    public function mergeNamedCounts(array $current, array $incoming): array
    {
        foreach ($incoming as $label => $total) {
            $current[$label] = ($current[$label] ?? 0) + (int) $total;
        }

        return $current;
    }

    public function metric(int $value, ?string $subtitle = null): array
    {
        return [
            'value' => $value,
            'subtitle' => $subtitle,
        ];
    }

    public function labelForRole(string $role): string
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

    public function toneForState(string $state): string
    {
        return match ($state) {
            Cita::ESTADO_CONFIRMADA => 'info',
            Cita::ESTADO_REALIZADA => 'success',
            Cita::ESTADO_CANCELADA, Cita::ESTADO_NO_SE_PRESENTO => 'danger',
            default => 'warning',
        };
    }

    public function toneForLabState(string $state): string
    {
        return match ($state) {
            LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE => 'success',
            LaboratorioOrden::ESTADO_MUESTRA_TOMADA => 'info',
            default => 'warning',
        };
    }

    public function toneForLabOrderState(string $state): string
    {
        return match ($state) {
            LabOrder::STATUS_RESULTADO_LISTO => 'success',
            LabOrder::STATUS_EN_ANALISIS, LabOrder::STATUS_MUESTRA_TOMADA => 'info',
            LabOrder::STATUS_CANCELADO => 'danger',
            default => 'warning',
        };
    }

    public function stateColors(int $count): array
    {
        $base = ['#0f766e', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6'];

        return array_slice($base, 0, max(1, $count));
    }
}
