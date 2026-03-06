<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cita;
use App\Models\User;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\LaboratorioOrden;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Events\CitaAgendada;
use App\Events\CitaAtendida;
use Carbon\Carbon;
use App\Services\CitaNoShowService;
use App\Services\PriorityEvaluator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Services\PagoService;
use App\Support\ValidationRules;

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

    protected function horarioParaSlot(int $profesionalId, string $fecha, Carbon $slot): ?Horario
    {
        return Horario::where('doctor_id', $profesionalId)
            ->whereDate('fecha', $fecha)
            ->whereTime('hora_inicio', '<=', $slot->format('H:i:s'))
            ->whereTime('hora_fin', '>', $slot->format('H:i:s'))
            ->orderBy('hora_inicio')
            ->first();
    }

    protected function intervaloHorario(Horario $horario): int
    {
        return max(1, (int) ($horario->intervalo_minutos ?: 30));
    }

    protected function slotAlineadoConHorario(Horario $horario, string $fecha, Carbon $slot, string $tz = 'America/Guayaquil'): bool
    {
        $inicio = Carbon::parse($fecha.' '.substr((string) $horario->hora_inicio, 0, 5), $tz);
        $seleccionado = Carbon::parse($fecha.' '.$slot->format('H:i'), $tz);

        return $inicio->diffInMinutes($seleccionado) % $this->intervaloHorario($horario) === 0;
    }

    /** ======================= PACIENTE: LISTADO ======================= */
    public function index(Request $request)
    {
        app(CitaNoShowService::class)->marcarVencidas();
        $userId = Auth::id();
        $q = trim((string) $request->get('q', ''));
        $estado = (string) $request->get('estado', '');
        if ($estado === 'all') {
            $estado = '';
        }
        $validStates = ['pendiente','confirmada','cancelada','realizada','no_se_presento'];
        $estadoLabel = match ($estado) {
            Cita::ESTADO_PENDIENTE => 'En revision',
            Cita::ESTADO_NO_SE_PRESENTO => 'No se presento',
            default => ucfirst($estado),
        };
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
                        $dq->whereRaw('LOWER(name) LIKE ', ['%'.$qNorm.'%']);
                    })->orWhereHas('especialidad', function ($eq) use ($qNorm) {
                        $eq->whereRaw('LOWER(nombre) LIKE ', ['%'.$qNorm.'%']);
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
                    ->whereRaw('LOWER(name) LIKE ', ['%'.$qNorm.'%'])->exists();
                $especialidadExists = Especialidad::whereRaw('LOWER(nombre) LIKE ', ['%'.$qNorm.'%'])->exists();

                if ($especialidadExists && !$doctorExists) {
                $emptyMessage = 'No tienes cita en la especialidad "'.$q.'" con estado '.$estadoLabel.'.';
                } elseif ($doctorExists && !$especialidadExists) {
                $emptyMessage = 'No tienes cita con el doctor "'.$q.'" en estado '.$estadoLabel.'.';
                } elseif (!$doctorExists && !$especialidadExists) {
                $emptyMessage = 'No existe la especialidad "'.$q.'" ni un doctor con ese nombre en estado '.$estadoLabel.'.';
                } else {
                $emptyMessage = 'No hay coincidencias para "'.$q.'" en estado '.$estadoLabel.'.';
                }
            } elseif ($q !== '') {
                $doctorExists = User::whereHas('roles', fn($r) => $r->where('name','doctor'))
                    ->whereRaw('LOWER(name) LIKE ', ['%'.$qNorm.'%'])->exists();
                $especialidadExists = Especialidad::whereRaw('LOWER(nombre) LIKE ', ['%'.$qNorm.'%'])->exists();

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
                $emptyMessage = 'No tienes citas en estado '.$estadoLabel.'.';
            } else {
                $emptyMessage = 'No tienes citas registradas.';
            }
        }

        $bloqueoPagosPendientes = app(PagoService::class)->pacienteTieneBloqueo($userId);

        return view('paciente.citas', compact('citas', 'emptyMessage', 'totalesPorEstado', 'bloqueoPagosPendientes'));
    }

    /** ======================= PACIENTE: CREAR ======================= */
    protected function laboratorioEspecialidadId(): int
    {
        return Especialidad::where('nombre', 'Laboratorio Clinico')->value('id');
    }

    protected function laboratorioPreparacionGenerica(): array
    {
        return [
            'ayuno' => 'Confirmar en la orden medica.',
            'agua' => 'Agua permitida salvo indicacion.',
            'horario' => 'Preferible en la mañana.',
        ];
    }

    protected function laboratorioCatalogoExamenes(): array
    {
        return [
            [
                'value' => 'Hemograma completo',
                'label' => 'Hemograma completo',
                'prep' => [
                    'ayuno' => 'No requiere ayuno.',
                    'agua' => 'Agua permitida.',
                    'horario' => 'Preferible en la mañana.',
                ],
            ],
            [
                'value' => 'Perfil lipidico',
                'label' => 'Perfil lipidico',
                'prep' => [
                    'ayuno' => 'Ayuno de 9 a 12 horas.',
                    'agua' => 'Agua permitida.',
                    'horario' => 'Ideal en la mañana.',
                ],
            ],
            [
                'value' => 'Glucosa en ayunas',
                'label' => 'Glucosa en ayunas',
                'prep' => [
                    'ayuno' => 'Ayuno de 8 horas.',
                    'agua' => 'Agua permitida.',
                    'horario' => 'Preferible antes de las 09:00.',
                ],
            ],
            [
                'value' => 'Uroanalisis',
                'label' => 'Uroanalisis',
                'prep' => [
                    'ayuno' => 'No requiere ayuno.',
                    'agua' => 'Evitar exceso de liquidos antes de la toma.',
                    'horario' => 'Primera orina de la mañana.',
                ],
            ],
            [
                'value' => 'Otro examen',
                'label' => 'Otro examen (segun orden medica)',
                'prep' => $this->laboratorioPreparacionGenerica(),
            ],
        ];
    }

    protected function laboratorioPreparacionPorTipo(string $tipoExamen): ?string
    {
        foreach ($this->laboratorioCatalogoExamenes() as $examen) {
            if ($examen['value'] === $tipoExamen) {
                return $this->formatearPreparacion($examen['prep'] ?? []);
            }
        }

        return null;
    }

    protected function formatearPreparacion(array $prep): string
    {
        $partes = [];
        if (!empty($prep['ayuno'])) {
            $partes[] = 'Ayuno: '.$prep['ayuno'];
        }
        if (!empty($prep['agua'])) {
            $partes[] = 'Agua: '.$prep['agua'];
        }
        if (!empty($prep['horario'])) {
            $partes[] = 'Horario recomendado: '.$prep['horario'];
        }

        return implode(' | ', $partes);
    }

    public function create(Request $request)
    {
        $doctores = User::whereHas('roles', fn($q) => $q->where('name','doctor'))->get();
        $especialidades = Especialidad::all();
        $prefEspecialidad = $request->get('especialidad');
        $laboratorioId = $this->laboratorioEspecialidadId();
        $labExamenes = $this->laboratorioCatalogoExamenes();
        return view('paciente.crear-cita', compact('doctores', 'especialidades', 'prefEspecialidad', 'laboratorioId', 'labExamenes'));
    }

    public function store(Request $request, PriorityEvaluator $priorityEvaluator)
    {
        if (app(PagoService::class)->pacienteTieneBloqueo((int) Auth::id())) {
            return back()->withErrors(['error' => PagoService::MENSAJE_BLOQUEO])->withInput();
        }

        $request->validate(
            [
                'doctor_id'        => 'required|exists:users,id',
                'especialidad_id'  => 'required|exists:especialidades,id',
                'fecha'            => 'required|date',
                'hora'             => 'required|date_format:H:i',
                'motivo_consulta'  => ValidationRules::motivoConsulta(),
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
                'motivo_consulta.required' => 'El motivo de consulta es obligatorio.',
                'motivo_consulta.min'      => 'El motivo debe tener al menos 3 caracteres.',
                'motivo_consulta.max'      => 'El motivo no puede superar 80 caracteres.',
                'motivo_consulta.regex'    => 'El motivo debe ir en una sola linea.',
            ]
        );

        $motivoConsulta = $priorityEvaluator->sanitizeMotivo($request->input('motivo_consulta'));

        $labId = $this->laboratorioEspecialidadId();
        $isLab = $labId && (int)$request->especialidad_id === (int)$labId;

        if ($isLab) {
            $request->validate(
                [
                    'tipo_examen'   => 'required|string|max:255',
                    'prioridad'     => 'required|in:normal,urgente',
                    'indicaciones'  => 'prohibited',
                    'preparacion'   => 'prohibited',
                ],
                [
                    'tipo_examen.required' => 'Indica el tipo de examen.',
                ]
            );

            $preparacion = $this->laboratorioPreparacionPorTipo($request->tipo_examen)
                ?: $this->formatearPreparacion($this->laboratorioPreparacionGenerica());

            $labUserOk = User::where('id', $request->doctor_id)
                ->whereHas('roles', fn($q) => $q->where('name', 'laboratorio'))
                ->exists();
            if (!$labUserOk) {
                return back()->withErrors(['doctor_id' => 'Selecciona un laboratorio valido.'])->withInput();
            }

            $prioridad = $request->prioridad ?: 'normal';
        }

        $ahora = now('America/Guayaquil');
        $fechaHora = Carbon::createFromFormat('Y-m-d H:i', $request->fecha.' '.$request->hora, 'America/Guayaquil');
        if ($fechaHora->lt($ahora->copy()->addHour())) {
            return back()->withErrors(['error' => 'Debes agendar con al menos 1 hora de anticipación.'])->withInput();
        }

        $slot = Carbon::createFromFormat('H:i', $request->hora);
        $horarioSeleccionado = $this->horarioParaSlot((int) $request->doctor_id, (string) $request->fecha, $slot);

        if (!$horarioSeleccionado) {
            return back()->withErrors(['error' => 'No hay horario configurado para ese profesional en ese dia y hora.'])->withInput();
        }

        if (!$this->slotAlineadoConHorario($horarioSeleccionado, (string) $request->fecha, $slot)) {
            return back()->withErrors(['hora' => 'La hora seleccionada no coincide con un bloque disponible del horario configurado.'])->withInput();
        }

        $intervalo = $this->intervaloHorario($horarioSeleccionado);

        // choques +/- intervalo

        $citasMismoDia = Cita::where('doctor_id', $request->doctor_id)
            ->whereDate('fecha', $request->fecha)
            ->where('activo', true)
            ->get(['id','hora']);

        $existe = $citasMismoDia->contains(function ($c) use ($slot, $intervalo) {
            $h = strlen($c->hora) >= 5 ? substr($c->hora, 0, 5) : $c->hora;
            $otro = Carbon::createFromFormat('H:i', $h);
            return $otro->diffInMinutes($slot) <= ($intervalo - 1);
        });

        if ($existe) {
            $msg = 'El profesional ya tiene una cita en ese horario o en un bloque inmediato del horario configurado.';
            return back()->withErrors(['error' => $msg])->withInput();
        }

        try {
            DB::beginTransaction();

            $cita = Cita::create([
                'paciente_id'     => Auth::id(),
                'doctor_id'       => $request->doctor_id,
                'especialidad_id' => $request->especialidad_id,
                'fecha'           => $request->fecha,
                'hora'            => $slot->format('H:i:00'),
                'motivo_consulta' => $motivoConsulta,
                'estado'          => Cita::ESTADO_PENDIENTE,
                'activo'          => true,
            ]);

            $priorityEvaluator->apply($cita);
            $cita->save();

            if ($isLab) {
                LaboratorioOrden::create([
                    'cita_id'            => $cita->id,
                    'solicitante_id'     => Auth::id(),
                    'origen'             => 'paciente',
                    'prioridad'          => $prioridad,
                    'tipo_examen'        => $request->tipo_examen,
                    'indicaciones'       => null,
                    'preparacion'        => $preparacion,
                    'estado'             => LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
                ]);
            }

            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();
            $msg = $isLab
                ? 'El laboratorio ya tiene una cita exactamente a esa hora.'
                : 'El doctor ya tiene una cita exactamente a esa hora.';
            return back()->withErrors(['error' => $msg])->withInput();
        }

        event(new CitaAgendada($cita));
        EnviarConfirmacionCitaJob::dispatch($cita);

        return redirect()->route('paciente.citas')
            ->with('success', 'Cita creada con éxito. Confirmación enviada y doctor notificado.')
            ->with('success_action_url', route('paciente.citas'))
            ->with('success_action_label', 'Ver mis citas')
            ->with('highlight_cita', $cita->id);
    }

    public function cancelar($id)
    {
        $cita = Cita::findOrFail($id);

        if ($cita->paciente_id != Auth::id()) {
            return back()->with('error', 'No puedes cancelar esta cita.');
        }

        if (app(CitaNoShowService::class)->marcarSiVencio($cita)) {
            return back()->with('error', 'La cita ya vencio y se marco como no se presento.');
        }

        if (in_array($cita->estado, [Cita::ESTADO_CANCELADA, Cita::ESTADO_REALIZADA, Cita::ESTADO_NO_SE_PRESENTO])) {
            return back()->with('error', 'Esta cita ya no puede ser cancelada.');
        }

        $cita->estado = Cita::ESTADO_CANCELADA;
        $cita->activo = false;
        $cita->save();

        NotificarCambioEstadoCitaJob::dispatch($cita, 'cancelada', 'paciente');

        return back()
            ->with('success', 'Cita cancelada.')
            ->with('success_action_url', route('paciente.citas'))
            ->with('success_action_label', 'Ver mis citas')
            ->with('highlight_cita', $cita->id);
    }

    /** ======================= PACIENTE: EDITAR/ACTUALIZAR ======================= */
    public function edit($id)
    {
        $cita = Cita::with(['doctor','especialidad'])->findOrFail($id);

        if ($cita->paciente_id != Auth::id()) {
            return back()->with('error', 'No puedes editar esta cita.');
        }

        if (app(CitaNoShowService::class)->marcarSiVencio($cita)) {
            return back()->with('error', 'La cita ya vencio y se marco como no se presento.');
        }

        if (in_array($cita->estado, [Cita::ESTADO_CANCELADA, Cita::ESTADO_REALIZADA, Cita::ESTADO_NO_SE_PRESENTO])) {
            return back()->with('error', 'Esta cita no puede ser modificada.');
        }

        return view('paciente.editar-cita', compact('cita'));
    }

    public function actualizar(Request $request, $id, PriorityEvaluator $priorityEvaluator)
    {
        $cita = Cita::findOrFail($id);

        if ($cita->paciente_id != Auth::id()) {
            return back()->with('error', 'No puedes modificar esta cita.');
        }

        if (app(CitaNoShowService::class)->marcarSiVencio($cita)) {
            return back()->with('error', 'La cita ya vencio y se marco como no se presento.');
        }

        $request->validate(
            [
                'fecha' => 'required|date',
                'hora'  => 'required|date_format:H:i',
                'motivo_consulta' => ValidationRules::motivoConsulta(),
            ],
            [
                'fecha.required'   => 'Seleccione una fecha.',
                'fecha.date'       => 'La fecha no es válida.',
                'hora.required'    => 'Ingrese una hora.',
                'hora.date_format' => 'Formato de hora inválido. Use HH:MM.',
                'motivo_consulta.required' => 'El motivo de consulta es obligatorio.',
                'motivo_consulta.min'      => 'El motivo debe tener al menos 3 caracteres.',
                'motivo_consulta.max'      => 'El motivo no puede superar 80 caracteres.',
                'motivo_consulta.regex'    => 'El motivo debe ir en una sola linea.',
            ]
        );

        $motivoConsulta = $priorityEvaluator->sanitizeMotivo($request->input('motivo_consulta'));

        $ahora = now('America/Guayaquil');
        $fechaHora = Carbon::createFromFormat('Y-m-d H:i', $request->fecha.' '.$request->hora, 'America/Guayaquil');
        if ($fechaHora->lt($ahora->copy()->addHour())) {
            return back()->withErrors(['error' => 'Debes reagendar con al menos 1 hora de anticipación.'])->withInput();
        }

        $slot = Carbon::createFromFormat('H:i', $request->hora);
        $horarioSeleccionado = $this->horarioParaSlot((int) $cita->doctor_id, (string) $request->fecha, $slot);

        if (!$horarioSeleccionado) {
            return back()->withErrors(['error' => 'No hay horario configurado para ese profesional en ese dia y hora.'])->withInput();
        }

        if (!$this->slotAlineadoConHorario($horarioSeleccionado, (string) $request->fecha, $slot)) {
            return back()->withErrors(['hora' => 'La hora seleccionada no coincide con un bloque disponible del horario configurado.'])->withInput();
        }

        $intervalo = $this->intervaloHorario($horarioSeleccionado);
        $citasMismoDia = Cita::where('doctor_id', $cita->doctor_id)
            ->whereDate('fecha', $request->fecha)
            ->where('activo', true)
            ->where('id', '!=', $cita->id)
            ->get(['id','hora']);

        $existe = $citasMismoDia->contains(function ($c) use ($slot, $intervalo) {
            $h = strlen($c->hora) >= 5 ? substr($c->hora, 0, 5) : $c->hora;
            $otro = Carbon::createFromFormat('H:i', $h);
            return $otro->diffInMinutes($slot) <= ($intervalo - 1);
        });

        if ($existe) {
            $msg = 'El profesional ya tiene una cita en ese horario o en un bloque inmediato del horario configurado.';
            return back()->withErrors(['error' => $msg])->withInput();
        }

        try {
            DB::beginTransaction();

            $cita->update([
                'fecha'  => $request->fecha,
                'hora'   => $slot->format('H:i:00'),
                'motivo_consulta' => $motivoConsulta,
                'estado' => Cita::ESTADO_PENDIENTE,
                'activo' => true,
            ]);

            $priorityEvaluator->apply($cita);
            $cita->save();

            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();
            $msg = $isLab
                ? 'El laboratorio ya tiene una cita exactamente a esa hora.'
                : 'El doctor ya tiene una cita exactamente a esa hora.';
            return back()->withErrors(['error' => $msg])->withInput();
        }

        NotificarCambioEstadoCitaJob::dispatch($cita, 'reagendada', 'paciente');

        return redirect()->route('paciente.citas')
            ->with('success', 'Cita reagendada.')
            ->with('success_action_url', route('paciente.citas'))
            ->with('success_action_label', 'Ver mis citas')
            ->with('highlight_cita', $cita->id);
    }

    /** ======================= DOCTOR: LISTADO ======================= */
    public function indexDoctor(Request $request)
{
    app(CitaNoShowService::class)->marcarVencidas();
    $doctorId = Auth::id();
    $estado = $this->obtenerEstadoFiltroDoctor($request);
    $prioridad = $this->obtenerPrioridadFiltroDoctor($request);

    // Para la tabla
    $citas = \App\Models\Cita::where('doctor_id', $doctorId)
        ->when($estado !== '', fn($query) => $query->where('estado', $estado))
        ->when($prioridad !== '', fn($query) => $query->where('prioridad_nivel', $prioridad))
        ->with(['paciente','especialidad','receta','notaSoap'])
        ->orderByRaw(Cita::prioridadOrderSql())
        ->orderBy('fecha', 'asc')
        ->orderBy('hora', 'asc')
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

    return view('doctor.citas', compact('citas', 'estado', 'prioridad'));
}

    protected function obtenerEstadoFiltroDoctor(Request $request): string
    {
        $estado = trim((string) $request->get('estado', ''));
        if ($estado === 'all') {
            return '';
        }
        $validStates = ['pendiente','confirmada','cancelada','realizada','no_se_presento'];
        return in_array($estado, $validStates, true) ? $estado : '';
    }

    protected function obtenerPrioridadFiltroDoctor(Request $request): string
    {
        $prioridad = strtoupper(trim((string) $request->get('prioridad', '')));
        if ($prioridad === 'ALL') {
            return '';
        }

        return in_array($prioridad, Cita::PRIORIDAD_NIVELES, true) ? $prioridad : '';
    }

    protected function etiquetaEstadoCita(string $estado): string
    {
        return $estado === \App\Models\Cita::ESTADO_NO_SE_PRESENTO ? 'No se presento' : ucfirst($estado);
    }

    protected function queryDoctorCitas(int $doctorId, string $estado = '', string $prioridad = '')
    {
        return \App\Models\Cita::where('doctor_id', $doctorId)
            ->when($estado !== '', fn($query) => $query->where('estado', $estado))
            ->when($prioridad !== '', fn($query) => $query->where('prioridad_nivel', $prioridad))
            ->orderByRaw(Cita::prioridadOrderSql())
            ->orderBy('fecha', 'asc')
            ->orderBy('hora', 'asc');
    }

    public function aceptar($id)
    {
        $cita = Cita::findOrFail($id);

        if ($cita->doctor_id != Auth::id()) {
            return back()->with('error', 'No puedes aceptar esta cita.');
        }

        if (app(CitaNoShowService::class)->marcarSiVencio($cita)) {
            return back()->with('error', 'La cita ya vencio y se marco como no se presento.');
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

        if (app(CitaNoShowService::class)->marcarSiVencio($cita)) {
            return back()->with('error', 'La cita ya vencio y se marco como no se presento.');
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
        $cita = Cita::with('notaSoap')->findOrFail($id);

        if ($cita->doctor_id != Auth::id()) {
            return back()->with('error', 'No puedes marcar esta cita.');
        }

        if (app(CitaNoShowService::class)->marcarSiVencio($cita)) {
            return back()->with('error', 'La cita ya vencio y se marco como no se presento.');
        }

        if ($cita->estado !== Cita::ESTADO_CONFIRMADA) {
            return back()->with('error', 'Solo puedes marcar como realizada citas confirmadas.');
        }

        if (!$cita->notaSoap || $cita->notaSoap->estado !== \App\Models\NotaSoap::ESTADO_FIRMADA) {
            return redirect()->route('doctor.citas.soap', $cita->id)
                ->with('error', 'Debes firmar la nota clínica (SOAP) antes de marcar la cita como realizada.');
        }

        $cita->estado = Cita::ESTADO_REALIZADA;
        $cita->activo = true;
        $cita->save();

        event(new CitaAtendida($cita));

        return back()->with('success', 'Cita marcada como realizada.');
    }

    public function exportarDoctorExcel(Request $request)
    {
        $doctorId = Auth::id();
        $estado = $this->obtenerEstadoFiltroDoctor($request);
        $prioridad = $this->obtenerPrioridadFiltroDoctor($request);

        $rows = $this->queryDoctorCitas($doctorId, $estado, $prioridad)
            ->with(['paciente:id,name', 'especialidad:id,nombre'])
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Paciente', 'Especialidad', 'Fecha', 'Hora', 'Estado', 'Prioridad']], null, 'A1', true);

        $fila = 2;
        foreach ($rows as $cita) {
            $sheet->setCellValue("A{$fila}", $cita->paciente?->name ?? 'Sin paciente');
            $sheet->setCellValue("B{$fila}", $cita->especialidad?->nombre ?? 'Sin especialidad');
            $sheet->setCellValue("C{$fila}", $cita->fecha);
            $sheet->setCellValue("D{$fila}", substr((string)$cita->hora, 0, 5));
            $sheet->setCellValue("E{$fila}", $this->etiquetaEstadoCita($cita->estado));
            $sheet->setCellValue("F{$fila}", ($cita->prioridad_nivel ?? Cita::PRIORIDAD_BAJA) . ($cita->prioridad_red_flag ? ' (Red flag)' : ''));
            $fila++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'citas_doctor_'.$doctorId.'_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            if (ob_get_length()) {
                ob_end_clean();
            }
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function exportarDoctorPdf(Request $request)
    {
        $doctorId = Auth::id();
        $estado = $this->obtenerEstadoFiltroDoctor($request);
        $prioridad = $this->obtenerPrioridadFiltroDoctor($request);

        $citas = $this->queryDoctorCitas($doctorId, $estado, $prioridad)
            ->with(['paciente:id,name', 'especialidad:id,nombre'])
            ->get();

        $html = view('doctor.citas-export-pdf', [
            'citas' => $citas,
            'doctor' => Auth::user(),
            'estado' => $estado,
            'prioridad' => $prioridad,
        ])->render();

        $opt = new Options();
        $opt->set('isRemoteEnabled', true);
        $opt->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($opt);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $fileName = 'citas_doctor_'.$doctorId.'_'.now()->format('Ymd_His').'.pdf';
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
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
     * API: slots disponibles segun rol y fecha.
     * GET /api/doctor/{doctor}/fecha/{fecha}/slots
     */
    public function slotsDisponibles($doctor, $fecha)
    {
        $horarios = Horario::where('doctor_id', $doctor)
            ->whereDate('fecha', $fecha)
            ->orderBy('hora_inicio')
            ->get(['hora_inicio','hora_fin','intervalo_minutos']);

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
        $limiteHoyMin = ($ahora->hour * 60) + $ahora->minute + 60;

        foreach ($horarios as $h) {
            $ini = $this->parseHoraFlexible($h->hora_inicio);
            $fin = $this->parseHoraFlexible($h->hora_fin);
            $intervalo = max(1, (int) ($h->intervalo_minutos ?: 30));

            for ($t = $ini->copy(); $t->lt($fin); $t->addMinutes($intervalo)) {
                if ($esHoy) {
                    $slotMin = ($t->hour * 60) + $t->minute;
                    if ($slotMin < $limiteHoyMin) {
                        continue;
                    }
                }

                $hhmm = $t->format('H:i');

                $choca = collect($ocupadas)->contains(function ($o) use ($hhmm, $intervalo) {
                    $a = Carbon::createFromFormat('H:i', $o);
                    $b = Carbon::createFromFormat('H:i', $hhmm);
                    return $a->diffInMinutes($b) <= ($intervalo - 1);
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
        $ref = $r->date('fecha_preferida') ?? now('America/Guayaquil');

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
    public function proximaPlanificada(Request $request, Cita $cita, PriorityEvaluator $priorityEvaluator)
    {
        if ($cita->doctor_id !== Auth::id()) abort(403);

        if (app(PagoService::class)->pacienteTieneBloqueo((int) $cita->paciente_id)) {
            return response()->json([
                'ok' => false,
                'msg' => PagoService::MENSAJE_BLOQUEO,
            ], 423);
        }

        $request->validate(
            ['fecha'=>'required|date','hora'=>'required|date_format:H:i'],
            ['fecha.required'=>'Seleccione una fecha.','hora.required'=>'Seleccione una hora.']
        );

        $slot = Carbon::createFromFormat('H:i', $request->hora);
        if ($slot->minute % 30 !== 0) {
            return response()->json(['ok'=>false,'msg'=>'La hora debe ser 00 o 30 minutos.'], 422);
        }

        $fechaHora = Carbon::createFromFormat('Y-m-d H:i', $request->fecha.' '.$request->hora, 'America/Guayaquil');
        if ($fechaHora->lt(now('America/Guayaquil')->addHour())) {
            return response()->json(['ok'=>false,'msg'=>'Debe ser al menos 1 hora después de la hora actual.'], 422);
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

        DB::transaction(function() use ($cita, $request, $slot, $priorityEvaluator) {
            $nueva = Cita::create([
                'paciente_id'     => $cita->paciente_id,
                'doctor_id'       => $cita->doctor_id,
                'especialidad_id' => $cita->especialidad_id,
                'fecha'           => $request->fecha,
                'hora'            => $slot->format('H:i:00'),
                'motivo_consulta' => $cita->motivo_consulta ?: 'Seguimiento medico',
                'estado'          => Cita::ESTADO_PENDIENTE,
                'activo'          => true,
            ]);
            $priorityEvaluator->apply($nueva);
            $nueva->save();
            event(new CitaAgendada($nueva));
            EnviarConfirmacionCitaJob::dispatch($nueva);
        });

        return response()->json(['ok'=>true]);
    }
}
