<?php

namespace App\Services;

use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Models\Cita;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $candidatos) use ($citas, $tz, $duracionMin): void {
                foreach ($candidatos as $candidate) {
                    $candidateModel = Cita::find($candidate->id);
                    if ($candidateModel && $this->marcarSiVencio($candidateModel, $tz, $duracionMin)) {
                        $citas->push($candidateModel->refresh());
                    }
                }
            });

        return $citas;
    }

    public function marcarSiVencio(Cita $cita, string $tz = 'America/Guayaquil', int $duracionMin = 30): bool
    {
        $marcada = DB::transaction(function () use ($cita, $tz, $duracionMin): ?Cita {
            /** @var Cita|null $citaBloqueada */
            $citaBloqueada = Cita::query()
                ->whereKey($cita->getKey())
                ->lockForUpdate()
                ->first();

            if (! $citaBloqueada) {
                return null;
            }

            if (! in_array($citaBloqueada->estado, [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA], true)) {
                return null;
            }

            if (! $citaBloqueada->activo) {
                return null;
            }

            if (! $citaBloqueada->estaVencida($tz, $duracionMin)) {
                return null;
            }

            $citaBloqueada->estado = Cita::ESTADO_NO_SE_PRESENTO;
            $citaBloqueada->activo = false;
            $citaBloqueada->save();

            return $citaBloqueada;
        });

        if (! $marcada) {
            return false;
        }

        $this->dispatchNoShowNotification($marcada);
        $this->syncComprobanteSafely($marcada, 'marcarSiVencio');

        return true;
    }

    private function syncComprobanteSafely(Cita $cita, string $origin): void
    {
        if (! app()->runningInConsole()) {
            return;
        }

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
