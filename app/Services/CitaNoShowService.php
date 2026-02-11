<?php

namespace App\Services;

use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Models\Cita;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CitaNoShowService
{
    public function marcarVencidas(string $tz = 'America/Guayaquil', int $duracionMin = 30): Collection
    {
        $threshold = Carbon::now($tz)->subMinutes($duracionMin);
        $fecha = $threshold->toDateString();
        $hora = $threshold->format('H:i:s');

        $citas = Cita::query()
            ->whereIn('estado', [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA])
            ->where('activo', true)
            ->where(function ($q) use ($fecha, $hora) {
                $q->whereDate('fecha', '<', $fecha)
                    ->orWhere(function ($qq) use ($fecha, $hora) {
                        $qq->whereDate('fecha', $fecha)
                            ->whereTime('hora', '<=', $hora);
                    });
            })
            ->get();

        foreach ($citas as $cita) {
            $cita->estado = Cita::ESTADO_NO_SE_PRESENTO;
            $cita->activo = false;
            $cita->save();

            NotificarCambioEstadoCitaJob::dispatch($cita, 'no_se_presento', 'sistema');
        }

        return $citas;
    }

    public function marcarSiVencio(Cita $cita, string $tz = 'America/Guayaquil', int $duracionMin = 30): bool
    {
        if (!in_array($cita->estado, [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA], true)) {
            return false;
        }

        if (!$cita->estaVencida($tz, $duracionMin)) {
            return false;
        }

        $cita->estado = Cita::ESTADO_NO_SE_PRESENTO;
        $cita->activo = false;
        $cita->save();

        NotificarCambioEstadoCitaJob::dispatch($cita, 'no_se_presento', 'sistema');

        return true;
    }
}
