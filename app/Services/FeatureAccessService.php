<?php

namespace App\Services;

use App\Models\FeatureAccessRequest;
use App\Models\User;

class FeatureAccessService
{
    public function __construct(
        private readonly ApplicationModeService $applicationMode,
    ) {}

    public function hasAccess(?User $user, string $feature): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('superadmin')) {
            return true;
        }

        // In demo mode, administradores may explore any feature without a FeatureAccessRequest.
        // This exception is strictly scoped: only ApplicationModeService::isDemo() + administrador role.
        // Production always requires an active approved FeatureAccessRequest (fail-closed).
        if ($this->applicationMode->isDemo() && $user->hasRole('administrador')) {
            return true;
        }

        return FeatureAccessRequest::query()
            ->where('user_id', $user->id)
            ->forFeature($feature)
            ->approvedActive()
            ->exists();
    }

    public function pendingRequest(?User $user, string $feature): ?FeatureAccessRequest
    {
        if (! $user) {
            return null;
        }

        return FeatureAccessRequest::query()
            ->where('user_id', $user->id)
            ->forFeature($feature)
            ->pending()
            ->latest()
            ->first();
    }

    public function latestApproved(?User $user, string $feature): ?FeatureAccessRequest
    {
        if (! $user) {
            return null;
        }

        return FeatureAccessRequest::query()
            ->where('user_id', $user->id)
            ->forFeature($feature)
            ->approvedActive()
            ->latest('reviewed_at')
            ->first();
    }

    public function status(?User $user, string $feature): array
    {
        if (! $user) {
            return [
                'can_access' => false,
                'pending' => false,
                'expires_at' => null,
            ];
        }

        $pending = $this->pendingRequest($user, $feature);
        $canAccess = $this->hasAccess($user, $feature);
        $approved = $canAccess
            ? $this->latestApproved($user, $feature)
            : null;

        return [
            'can_access' => $canAccess,
            'pending' => (bool) $pending,
            'expires_at' => $approved?->approved_until,
        ];
    }
}
