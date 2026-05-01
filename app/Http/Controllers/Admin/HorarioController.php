<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Horario;
use App\Models\User;
use App\Support\WeeklyCalendarData;
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
    public function store(Request $request)
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

        if (! $modoPerDia) {
            if (! $this->isThirtyStep($data['hora_inicio']) || ! $this->isThirtyStep($data['hora_fin'])) {
                return back()->withErrors(['hora_fin' => 'Usa intervalos de 30 minutos.'])->withInput();
            }
        }

        // Rango inclusivo y sin sesgos de hora
        $inicio = Carbon::parse($data['fecha_inicio'])->startOfDay();
        $fin = Carbon::parse($data['fecha_fin'])->endOfDay();
        $diasSel = collect($data['dias'])->map(fn ($d) => (int) $d)->unique();

        $creados = 0;
        $omitidos = 0;

        DB::transaction(function () use ($modoPerDia, $data, $inicio, $fin, $diasSel, &$creados, &$omitidos) {
            $cursor = $inicio->copy();

            while ($cursor->lte($fin)) {
                $dow = $cursor->isoWeekday(); // 1..7 (Dom=7)

                if (! $diasSel->contains($dow)) {
                    $cursor->addDay();

                    continue;
                }

                if ($modoPerDia) {
                    $par = $data['horas'][$dow] ?? null;
                    $hi = $par['inicio'] ?? null;
                    $hf = $par['fin'] ?? null;

                    if (! $hi || ! $hf || $hf <= $hi) {
                        $cursor->addDay();

                        continue;
                    }
                    if (! $this->isThirtyStep($hi) || ! $this->isThirtyStep($hf)) {
                        $cursor->addDay();

                        continue;
                    }
                } else {
                    $hi = $data['hora_inicio'];
                    $hf = $data['hora_fin'];
                }

                $fecha = $cursor->toDateString();

                if ($this->overlapExists((int) $data['doctor_id'], $fecha, $hi, $hf)) {
                    $omitidos++;
                } else {
                    $horario = Horario::firstOrCreate([
                        'doctor_id' => (int) $data['doctor_id'],
                        'fecha' => $fecha,          // idealmente columna DATE
                        'hora_inicio' => $hi,
                        'hora_fin' => $hf,
                    ]);
                    if ($horario->wasRecentlyCreated) {
                        $creados++;
                    } else {
                        $omitidos++;
                    }
                }

                $cursor->addDay();
            }
        });

        $week = $inicio->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

        return redirect()->route('admin.horarios.index', [
            'doctor_id' => $data['doctor_id'],
            'week' => $week,
        ])->with('success', 'Horarios creados correctamente.');
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
    public function update(Request $request, Horario $horario)
    {
        $data = $request->validate([
            'doctor_id' => ['required', 'exists:users,id'],
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
        ]);

        if (! $this->activeDoctorExists((int) $data['doctor_id'])) {
            return back()->withErrors(['doctor_id' => 'Selecciona un doctor activo.'])->withInput();
        }

        if (! $this->isThirtyStep($data['hora_inicio']) || ! $this->isThirtyStep($data['hora_fin'])) {
            return back()->withErrors(['hora_fin' => 'Usa intervalos de 30 minutos.'])->withInput();
        }

        if ($this->overlapExists((int) $data['doctor_id'], (string) $data['fecha'], (string) $data['hora_inicio'], (string) $data['hora_fin'], $horario->id)) {
            return back()->withErrors(['hora_inicio' => 'Existe un horario que se superpone.'])->withInput();
        }

        $horario->update($data);

        $week = Carbon::parse($data['fecha'])->startOfWeek(Carbon::MONDAY)->toDateString();

        return redirect()->route('admin.horarios.index', ['doctor_id' => $data['doctor_id'], 'week' => $week])
            ->with('success', 'Horario actualizado.');
    }

    /** DESTROY */
    public function destroy(Horario $horario)
    {
        $week = Carbon::parse($horario->fecha)->startOfWeek(Carbon::MONDAY)->toDateString();
        $doctorId = $horario->doctor_id;

        $horario->delete();

        return redirect()->route('admin.horarios.index', ['doctor_id' => $doctorId, 'week' => $week])
            ->with('success', 'Horario eliminado.');
    }

    /** Helpers */
    private function isThirtyStep(string $hhmm): bool
    {
        if (! str_contains($hhmm, ':')) {
            return false;
        }
        [, $m] = explode(':', $hhmm, 2);

        return ((int) $m) % 30 === 0;
    }

    private function overlapExists(int $doctorId, string $fecha, string $hi, string $hf, ?int $ignoreId = null): bool
    {
        return Horario::where('doctor_id', $doctorId)
            ->whereDate('fecha', $fecha) // robusto para DATE/DATETIME
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where(function ($q) use ($hi, $hf) {
                $q->whereBetween('hora_inicio', [$hi, $hf])
                    ->orWhereBetween('hora_fin', [$hi, $hf])
                    ->orWhere(fn ($qq) => $qq->where('hora_inicio', '<=', $hi)->where('hora_fin', '>=', $hf));
            })->exists();
    }

    private function activeDoctorExists(int $doctorId): bool
    {
        return User::query()
            ->onlyActive()
            ->whereKey($doctorId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'doctor'))
            ->exists();
    }
}
