<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cita;
use App\Models\User;
use App\Models\Especialidad;
use App\Models\Horario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Events\CitaAgendada;
use App\Events\CitaAtendida;
use Carbon\Carbon;

class CitaController extends Controller
{
    /** ======================= UTIL ======================= */
    /** Normaliza una hora DB que puede venir como H:i o H:i:s */
    protected function parseHoraFlexible(string $h): Carbon
    {
        return strlen($h) >= 8
            ? Carbon::createFromFormat('H:i:s', $h)
            : Carbon::createFromFormat('H:i', $h);
    }

    /** ======================= PACIENTE: LISTADO ======================= */
    public function index(Request $request)
    {
        $userId = Auth::id();
        $q = trim((string) $request->get('q', ''));
        $estado = (string) $request->get('estado', '');
        $validStates = ['pendiente','confirmada','cancelada','realizada'];
        $qNorm = mb_strtolower($q);

        $totalesPorEstado = Cita::where('paciente_id', $userId)
            ->select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $citas = Cita::with(['doctor:id,name', 'especialidad:id,nombre'])
            ->where('paciente_id', $userId)
            ->when($q !== '', function ($query) use ($qNorm) {
                $query->where(function ($qq) use ($qNorm) {
                    $qq->whereHas('doctor', function ($dq) use ($qNorm) {
                        $dq->whereRaw('LOWER(name) LIKE ?', ['%'.$qNorm.'%']);
                    })->orWhereHas('especialidad', function ($eq) use ($qNorm) {
                        $eq->whereRaw('LOWER(nombre) LIKE ?', ['%'.$qNorm.'%']);
                    });
                });
            })
            ->when(in_array($estado, $validStates, true), function ($query) use ($estado) {
                $query->where('estado', $estado);
            })
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->paginate(10)
            ->withQueryString();

        $emptyMessage = null;

        if ($citas->count() === 0) {
            if ($q !== '' && in_array($estado, $validStates, true)) {
                $doctorExists = User::whereHas('roles', fn($r) => $r->where('name','doctor'))
                    ->whereRaw('LOWER(name) LIKE ?', ['%'.$qNorm.'%'])->exists();
                $especialidadExists = Especialidad::whereRaw('LOWER(nombre) LIKE ?', ['%'.$qNorm.'%'])->exists();

                if ($especialidadExists && !$doctorExists) {
                    $emptyMessage = 'No tienes cita en la especialidad "'.$q.'" con estado '.ucfirst($estado).'.';
                } elseif ($doctorExists && !$especialidadExists) {
                    $emptyMessage = 'No tienes cita con el doctor "'.$q.'" en estado '.ucfirst($estado).'.';
                } elseif (!$doctorExists && !$especialidadExists) {
                    $emptyMessage = 'No existe la especialidad "'.$q.'" ni un doctor con ese nombre en estado '.ucfirst($estado).'.';
                } else {
                    $emptyMessage = 'No hay coincidencias para "'.$q.'" en estado '.ucfirst($estado).'.';
                }
            } elseif ($q !== '') {
                $doctorExists = User::whereHas('roles', fn($r) => $r->where('name','doctor'))
                    ->whereRaw('LOWER(name) LIKE ?', ['%'.$qNorm.'%'])->exists();
                $especialidadExists = Especialidad::whereRaw('LOWER(nombre) LIKE ?', ['%'.$qNorm.'%'])->exists();

                if ($especialidadExists && !$doctorExists) {
                    $emptyMessage = 'No tienes cita agendada en la especialidad "'.$q.'".';
                } elseif ($doctorExists && !$especialidadExists) {
                    $emptyMessage = 'No tienes cita agendada con el doctor "'.$q.'".';
                } elseif (!$doctorExists && !$especialidadExists) {
                    $emptyMessage = 'No existe la especialidad "'.$q.'" ni un doctor con ese nombre.';
                } else {
                    $emptyMessage = 'No tienes citas que coincidan con "'.$q.'".';
                }
            } elseif (in_array($estado, $validStates, true)) {
                $emptyMessage = 'No tienes citas en estado '.ucfirst($estado).'.';
            } else {
                $emptyMessage = 'No tienes citas registradas.';
            }
        }

        return view('paciente.citas', compact('citas', 'emptyMessage', 'totalesPorEstado'));
    }

    /** ======================= PACIENTE: CREAR ======================= */
    public function create()
    {
        $doctores = User::whereHas('roles', fn($q) => $q->where('name','doctor'))->get();
        $especialidades = Especialidad::all();
        return view('paciente.crear-cita', compact('doctores', 'especialidades'));
    }

    public function store(Request $request)
    {
        $request->validate(
            [
                'doctor_id'        => 'required|exists:users,id',
                'especialidad_id'  => 'required|exists:especialidades,id',
                'fecha'            => 'required|date',
                'hora'             => 'required|date_format:H:i',
            ],
            [
                'doctor_id.required'       => 'Seleccione un doctor.',
                'doctor_id.exists'         => 'El doctor seleccionado no existe.',
                'especialidad_id.required' => 'Seleccione una especialidad.',
                'especialidad_id.exists'   => 'La especialidad seleccionada no existe.',
                'fecha.required'           => 'Seleccione una fecha.',
                'fecha.date'               => 'La fecha no es válida.',
                'hora.required'            => 'Ingrese una hora.',
                'hora.date_format'         => 'Formato de hora inválido. Use HH:MM.',
            ]
        );

        $fechaHora = Carbon::createFromFormat('Y-m-d H:i', $request->fecha.' '.$request->hora, 'America/Guayaquil');
        $ahora = now('America/Guayaquil');
        if ($fechaHora->lessThanOrEqualTo($ahora)) {
            return back()->withErrors(['error' => 'La fecha y hora debe ser posterior al momento actual.'])->withInput();
        }

        $slot = Carbon::createFromFormat('H:i', $request->hora);
        if ($slot->minute % 30 !== 0) {
            return back()->withErrors(['hora' => 'La hora debe estar en intervalos de 30 minutos (por ejemplo 08:00, 08:30, 09:00).'])->withInput();
        }

        // DISPONIBILIDAD
        $hayHorario = Horario::where('doctor_id', $request->doctor_id)
            ->whereDate('fecha', $request->fecha)
            ->whereTime('hora_inicio', '<=', $slot->format('H:i:s'))
            ->whereTime('hora_fin',   '>',  $slot->format('H:i:s'))
            ->exists();

        if (!$hayHorario) {
            return back()->withErrors(['error' => 'No hay horario disponible del doctor para ese día y hora.'])->withInput();
        }

        // choques +/- 30m
        $citasMismoDia = Cita::where('doctor_id', $request->doctor_id)
            ->whereDate('fecha', $request->fecha)
            ->where('activo', true)
            ->get(['id','hora']);

        $existe = $citasMismoDia->contains(function ($c) use ($slot) {
            $h = strlen($c->hora) >= 5 ? substr($c->hora, 0, 5) : $c->hora;
            $otro = Carbon::createFromFormat('H:i', $h);
            return $otro->diffInMinutes($slot) <= 29;
        });

        if ($existe) {
            return back()->withErrors(['error' => 'El doctor ya tiene una cita en ese horario o en un rango de 30 minutos.'])->withInput();
        }

        try {
            DB::beginTransaction();

            $cita = Cita::create([
                'paciente_id'     => Auth::id(),
                'doctor_id'       => $request->doctor_id,
                'especialidad_id' => $request->especialidad_id,
                'fecha'           => $request->fecha,
                'hora'            => $slot->format('H:i:00'),
                'estado'          => Cita::ESTADO_PENDIENTE,
                'activo'          => true,
            ]);

            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'El doctor ya tiene una cita exactamente a esa hora.'])->withInput();
        }

        event(new CitaAgendada($cita));
        EnviarConfirmacionCitaJob::dispatch($cita);

        return redirect()->route('paciente.citas')->with('success', 'Cita creada con éxito. Confirmación enviada y doctor notificado.');
    }

    public function cancelar($id)
    {
        $cita = Cita::findOrFail($id);

        if ($cita->paciente_id != Auth::id()) {
            return back()->with('error', 'No puedes cancelar esta cita.');
        }

        if (in_array($cita->estado, [Cita::ESTADO_CANCELADA, Cita::ESTADO_REALIZADA])) {
            return back()->with('error', 'Esta cita ya no puede ser cancelada.');
        }

        $cita->estado = Cita::ESTADO_CANCELADA;
        $cita->activo = false;
        $cita->save();

        NotificarCambioEstadoCitaJob::dispatch($cita, 'cancelada', 'paciente');

        return back()->with('success', 'Cita cancelada.');
    }

    /** ======================= PACIENTE: EDITAR/ACTUALIZAR ======================= */
    public function edit($id)
    {
        $cita = Cita::with(['doctor','especialidad'])->findOrFail($id);

        if ($cita->paciente_id != Auth::id()) {
            return back()->with('error', 'No puedes editar esta cita.');
        }

        if (in_array($cita->estado, [Cita::ESTADO_CANCELADA, Cita::ESTADO_REALIZADA])) {
            return back()->with('error', 'Esta cita no puede ser modificada.');
        }

        return view('paciente.editar-cita', compact('cita'));
    }

    public function actualizar(Request $request, $id)
    {
        $cita = Cita::findOrFail($id);

        if ($cita->paciente_id != Auth::id()) {
            return back()->with('error', 'No puedes modificar esta cita.');
        }

        $request->validate(
            [
                'fecha' => 'required|date',
                'hora'  => 'required|date_format:H:i',
            ],
            [
                'fecha.required'   => 'Seleccione una fecha.',
                'fecha.date'       => 'La fecha no es válida.',
                'hora.required'    => 'Ingrese una hora.',
                'hora.date_format' => 'Formato de hora inválido. Use HH:MM.',
            ]
        );

        $fechaHora = Carbon::createFromFormat('Y-m-d H:i', $request->fecha.' '.$request->hora, 'America/Guayaquil');
        $ahora = now('America/Guayaquil');
        if ($fechaHora->lessThanOrEqualTo($ahora)) {
            return back()->withErrors(['error' => 'La fecha y hora debe ser posterior al momento actual.'])->withInput();
        }

        $slot = Carbon::createFromFormat('H:i', $request->hora);
        if ($slot->minute % 30 !== 0) {
            return back()->withErrors(['hora' => 'La hora debe estar en intervalos de 30 minutos (por ejemplo 08:00, 08:30, 09:00).'])->withInput();
        }

        $hayHorario = Horario::where('doctor_id', $cita->doctor_id)
            ->whereDate('fecha', $request->fecha)
            ->whereTime('hora_inicio', '<=', $slot->format('H:i:s'))
            ->whereTime('hora_fin',   '>',  $slot->format('H:i:s'))
            ->exists();

        if (!$hayHorario) {
            return back()->withErrors(['error' => 'No hay horario disponible del doctor para ese día y hora.'])->withInput();
        }

        $citasMismoDia = Cita::where('doctor_id', $cita->doctor_id)
            ->whereDate('fecha', $request->fecha)
            ->where('activo', true)
            ->where('id', '!=', $cita->id)
            ->get(['id','hora']);

        $existe = $citasMismoDia->contains(function ($c) use ($slot) {
            $h = strlen($c->hora) >= 5 ? substr($c->hora, 0, 5) : $c->hora;
            $otro = Carbon::createFromFormat('H:i', $h);
            return $otro->diffInMinutes($slot) <= 29;
        });

        if ($existe) {
            return back()->withErrors(['error' => 'El doctor ya tiene una cita en ese horario o en un rango de 30 minutos.'])->withInput();
        }

        try {
            DB::beginTransaction();

            $cita->update([
                'fecha'  => $request->fecha,
                'hora'   => $slot->format('H:i:00'),
                'estado' => Cita::ESTADO_PENDIENTE,
                'activo' => true,
            ]);

            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'El doctor ya tiene una cita exactamente a esa hora.'])->withInput();
        }

        NotificarCambioEstadoCitaJob::dispatch($cita, 'reagendada', 'paciente');

        return redirect()->route('paciente.citas')->with('success', 'Cita reagendada.');
    }

    /** ======================= DOCTOR: LISTADO ======================= */
    public function indexDoctor()
{
    $doctorId = Auth::id();

    // Para la tabla
    $citas = \App\Models\Cita::where('doctor_id', $doctorId)
        ->with(['paciente','especialidad','receta'])
        ->orderByRaw("
            CASE estado
            WHEN 'confirmada' THEN 1
            WHEN 'pendiente'  THEN 2
            WHEN 'cancelada'  THEN 3
            WHEN 'realizada'  THEN 4
            ELSE 5
            END
        ")
        ->orderBy('fecha','asc')
        ->orderBy('hora','asc')
        ->get();

    // Helper robusto fecha+hora
    $dt = function($fecha, $hora) {
        $d = \Carbon\Carbon::parse($fecha, 'America/Guayaquil'); // fecha puede venir con o sin hora
        if (!empty($hora)) {
            $hhmm = substr($hora, 0, 5);
            [$H,$M] = array_map('intval', explode(':', $hhmm));
            $d->setTime($H, $M, 0);
        }
        return $d;
    };
    $pairKey = fn($p,$d) => $p.'|'.$d;

    // Todas las citas del doctor, ordenadas por fecha+hora, agrupadas por (paciente,doctor)
    $todasPorPar = \App\Models\Cita::where('doctor_id', $doctorId)
        ->get(['id','paciente_id','doctor_id','fecha','hora','estado'])
        ->sortBy(fn($c) => $dt($c->fecha, $c->hora)->format('Y-m-d H:i:s'))
        ->groupBy(fn($c) => $pairKey($c->paciente_id, $c->doctor_id));

    // id_de_realizada -> cita inmediatamente posterior (independiente del estado actual de esa posterior)
    $mapProxima = [];

    foreach ($todasPorPar as $lista) {
        $count = $lista->count();
        if ($count < 2) continue;

        // Precalcular datetimes
        $fechas = [];
        foreach ($lista as $i => $c) {
            $fechas[$i] = $dt($c->fecha, $c->hora);
        }

        // Para cada realizada R en posición i, buscar el primer j>i
        foreach ($lista as $i => $c) {
            if ($c->estado !== \App\Models\Cita::ESTADO_REALIZADA) continue;

            $nextIdx = null;
            for ($j = $i + 1; $j < $count; $j++) {
                // primera posterior estricta
                if ($fechas[$j]->gt($fechas[$i])) { $nextIdx = $j; break; }
            }
            if ($nextIdx !== null) {
                $mapProxima[$c->id] = $lista[$nextIdx];
            }
        }
    }

    // Adjuntar en filas de la tabla
    foreach ($citas as $c) {
        $c->proxima_cita = $mapProxima[$c->id] ?? null;
    }

    return view('doctor.citas', compact('citas'));
}



    public function aceptar($id)
    {
        $cita = Cita::findOrFail($id);

        if ($cita->doctor_id != Auth::id()) {
            return back()->with('error', 'No puedes aceptar esta cita.');
        }

        if ($cita->estado !== Cita::ESTADO_PENDIENTE) {
            return back()->with('error', 'Solo puedes aceptar citas pendientes.');
        }

        $cita->estado = Cita::ESTADO_CONFIRMADA;
        $cita->activo = true;
        $cita->save();

        NotificarCambioEstadoCitaJob::dispatch($cita, 'aceptada', 'doctor');

        return back()->with('success', 'Cita confirmada.');
    }

    public function rechazar($id)
    {
        $cita = Cita::findOrFail($id);

        if ($cita->doctor_id != Auth::id()) {
            return back()->with('error', 'No puedes rechazar esta cita.');
        }

        if ($cita->estado !== Cita::ESTADO_PENDIENTE) {
            return back()->with('error', 'Solo puedes rechazar citas pendientes.');
        }

        $cita->estado = Cita::ESTADO_CANCELADA;
        $cita->activo = false;
        $cita->save();

        NotificarCambioEstadoCitaJob::dispatch($cita, 'cancelada', 'doctor');

        return back()->with('success', 'Cita rechazada.');
    }

    public function realizar($id)
    {
        $cita = Cita::findOrFail($id);

        if ($cita->doctor_id != Auth::id()) {
            return back()->with('error', 'No puedes marcar esta cita.');
        }

        if ($cita->estado !== Cita::ESTADO_CONFIRMADA) {
            return back()->with('error', 'Solo puedes marcar como realizada citas confirmadas.');
        }

        $cita->estado = Cita::ESTADO_REALIZADA;
        $cita->activo = true;
        $cita->save();

        event(new CitaAtendida($cita));

        return back()->with('success', 'Cita marcada como realizada.');
    }

    public function citasConfirmadas()
    {
        $citas = Cita::where('estado', Cita::ESTADO_CONFIRMADA)
            ->with('paciente:id,name')
            ->get(['id', 'paciente_id', 'estado']);

        $pacientes = $citas->pluck('paciente.name');

        return response()->json($pacientes);
    }

    /**
     * API: slots disponibles de 30 min para un doctor y fecha.
     * GET /api/doctor/{doctor}/fecha/{fecha}/slots
     */
    public function slotsDisponibles($doctor, $fecha)
    {
        $horarios = Horario::where('doctor_id', $doctor)
            ->whereDate('fecha', $fecha)
            ->orderBy('hora_inicio')
            ->get(['hora_inicio','hora_fin']);

        if ($horarios->isEmpty()) {
            return response()->json(['slots' => []]);
        }

        $ocupadas = Cita::where('doctor_id', $doctor)
            ->whereDate('fecha', $fecha)
            ->where('activo', true)
            ->pluck('hora')
            ->map(fn($h) => substr($h, 0, 5))
            ->toArray();

        $slots = [];
        $ahora = now('America/Guayaquil');
        $esHoy = $fecha === $ahora->format('Y-m-d');
        $limiteHoy = Carbon::createFromFormat('H:i', $ahora->format('H:i'));

        foreach ($horarios as $h) {
            $ini = $this->parseHoraFlexible($h->hora_inicio);
            $fin = $this->parseHoraFlexible($h->hora_fin);

            for ($t = $ini->copy(); $t->lt($fin); $t->addMinutes(30)) {
                if ($esHoy && $t->lte($limiteHoy)) continue;

                $hhmm = $t->format('H:i');

                $choca = collect($ocupadas)->contains(function ($o) use ($hhmm) {
                    $a = Carbon::createFromFormat('H:i', $o);
                    $b = Carbon::createFromFormat('H:i', $hhmm);
                    return $a->diffInMinutes($b) <= 29;
                });
                if ($choca) continue;

                $slots[] = $hhmm;
            }
        }

        $slots = collect($slots)->unique()->sort()->values()->all();
        return response()->json(['slots' => $slots]);
    }

    /** ======================= DOCTOR: DISPONIBILIDAD + PROXIMA CITA (TOAST) ======================= */

    /** GET /doctor/disponibilidad/check  */
    public function checkDisponibilidad(Request $r)
    {
        $doctorId = (int)Auth::id();
        $ref = $r->date('fecha_preferida') ?: now('America/Guayaquil');

        $tiene = Horario::where('doctor_id',$doctorId)
            ->whereBetween('fecha', [
                Carbon::parse($ref)->startOfWeek()->toDateString(),
                Carbon::parse($ref)->endOfWeek()->toDateString(),
            ])->exists();

        return $tiene
            ? response()->json(['ok'=>true])
            : response()->json(['ok'=>false,'reason'=>'sin_horario']);
    }

    /**
     * POST /doctor/citas/{cita}/proxima/planificar
     * Crea la próxima cita con fecha/hora elegidas por el doctor.
     */
    public function proximaPlanificada(Request $request, Cita $cita)
    {
        if ($cita->doctor_id !== Auth::id()) abort(403);

        $request->validate(
            ['fecha'=>'required|date','hora'=>'required|date_format:H:i'],
            ['fecha.required'=>'Seleccione una fecha.','hora.required'=>'Seleccione una hora.']
        );

        $slot = Carbon::createFromFormat('H:i', $request->hora);
        if ($slot->minute % 30 !== 0) {
            return response()->json(['ok'=>false,'msg'=>'La hora debe ser 00 o 30 minutos.'], 422);
        }

        $fechaHora = Carbon::createFromFormat('Y-m-d H:i', $request->fecha.' '.$request->hora, 'America/Guayaquil');
        if ($fechaHora->lessThanOrEqualTo(now('America/Guayaquil'))) {
            return response()->json(['ok'=>false,'msg'=>'Debe ser posterior al momento actual.'], 422);
        }

        $hayHorario = Horario::where('doctor_id', $cita->doctor_id)
            ->whereDate('fecha', $request->fecha)
            ->whereTime('hora_inicio', '<=', $slot->format('H:i:00'))
            ->whereTime('hora_fin', '>',  $slot->format('H:i:00'))
            ->exists();

        if (!$hayHorario) {
            return response()->json(['ok'=>false,'reason'=>'sin_horario','msg'=>'No hay horario para ese día/hora.'], 422);
        }

        $ocupadas = Cita::where('doctor_id', $cita->doctor_id)
            ->whereDate('fecha', $request->fecha)
            ->where('activo', true)
            ->get(['hora'])
            ->map(fn($r)=>substr($r->hora,0,5));

        $choca = $ocupadas->contains(function($o) use($slot){
            $a = Carbon::createFromFormat('H:i',$o);
            return $a->diffInMinutes($slot) <= 29;
        });
        if ($choca) {
            return response()->json(['ok'=>false,'msg'=>'Choque con otra cita en ±30 minutos.'], 422);
        }

        DB::transaction(function() use ($cita, $request, $slot) {
            $nueva = Cita::create([
                'paciente_id'     => $cita->paciente_id,
                'doctor_id'       => $cita->doctor_id,
                'especialidad_id' => $cita->especialidad_id,
                'fecha'           => $request->fecha,
                'hora'            => $slot->format('H:i:00'),
                'estado'          => Cita::ESTADO_PENDIENTE,
                'activo'          => true,
            ]);
            event(new CitaAgendada($nueva));
            EnviarConfirmacionCitaJob::dispatch($nueva);
        });

        return response()->json(['ok'=>true]);
    }
}
