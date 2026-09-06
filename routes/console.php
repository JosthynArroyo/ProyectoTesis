<?php

use App\Jobs\CreateDatabaseBackupJob;
use App\Models\DatabaseBackup;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $now = Carbon::now('America/Guayaquil');

    if ($now->day === 1) {
        $type = DatabaseBackup::TYPE_MONTHLY;
    } elseif ($now->isSunday()) {
        $type = DatabaseBackup::TYPE_WEEKLY;
    } else {
        $type = DatabaseBackup::TYPE_DAILY;
    }

    CreateDatabaseBackupJob::dispatch($type);
})
->timezone('America/Guayaquil')
->dailyAt('02:00')
->name('database-backups:scheduled-job')
->withoutOverlapping(60)
->onOneServer();

Schedule::command('users:deactivate-inactive')
    ->dailyAt('02:30')
    ->withoutOverlapping();

Schedule::command('citas:marcar-no-show')
    ->everyTenMinutes()
    ->withoutOverlapping();

Schedule::command('citas:sync-recordatorios')
    ->everyTenMinutes()
    ->withoutOverlapping();

Schedule::command('citas:expirar-slot-holds')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('citas:recalcular-prioridad')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('demo:maintain-schedules')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->when(fn () => app(\App\Services\ApplicationModeService::class)->isDemo());


