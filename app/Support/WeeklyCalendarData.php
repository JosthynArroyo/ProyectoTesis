<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class WeeklyCalendarData
{
    public static function resolveWeekStart(?string $seed = null, string $timezone = 'America/Guayaquil'): Carbon
    {
        $reference = $seed
            ? Carbon::parse($seed, $timezone)
            : Carbon::now($timezone);

        return $reference->copy()->startOfWeek(Carbon::MONDAY);
    }

    public static function inferEndTime(
        string $date,
        string $time,
        iterable $schedules,
        int $defaultInterval = 30,
        string $timezone = 'America/Guayaquil'
    ): string {
        $start = Carbon::parse($date.' '.self::normalizeTime($time), $timezone);

        foreach ($schedules as $schedule) {
            $scheduleDate = self::normalizeDate(data_get($schedule, 'fecha'));
            if ($scheduleDate !== $date) {
                continue;
            }

            $scheduleStart = Carbon::parse($date.' '.self::normalizeTime((string) data_get($schedule, 'hora_inicio')), $timezone);
            $scheduleEnd = Carbon::parse($date.' '.self::normalizeTime((string) data_get($schedule, 'hora_fin')), $timezone);

            if ($start->lt($scheduleStart) || $start->gte($scheduleEnd)) {
                continue;
            }

            $interval = max(5, (int) (data_get($schedule, 'intervalo_minutos') ?: $defaultInterval));

            return $start->copy()->addMinutes($interval)->format('H:i');
        }

        return $start->copy()->addMinutes($defaultInterval)->format('H:i');
    }

    public static function build(CarbonInterface $weekStart, iterable $entries, array $options = []): array
    {
        $interval = max(5, (int) ($options['interval'] ?? 30));
        $timezone = (string) ($options['timezone'] ?? 'America/Guayaquil');
        $defaultStart = (int) ($options['default_start_minutes'] ?? (8 * 60));
        $defaultEnd = (int) ($options['default_end_minutes'] ?? (18 * 60));

        $weekStart = Carbon::parse($weekStart, $timezone)->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $todayKey = Carbon::now($timezone)->toDateString();

        $items = Collection::make($entries)
            ->map(function ($entry) use ($timezone) {
                $date = self::normalizeDate(data_get($entry, 'date'));
                $start = self::normalizeTime((string) data_get($entry, 'start', '00:00'));
                $end = self::normalizeTime((string) data_get($entry, 'end', $start));

                $startMinutes = self::toMinutes($start);
                $endMinutes = self::toMinutes($end);
                if ($endMinutes <= $startMinutes) {
                    $endMinutes = $startMinutes + 30;
                    $end = self::fromMinutes($endMinutes);
                }

                return [
                    'layer' => data_get($entry, 'layer', 'foreground') === 'background' ? 'background' : 'foreground',
                    'date' => $date,
                    'start' => $start,
                    'end' => $end,
                    'start_minutes' => $startMinutes,
                    'end_minutes' => $endMinutes,
                    'title' => (string) data_get($entry, 'title', ''),
                    'subtitle' => self::nullableString(data_get($entry, 'subtitle')),
                    'eyebrow' => self::nullableString(data_get($entry, 'eyebrow')),
                    'meta' => self::nullableString(data_get($entry, 'meta')),
                    'tone' => (string) data_get($entry, 'tone', 'slate'),
                    'url' => self::nullableString(data_get($entry, 'url')),
                    'icon' => self::nullableString(data_get($entry, 'icon')),
                    'classes' => self::nullableString(data_get($entry, 'classes')),
                    'lane_key' => self::nullableString(data_get($entry, 'lane_key')) ?? 'default',
                ];
            })
            ->filter(function (array $entry) use ($weekStart, $weekEnd) {
                return $entry['date'] >= $weekStart->toDateString()
                    && $entry['date'] <= $weekEnd->toDateString();
            })
            ->sortBy([
                ['date', 'asc'],
                ['start_minutes', 'asc'],
                ['end_minutes', 'asc'],
            ])
            ->values();

        $startMinutes = $defaultStart;
        $endMinutes = $defaultEnd;

        if ($items->isNotEmpty()) {
            $startMinutes = min($defaultStart, self::roundDownToHour((int) $items->min('start_minutes')));
            $endMinutes = max($defaultEnd, self::roundUpToHour((int) $items->max('end_minutes')));
        }

        if ($endMinutes <= $startMinutes) {
            $endMinutes = $startMinutes + 60;
        }

        $rowCount = (int) ceil(($endMinutes - $startMinutes) / $interval);
        $endMinutes = $startMinutes + ($rowCount * $interval);

        $dayLaneMaps = $items->groupBy('date')->map(function (Collection $entries) {
            return $entries->pluck('lane_key')->unique()->values()->flip();
        });

        $days = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $todayKey, $dayLaneMaps) {
            $date = $weekStart->copy()->addDays($offset);
            $dateStr = $date->toDateString();
            $laneCount = max(1, $dayLaneMaps->get($dateStr, collect())->count());

            return [
                'key' => $dateStr,
                'day_short' => mb_strtoupper($date->locale('es')->isoFormat('ddd'), 'UTF-8'),
                'day_name' => $date->locale('es')->isoFormat('dddd'),
                'day_number' => $date->format('d'),
                'month_short' => mb_strtoupper($date->locale('es')->isoFormat('MMM'), 'UTF-8'),
                'is_today' => $dateStr === $todayKey,
                'lane_count' => $laneCount,
            ];
        })->values();

        $maxLaneCount = max(1, (int) ($days->max('lane_count') ?? 1));
        $dayColumns = $days->pluck('key')->flip();
        $gridRows = collect(range(0, $rowCount - 1))->map(function (int $offset) use ($interval, $startMinutes) {
            $minutes = $startMinutes + ($offset * $interval);
            $label = $minutes % 60 === 0 ? self::fromMinutes($minutes) : null;

            return [
                'index' => $offset,
                'grid_row' => $offset + 2,
                'label' => $label,
                'is_major' => $label !== null,
            ];
        })->values();

        $normalizedItems = $items->map(function (array $entry) use ($dayColumns, $dayLaneMaps, $startMinutes, $endMinutes, $interval) {
            $dayIndex = $dayColumns->get($entry['date']);
            if ($dayIndex === null) {
                return null;
            }

            $laneMap = $dayLaneMaps->get($entry['date'], collect());
            $laneIndex = (int) ($laneMap->get($entry['lane_key']) ?? 0);
            $laneCount = max(1, $laneMap->count());

            $clampedStart = max($startMinutes, $entry['start_minutes']);
            $clampedEnd = min($endMinutes, $entry['end_minutes']);
            if ($clampedEnd <= $clampedStart) {
                $clampedEnd = min($endMinutes, $clampedStart + $interval);
            }

            $rowStart = (int) floor(($clampedStart - $startMinutes) / $interval) + 2;
            $rowSpan = max(1, (int) ceil(($clampedEnd - $clampedStart) / $interval));

            return $entry + [
                'column' => $dayIndex + 2,
                'row_start' => $rowStart,
                'row_span' => $rowSpan,
                'lane_index' => $laneIndex,
                'lane_count' => $laneCount,
            ];
        })->filter()->values();

        return [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'range_label' => $weekStart->translatedFormat('d M').' - '.$weekEnd->translatedFormat('d M, Y'),
            'days' => $days->all(),
            'rows' => $gridRows->all(),
            'row_count' => $rowCount,
            'max_lane_count' => $maxLaneCount,
            'background_events' => $normalizedItems->where('layer', 'background')->values()->all(),
            'events' => $normalizedItems->where('layer', 'foreground')->values()->all(),
            'start_label' => self::fromMinutes($startMinutes),
            'end_label' => self::fromMinutes($endMinutes),
        ];
    }

    private static function normalizeDate(CarbonInterface|string|null $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        return Carbon::parse((string) $value)->toDateString();
    }

    private static function normalizeTime(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            return '00:00';
        }

        return strlen($time) >= 5 ? substr($time, 0, 5) : $time;
    }

    private static function toMinutes(string $time): int
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return ($hour * 60) + $minute;
    }

    private static function fromMinutes(int $minutes): string
    {
        $hours = (int) floor($minutes / 60);
        $mins = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }

    private static function roundDownToHour(int $minutes): int
    {
        return (int) floor($minutes / 60) * 60;
    }

    private static function roundUpToHour(int $minutes): int
    {
        return (int) ceil($minutes / 60) * 60;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
