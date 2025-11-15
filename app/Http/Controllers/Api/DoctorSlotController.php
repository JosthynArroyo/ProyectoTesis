<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Cita;
use App\Models\Horario;
use Carbon\Carbon;

class DoctorSlotController extends Controller
{
    public function __invoke(User $doctor, string $fecha)
    {
        $date = Carbon::parse($fecha)->toDateString();

        // Horario específico del día (tu modelo ya usa fecha)
        $horario = Horario::where('doctor_id', $doctor->id)
            ->whereDate('fecha', $date)
            ->first();

        if (!$horario) {
            return response()->json(['slots' => []]);
        }

        $step = property_exists($horario, 'intervalo_minutos')
            ? (int)($horario->intervalo_minutos ?: 30)
            : 30;

        $inicio = Carbon::parse("{$date} {$horario->hora_inicio}");
        $fin    = Carbon::parse("{$date} {$horario->hora_fin}");

        // Citas ocupadas del día
        $ocupadas = Cita::where('doctor_id', $doctor->id)
            ->whereDate('fecha', $date)
            ->where('activo', true)
            ->whereIn('estado', [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA])
            ->pluck('hora')
            ->map(fn ($t) => substr($t, 0, 5))   // "HH:MM"
            ->toArray();

        // Construir TODAS las franjas con estado libre/ocupado
        $slots = [];
        for ($t = $inicio->copy(); $t->lt($fin); $t->addMinutes($step)) {
            $hhmm = $t->format('H:i');
            $slots[] = [
                'hora'   => $hhmm,
                'estado' => in_array($hhmm, $ocupadas, true) ? 'ocupado' : 'libre',
            ];
        }

        return response()->json(['slots' => $slots]);
    }
}
