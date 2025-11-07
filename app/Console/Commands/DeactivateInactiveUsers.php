<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;

class DeactivateInactiveUsers extends Command
{
    protected $signature = 'users:deactivate-inactive';
    protected $description = 'Marca como inactivos a usuarios sin actividad por N días';

    public function handle(): int
    {
        $days = (int) config('accounts.inactivity_days', 90);
        $cut  = Carbon::now()->subDays($days);

        $count = User::where('status','active')
            ->where(function($q) use ($cut) {
                $q->whereNull('last_activity_at')
                  ->orWhere('last_activity_at','<',$cut);
            })
            ->update(['status' => 'inactive']);

        $this->info("Inactivados: {$count}");
        return self::SUCCESS;
    }
}
