<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Horario;
use App\Models\User;
use App\Support\WeeklyCalendarData;
use App\Services\ProfessionalScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HorarioController extends Controller
{
    /** INDEX: semana con filtro y navegación */
    public function index(Request $request)
    {
        $weekStart = WeeklyCalendarData::resolveWeekStart($request->get('week'));
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $doctores = User::whereHas('roles', fn ($q) => $q->where('name', 'doctor'))
            ->onlyActive()
            ->orderBy('name')
            ->get(['id', 'name']);

        $requestedDoctorId = trim((string) $request->get('doctor_id', 'all'));
        $doctorIds = $doctores->pluck('id')->map(fn ($id) => (string) $id);
        $showAllDoctors = $requestedDoctorId === ''
            || $requestedDoctorId === 'all'
            || ! $doctorIds->contains($requestedDoctorId);
        $doctorId = $showAllDoctors ? 'all' : $requestedDoctorId;
        $selectedDoctorIds = $showAllDoctors
            ? $doctores->pluck('id')->all()
            : [(int) $doctorId];

        $horarios = collect();
        $citas = collect();
        $calendar = WeeklyCalendarData::build($weekStart, [], [
            'default_start_minutes' => 7 * 60,
            'default_end_minutes' => 19 * 60,
        ]);

        if (! empty($selectedDoctorIds)) {
            $horarios = Horario::with(['doctor' => fn ($q) => $q->select('id', 'name')])
                ->whereIn('doctor_id', $selectedDoctorIds)
                ->whereDate('fecha', '>=', $weekStart->toDateString())
                ->whereDate('fecha', '<=', $weekEnd->toDateString())
                ->orderBy('doctor_id')
                ->orderBy('fecha')
                ->orderBy('hora_inicio')
                ->get();

            $citas = Cita::with(['paciente:id,name', 'especialidad:id,nombre', 'doctor:id,name'])
                ->whereIn('doctor_id', $selectedDoctorIds)
                ->whereDate('fecha', '>=', $weekStart->toDateString())
                ->whereDate('fecha', '<=', $weekEnd->toDateString())
                ->orderBy('doctor_id')
                ->orderBy('fecha')
                ->orderBy('hora')
                ->get();

            $calendarEntries = $horarios->map(function (Horario $horario) use ($showAllDoctors) {
                $doctorName = $horario->doctor?->name ?? 'Doctor';

                return [
                    'layer' => 'background',
                    'date' => $horario->fecha,
                    'start' => $horario->hora_inicio,
                    'end' => $horario->hora_fin,
                    'title' => $showAllDoctors ? $doctorName : 'Bloque disponible',
                    'subtitle' => $showAllDoctors ? 'Bloque disponible' : 'Horario del doctor',
                    'eyebrow' => substr((string) $horario->hora_inicio, 0, 5).' - '.substr((string) $horario->hora_fin, 0, 5),
                    'tone' => 'slate',
                    'lane_key' => 'doctor:'.$horario->doctor_id,
                ];
            })->values();

            $calendarEntries = $calendarEntries->concat(
                $citas->map(function (Cita $cita) use ($horarios, $showAllDoctors) {
                    $doctorHorarios = $showAllDoctors
                        ? $horarios->where('doctor_id', $cita->doctor_id)
                        : $horarios;
                    $end = WeeklyCalendarData::inferEndTime(
                        $cita->fecha->toDateString(),
                        (string) $cita->hora,
                        $doctorHorarios
                    );

                    $statusLabel = match ($cita->estado) {
                        Cita::ESTADO_PENDIENTE => 'Pendiente',
                        Cita::ESTADO_CONFIRMADA => 'Confirmada',
                        Cita::ESTADO_CANCELADA => 'Cancelada',
                        Cita::ESTADO_REALIZADA => 'Realizada',
                        Cita::ESTADO_NO_SE_PRESENTO => 'No se presentó',
                        default => ucfirst((string) $cita->estado),
                    };

                    $tone = match ($cita->estado) {
                        Cita::ESTADO_PENDIENTE => 'amber',
                        Cita::ESTADO_CONFIRMADA => 'blue',
                        Cita::ESTADO_REALIZADA => 'emerald',
                        default => 'rose',
                    };

                    $doctorName = $cita->doctor?->name ?? 'Doctor';
                    $meta = ['Prioridad '.($cita->prioridad_nivel ?: 'BAJA')];

                    if ($showAllDoctors) {
                        array_unshift($meta, $doctorName);
                    }

                    return [
                        'layer' => 'foreground',
                        'date' => $cita->fecha,
                        'start' => $cita->hora,
                        'end' => $end,
                        'title' => $cita->paciente?->name ?? 'Paciente',
                        'subtitle' => $cita->especialidad?->nombre ?? 'Cita médica',
                        'eyebrow' => $statusLabel,
                        'meta' => implode(' · ', $meta),
                        'tone' => $tone,
                        'lane_key' => 'doctor:'.$cita->doctor_id,
                    ];
                })
            );

            $calendar = WeeklyCalendarData::build($weekStart, $calendarEntries, [
                'default_start_minutes' => 7 * 60,
                'default_end_minutes' => 19 * 60,
            ]);
        }

        return view('admin.horarios.index', compact('horarios', 'citas', 'doctores', 'doctorId', 'showAllDoctors', 'weekStart', 'weekEnd', 'calendar'));
    }

    /** CREATE */
    public function create()
    {
        $doctores = User::whereHas('roles', fn ($q) => $q->where('name', 'doctor'))
            ->onlyActive()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.horarios.create', compact('doctores'));
    }

    /** STORE: rango + días (franja única o por día) */
    public function store(Request $request, ProfessionalScheduleService $scheduleService)
    {
        $modoPerDia = ! $request->boolean('misma_franja');

        $base = [
            'doctor_id' => ['required', 'exists:users,id'],
            'fecha_inicio' => ['required', 'date', 'after_or_equal:today'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'dias' => ['required', 'array', 'min:1'],
            'dias.*' => ['integer', 'between:1,7'],
            'misma_franja' => ['required', 'boolean'],
        ];

        $rules = $modoPerDia
            ? $base + [
                'horas' => ['required', 'array'],
                'horas.*.inicio' => ['required', 'date_format:H:i'],
                'horas.*.fin' => ['required', 'date_format:H:i'],
            ]
            : $base + [
                'hora_inicio' => ['required', 'date_format:H:i'],
                'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            ];

        $data = $request->validate($rules, [
            'doctor_id.required' => 'Seleccione un doctor.',
            'fecha_inicio.after_or_equal' => 'La fecha desde no puede ser anterior a hoy.',
            'dias.required' => 'Seleccione al menos un día.',
        ]);

        if (! $this->activeDoctorExists((int) $data['doctor_id'])) {
            return back()->withErrors(['doctor_id' => 'Selecciona un doctor activo.'])->withInput();
        }

        $inicio = Carbon::parse($data['fecha_inicio'])->startOfDay();
        $fin = Carbon::parse($data['fecha_fin'])->endOfDay();
        $diasSel = collect($data['dias'])->map(fn ($d) => (int) $d)->unique();

        $creados = [];
        $cerrados = [];
        $fueraRango = [];
        $superpuestos = [];

        DB::transaction(function () use ($modoPerDia, $data, $inicio, $fin, $diasSel, $scheduleService, &$creados, &$cerrados, &$fueraRango, &$superpuestos) {
            $cursor = $inicio->copy();

            while ($cursor->lte($fin)) {
                $dow = $cursor->isoWeekday();
                $fecha = $cursor->toDateString();

                if (! $diasSel->contains($dow)) {
                    $cursor->addDay();
                    continue;
                }

                if ($modoPerDia) {
                    $par = $data['horas'][$dow] ?? null;
                    $hi = $par['inicio'] ?? null;
                    $hf = $par['fin'] ?? null;
                } else {
                    $hi = $data['hora_inicio'];
                    $hf = $data['hora_fin'];
                }

                if (! $hi || ! $hf || $hf <= $hi) {
                    $cursor->addDay();
                    continue;
                }

                // Centralized clinic hours check
                $clinicH = $scheduleService->getClinicHours($dow);
                if ($clinicH['status'] === 0) {
                    $cerrados[] = $fecha;
                    $cursor->addDay();
                    continue;
                }

                if (substr($hi, 0, 5) < $clinicH['opening'] || substr($hf, 0, 5) > $clinicH['closing']) {
                    $fueraRango[] = $fecha;
                    $cursor->addDay();
                    continue;
                }

                // Check overlap with row locking
                if ($scheduleService->checkOverlapsWithLock((int)$data['doctor_id'], $fecha, $hi, $hf)) {
                    $superpuestos[] = $fecha;
                } else {
                    $horario = Horario::firstOrCreate([
                        'doctor_id' => (int) $data['doctor_id'],
                        'fecha' => $fecha,
                        'hora_inicio' => $hi,
                        'hora_fin' => $hf,
                    ], [
                        'intervalo_minutos' => 30
                    ]);

                    if ($horario->wasRecentlyCreated) {
                        $creados[] = $fecha;
                    } else {
                        $superpuestos[] = $fecha;
                    }
                }

                $cursor->addDay();
            }
        });

        $week = $inicio->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

        $successMessage = "Procesamiento finalizado. Creados: " . count($creados) . " bloques.";
        if (count($cerrados) > 0) {
            $successMessage .= " Omitidos por día cerrado: " . count($cerrados) . " (" . implode(', ', $cerrados) . ").";
        }
        if (count($fueraRango) > 0) {
            $successMessage .= " Omitidos fuera de franja institucional: " . count($fueraRango) . " (" . implode(', ', $fueraRango) . ").";
        }
        if (count($superpuestos) > 0) {
            $successMessage .= " Omitidos por superposición: " . count($superpuestos) . " (" . implode(', ', $superpuestos) . ").";
        }

        return redirect()->route('admin.horarios.index', [
            'doctor_id' => $data['doctor_id'],
            'week' => $week,
        ])->with('success', $successMessage);
    }

    /** EDIT */
    public function edit(Horario $horario)
    {
        if (! $this->activeDoctorExists((int) $horario->doctor_id)) {
            return redirect()
                ->route('admin.horarios.index')
                ->withErrors(['doctor_id' => 'No puedes editar horarios de un doctor inactivo. Reactivalo antes de gestionar disponibilidad.']);
        }

        $doctores = User::whereHas('roles', fn ($q) => $q->where('name', 'doctor'))
            ->onlyActive()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.horarios.edit', compact('horario', 'doctores'));
    }

    /** UPDATE */
    public function update(Request $request, Horario $horario, ProfessionalScheduleService $scheduleService)
    {
        $data = $request->validate([
            'doctor_id' => ['required', 'exists:users,id'],
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'confirmar_conflictos' => ['nullable', 'in:0,1'],
        ]);

        if (! $this->activeDoctorExists((int) $data['doctor_id'])) {
            return back()->withErrors(['doctor_id' => 'Selecciona un doctor activo.'])->withInput();
        }

        // Validate clinic hours
        $errorMsg = $scheduleService->checkTimeWithinClinicHours($data['fecha'], $data['hora_inicio'], $data['hora_fin']);
        if ($errorMsg) {
            return back()->withErrors(['hora_inicio' => $errorMsg])->withInput();
        }

        // Run checking in transaction with locks
        $hasOverlap = false;
        $conflicts = 0;

        DB::transaction(function () use ($horario, $data, $scheduleService, &$hasOverlap, &$conflicts) {
            $hasOverlap = $scheduleService->checkOverlapsWithLock(
                (int) $data['doctor_id'],
                (string) $data['fecha'],
                (string) $data['hora_inicio'],
                (string) $data['hora_fin'],
                $horario->id
            );

            if (!$hasOverlap) {
                // Mock remaining schedules to calculate uncovered citas
                $mockSchedule = clone $horario;
                $mockSchedule->hora_inicio = $data['hora_inicio'];
                $mockSchedule->hora_fin = $data['hora_fin'];

                $remaining = collect([$mockSchedule])->concat(
                    Horario::where('doctor_id', $horario->doctor_id)
                        ->whereDate('fecha', $horario->fecha)
                        ->where('id', '!=', $horario->id)
                        ->get()
                );

                $conflicts = $scheduleService->countUncoveredCitas($horario->doctor_id, $horario->fecha->toDateString(), $remaining);
            }
        });

        if ($hasOverlap) {
            return back()->withErrors(['hora_inicio' => 'Existe un horario que se superpone.'])->withInput();
        }

        if ($conflicts > 0 && !$request->boolean('confirmar_conflictos')) {
            return back()->withInput()->with('horario_conflicts', $conflicts);
        }

        // Persist inside transaction
        DB::transaction(function () use ($horario, $data) {
            $horario->update([
                'doctor_id' => $data['doctor_id'],
                'fecha' => $data['fecha'],
                'hora_inicio' => $data['hora_inicio'],
                'hora_fin' => $data['hora_fin'],
            ]);
        });

        $week = Carbon::parse($data['fecha'])->startOfWeek(Carbon::MONDAY)->toDateString();

        return redirect()->route('admin.horarios.index', ['doctor_id' => $data['doctor_id'], 'week' => $week])
            ->with('success', 'Horario actualizado.');
    }

    /** DESTROY */
    public function destroy(Horario $horario, Request $request, ProfessionalScheduleService $scheduleService)
    {
        $week = Carbon::parse($horario->fecha)->startOfWeek(Carbon::MONDAY)->toDateString();
        $doctorId = $horario->doctor_id;

        // Check if deleting leaves appointments uncovered
        $remaining = Horario::where('doctor_id', $horario->doctor_id)
            ->whereDate('fecha', $horario->fecha)
            ->where('id', '!=', $horario->id)
            ->get();

        $conflicts = $scheduleService->countUncoveredCitas($horario->doctor_id, $horario->fecha->toDateString(), $remaining);

        if ($conflicts > 0 && !$request->boolean('confirmar_conflictos')) {
            return back()->with([
                'horario_conflicts' => $conflicts,
                'conflict_target_route' => route('admin.horarios.destroy', $horario),
                'conflict_target_method' => 'DELETE',
                'conflict_payload' => ['confirmar_conflictos' => 1]
            ]);
        }

        DB::transaction(function () use ($horario) {
            $horario->delete();
        });

        return redirect()->route('admin.horarios.index', ['doctor_id' => $doctorId, 'week' => $week])
            ->with('success', 'Horario eliminado.');
    }

    /** Helpers */
    private function activeDoctorExists(int $doctorId): bool
    {
        return User::query()
            ->onlyActive()
            ->whereKey($doctorId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'doctor'))
            ->exists();
    }
}
