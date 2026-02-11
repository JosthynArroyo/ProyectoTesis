<?php

namespace App\Jobs;

use App\Models\Cita;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnviarRecordatorioWhatsappCitaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $citaId;

    public function __construct(Cita $cita)
    {
        $this->citaId = $cita->id;
    }

    public function handle(WhatsAppService $whatsapp): void
    {
        $cita = Cita::with(['paciente', 'doctor', 'especialidad'])->find($this->citaId);
        if (!$cita) {
            return;
        }

        if ($cita->estado !== Cita::ESTADO_CONFIRMADA || !$cita->activo) {
            return;
        }

        $tz = config('app.timezone', 'America/Guayaquil');
        $hours = (int) config('services.whatsapp.reminder_hours', 6);
        $window = (int) config('services.whatsapp.reminder_window_minutes', 10);

        $now = Carbon::now($tz);
        $start = $now->copy()->addHours($hours)->subMinutes($window);
        $end = $now->copy()->addHours($hours)->addMinutes($window);
        $inicio = $cita->inicioProgramado($tz);

        if (!$inicio->between($start, $end)) {
            Log::info("EnviarRecordatorioWhatsappCitaJob: cita {$cita->id} fuera de ventana.");
            return;
        }

        if ($cita->paciente) {
            $whatsapp->sendRecordatorio6h($cita, $cita->paciente, 'paciente', $inicio);
        }

        if ($cita->doctor) {
            $whatsapp->sendRecordatorio6h($cita, $cita->doctor, 'doctor', $inicio);
        }
    }
}
