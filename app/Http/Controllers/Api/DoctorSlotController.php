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
        $tz = config('app.timezone', 'America/Guayaquil');
        $ahora = Carbon::now($tz);
        $limiteHora = $ahora->copy()->addHour();
        $date = Carbon::parse($fecha, $tz)->toDateString();
        $esHoy = $date === $ahora->toDateString();
        $isLab = $doctor->hasRole('laboratorio');

        if ($isLab) {
            $inicio = Carbon::parse("{$date} 08:00", $tz);
            $fin    = Carbon::parse("{$date} 18:00", $tz);
            $step   = 15;

            $ocupadas = Cita::where('doctor_id', $doctor->id)
                ->whereDate('fecha', $date)
                ->where('activo', true)
                ->whereIn('estado', [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA])
                ->pluck('hora')
                ->map(fn ($t) => substr($t, 0, 5))
                ->toArray();

            $slots = [];
            for ($t = $inicio->copy(); $t->lt($fin); $t->addMinutes($step)) {
                if ($esHoy && $t->lt($limiteHora)) {
                    continue;
                }

                $hhmm = $t->format('H:i');
                $slots[] = [
                    'hora'   => $hhmm,
                    'estado' => in_array($hhmm, $ocupadas, true) ? 'ocupado' : 'libre',
                ];
            }

            return response()->json(['slots' => $slots]);
        }

        // Horarios del dia
        $horarios = Horario::where('doctor_id', $doctor->id)
            ->whereDate('fecha', $date)
            ->orderBy('hora_inicio')
            ->get();

        if ($horarios->isEmpty()) {
            return response()->json(['slots' => []]);
        }

        // Citas ocupadas del día
        $ocupadas = Cita::where('doctor_id', $doctor->id)
            ->whereDate('fecha', $date)
            ->where('activo', true)
            ->whereIn('estado', [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA])
            ->pluck('hora')
            ->map(fn ($t) => substr($t, 0, 5))   // "HH:MM"
            ->toArray();

        $ocupadasSet = array_flip($ocupadas);

        // Construir TODAS las franjas con estado libre/ocupado
        $slots = [];
        foreach ($horarios as $horario) {
            $step = (int) ($horario->intervalo_minutos ?: 30);
            $inicio = Carbon::parse("{$date} {$horario->hora_inicio}", $tz);
            $fin    = Carbon::parse("{$date} {$horario->hora_fin}", $tz);

            for ($t = $inicio->copy(); $t->lt($fin); $t->addMinutes($step)) {
                if ($esHoy && $t->lt($limiteHora)) {
                    continue; // No mostrar bloques dentro de la prxima hora en la zona horaria de Ecuador
                }

                $hhmm = $t->format('H:i');
                if (!isset($slots[$hhmm])) {
                    $slots[$hhmm] = [
                        'hora'   => $hhmm,
                        'estado' => isset($ocupadasSet[$hhmm]) ? 'ocupado' : 'libre',
                    ];
                }
            }
        }

        ksort($slots);
        return response()->json(['slots' => array_values($slots)]);
    }
}

