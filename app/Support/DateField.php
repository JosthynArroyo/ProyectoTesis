<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Http\Request;

class DateField
{
    public static function parts(null|string|DateTimeInterface|CarbonInterface $value): array
    {
        if ($value instanceof DateTimeInterface) {
            return [
                'day' => $value->format('d'),
                'month' => $value->format('m'),
                'year' => $value->format('Y'),
            ];
        }

        $stringValue = trim((string) $value);

        if (preg_match('/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})$/', $stringValue, $matches) === 1) {
            return [
                'day' => $matches['day'],
                'month' => $matches['month'],
                'year' => $matches['year'],
            ];
        }

        return [
            'day' => '',
            'month' => '',
            'year' => '',
        ];
    }

    public static function mergeIntoRequest(Request $request, string $field): void
    {
        if ($request->filled($field)) {
            return;
        }

        $day = trim((string) $request->input($field.'_day', ''));
        $month = trim((string) $request->input($field.'_month', ''));
        $year = trim((string) $request->input($field.'_year', ''));

        if ($day === '' && $month === '' && $year === '') {
            return;
        }

        if (! ctype_digit($day.$month.$year)) {
            $request->merge([$field => 'invalid-date']);

            return;
        }

        $request->merge([
            $field => sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day),
        ]);
    }
}
