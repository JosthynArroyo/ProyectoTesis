<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Horario;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HorarioController extends Controller
{
    /** INDEX: semana con filtro y navegación */
    public function index(Request $request)
    {
        $doctorId = $request->get('doctor_id');
        $weekRef  = $request->get('week');

        // Semana ISO: lunes a domingo
        $seed      = $weekRef ?: Carbon::now()->toDateString();
        $weekStart = Carbon::parse($seed)->startOfWeek(Carbon::MONDAY);
        $weekEnd   = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $query = Horario::with(['doctor' => fn($q) => $q->select('id','name')])
            // Inclusivo siempre, funcione con DATE o DATETIME
            ->whereDate('fecha', '>=', $weekStart->toDateString())
            ->whereDate('fecha', '<=', $weekEnd->toDateString())
            ->orderBy('fecha')
            ->orderBy('hora_inicio');

        if ($doctorId) {
            $query->where('doctor_id', $doctorId);
        }

        $horarios = $query->get();

        $doctores = User::whereHas('roles', fn($q) => $q->where('name','doctor'))
            ->where('active', true)
            ->orderBy('name')
            ->get(['id','name']);

        return view('admin.horarios.index', compact('horarios','doctores','doctorId','weekStart','weekEnd'));
    }

    /** CREATE */
    public function create()
    {
        $doctores = User::whereHas('roles', fn($q) => $q->where('name','doctor'))
            ->where('active', true)
            ->orderBy('name')
            ->get(['id','name']);

        return view('admin.horarios.create', compact('doctores'));
    }

    /** STORE: rango + días (franja única o por día) */
    public function store(Request $request)
    {
        $modoPerDia = !$request->boolean('misma_franja');

        $base = [
            'doctor_id'    => ['required','exists:users,id'],
            'fecha_inicio' => ['required','date'],
            'fecha_fin'    => ['required','date','after_or_equal:fecha_inicio'],
            'dias'         => ['required','array','min:1'],
            'dias.*'       => ['integer','between:1,7'],
        ];

        $rules = $modoPerDia
            ? $base + [
                'horas'           => ['required','array'],
                'horas.*.inicio'  => ['nullable','date_format:H:i'],
                'horas.*.fin'     => ['nullable','date_format:H:i'],
              ]
            : $base + [
                'hora_inicio' => ['required','date_format:H:i'],
                'hora_fin'    => ['required','date_format:H:i','after:hora_inicio'],
              ];

        $data = $request->validate($rules, [
            'doctor_id.required' => 'Seleccione un doctor.',
            'dias.required'      => 'Seleccione al menos un día.',
        ]);

        if (!$modoPerDia) {
            if (!$this->isThirtyStep($data['hora_inicio']) || !$this->isThirtyStep($data['hora_fin'])) {
                return back()->withErrors(['hora_fin' => 'Usa intervalos de 30 minutos.'])->withInput();
            }
        }

        // Rango inclusivo y sin sesgos de hora
        $inicio  = Carbon::parse($data['fecha_inicio'])->startOfDay();
        $fin     = Carbon::parse($data['fecha_fin'])->endOfDay();
        $diasSel = collect($data['dias'])->map(fn($d)=>(int)$d)->unique();

        $creados = 0;
        $omitidos = 0;

        DB::transaction(function () use ($modoPerDia, $data, $inicio, $fin, $diasSel, &$creados, &$omitidos) {
            $cursor = $inicio->copy();

            while ($cursor->lte($fin)) {
                $dow = $cursor->isoWeekday(); // 1..7 (Dom=7)

                if (!$diasSel->contains($dow)) {
                    $cursor->addDay();
                    continue;
                }

                if ($modoPerDia) {
                    $par = $data['horas'][$dow] ?? null;
                    $hi = $par['inicio'] ?? null;
                    $hf = $par['fin'] ?? null;

                    if (!$hi || !$hf || $hf <= $hi) {
                        $cursor->addDay();
                        continue;
                    }
                    if (!$this->isThirtyStep($hi) || !$this->isThirtyStep($hf)) {
                        $cursor->addDay();
                        continue;
                    }
                } else {
                    $hi = $data['hora_inicio'];
                    $hf = $data['hora_fin'];
                }

                $fecha = $cursor->toDateString();

                if ($this->overlapExists((int)$data['doctor_id'], $fecha, $hi, $hf)) {
                    $omitidos++;
                } else {
                    Horario::create([
                        'doctor_id'   => (int)$data['doctor_id'],
                        'fecha'       => $fecha,          // idealmente columna DATE
                        'hora_inicio' => $hi,
                        'hora_fin'    => $hf,
                    ]);
                    $creados++;
                }

                $cursor->addDay();
            }
        });

        $week = $inicio->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

        return redirect()->route('admin.horarios.index', [
                'doctor_id' => $data['doctor_id'],
                'week'      => $week,
            ])->with('success', "Horarios creados: {$creados}. Omitidos: {$omitidos}.");
    }

    /** EDIT */
    public function edit(Horario $horario)
    {
        $doctores = User::whereHas('roles', fn($q) => $q->where('name','doctor'))
            ->where('active', true)
            ->orderBy('name')
            ->get(['id','name']);

        return view('admin.horarios.edit', compact('horario','doctores'));
    }

    /** UPDATE */
    public function update(Request $request, Horario $horario)
    {
        $data = $request->validate([
            'doctor_id'   => ['required','exists:users,id'],
            'fecha'       => ['required','date'],
            'hora_inicio' => ['required','date_format:H:i'],
            'hora_fin'    => ['required','date_format:H:i','after:hora_inicio'],
        ]);

        if (!$this->isThirtyStep($data['hora_inicio']) || !$this->isThirtyStep($data['hora_fin'])) {
            return back()->withErrors(['hora_fin' => 'Usa intervalos de 30 minutos.'])->withInput();
        }

        if ($this->overlapExists((int)$data['doctor_id'], (string)$data['fecha'], (string)$data['hora_inicio'], (string)$data['hora_fin'], $horario->id)) {
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
        if (!str_contains($hhmm, ':')) return false;
        [, $m] = explode(':', $hhmm, 2);
        return ((int) $m) % 30 === 0;
    }

    private function overlapExists(int $doctorId, string $fecha, string $hi, string $hf, ?int $ignoreId = null): bool
    {
        return Horario::where('doctor_id', $doctorId)
            ->whereDate('fecha', $fecha) // robusto para DATE/DATETIME
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->where(function ($q) use ($hi, $hf) {
                $q->whereBetween('hora_inicio', [$hi, $hf])
                  ->orWhereBetween('hora_fin',   [$hi, $hf])
                  ->orWhere(fn($qq) => $qq->where('hora_inicio','<=',$hi)->where('hora_fin','>=',$hf));
            })->exists();
    }
}
