<?php

namespace App\Observers;

use App\Models\Cita;
use App\Models\CitaEvento;
use App\Services\CitaRecordatorioService;
use Illuminate\Support\Facades\Auth;

class CitaObserver
{
    public function created(Cita $cita): void
    {
        CitaEvento::create([
            'cita_id' => $cita->id,
            'user_id' => Auth::id(),
            'tipo' => 'agendada',
            'a_estado' => $cita->estado,
            'a_fecha' => $cita->fecha,
            'a_hora' => $cita->hora,
        ]);

        app(CitaRecordatorioService::class)->syncForCita($cita);
    }

    public function updated(Cita $cita): void
    {
        $actorId = Auth::id();

        // Cambio de estado
        if ($cita->isDirty('estado')) {
            $map = [
                Cita::ESTADO_CONFIRMADA => 'confirmada',
                Cita::ESTADO_CANCELADA => 'cancelada',
                Cita::ESTADO_REALIZADA => 'realizada',
                Cita::ESTADO_PENDIENTE => 'pendiente', // por si vuelve a pendiente
                Cita::ESTADO_NO_SE_PRESENTO => 'no_se_presento',
            ];
            CitaEvento::create([
                'cita_id' => $cita->id,
                'user_id' => $actorId,
                'tipo' => $map[$cita->estado] ?? 'estado',
                'de_estado' => $cita->getOriginal('estado'),
                'a_estado' => $cita->estado,
            ]);
        }

        // Reprogramación (fecha u hora)
        if ($cita->isDirty('fecha') || $cita->isDirty('hora')) {
            CitaEvento::create([
                'cita_id' => $cita->id,
                'user_id' => $actorId,
                'tipo' => 'reprogramada',
                'de_fecha' => $cita->getOriginal('fecha'),
                'a_fecha' => $cita->fecha,
                'de_hora' => $cita->getOriginal('hora'),
                'a_hora' => $cita->hora,
                'de_estado' => $cita->getOriginal('estado'),
                'a_estado' => $cita->estado,
            ]);
        }

        app(CitaRecordatorioService::class)->syncForCita($cita);
    }
}
