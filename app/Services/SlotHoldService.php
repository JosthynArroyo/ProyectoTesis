<?php

namespace App\Services;

use App\Models\AppointmentSlotHold;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SlotHoldService
{
    public const DEFAULT_TTL_MINUTES = 10;

    public function __construct(
        protected ProfessionalScheduleService $scheduleService
    ) {}

    public function issueToken(): string
    {
        return (string) Str::uuid();
    }

    public function acquire(
        int $professionalId,
        string $date,
        string $time,
        ?string $token = null,
        ?int $patientId = null,
        ?string $sessionId = null,
        array $messages = [],
        string $timezone = 'America/Guayaquil'
    ): array {
        $resolvedToken = filled($token) ? trim((string) $token) : $this->issueToken();
        $normalizedDate = Carbon::parse($date, $timezone)->toDateString();
        $slot = $this->scheduleService->parseFlexibleTime($time);
        $normalizedTime = $slot->format('H:i:00');
        $expiresAt = now($timezone)->copy()->addMinutes(self::DEFAULT_TTL_MINUTES);

        try {
            return Cache::lock($this->lockKey($professionalId, $normalizedDate, $normalizedTime), 5)
                ->block(5, function () use (
                    $professionalId,
                    $normalizedDate,
                    $slot,
                    $normalizedTime,
                    $resolvedToken,
                    $patientId,
                    $sessionId,
                    $messages,
                    $timezone,
                    $expiresAt
                ) {
                    return DB::transaction(function () use (
                        $professionalId,
                        $normalizedDate,
                        $slot,
                        $normalizedTime,
                        $resolvedToken,
                        $patientId,
                        $sessionId,
                        $messages,
                        $timezone,
                        $expiresAt
                    ) {
                        $this->expireStaleHolds();

                        $validationError = $this->scheduleService->validateBookingSlot(
                            professionalId: $professionalId,
                            date: $normalizedDate,
                            slot: $slot,
                            messages: $messages,
                            exceptHoldToken: $resolvedToken,
                            timezone: $timezone
                        );

                        if ($validationError) {
                            return [
                                'ok' => false,
                                'status' => 422,
                                'field' => $validationError['field'],
                                'message' => $validationError['message'],
                                'token' => $resolvedToken,
                            ];
                        }

                        $hold = AppointmentSlotHold::query()->firstOrNew([
                            'token' => $resolvedToken,
                        ]);

                        $hold->fill([
                            'doctor_id' => $professionalId,
                            'paciente_id' => $patientId,
                            'fecha' => $normalizedDate,
                            'hora' => $normalizedTime,
                            'session_id' => $sessionId,
                            'status' => AppointmentSlotHold::STATUS_ACTIVE,
                            'expires_at' => $expiresAt,
                        ]);
                        $hold->save();

                        return [
                            'ok' => true,
                            'status' => 200,
                            'token' => $resolvedToken,
                            'hold' => $hold->refresh(),
                        ];
                    });
                });
        } catch (LockTimeoutException) {
            return [
                'ok' => false,
                'status' => 423,
                'field' => $messages['conflict_field'] ?? 'hora',
                'message' => $messages['conflict'] ?? 'El horario seleccionado ya no esta disponible.',
                'token' => $resolvedToken,
            ];
        }
    }

    public function completeByToken(?string $token, ?int $professionalId = null, ?string $date = null, ?string $time = null): void
    {
        $this->updateStatusByToken(
            token: $token,
            status: AppointmentSlotHold::STATUS_COMPLETED,
            professionalId: $professionalId,
            date: $date,
            time: $time
        );
    }

    public function releaseByToken(?string $token, ?int $professionalId = null, ?string $date = null, ?string $time = null): void
    {
        $this->updateStatusByToken(
            token: $token,
            status: AppointmentSlotHold::STATUS_RELEASED,
            professionalId: $professionalId,
            date: $date,
            time: $time
        );
    }

    public function expireStaleHolds(): int
    {
        return AppointmentSlotHold::query()
            ->where('status', AppointmentSlotHold::STATUS_ACTIVE)
            ->where('expires_at', '<=', now())
            ->update([
                'status' => AppointmentSlotHold::STATUS_EXPIRED,
                'updated_at' => now(),
            ]);
    }

    protected function updateStatusByToken(
        ?string $token,
        string $status,
        ?int $professionalId = null,
        ?string $date = null,
        ?string $time = null
    ): void {
        if (! filled($token)) {
            return;
        }

        $query = AppointmentSlotHold::query()
            ->where('token', trim((string) $token))
            ->where('status', AppointmentSlotHold::STATUS_ACTIVE);

        if ($professionalId) {
            $query->where('doctor_id', $professionalId);
        }

        if ($date) {
            $query->whereDate('fecha', $date);
        }

        if ($time) {
            $query->where('hora', $this->scheduleService->parseFlexibleTime($time)->format('H:i:00'));
        }

        $query->update([
            'status' => $status,
            'expires_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function lockKey(int $professionalId, string $date, string $time): string
    {
        return sprintf('slot-hold:%d:%s:%s', $professionalId, $date, substr($time, 0, 5));
    }
}
