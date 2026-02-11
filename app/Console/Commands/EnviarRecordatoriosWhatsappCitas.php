<?php

namespace App\Console\Commands;

use App\Jobs\EnviarRecordatorioWhatsappCitaJob;
use App\Models\Cita;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EnviarRecordatoriosWhatsappCitas extends Command
{
    protected $signature = 'citas:recordatorio-whatsapp';

    protected $description = 'Envia recordatorios de citas por WhatsApp 6 horas antes.';

    public function handle(): int
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $hours = (int) config('services.whatsapp.reminder_hours', 6);
        $window = (int) config('services.whatsapp.reminder_window_minutes', 10);

        $now = Carbon::now($tz);
        $start = $now->copy()->addHours($hours)->subMinutes($window);
        $end = $now->copy()->addHours($hours)->addMinutes($window);

        $citas = Cita::query()
            ->where('estado', Cita::ESTADO_CONFIRMADA)
            ->where('activo', true)
            ->whereBetween('fecha', [$start->toDateString(), $end->toDateString()])
            ->get(['id', 'fecha', 'hora']);

        $enVentana = $citas->filter(function (Cita $cita) use ($tz, $start, $end) {
            $inicio = $cita->inicioProgramado($tz);
            return $inicio->between($start, $end);
        });

        foreach ($enVentana as $cita) {
            EnviarRecordatorioWhatsappCitaJob::dispatch($cita);
        }

        $this->info('Recordatorios en cola: '.$enVentana->count());

        return self::SUCCESS;
    }
}
