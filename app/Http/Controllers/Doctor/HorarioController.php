<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Horario;
use App\Services\ProfessionalScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HorarioController extends Controller
{
    public function index(Request $r)
    {
        $doctorId = Auth::id();
        $desde = $r->query('desde', Carbon::now()->startOfWeek()->toDateString());
        $hasta = $r->query('hasta', Carbon::now()->endOfWeek()->toDateString());

        $items = Horario::where('doctor_id', $doctorId)
            ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta))
            ->orderBy('fecha')->get();

        return view('doctor.horario.index', compact('items', 'desde', 'hasta'));
    }

    public function store(Request $r, ProfessionalScheduleService $scheduleService)
    {
        $data = $r->validate([
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'intervalo_minutos' => ['required', 'integer', 'in:10,15,20,30,45,60'],
            'confirmar_conflictos' => ['nullable', 'in:0,1'],
        ]);

        $doctorId = Auth::id();

        // Clinic hours check
        $errorMsg = $scheduleService->checkTimeWithinClinicHours($data['fecha'], $data['hora_inicio'], $data['hora_fin']);
        if ($errorMsg) {
            return back()->withErrors(['hora_inicio' => $errorMsg])->withInput();
        }

        // Interval checks
        $interval = (int) $data['intervalo_minutos'];
        if ($interval <= 0) {
            return back()->withErrors(['intervalo_minutos' => 'El intervalo debe ser un entero positivo.'])->withInput();
        }
        $durationMinutes = Carbon::parse($data['hora_inicio'])->diffInMinutes(Carbon::parse($data['hora_fin']));
        if ($durationMinutes < $interval) {
            return back()->withErrors(['hora_fin' => "La duración del bloque ({$durationMinutes} min) es menor que el intervalo ({$interval} min)."])->withInput();
        }

        $hasOverlap = false;
        DB::transaction(function () use ($doctorId, $data, $scheduleService, &$hasOverlap) {
            $hasOverlap = $scheduleService->checkOverlapsWithLock(
                $doctorId,
                $data['fecha'],
                $data['hora_inicio'],
                $data['hora_fin']
            );
        });

        if ($hasOverlap) {
            return back()->withErrors(['hora_inicio' => 'Existe un horario que se superpone.'])->withInput();
        }

        DB::transaction(function () use ($doctorId, $data) {
            Horario::firstOrCreate([
                'doctor_id' => $doctorId,
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

        return view('doctor.horario.edit', ['h' => $horario]);
    }

    public function update(Request $r, Horario $horario, ProfessionalScheduleService $scheduleService)
    {
        abort_unless($horario->doctor_id === Auth::id(), 403);

        $data = $r->validate([
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'intervalo_minutos' => ['required', 'integer', 'in:10,15,20,30,45,60'],
            'confirmar_conflictos' => ['nullable', 'in:0,1'],
        ]);

        $doctorId = Auth::id();

        // Clinic hours check
        $errorMsg = $scheduleService->checkTimeWithinClinicHours($data['fecha'], $data['hora_inicio'], $data['hora_fin']);
        if ($errorMsg) {
            return back()->withErrors(['hora_inicio' => $errorMsg])->withInput();
        }

        // Interval checks
        $interval = (int) $data['intervalo_minutos'];
        if ($interval <= 0) {
            return back()->withErrors(['intervalo_minutos' => 'El intervalo debe ser un entero positivo.'])->withInput();
        }
        $durationMinutes = Carbon::parse($data['hora_inicio'])->diffInMinutes(Carbon::parse($data['hora_fin']));
        if ($durationMinutes < $interval) {
            return back()->withErrors(['hora_fin' => "La duración del bloque ({$durationMinutes} min) es menor que el intervalo ({$interval} min)."])->withInput();
        }

        $hasOverlap = false;
        $conflicts = 0;

        DB::transaction(function () use ($horario, $doctorId, $data, $scheduleService, &$hasOverlap, &$conflicts) {
            $hasOverlap = $scheduleService->checkOverlapsWithLock(
                $doctorId,
                $data['fecha'],
                $data['hora_inicio'],
                $data['hora_fin'],
                $horario->id
            );

            if (!$hasOverlap) {
                $mockSchedule = clone $horario;
                $mockSchedule->hora_inicio = $data['hora_inicio'];
                $mockSchedule->hora_fin = $data['hora_fin'];

                $remaining = collect([$mockSchedule])->concat(
                    Horario::where('doctor_id', $horario->doctor_id)
                        ->whereDate('fecha', $horario->fecha)
                        ->where('id', '!=', $horario->id)
                        ->get()
                );

                $conflicts = $scheduleService->countUncoveredCitas($doctorId, $horario->fecha->toDateString(), $remaining);
            }
        });

        if ($hasOverlap) {
            return back()->withErrors(['hora_inicio' => 'Existe un horario que se superpone.'])->withInput();
        }

        if ($conflicts > 0 && !$r->boolean('confirmar_conflictos')) {
            return back()->withInput()->with('horario_conflicts', $conflicts);
        }

        DB::transaction(function () use ($horario, $data) {
            $horario->update($data);
        });

        return redirect()->route('doctor.horario.index')->with('success', 'Horario actualizado.');
    }

    public function destroy(Horario $horario, Request $r, ProfessionalScheduleService $scheduleService)
    {
        abort_unless($horario->doctor_id === Auth::id(), 403);
        $doctorId = Auth::id();

        // Check if deleting leaves appointments uncovered
        $remaining = Horario::where('doctor_id', $horario->doctor_id)
            ->whereDate('fecha', $horario->fecha)
            ->where('id', '!=', $horario->id)
            ->get();

        $conflicts = $scheduleService->countUncoveredCitas($doctorId, $horario->fecha->toDateString(), $remaining);

        if ($conflicts > 0 && !$r->boolean('confirmar_conflictos')) {
            return back()->with([
                'horario_conflicts' => $conflicts,
                'conflict_target_route' => route('doctor.horario.destroy', $horario),
                'conflict_target_method' => 'DELETE',
                'conflict_payload' => ['confirmar_conflictos' => 1]
            ]);
        }

        DB::transaction(function () use ($horario) {
            $horario->delete();
        });

        return back()->with('success', 'Horario eliminado.');
    }

    public function generarRango(Request $r, ProfessionalScheduleService $scheduleService)
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
            'confirmar_conflictos' => ['nullable', 'in:0,1'],
        ]);

        $doctorId = Auth::id();
        $ini = Carbon::parse($data['desde']);
        $fin = Carbon::parse($data['hasta']);
        $dias = collect($data['dias'])->map(fn ($d) => (int) $d)->all();

        // Interval checks
        $interval = (int) $data['intervalo_minutos'];
        if ($interval <= 0) {
            return back()->withErrors(['intervalo_minutos' => 'El intervalo debe ser un entero positivo.'])->withInput();
        }
        $durationMinutes = Carbon::parse($data['hora_inicio'])->diffInMinutes(Carbon::parse($data['hora_fin']));
        if ($durationMinutes < $interval) {
            return back()->withErrors(['hora_fin' => "La duración del bloque ({$durationMinutes} min) es menor que el intervalo ({$interval} min)."])->withInput();
        }

        $totalConflicts = 0;
        if (!empty($data['sobrescribir'])) {
            $newBlock = (object)[
                'hora_inicio' => $data['hora_inicio'],
                'hora_fin' => $data['hora_fin'],
                'intervalo_minutos' => $data['intervalo_minutos']
            ];

            for ($d = $ini->copy(); $d->lte($fin); $d->addDay()) {
                $fecha = $d->toDateString();
                if (!in_array((int)$d->isoWeekday(), $dias, true)) {
                    continue;
                }
                $totalConflicts += $scheduleService->countUncoveredCitas($doctorId, $fecha, collect([$newBlock]));
            }
        }

        if ($totalConflicts > 0 && !$r->boolean('confirmar_conflictos')) {
            return back()->withInput()->with([
                'horario_conflicts' => $totalConflicts,
                'conflict_target_route' => route('doctor.horario.generar'),
                'conflict_target_method' => 'POST',
                'conflict_payload' => $r->all() + ['confirmar_conflictos' => 1]
            ]);
        }

        $creados = [];
        $cerrados = [];
        $fueraRango = [];
        $superpuestos = [];

        DB::transaction(function () use ($doctorId, $ini, $fin, $dias, $data, $scheduleService, &$creados, &$cerrados, &$fueraRango, &$superpuestos) {
            for ($d = $ini->copy(); $d->lte($fin); $d->addDay()) {
                $dow = $d->isoWeekday();
                $fecha = $d->toDateString();

                if (! in_array((int) $dow, $dias, true)) {
                    continue;
                }

                // Clinic hours check
                $clinicH = $scheduleService->getClinicHours($dow);
                if ($clinicH['status'] === 0) {
                    $cerrados[] = $fecha;
                    continue;
                }

                if (substr($data['hora_inicio'], 0, 5) < $clinicH['opening'] || substr($data['hora_fin'], 0, 5) > $clinicH['closing']) {
                    $fueraRango[] = $fecha;
                    continue;
                }

                if (! empty($data['sobrescribir'])) {
                    Horario::where('doctor_id', $doctorId)->whereDate('fecha', $fecha)->delete();
                }

                // Check overlap
                if ($scheduleService->checkOverlapsWithLock($doctorId, $fecha, $data['hora_inicio'], $data['hora_fin'])) {
                    $superpuestos[] = $fecha;
                } else {
                    $horario = Horario::firstOrCreate([
                        'doctor_id' => $doctorId,
                        'fecha' => $fecha,
                        'hora_inicio' => $data['hora_inicio'],
                        'hora_fin' => $data['hora_fin'],
                    ], [
                        'intervalo_minutos' => $data['intervalo_minutos'],
                    ]);

                    if ($horario->wasRecentlyCreated) {
                        $creados[] = $fecha;
                    } else {
                        $superpuestos[] = $fecha;
                    }
                }
            }
        });

        $successMessage = "Generación finalizada. Creados: " . count($creados) . " días.";
        if (count($cerrados) > 0) {
            $successMessage .= " Omitidos por día cerrado: " . count($cerrados) . " (" . implode(', ', $cerrados) . ").";
        }
        if (count($fueraRango) > 0) {
            $successMessage .= " Omitidos por exceder horario institucional: " . count($fueraRango) . " (" . implode(', ', $fueraRango) . ").";
        }
        if (count($superpuestos) > 0) {
            $successMessage .= " Omitidos por superposición: " . count($superpuestos) . " (" . implode(', ', $superpuestos) . ").";
        }

        return back()->with('success', $successMessage);
    }
}
