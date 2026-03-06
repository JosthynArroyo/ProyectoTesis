<?php

namespace App\Console\Commands;

use App\Jobs\NotificarPrioridadCitaJob;
use App\Models\Cita;
use App\Models\CitaEvento;
use Illuminate\Console\Command;

class RecalculateCitaPriorities extends Command
{
    protected $signature = 'citas:recalcular-prioridad';

    protected $description = 'Recalcula prioridades por reglas en citas pendientes y notifica cambios de nivel.';

    public function handle(): int
    {
        $citas = Cita::with(['paciente.patientFlag', 'doctor'])
            ->where('estado', Cita::ESTADO_PENDIENTE)
            ->where('activo', true)
            ->where(function ($query) {
                $query->whereNull('prioridad_fuente')
                    ->orWhere('prioridad_fuente', '!=', Cita::FUENTE_PRIORIDAD_MANUAL);
            })
            ->get();

        $actualizadas = 0;
        $notificadas = 0;

        foreach ($citas as $cita) {
            $nivelAnterior = (string) ($cita->prioridad_nivel ?: Cita::PRIORIDAD_BAJA);
            $fuenteAnterior = (string) ($cita->prioridad_fuente ?: Cita::FUENTE_PRIORIDAD_AUTOMATICA);
            $redFlagAnterior = (bool) $cita->prioridad_red_flag;
            $redFlagTipoAnterior = $cita->prioridad_red_flag_tipo;
            $cambio = $cita->refreshPriority();
            if (!$cambio) {
                continue;
            }

            $nivelNuevo = (string) ($cita->prioridad_nivel ?: Cita::PRIORIDAD_BAJA);
            $fuenteNueva = (string) ($cita->prioridad_fuente ?: Cita::FUENTE_PRIORIDAD_AUTOMATICA);

            $subioNivel = Cita::prioridadRank($nivelNuevo) > Cita::prioridadRank($nivelAnterior);
            $cita->save();
            $actualizadas++;

            if ($subioNivel) {
                CitaEvento::create([
                    'cita_id'   => $cita->id,
                    'user_id'   => null,
                    'tipo'      => 'prioridad',
                    'de_estado' => $nivelAnterior,
                    'a_estado'  => $nivelNuevo,
                    'valor_anterior' => $this->buildAuditValue($nivelAnterior, $fuenteAnterior, $redFlagAnterior, $redFlagTipoAnterior),
                    'valor_nuevo' => $this->buildAuditValue($nivelNuevo, $fuenteNueva, (bool) $cita->prioridad_red_flag, $cita->prioridad_red_flag_tipo),
                ]);
                NotificarPrioridadCitaJob::dispatch($cita, $nivelAnterior, $nivelNuevo);
                $notificadas++;
            }
        }

        $this->info("Citas actualizadas: {$actualizadas} | Notificadas: {$notificadas}");

        return self::SUCCESS;
    }

    private function buildAuditValue(string $nivel, string $fuente, bool $redFlag, ?string $redFlagType): string
    {
        $parts = [
            'NIVEL:' . strtoupper($nivel),
            'FUENTE:' . strtoupper($fuente),
            $redFlag ? 'RED_FLAG:' . ($redFlagType ?: 'SI') : 'RED_FLAG:NO',
        ];

        return implode(' | ', $parts);
    }
}
