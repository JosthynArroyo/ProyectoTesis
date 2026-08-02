<?php

namespace App\Services;

use App\Models\AppointmentSlotHold;
use App\Models\Cita;
use App\Models\Horario;
use Carbon\Carbon;

class ProfessionalScheduleService
{
    private SiteSettingsService $siteSettings;

    public function __construct(SiteSettingsService $siteSettings)
    {
        $this->siteSettings = $siteSettings;
    }

    public function parseFlexibleTime(string $time): Carbon
    {
        return strlen($time) >= 8
            ? Carbon::createFromFormat('H:i:s', $time)
            : Carbon::createFromFormat('H:i', $time);
    }

    public function findScheduleForSlot(int $professionalId, string $date, Carbon $slot): ?Horario
    {
        return Horario::query()
            ->where('doctor_id', $professionalId)
            ->whereDate('fecha', $date)
            ->whereTime('hora_inicio', '<=', $slot->format('H:i:s'))
            ->whereTime('hora_fin', '>', $slot->format('H:i:s'))
            ->orderBy('hora_inicio')
            ->first();
    }

    public function intervalMinutes(Horario $horario): int
    {
        return max(1, (int) ($horario->intervalo_minutos ?: 30));
    }

    public function slotAlignedWithSchedule(Horario $horario, string $date, Carbon $slot, string $timezone = 'America/Guayaquil'): bool
    {
        $inicio = Carbon::parse($date.' '.substr((string) $horario->hora_inicio, 0, 5), $timezone);
        $seleccionado = Carbon::parse($date.' '.$slot->format('H:i'), $timezone);

        return $inicio->diffInMinutes($seleccionado) % $this->intervalMinutes($horario) === 0;
    }

    public function hasConflict(
        int $professionalId,
        string $date,
        Carbon $slot,
        int $interval,
        ?int $exceptCitaId = null,
        ?string $exceptHoldToken = null
    ): bool
    {
        $inicioNuevoStr = $slot->format('H:i:00');
        $finNuevoStr = $slot->copy()->addMinutes($interval)->format('H:i:00');

        $activeStates = [
            Cita::ESTADO_PENDIENTE,
            Cita::ESTADO_CONFIRMADA,
            Cita::ESTADO_REALIZADA,
        ];

        $citas = Cita::query()
            ->where('doctor_id', $professionalId)
            ->whereDate('fecha', $date)
            ->where('activo', true)
            ->whereIn('estado', $activeStates)
            ->when($exceptCitaId, fn ($q) => $q->where('id', '!=', $exceptCitaId))
            ->get(['hora']);

        foreach ($citas as $row) {
            $inicioExistente = $this->parseFlexibleTime((string) $row->hora);
            $finExistente = $inicioExistente->copy()->addMinutes($interval);

            $inicioExistenteStr = $inicioExistente->format('H:i:00');
            $finExistenteStr = $finExistente->format('H:i:00');

            if ($inicioNuevoStr < $finExistenteStr && $finNuevoStr > $inicioExistenteStr) {
                return true;
            }
        }

        $holdQuery = AppointmentSlotHold::query()
            ->active()
            ->where('doctor_id', $professionalId)
            ->whereDate('fecha', $date);

        if (filled($exceptHoldToken)) {
            $holdQuery->where('token', '!=', trim((string) $exceptHoldToken));
        }

        foreach ($holdQuery->get(['hora']) as $row) {
            $inicioExistente = $this->parseFlexibleTime((string) $row->hora);
            $finExistente = $inicioExistente->copy()->addMinutes($interval);

            $inicioExistenteStr = $inicioExistente->format('H:i:00');
            $finExistenteStr = $finExistente->format('H:i:00');

            if ($inicioNuevoStr < $finExistenteStr && $finNuevoStr > $inicioExistenteStr) {
                return true;
            }
        }

        return false;
    }

    public function validateBookingSlot(
        int $professionalId,
        string $date,
        Carbon $slot,
        array $messages = [],
        ?int $exceptCitaId = null,
        ?string $exceptHoldToken = null,
        string $timezone = 'America/Guayaquil'
    ): ?array {
        $day = Carbon::parse($date)->isoWeekday();
        $clinicHours = $this->getClinicHours($day);
        if ($clinicHours['status'] === 0) {
            return [
                'field' => $messages['missing_schedule_field'] ?? 'hora',
                'message' => $messages['missing_schedule'] ?? 'La clínica está cerrada este día.',
            ];
        }

        $slotStr = $slot->format('H:i');

        $schedule = $this->findScheduleForSlot($professionalId, $date, $slot);
        if (! $schedule) {
            return [
                'field' => $messages['missing_schedule_field'] ?? 'hora',
                'message' => $messages['missing_schedule'] ?? 'No hay horario configurado para ese profesional en ese dia y hora.',
            ];
        }

        // Intersect schedule and clinic hours to find effective boundary
        $effectiveStart = max(substr($schedule->hora_inicio, 0, 5), $clinicHours['opening']);
        $effectiveEnd = min(substr($schedule->hora_fin, 0, 5), $clinicHours['closing']);

        if ($slotStr < $effectiveStart || $slotStr >= $effectiveEnd) {
            return [
                'field' => $messages['missing_schedule_field'] ?? 'hora',
                'message' => $messages['missing_schedule'] ?? 'La hora seleccionada está fuera del horario permitido.',
            ];
        }

        $interval = $this->intervalMinutes($schedule);
        // Slot complete duration must fit before effectiveEnd
        $slotEnd = $slot->copy()->addMinutes($interval)->format('H:i');
        if ($slotEnd > $effectiveEnd) {
            return [
                'field' => $messages['missing_schedule_field'] ?? 'hora',
                'message' => $messages['missing_schedule'] ?? 'La consulta excede el horario de cierre.',
            ];
        }

        if (! $this->slotAlignedWithSchedule($schedule, $date, $slot, $timezone)) {
            return [
                'field' => $messages['misaligned_field'] ?? 'hora',
                'message' => $messages['misaligned'] ?? 'La hora seleccionada no coincide con un bloque disponible del horario configurado.',
            ];
        }

        $dateTime = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$slot->format('H:i'), $timezone);
        if ($dateTime->lt(now($timezone)->copy()->addHour())) {
            return [
                'field' => $messages['lead_time_field'] ?? 'error',
                'message' => $messages['lead_time'] ?? 'Debes agendar con al menos 1 hora de anticipacion.',
            ];
        }

        if ($this->hasConflict($professionalId, $date, $slot, $interval, $exceptCitaId, $exceptHoldToken)) {
            return [
                'field' => $messages['conflict_field'] ?? 'error',
                'message' => $messages['conflict'] ?? 'El profesional ya tiene una cita en ese horario o en un bloque inmediato del horario configurado.',
            ];
        }

        return null;
    }

    public function buildSlotsForDate(
        int $professionalId,
        string $date,
        string $timezone = 'America/Guayaquil',
        ?string $exceptHoldToken = null
    ): array
    {
        $ahora = Carbon::now($timezone);
        $limiteHora = $ahora->copy()->addHour();
        $normalizedDate = Carbon::parse($date, $timezone)->toDateString();
        $esHoy = $normalizedDate === $ahora->toDateString();

        $day = Carbon::parse($normalizedDate)->isoWeekday();
        $clinicHours = $this->getClinicHours($day);
        if ($clinicHours['status'] === 0) {
            return [];
        }

        $horarios = Horario::query()
            ->where('doctor_id', $professionalId)
            ->whereDate('fecha', $normalizedDate)
            ->orderBy('hora_inicio')
            ->get();

        if ($horarios->isEmpty()) {
            return [];
        }

        $ocupadas = Cita::query()
            ->where('doctor_id', $professionalId)
            ->whereDate('fecha', $normalizedDate)
            ->where('activo', true)
            ->whereIn('estado', [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA])
            ->pluck('hora')
            ->map(fn ($time) => substr((string) $time, 0, 5))
            ->flip();

        $holdQuery = AppointmentSlotHold::query()
            ->active()
            ->where('doctor_id', $professionalId)
            ->whereDate('fecha', $normalizedDate);

        if (filled($exceptHoldToken)) {
            $holdQuery->where('token', '!=', trim((string) $exceptHoldToken));
        }

        $reservadas = $holdQuery->pluck('hora')
            ->map(fn ($time) => substr((string) $time, 0, 5))
            ->flip();

        $slots = [];
        foreach ($horarios as $horario) {
            $step = $this->intervalMinutes($horario);
            
            // Intersect schedule and clinic hours to find effective boundary
            $effectiveStart = max(substr($horario->hora_inicio, 0, 5), $clinicHours['opening']);
            $effectiveEnd = min(substr($horario->hora_fin, 0, 5), $clinicHours['closing']);

            if ($effectiveStart >= $effectiveEnd) {
                continue;
            }

            $inicio = Carbon::parse($normalizedDate.' '.$effectiveStart, $timezone);
            $fin = Carbon::parse($normalizedDate.' '.$effectiveEnd, $timezone);

            for ($time = $inicio->copy(); $time->copy()->addMinutes($step)->lte($fin); $time->addMinutes($step)) {
                if ($esHoy && $time->lt($limiteHora)) {
                    continue;
                }

                $hhmm = $time->format('H:i');
                if (! isset($slots[$hhmm])) {
                    $slots[$hhmm] = [
                        'hora' => $hhmm,
                        'estado' => isset($ocupadas[$hhmm]) || isset($reservadas[$hhmm]) ? 'ocupado' : 'libre',
                    ];
                }
            }
        }

        ksort($slots);

        return array_values($slots);
    }

    public function getClinicHours(int $day): array
    {
        $status = $this->siteSettings->get("clinic_hours.{$day}.status", $day === 7 ? '0' : '1');
        $opening = $this->siteSettings->get("clinic_hours.{$day}.opening", '08:00');
        $closing = $this->siteSettings->get("clinic_hours.{$day}.closing", $day === 6 ? '13:00' : '18:00');

        return [
            'status' => (string) $status === '1' || (string) $status === 'true' || $status === true ? 1 : 0,
            'opening' => substr((string) $opening, 0, 5),
            'closing' => substr((string) $closing, 0, 5),
        ];
    }

    public function getFormattedClinicSchedule(?array $customClinicHours = null): array
    {
        $dayNames = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];

        $daysData = [];
        for ($d = 1; $d <= 7; $d++) {
            if ($customClinicHours && isset($customClinicHours[$d])) {
                $status = (int) ($customClinicHours[$d]['status'] ?? 0);
                $opening = substr((string) ($customClinicHours[$d]['opening'] ?? '08:00'), 0, 5);
                $closing = substr((string) ($customClinicHours[$d]['closing'] ?? '18:00'), 0, 5);
                $daysData[$d] = [
                    'status' => $status,
                    'opening' => $opening,
                    'closing' => $closing,
                ];
            } else {
                $daysData[$d] = $this->getClinicHours($d);
            }
        }

        $groups = [];
        foreach ($daysData as $day => $data) {
            if ($data['status'] === 0) {
                $key = 'closed';
            } else {
                $key = $data['opening'] . '–' . $data['closing'];
            }
            $groups[$key][] = $day;
        }

        if (isset($groups['closed']) && count($groups['closed']) === 7) {
            return [
                'summary' => 'Temporalmente cerrado',
                'is_all_closed' => true,
                'lines' => [
                    [
                        'label' => 'Lunes a domingo',
                        'hours' => 'Cerrado',
                        'is_closed' => true,
                    ]
                ],
            ];
        }

        $formatDaysLabel = function (array $days) use ($dayNames): string {
            sort($days);
            $count = count($days);
            if ($count === 0) return '';
            if ($count === 1) return $dayNames[$days[0]];

            $isConsecutive = true;
            for ($i = 1; $i < $count; $i++) {
                if ($days[$i] !== $days[$i - 1] + 1) {
                    $isConsecutive = false;
                    break;
                }
            }

            if ($isConsecutive && $count >= 3) {
                $first = $dayNames[$days[0]];
                $last = mb_strtolower($dayNames[$days[$count - 1]]);
                return "{$first} a {$last}";
            }

            if ($count === 2) {
                $first = $dayNames[$days[0]];
                $second = mb_strtolower($dayNames[$days[1]]);
                return "{$first} y {$second}";
            }

            $names = array_map(fn($d) => $dayNames[$d], $days);
            $last = mb_strtolower(array_pop($names));
            $firstN = array_shift($names);
            $middle = array_map(fn($n) => mb_strtolower($n), $names);
            return "{$firstN}, " . implode(', ', $middle) . " y {$last}";
        };

        $lines = [];
        $closedDays = $groups['closed'] ?? [];
        unset($groups['closed']);

        $sortedKeys = array_keys($groups);
        usort($sortedKeys, function($a, $b) use ($groups) {
            return min($groups[$a]) <=> min($groups[$b]);
        });

        foreach ($sortedKeys as $key) {
            $days = $groups[$key];
            $label = $formatDaysLabel($days);
            $lines[] = [
                'label' => $label,
                'hours' => $key,
                'is_closed' => false,
            ];
        }

        if (!empty($closedDays)) {
            $closedLabel = $formatDaysLabel($closedDays);
            $lines[] = [
                'label' => $closedLabel,
                'hours' => 'Cerrado',
                'is_closed' => true,
            ];
        }

        if (count($sortedKeys) === 1) {
            $openKey = $sortedKeys[0];
            $openDays = $groups[$openKey];
            $openLabel = $formatDaysLabel($openDays);

            if (count($openDays) === 7 || (count($openDays) >= 2 && (max($openDays) - min($openDays) === count($openDays) - 1))) {
                $summary = "{$openLabel}, {$openKey}";
            } else {
                $summary = "{$openLabel}: {$openKey}";
            }
        } else {
            $summaryParts = [];
            foreach ($lines as $line) {
                $summaryParts[] = "{$line['label']}: {$line['hours']}";
            }
            $summary = implode(' | ', $summaryParts);
        }

        return [
            'summary' => $summary,
            'is_all_closed' => false,
            'lines' => $lines,
        ];
    }

    public function checkTimeWithinClinicHours(string $fecha, string $hi, string $hf): ?string
    {
        $day = Carbon::parse($fecha)->isoWeekday();
        $hours = $this->getClinicHours($day);

        if ($hours['status'] === 0) {
            return "La clínica no atiende los " . $this->getDayNameInSpanish($day) . "s.";
        }

        $hiFormatted = substr($hi, 0, 5);
        $hfFormatted = substr($hf, 0, 5);

        if ($hiFormatted < $hours['opening'] || $hfFormatted > $hours['closing']) {
            return "La clínica atiende los " . $this->getDayNameInSpanish($day) . "s de " . $hours['opening'] . " a " . $hours['closing'] . ".";
        }

        return null;
    }

    public function checkOverlapsWithLock(int $doctorId, string $fecha, string $hi, string $hf, ?int $ignoreId = null): bool
    {
        $hiFormatted = substr($hi, 0, 5);
        $hfFormatted = substr($hf, 0, 5);

        return Horario::where('doctor_id', $doctorId)
            ->whereDate('fecha', $fecha)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->lockForUpdate()
            ->get()
            ->contains(function ($h) use ($hiFormatted, $hfFormatted) {
                $existHi = substr($h->hora_inicio, 0, 5);
                $existHf = substr($h->hora_fin, 0, 5);
                return $hiFormatted < $existHf && $hfFormatted > $existHi;
            });
    }

    public function isCitaCovered(Cita $cita, $schedules): bool
    {
        $citaTime = substr((string) $cita->hora, 0, 5);
        $citaCarbon = Carbon::parse($cita->fecha->toDateString() . ' ' . $citaTime);

        foreach ($schedules as $s) {
            $sHi = substr($s->hora_inicio, 0, 5);
            $sHf = substr($s->hora_fin, 0, 5);
            $step = $s->intervalo_minutos ?: 30;

            $citaEnd = $citaCarbon->copy()->addMinutes($step);
            $sFinCarbon = Carbon::parse($cita->fecha->toDateString() . ' ' . $sHf);

            if ($citaTime >= $sHi && $citaEnd->lte($sFinCarbon)) {
                return true;
            }
        }

        return false;
    }

    public function countUncoveredCitas(int $doctorId, string $fecha, $schedulesList): int
    {
        $timezone = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::now($timezone);
        $todayStr = $now->toDateString();
        $timeStr = $now->toTimeString();

        $appointments = Cita::query()
            ->where('doctor_id', $doctorId)
            ->whereDate('fecha', $fecha)
            ->where('activo', 1)
            ->whereIn('estado', [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA])
            ->where(function ($q) use ($todayStr, $timeStr) {
                $q->where('fecha', '>', $todayStr)
                  ->orWhere(function ($qq) use ($todayStr, $timeStr) {
                      $qq->where('fecha', '=', $todayStr)
                        ->where('hora', '>=', $timeStr);
                  });
            })
            ->get();

        $conflictsCount = 0;
        foreach ($appointments as $cita) {
            if (!$this->isCitaCovered($cita, $schedulesList)) {
                $conflictsCount++;
            }
        }

        return $conflictsCount;
    }

    public function detectConflictsForClinicHoursChange(array $newClinicHours): array
    {
        $timezone = config('app.timezone', 'America/Guayaquil');
        $now = Carbon::now($timezone);
        $todayStr = $now->toDateString();
        $timeStr = $now->toTimeString();

        // 1. Existing doctor schedules affected
        $affectedSchedulesCount = 0;
        $affectedDays = [];

        $schedules = Horario::whereDate('fecha', '>=', $todayStr)->get();
        foreach ($schedules as $h) {
            $day = $h->fecha->isoWeekday();
            $hours = $newClinicHours[$day] ?? null;
            if (!$hours) {
                continue;
            }

            $status = (int) ($hours['status'] ?? 0);
            $opening = substr($hours['opening'] ?? '08:00', 0, 5);
            $closing = substr($hours['closing'] ?? '18:00', 0, 5);

            $isInvalid = ($status === 0)
                || (substr($h->hora_inicio, 0, 5) < $opening)
                || (substr($h->hora_fin, 0, 5) > $closing);

            if ($isInvalid) {
                $affectedSchedulesCount++;
                $affectedDays[$h->fecha->toDateString()] = true;
            }
        }

        // 2. Future active appointments affected
        $affectedCitasCount = 0;
        $appointments = Cita::query()
            ->where('activo', 1)
            ->whereIn('estado', [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA])
            ->where(function ($q) use ($todayStr, $timeStr) {
                $q->where('fecha', '>', $todayStr)
                  ->orWhere(function ($qq) use ($todayStr, $timeStr) {
                      $qq->where('fecha', '=', $todayStr)
                        ->where('hora', '>=', $timeStr);
                  });
            })
            ->get();

        foreach ($appointments as $cita) {
            $day = $cita->fecha->isoWeekday();
            $hours = $newClinicHours[$day] ?? null;
            if (!$hours) {
                continue;
            }

            $status = (int) ($hours['status'] ?? 0);

            $isAffected = false;
            if ($status === 0) {
                $isAffected = true;
            } else {
                $opening = substr($hours['opening'] ?? '08:00', 0, 5);
                $closing = substr($hours['closing'] ?? '18:00', 0, 5);
                $citaStart = substr((string) $cita->hora, 0, 5);

                // We need the schedule block covering this appointment to get its interval_minutos
                $coveringHorarios = Horario::where('doctor_id', $cita->doctor_id)
                    ->whereDate('fecha', $cita->fecha)
                    ->get();

                $interval = 30; // default
                foreach ($coveringHorarios as $ch) {
                    if ($citaStart >= substr($ch->hora_inicio, 0, 5) && $citaStart < substr($ch->hora_fin, 0, 5)) {
                        $interval = $ch->intervalo_minutos ?: 30;
                        break;
                    }
                }

                $citaCarbon = Carbon::parse($cita->fecha->toDateString() . ' ' . $citaStart);
                $citaEnd = $citaCarbon->copy()->addMinutes($interval)->format('H:i');

                if ($citaStart < $opening || $citaEnd > $closing) {
                    $isAffected = true;
                }
            }

            if ($isAffected) {
                $affectedCitasCount++;
                $affectedDays[$cita->fecha->toDateString()] = true;
            }
        }

        return [
            'schedules_count' => $affectedSchedulesCount,
            'citas_count' => $affectedCitasCount,
            'days_count' => count($affectedDays),
        ];
    }

    private function getDayNameInSpanish(int $day): string
    {
        return match ($day) {
            1 => 'lunes',
            2 => 'martes',
            3 => 'miércoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sábado',
            7 => 'domingo',
            default => 'desconocido'
        };
    }
}
