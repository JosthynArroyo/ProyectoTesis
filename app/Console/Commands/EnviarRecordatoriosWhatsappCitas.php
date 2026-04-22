<?php

namespace App\Console\Commands;

use App\Jobs\EnviarRecordatorioWhatsappCitaJob;
use App\Models\Cita;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EnviarRecordatoriosWhatsappCitas extends Command
{
    protected $signature = 'citas:recordatorio-whatsapp';

    protected $description = 'Envia recordatorios de citas por WhatsApp a las 12:00 PM del dia anterior.';

    public function handle(): int
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $dispatchHour = (int) config('services.whatsapp.reminder_previous_day_hour', 12);
        $window = (int) config('services.whatsapp.reminder_window_minutes', 10);

        $now = Carbon::now($tz);
        $start = $now->copy()->subMinutes($window);
        $end = $now->copy()->addMinutes($window);
        $tomorrow = $now->copy()->addDay()->toDateString();

        $citas = Cita::query()
            ->where('estado', Cita::ESTADO_CONFIRMADA)
            ->where('activo', true)
            ->whereDate('fecha', $tomorrow)
            ->get(['id', 'fecha', 'hora']);

        $enVentana = $citas->filter(function (Cita $cita) use ($tz, $dispatchHour, $start, $end) {
            $objetivo = $cita->inicioProgramado($tz)
                ->copy()
                ->subDay()
                ->setTime($dispatchHour, 0, 0);

            return $objetivo->between($start, $end);
        });

        foreach ($enVentana as $cita) {
            EnviarRecordatorioWhatsappCitaJob::dispatch($cita);
        }

        $this->info('Recordatorios en cola: '.$enVentana->count());

        return self::SUCCESS;
    }
}
