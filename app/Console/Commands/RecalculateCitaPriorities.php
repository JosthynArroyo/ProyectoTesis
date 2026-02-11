<?php

namespace App\Console\Commands;

use App\Jobs\NotificarPrioridadCitaJob;
use App\Models\Cita;
use App\Models\CitaEvento;
use Illuminate\Console\Command;

class RecalculateCitaPriorities extends Command
{
    protected $signature = 'citas:recalcular-prioridad';

    protected $description = 'Recalcula prioridades de citas pendientes y notifica cambios de nivel.';

    public function handle(): int
    {
        $citas = Cita::with(['paciente.patientFlag', 'doctor'])
            ->where('estado', Cita::ESTADO_PENDIENTE)
            ->where('activo', true)
            ->get();

        $actualizadas = 0;
        $notificadas = 0;

        foreach ($citas as $cita) {
            $nivelAnterior = $cita->priority_level ?? 'baja';
            $cambio = $cita->refreshPriority();
            if (!$cambio) {
                continue;
            }

            $subioNivel = $this->rankNivel($cita->priority_level) > $this->rankNivel($nivelAnterior);
            if ($subioNivel) {
                $cita->last_priority_notified_at = now();
                CitaEvento::create([
                    'cita_id'   => $cita->id,
                    'user_id'   => null,
                    'tipo'      => 'prioridad',
                    'de_estado' => $nivelAnterior,
                    'a_estado'  => $cita->priority_level,
                ]);
                NotificarPrioridadCitaJob::dispatch($cita, $nivelAnterior, $cita->priority_level);
                $notificadas++;
            }

            $cita->save();
            $actualizadas++;
        }

        $this->info("Citas actualizadas: {$actualizadas} | Notificadas: {$notificadas}");

        return self::SUCCESS;
    }

    private function rankNivel(string $nivel): int
    {
        return match ($nivel) {
            'media' => 1,
            'alta' => 2,
            'critica' => 3,
            default => 0,
        };
    }
}
