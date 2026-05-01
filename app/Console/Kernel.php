<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('users:deactivate-inactive')->dailyAt('02:30')->withoutOverlapping();
        $schedule->command('citas:marcar-no-show')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('citas:sync-recordatorios')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('citas:expirar-slot-holds')->everyMinute()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
