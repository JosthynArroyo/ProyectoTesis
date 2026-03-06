<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\LaboratorioOrden;
use App\Models\User;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Events\CitaAgendada;
use App\Services\PagoService;
use App\Services\PriorityEvaluator;
use App\Support\ValidationRules;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LaboratorioController extends Controller
{
    protected function laboratorioEspecialidadId(): int
    {
        return Especialidad::where('nombre', 'Laboratorio Clinico')->value('id');
    }

    protected function horarioParaSlot(int $profesionalId, string $fecha, Carbon $slot): ?Horario
    {
        return Horario::query()
            ->where('doctor_id', $profesionalId)
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

    public function create(Request $request)
    {
        $labId = $this->laboratorioEspecialidadId();
        $pacientes = User::whereHas('roles', fn($q) => $q->where('name', 'paciente'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $doctoresLab = $labId
            ? User::whereHas('roles', fn($q) => $q->where('name', 'laboratorio'))
                ->whereHas('especialidades', fn($q) => $q->where('especialidad_id', $labId))
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $prefPaciente = $request->get('paciente_id');
        $defaultLabId = optional($doctoresLab->first(function ($doctor) {
            return mb_strtolower($doctor->name) === 'laboratorio clinico';
        }))->id;

        if (!$defaultLabId && $doctoresLab->count() >= 1) {
            $defaultLabId = $doctoresLab->first()->id;
        }

        $defaultLabName = $defaultLabId
            ? (optional($doctoresLab->firstWhere('id', $defaultLabId))->name ?? 'Laboratorio Clinico')
            : null;

        return view('doctor.laboratorio.crear', compact('pacientes', 'doctoresLab', 'prefPaciente', 'labId', 'defaultLabId', 'defaultLabName'));
    }

    public function store(Request $request, PriorityEvaluator $priorityEvaluator)
    {
        $data = $request->validate(
            [
                'paciente_id'  => 'required|exists:users,id',
                'doctor_id'    => 'required|exists:users,id',
                'fecha'        => 'required|date',
                'hora'         => 'required|date_format:H:i',
                'motivo_consulta' => ValidationRules::motivoConsulta(),
                'tipo_examen'  => 'required|string|max:255',
                'prioridad'    => 'required|in:normal,urgente',
                'indicaciones' => 'required|string|max:2000',
                'preparacion'  => 'required|string|max:2000',
            ],
            [
                'paciente_id.required' => 'Selecciona un paciente.',
                'doctor_id.required'   => 'Selecciona el laboratorio.',
                'tipo_examen.required' => 'Indica el tipo de examen.',
                'motivo_consulta.required' => 'El motivo de consulta es obligatorio.',
            ]
        );

        $motivoConsulta = $priorityEvaluator->sanitizeMotivo($data['motivo_consulta']);

        if (app(PagoService::class)->pacienteTieneBloqueo((int) $data['paciente_id'])) {
            return back()->withErrors(['error' => PagoService::MENSAJE_BLOQUEO])->withInput();
        }

        $labId = $this->laboratorioEspecialidadId();
        if (!$labId) {
            return back()->withErrors(['error' => 'No existe la especialidad de laboratorio en el sistema.'])->withInput();
        }

        $doctorLabOk = User::where('id', $data['doctor_id'])
            ->whereHas('roles', fn($q) => $q->where('name', 'laboratorio'))
            ->whereHas('especialidades', fn($q) => $q->where('especialidad_id', $labId))
            ->exists();

        if (!$doctorLabOk) {
            return back()->withErrors(['doctor_id' => 'El usuario seleccionado no pertenece a laboratorio.'])->withInput();
        }

        $ahora = now('America/Guayaquil');
        $fechaHora = Carbon::createFromFormat('Y-m-d H:i', $data['fecha'].' '.$data['hora'], 'America/Guayaquil');
        if ($fechaHora->lt($ahora->copy()->addHour())) {
            return back()->withErrors(['error' => 'Debes agendar con al menos 1 hora de anticipacion.'])->withInput();
        }

        $slot = Carbon::createFromFormat('H:i', $data['hora']);
        $horarioSeleccionado = $this->horarioParaSlot((int) $data['doctor_id'], (string) $data['fecha'], $slot);

        if (!$horarioSeleccionado) {
            return back()->withErrors(['hora' => 'No hay horario configurado para ese laboratorio en ese dia y hora.'])->withInput();
        }

        if (!$this->slotAlineadoConHorario($horarioSeleccionado, (string) $data['fecha'], $slot)) {
            return back()->withErrors(['hora' => 'La hora seleccionada no coincide con un bloque disponible del horario configurado.'])->withInput();
        }

        $intervalo = $this->intervaloHorario($horarioSeleccionado);
        $citasMismoDia = Cita::where('doctor_id', $data['doctor_id'])
            ->whereDate('fecha', $data['fecha'])
            ->where('activo', true)
            ->get(['id','hora']);

        $existe = $citasMismoDia->contains(function ($c) use ($slot, $intervalo) {
            $h = strlen($c->hora) >= 5 ? substr($c->hora, 0, 5) : $c->hora;
            $otro = Carbon::createFromFormat('H:i', $h);
            return $otro->diffInMinutes($slot) <= ($intervalo - 1);
        });

        if ($existe) {
            return back()->withErrors(['error' => 'El laboratorio ya tiene una cita en ese horario o en un bloque inmediato del horario configurado.'])->withInput();
        }

        try {
            DB::beginTransaction();

            $cita = Cita::create([
                'paciente_id'     => $data['paciente_id'],
                'doctor_id'       => $data['doctor_id'],
                'especialidad_id' => $labId,
                'fecha'           => $data['fecha'],
                'hora'            => $slot->format('H:i:00'),
                'motivo_consulta' => $motivoConsulta,
                'estado'          => Cita::ESTADO_PENDIENTE,
                'activo'          => true,
            ]);
            $priorityEvaluator->apply($cita);
            $cita->save();

            LaboratorioOrden::create([
                'cita_id'        => $cita->id,
                'solicitante_id' => Auth::id(),
                'origen'         => 'doctor',
                'prioridad'      => $data['prioridad'],
                'tipo_examen'    => $data['tipo_examen'],
                'indicaciones'   => $data['indicaciones'] ?? null,
                'preparacion'    => $data['preparacion'] ?? null,
                'estado'         => LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
            ]);

            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'El laboratorio ya tiene una cita exactamente a esa hora.'])->withInput();
        }

        event(new CitaAgendada($cita));
        EnviarConfirmacionCitaJob::dispatch($cita);

        return redirect()->route('doctor.citas')
            ->with('success', 'Orden de laboratorio creada y cita agendada.');
    }
}
