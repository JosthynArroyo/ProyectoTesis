<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class UpdateLastLoginAndGuardStatus
{
    public function handle(Login $event): void
    {
        $u = $event->user;

        // Solo marca último acceso. El bloqueo se maneja en LoginController y middleware.
        $u->forceFill([
            'last_login_at' => now(),
        ])->saveQuietly();
    }
}
