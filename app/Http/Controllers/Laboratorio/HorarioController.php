<?php

namespace App\Http\Controllers\Laboratorio;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Horario;
use App\Models\LabOrder;
use App\Models\LaboratorioOrden;
use App\Support\WeeklyCalendarData;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HorarioController extends Controller
{
    public function index(Request $r)
    {
        $labId = Auth::id();
        $weekStart = WeeklyCalendarData::resolveWeekStart($r->query('week', $r->query('desde')));
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $desde = $r->query('desde', $weekStart->toDateString());
        $hasta = $r->query('hasta', $weekEnd->toDateString());

        $items = Horario::where('doctor_id', $labId)
            ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta))
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        $weekHorarios = Horario::where('doctor_id', $labId)
            ->whereDate('fecha', '>=', $weekStart->toDateString())
            ->whereDate('fecha', '<=', $weekEnd->toDateString())
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        $legacyAppointments = Cita::with(['paciente:id,name', 'laboratorioOrden'])
            ->where('doctor_id', $labId)
            ->whereDate('fecha', '>=', $weekStart->toDateString())
            ->whereDate('fecha', '<=', $weekEnd->toDateString())
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        $selfServiceAppointments = LabOrder::with(['patient:id,name', 'items.test:id,nombre'])
            ->whereNotNull('scheduled_at')
            ->where(function ($query) use ($labId) {
                $query->whereNull('laboratorio_id')
                    ->orWhere('laboratorio_id', $labId);
            })
            ->whereDate('scheduled_at', '>=', $weekStart->toDateString())
            ->whereDate('scheduled_at', '<=', $weekEnd->toDateString())
            ->orderBy('scheduled_at')
            ->get();

        $calendarEntries = $weekHorarios->map(function (Horario $horario) {
            return [
                'layer' => 'background',
                'date' => $horario->fecha,
                'start' => $horario->hora_inicio,
                'end' => $horario->hora_fin,
                'title' => 'Recepción activa',
                'subtitle' => 'Bloque de atención',
                'eyebrow' => substr((string) $horario->hora_inicio, 0, 5).' - '.substr((string) $horario->hora_fin, 0, 5),
                'tone' => 'slate',
            ];
        })->values();

        $calendarEntries = $calendarEntries->concat(
            $legacyAppointments->map(function (Cita $cita) use ($weekHorarios) {
                $orden = $cita->laboratorioOrden;
                $status = $orden?->estado ?? LaboratorioOrden::ESTADO_CITA_PROGRAMADA;
                $statusLabel = match ($status) {
                    LaboratorioOrden::ESTADO_ORDEN_CREADA => 'Orden creada',
                    LaboratorioOrden::ESTADO_CITA_PROGRAMADA => 'Cita programada',
                    LaboratorioOrden::ESTADO_MUESTRA_TOMADA => 'Muestra tomada',
                    LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE => 'Resultado disponible',
                    default => 'En proceso',
                };

                $tone = match ($status) {
                    LaboratorioOrden::ESTADO_ORDEN_CREADA, LaboratorioOrden::ESTADO_CITA_PROGRAMADA => 'blue',
                    LaboratorioOrden::ESTADO_MUESTRA_TOMADA => 'amber',
                    LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE => 'emerald',
                    default => 'slate',
                };

                return [
                    'layer' => 'foreground',
                    'date' => $cita->fecha,
                    'start' => $cita->hora,
                    'end' => WeeklyCalendarData::inferEndTime(
                        $cita->fecha->toDateString(),
                        (string) $cita->hora,
                        $weekHorarios
                    ),
                    'title' => $cita->paciente?->name ?? 'Paciente',
                    'subtitle' => $orden?->tipo_examen ?? 'Examen de laboratorio',
                    'eyebrow' => $statusLabel,
                    'meta' => 'Orden clínica',
                    'tone' => $tone,
                ];
            })
        )->concat(
            $selfServiceAppointments->map(function (LabOrder $order) {
                $scheduledAt = $order->scheduled_at?->copy();
                if (! $scheduledAt) {
                    return null;
                }

                $tone = match ($order->status) {
                    LabOrder::STATUS_PENDIENTE_TOMA => 'blue',
                    LabOrder::STATUS_MUESTRA_TOMADA, LabOrder::STATUS_EN_ANALISIS => 'amber',
                    LabOrder::STATUS_RESULTADO_LISTO => 'emerald',
                    default => 'rose',
                };

                $label = match ($order->status) {
                    LabOrder::STATUS_PENDIENTE_TOMA => 'Pendiente de toma',
                    LabOrder::STATUS_MUESTRA_TOMADA => 'Muestra tomada',
                    LabOrder::STATUS_EN_ANALISIS => 'En análisis',
                    LabOrder::STATUS_RESULTADO_LISTO => 'Resultado listo',
                    LabOrder::STATUS_CANCELADO => 'Cancelado',
                    LabOrder::STATUS_NO_SE_PRESENTO => 'No se presentó',
                    default => 'En proceso',
                };

                return [
                    'layer' => 'foreground',
                    'date' => $scheduledAt->toDateString(),
                    'start' => $scheduledAt->format('H:i'),
                    'end' => $scheduledAt->copy()->addMinutes(30)->format('H:i'),
                    'title' => $order->patient?->name ?? 'Paciente',
                    'subtitle' => $order->tipo_examen ?? 'Examen de laboratorio',
                    'eyebrow' => $label,
                    'meta' => 'Orden directa',
                    'tone' => $tone,
                ];
            })->filter()
        );

        $calendar = WeeklyCalendarData::build($weekStart, $calendarEntries, [
            'default_start_minutes' => 7 * 60,
            'default_end_minutes' => 19 * 60,
        ]);

        return view('laboratorio.horario.index', compact('items', 'calendar', 'weekStart', 'weekEnd', 'desde', 'hasta'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'intervalo_minutos' => ['required', 'integer', 'in:10,15,20,30,45,60'],
        ]);

        $interval = (int) $data['intervalo_minutos'];
        if ($interval <= 0) {
            return back()->withErrors(['intervalo_minutos' => 'El intervalo debe ser un entero positivo.'])->withInput();
        }
        $durationMinutes = Carbon::parse($data['hora_inicio'])->diffInMinutes(Carbon::parse($data['hora_fin']));
        if ($durationMinutes < $interval) {
            return back()->withErrors(['hora_fin' => "La duración del bloque ({$durationMinutes} min) es menor que el intervalo ({$interval} min)."])->withInput();
        }

        DB::transaction(function () use ($data) {
            Horario::firstOrCreate([
                'doctor_id' => Auth::id(),
                'fecha' => $data['fecha'],
                'hora_inicio' => $data['hora_inicio'],
                'hora_fin' => $data['hora_fin'],
            ], [
                'intervalo_minutos' => $data['intervalo_minutos'],
            ]);
        });

        return back()->with('success', 'Horario creado.');
    }

    public function edit(Horario $horario)
    {
        abort_unless($horario->doctor_id === Auth::id(), 403);

        return view('laboratorio.horario.edit', ['h' => $horario]);
    }

    public function update(Request $r, Horario $horario)
    {
        abort_unless($horario->doctor_id === Auth::id(), 403);

        $data = $r->validate([
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'intervalo_minutos' => ['required', 'integer', 'in:10,15,20,30,45,60'],
        ]);

        $interval = (int) $data['intervalo_minutos'];
        if ($interval <= 0) {
            return back()->withErrors(['intervalo_minutos' => 'El intervalo debe ser un entero positivo.'])->withInput();
        }
        $durationMinutes = Carbon::parse($data['hora_inicio'])->diffInMinutes(Carbon::parse($data['hora_fin']));
        if ($durationMinutes < $interval) {
            return back()->withErrors(['hora_fin' => "La duración del bloque ({$durationMinutes} min) es menor que el intervalo ({$interval} min)."])->withInput();
        }

        DB::transaction(function () use ($horario, $data) {
            $horario->update($data);
        });

        return redirect()->route('laboratorio.horario.index')->with('success', 'Horario actualizado.');
    }

    public function destroy(Horario $horario)
    {
        abort_unless($horario->doctor_id === Auth::id(), 403);
        
        DB::transaction(function () use ($horario) {
            $horario->delete();
        });

        return back()->with('success', 'Horario eliminado.');
    }

    public function generarRango(Request $r)
    {
        $data = $r->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'dias' => ['required', 'array', 'min:1'],
            'dias.*' => ['integer', 'between:1,7'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'intervalo_minutos' => ['required', 'integer', 'in:10,15,20,30,45,60'],
            'sobrescribir' => ['required', 'boolean'],
        ]);

        $interval = (int) $data['intervalo_minutos'];
        if ($interval <= 0) {
            return back()->withErrors(['intervalo_minutos' => 'El intervalo debe ser un entero positivo.'])->withInput();
        }
        $durationMinutes = Carbon::parse($data['hora_inicio'])->diffInMinutes(Carbon::parse($data['hora_fin']));
        if ($durationMinutes < $interval) {
            return back()->withErrors(['hora_fin' => "La duración del bloque ({$durationMinutes} min) es menor que el intervalo ({$interval} min)."])->withInput();
        }

        $labId = Auth::id();
        $ini = Carbon::parse($data['desde']);
        $fin = Carbon::parse($data['hasta']);
        $dias = collect($data['dias'])->map(fn ($d) => (int) $d)->all();
        $n = 0;

        DB::transaction(function () use ($labId, $ini, $fin, $dias, $data, &$n) {
            for ($d = $ini->copy(); $d->lte($fin); $d->addDay()) {
                if (! in_array((int) $d->isoWeekday(), $dias, true)) {
                    continue;
                }

                if (! empty($data['sobrescribir'])) {
                    Horario::where('doctor_id', $labId)->whereDate('fecha', $d->toDateString())->delete();
                }

                Horario::firstOrCreate([
                    'doctor_id' => $labId,
                    'fecha' => $d->toDateString(),
                    'hora_inicio' => $data['hora_inicio'],
                    'hora_fin' => $data['hora_fin'],
                ], [
                    'intervalo_minutos' => $data['intervalo_minutos'],
                ]);
                $n++;
            }
        });

        return back()->with('success',"Generados {$n} dias.");
    }
}
