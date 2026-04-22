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
    private const CACHE_SECONDS = 30;

    public function adminNotifications(?User $user): array
    {
        if (! $this->userHasRole($user, 'administrador')) {
            return [
                'pendingPagos' => 0,
                'conflictosHorarios' => 0,
                'notificacionesTotal' => 0,
            ];
        }

        return $this->remember('layout:admin:notifications', function (): array {
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

        return $this->remember("layout:feature-status:{$feature}:user:{$user->id}", function () use ($user, $feature): array {
            $pending = FeatureAccessRequest::query()
                ->where('user_id', $user->id)
                ->forFeature($feature)
                ->pending()
                ->exists();

            $approved = FeatureAccessRequest::query()
                ->where('user_id', $user->id)
                ->forFeature($feature)
                ->where('status', 'approved')
                ->whereNull('revoked_at')
                ->latest('reviewed_at')
                ->first(['approved_until']);

            $canAccess = $approved !== null
                && ($approved->approved_until === null || $approved->approved_until->isFuture());

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

        return $this->remember('layout:superadmin:personalizacion:pending', fn (): int => (int) FeatureAccessRequest::query()
            ->forFeature('personalizacion')
            ->pending()
            ->count());
    }

    public function patientHasPaymentBlock(?User $user): bool
    {
        if (! $this->userHasRole($user, 'paciente')) {
            return false;
        }

        return $this->remember(
            "layout:patient:{$user->id}:payment-block",
            fn (): bool => app(PagoService::class)->pacienteTieneBloqueo((int) $user->id)
        );
    }

    private function adminRecordatoriosPendientes(): int
    {
        return $this->remember('layout:admin:recordatorios:pendientes', fn (): int => app(CitaRecordatorioService::class)->countPendingDue());
    }

    private function userHasRole(?User $user, string $role): bool
    {
        if (! $user) {
            return false;
        }

        $user->loadMissing('roles:id,name');

        return $user->roles->contains('name', $role);
    }

    private function emptyFeatureStatus(): array
    {
        return [
            'can_access' => false,
            'pending' => false,
            'expires_at' => null,
        ];
    }

    private function remember(string $key, Closure $callback): mixed
    {
        try {
            return Cache::remember($key, now()->addSeconds(self::CACHE_SECONDS), $callback);
        } catch (Throwable) {
            return $callback();
        }
    }
}
