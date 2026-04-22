<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Horario;
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

    public function store(Request $r)
    {
        $data = $r->validate([
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'intervalo_minutos' => ['required', 'integer', 'in:10,15,20,30,45,60'],
        ]);

        Horario::create([
            'doctor_id' => Auth::id(),
            'fecha' => $data['fecha'],
            'hora_inicio' => $data['hora_inicio'],
            'hora_fin' => $data['hora_fin'],
            'intervalo_minutos' => $data['intervalo_minutos'],
        ]);

        return back()->with('success', 'Horario creado.');
    }

    public function edit(Horario $horario)
    {
        abort_unless($horario->doctor_id === Auth::id(), 403);

        return view('doctor.horario.edit', ['h' => $horario]);
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

        $horario->update($data);

        return redirect()->route('doctor.horario.index')->with('success', 'Horario actualizado.');
    }

    public function destroy(Horario $horario)
    {
        abort_unless($horario->doctor_id === Auth::id(), 403);
        $horario->delete();

        return back()->with('success', 'Horario eliminado.');
    }

    public function generarRango(Request $r)
    {
        $data = $r->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'dias' => ['required', 'array', 'min:1'],
            'dias.*' => ['integer', 'between:1,7'], // 1=Lun ... 7=Dom
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'intervalo_minutos' => ['required', 'integer', 'in:10,15,20,30,45,60'],
            'sobrescribir' => ['required', 'boolean'],
        ]);

        $doctorId = Auth::id();
        $ini = Carbon::parse($data['desde']);
        $fin = Carbon::parse($data['hasta']);
        $dias = collect($data['dias'])->map(fn ($d) => (int) $d)->all();
        $n = 0;

        DB::transaction(function () use ($doctorId, $ini, $fin, $dias, $data, &$n) {
            for ($d = $ini->copy(); $d->lte($fin); $d->addDay()) {
                if (! in_array((int) $d->isoWeekday(), $dias, true)) {
                    continue;
                }

                if (! empty($data['sobrescribir'])) {
                    Horario::where('doctor_id', $doctorId)->whereDate('fecha', $d->toDateString())->delete();
                }

                Horario::firstOrCreate([
                    'doctor_id' => $doctorId,
                    'fecha' => $d->toDateString(),
                    'hora_inicio' => $data['hora_inicio'],
                    'hora_fin' => $data['hora_fin'],
                ], [
                    'intervalo_minutos' => $data['intervalo_minutos'],
                ]);
                $n++;
            }
        });

        return back()->with('success',"Generados {$n} días.");
    }
}
