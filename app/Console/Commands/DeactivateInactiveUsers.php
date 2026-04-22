<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeactivateInactiveUsers extends Command
{
    protected $signature = 'users:deactivate-inactive';

    protected $description = 'Marca como inactivos a usuarios sin actividad por N días';

    public function handle(): int
    {
        $days = (int) config('accounts.inactivity_days', 90);
        $cut = Carbon::now()->subDays($days);

        $count = User::where('status', User::STATUS_ACTIVE)
            ->where(function ($q) use ($cut) {
                $q->whereNull('last_activity_at')
                    ->orWhere('last_activity_at', '<', $cut);
            })
            ->update([
                'status' => User::STATUS_INACTIVE,
                'active' => false,
                'suspended_until' => null,
            ]);

        $this->info("Inactivados: {$count}");

        return self::SUCCESS;
    }
}
