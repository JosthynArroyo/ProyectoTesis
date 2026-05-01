<?php

namespace App\Services;

use App\Models\AppointmentSlotHold;
use App\Models\Cita;
use App\Models\Horario;
use Carbon\Carbon;

class ProfessionalScheduleService
{
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
        $query = Cita::query()
            ->where('doctor_id', $professionalId)
            ->whereDate('fecha', $date)
            ->where('activo', true);

        if ($exceptCitaId) {
            $query->where('id', '!=', $exceptCitaId);
        }

        if ($query->get(['hora'])->contains(function ($row) use ($slot, $interval) {
            $otro = $this->parseFlexibleTime((string) $row->hora);

            return $otro->diffInMinutes($slot) <= ($interval - 1);
        })) {
            return true;
        }

        $holdQuery = AppointmentSlotHold::query()
            ->active()
            ->where('doctor_id', $professionalId)
            ->whereDate('fecha', $date);

        if (filled($exceptHoldToken)) {
            $holdQuery->where('token', '!=', trim((string) $exceptHoldToken));
        }

        return $holdQuery->get(['hora'])->contains(function ($row) use ($slot, $interval) {
            $otro = $this->parseFlexibleTime((string) $row->hora);

            return $otro->diffInMinutes($slot) <= ($interval - 1);
        });
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
        $schedule = $this->findScheduleForSlot($professionalId, $date, $slot);
        if (! $schedule) {
            return [
                'field' => $messages['missing_schedule_field'] ?? 'hora',
                'message' => $messages['missing_schedule'] ?? 'No hay horario configurado para ese profesional en ese dia y hora.',
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

        $interval = $this->intervalMinutes($schedule);
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
            $inicio = Carbon::parse($normalizedDate.' '.$horario->hora_inicio, $timezone);
            $fin = Carbon::parse($normalizedDate.' '.$horario->hora_fin, $timezone);

            for ($time = $inicio->copy(); $time->lt($fin); $time->addMinutes($step)) {
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
}
