<?php

namespace App\Services\Analytics;

use App\Models\Cita;
use App\Models\User;
use Carbon\Carbon;

class DashboardPeriodResolver
{
    public function resolveRange(array $filters, string $defaultPeriod = 'month', ?Carbon $now = null, ?string $timezone = null): array
    {
        $tz = $timezone ?: config('app.timezone', 'America/Guayaquil');
        if (! $now) {
            $now = Carbon::now($tz);
        }

        $period = strtolower(trim((string) ($filters['period'] ?? $defaultPeriod)));
        $allowed = ['all', '7d', '30d', 'month', 'year'];
        if (! in_array($period, $allowed, true)) {
            $period = 'month';
        }

        $todayEnd = $now->copy()->endOfDay();
        $systemStart = $this->getSystemStartDate($now, $tz);

        return match ($period) {
            'all' => $this->buildAllRange($systemStart, $now, $tz),
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

    public function getSystemStartDate(Carbon $now, string $timezone): Carbon
    {
        $firstUser = User::query()
            ->orderBy('created_at')
            ->orderBy('id')
            ->first(['created_at']);

        return $firstUser ? $firstUser->created_at->copy()->tz($timezone)->startOfDay() : $now->copy()->startOfDay();
    }

    public function laterDate(Carbon $first, Carbon $second): Carbon
    {
        return $first->greaterThan($second) ? $first->copy() : $second->copy();
    }

    public function earlierDate(Carbon $first, Carbon $second): Carbon
    {
        return $first->lessThan($second) ? $first->copy() : $second->copy();
    }

    public function getVisualStartDate(Carbon $systemStart, ?string $minRecordDate, string $timezone): Carbon
    {
        if (! $minRecordDate) {
            return $systemStart->copy();
        }
        $recordCarbon = Carbon::parse($minRecordDate, $timezone)->startOfDay();

        return $this->earlierDate($systemStart, $recordCarbon);
    }

    public function getVisualEndDate(?Carbon $rangeEnd, ?string $maxRecordDate, Carbon $now, string $timezone): Carbon
    {
        if ($rangeEnd !== null) {
            return $rangeEnd->copy()->endOfDay();
        }
        if (! $maxRecordDate) {
            return $now->copy()->endOfDay();
        }
        $recordCarbon = Carbon::parse($maxRecordDate, $timezone)->endOfDay();

        return $this->laterDate($now, $recordCarbon);
    }

    public function buildAllRange(Carbon $systemStart, Carbon $now, string $timezone): array
    {
        $maxCitaFecha = Cita::max('fecha');
        $maxCitaCarbon = $maxCitaFecha ? Carbon::parse($maxCitaFecha, $timezone)->endOfDay() : null;
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

    public function buildResolvedRange(string $period, Carbon $start, Carbon $end, string $grouping, Carbon $now): array
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

    public function periodLabel(string $period): string
    {
        return $this->periodOptions()[$period] ?? 'Este mes';
    }

    public function rangeQueryParams(?array $range): array
    {
        if (! $range) {
            return [];
        }

        return [
            'period' => $range['period'] ?? 'month',
        ];
    }

    public function buildCitaStateLinks(string $routeName, array $range): array
    {
        return [
            route($routeName, array_merge($this->rangeQueryParams($range), ['estado' => Cita::ESTADO_PENDIENTE])),
            route($routeName, array_merge($this->rangeQueryParams($range), ['estado' => Cita::ESTADO_CONFIRMADA])),
            route($routeName, array_merge($this->rangeQueryParams($range), ['estado' => Cita::ESTADO_CANCELADA])),
            route($routeName, array_merge($this->rangeQueryParams($range), ['estado' => Cita::ESTADO_REALIZADA])),
            route($routeName, array_merge($this->rangeQueryParams($range), ['estado' => Cita::ESTADO_NO_SE_PRESENTO])),
        ];
    }
}
