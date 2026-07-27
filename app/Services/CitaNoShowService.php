<?php

namespace App\Services;

use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Models\Cita;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CitaNoShowService
{
    public function __construct(private readonly CitaComprobanteService $comprobanteService)
    {
    }

    public function marcarVencidas(string $tz = 'America/Guayaquil', int $duracionMin = 30): Collection
    {
        $threshold = Carbon::now($tz)->subMinutes($duracionMin);
        $fecha = $threshold->toDateString();
        $hora = $threshold->format('H:i:s');

        $citas = collect();

        Cita::query()
            ->whereIn('estado', [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA])
            ->where('activo', true)
            ->where(function ($q) use ($fecha, $hora) {
                $q->whereDate('fecha', '<', $fecha)
                    ->orWhere(function ($qq) use ($fecha, $hora) {
                        $qq->whereDate('fecha', $fecha)
                            ->whereTime('hora', '<=', $hora);
                    });
            })
            ->select(['id', 'estado', 'activo', 'fecha', 'hora'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $vencidas) use ($citas): void {
                foreach ($vencidas as $cita) {
                    $cita->estado = Cita::ESTADO_NO_SE_PRESENTO;
                    $cita->activo = false;
                    $cita->save();
                    $this->dispatchNoShowNotification($cita);
                    $this->syncComprobanteSafely($cita, 'marcarVencidas');
                    $citas->push($cita);
                }
            });

        return $citas;
    }

    public function marcarSiVencio(Cita $cita, string $tz = 'America/Guayaquil', int $duracionMin = 30): bool
    {
        if (! in_array($cita->estado, [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA], true)) {
            return false;
        }

        if (! $cita->estaVencida($tz, $duracionMin)) {
            return false;
        }

        $cita->estado = Cita::ESTADO_NO_SE_PRESENTO;
        $cita->activo = false;
        $cita->save();

        $this->dispatchNoShowNotification($cita);
        $this->syncComprobanteSafely($cita, 'marcarSiVencio');

        return true;
    }

    private function syncComprobanteSafely(Cita $cita, string $origin): void
    {
        try {
            $this->comprobanteService->sincronizarComprobante($cita);
        } catch (\Throwable $e) {
            Log::error('No se pudo regenerar el comprobante al marcar una cita como no se presentó.', [
                'origin' => $origin,
                'cita_id' => $cita->id,
                'estado' => $cita->estado,
                'fecha' => optional($cita->fecha)->toDateString(),
                'hora' => (string) $cita->hora,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
        }
    }

    private function dispatchNoShowNotification(Cita $cita): void
    {
        if (app()->runningInConsole()) {
            NotificarCambioEstadoCitaJob::dispatch($cita, 'no_se_presento', 'sistema');

            return;
        }

        NotificarCambioEstadoCitaJob::dispatchAfterResponse($cita, 'no_se_presento', 'sistema');
    }
}
