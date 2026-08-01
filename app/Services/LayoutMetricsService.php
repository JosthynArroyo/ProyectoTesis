<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\FeatureAccessRequest;
use App\Models\Pago;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class LayoutMetricsService
{
    private const ADMIN_NOTIFICATIONS_CACHE_SECONDS = 180;

    private const ADMIN_RECORDATORIOS_CACHE_SECONDS = 180;

    private const FEATURE_STATUS_CACHE_SECONDS = 300;

    private const SUPERADMIN_PENDING_CACHE_SECONDS = 180;

    private const PATIENT_PAYMENT_BLOCK_CACHE_SECONDS = 180;

    private array $memo = [];

    public function adminNotifications(?User $user): array
    {
        if (! $this->userHasRole($user, 'administrador')) {
            return [
                'pendingPagos' => 0,
                'conflictosHorarios' => 0,
                'notificacionesTotal' => 0,
            ];
        }

        return $this->remember('layout:admin:notifications', self::ADMIN_NOTIFICATIONS_CACHE_SECONDS, function (): array {
            $pendingPagos = Pago::query()
                ->where('estado', Pago::ESTADO_EN_VERIFICACION)
                ->count();

            $conflictQuery = Cita::query()
                ->where('activo', true)
                ->whereIn('estado', [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA])
                ->selectRaw('doctor_id, fecha, hora')
                ->groupBy('doctor_id', 'fecha', 'hora')
                ->havingRaw('COUNT(*) > 1');

            $conflictosHorarios = DB::query()
                ->fromSub($conflictQuery, 'conflictos_horarios')
                ->count();

            return [
                'pendingPagos' => (int) $pendingPagos,
                'conflictosHorarios' => (int) $conflictosHorarios,
                'notificacionesTotal' => (int) $pendingPagos + (int) $conflictosHorarios,
            ];
        });
    }

    public function adminSidebar(?User $user): array
    {
        if (! $this->userHasRole($user, 'administrador')) {
            return [
                'personalizacion' => $this->emptyFeatureStatus(),
                'recordatoriosPendientes' => 0,
            ];
        }

        return [
            'personalizacion' => $this->featureStatus($user, 'personalizacion'),
            'recordatoriosPendientes' => $this->adminRecordatoriosPendientes(),
        ];
    }

    public function forgetFeatureStatus(int $userId, string $feature): void
    {
        if ($userId < 1 || trim($feature) === '') {
            return;
        }

        Cache::forget($this->featureStatusCacheKey($userId, $feature));
        unset($this->memo[$this->featureStatusCacheKey($userId, $feature)]);
    }

    public function forgetPendingFeatureRequests(string $feature): void
    {
        $feature = trim($feature);
        if ($feature === '') {
            return;
        }

        Cache::forget($this->pendingFeatureRequestsCacheKey($feature));
        unset($this->memo[$this->pendingFeatureRequestsCacheKey($feature)]);
    }

    public function featureStatus(?User $user, string $feature): array
    {
        if (! $user) {
            return $this->emptyFeatureStatus();
        }

        if ($this->userHasRole($user, 'superadmin')) {
            return [
                'can_access' => true,
                'pending' => false,
                'expires_at' => null,
            ];
        }

        return $this->remember($this->featureStatusCacheKey($user->id, $feature), self::FEATURE_STATUS_CACHE_SECONDS, function () use ($user, $feature): array {
            $pending = FeatureAccessRequest::query()
                ->where('user_id', $user->id)
                ->forFeature($feature)
                ->pending()
                ->exists();

            $approved = FeatureAccessRequest::query()
                ->where('user_id', $user->id)
                ->forFeature($feature)
                ->approvedActive()
                ->latest('reviewed_at')
                ->first(['approved_until']);

            $canAccess = $approved !== null;

            return [
                'can_access' => $canAccess,
                'pending' => $pending,
                'expires_at' => $approved?->approved_until,
            ];
        });
    }

    public function pendingPersonalizacion(?User $user): int
    {
        if (! $this->userHasRole($user, 'superadmin')) {
            return 0;
        }

        return $this->remember($this->pendingFeatureRequestsCacheKey('personalizacion'), self::SUPERADMIN_PENDING_CACHE_SECONDS, fn (): int => (int) FeatureAccessRequest::query()
            ->forFeature('personalizacion')
            ->pending()
            ->count());
    }

    public function patientHasPaymentBlock(?User $user): bool
    {
        if (! $this->userHasRole($user, 'paciente')) {
            return false;
        }

        if ($user && ($cached = $this->requestCachedPatientPaymentBlock($user->id)) !== null) {
            return $cached;
        }

        return $this->remember(
            "layout:patient:{$user->id}:payment-block",
            self::PATIENT_PAYMENT_BLOCK_CACHE_SECONDS,
            fn (): bool => app(PagoService::class)->pacienteTieneBloqueo((int) $user->id)
        );
    }

    public function cachePatientPaymentBlockForRequest(int $userId, bool $value): void
    {
        if ($userId < 1 || ! app()->bound('request')) {
            return;
        }

        app('request')->attributes->set($this->patientPaymentBlockRequestKey($userId), $value);
    }

    public function forgetPatientPaymentBlock(int $userId): void
    {
        if ($userId < 1) {
            return;
        }

        Cache::forget($this->patientPaymentBlockCacheKey($userId));

        if (app()->bound('request')) {
            app('request')->attributes->remove($this->patientPaymentBlockRequestKey($userId));
        }
    }

    private function adminRecordatoriosPendientes(): int
    {
        return $this->remember(
            'layout:admin:recordatorios:pendientes',
            self::ADMIN_RECORDATORIOS_CACHE_SECONDS,
            fn (): int => app(CitaRecordatorioService::class)->countPendingDue()
        );
    }

    private function userHasRole(?User $user, string $role): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole($role);
    }

    private function emptyFeatureStatus(): array
    {
        return [
            'can_access' => false,
            'pending' => false,
            'expires_at' => null,
        ];
    }

    private function remember(string $key, int $seconds, Closure $callback): mixed
    {
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        try {
            return $this->memo[$key] = Cache::remember($key, now()->addSeconds($seconds), $callback);
        } catch (Throwable) {
            return $this->memo[$key] = $callback();
        }
    }

    private function featureStatusCacheKey(int $userId, string $feature): string
    {
        return "layout:feature-status:{$feature}:user:{$userId}";
    }

    private function patientPaymentBlockRequestKey(int $userId): string
    {
        return "layout:patient:{$userId}:payment-block";
    }

    private function patientPaymentBlockCacheKey(int $userId): string
    {
        return "layout:patient:{$userId}:payment-block";
    }

    private function requestCachedPatientPaymentBlock(int $userId): ?bool
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');
        $key = $this->patientPaymentBlockRequestKey($userId);

        return $request->attributes->has($key)
            ? (bool) $request->attributes->get($key)
            : null;
    }

    private function pendingFeatureRequestsCacheKey(string $feature): string
    {
        return "layout:superadmin:{$feature}:pending";
    }
}
