<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCitaOverrideRequest;
use App\Events\CitaAgendada;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\LaboratorioOrden;
use App\Models\User;
use App\Services\PagoService;
use App\Services\PriorityEvaluator;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CitaOverrideController extends Controller
{
    protected function laboratorioEspecialidadId(): ?int
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

    public function create()
    {
        $pacientes = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'paciente'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'dni']);

        $especialidades = Especialidad::orderBy('nombre')->get(['id', 'nombre']);
        $labId = $this->laboratorioEspecialidadId();

        return view('admin.citas.override-create', compact('pacientes', 'especialidades', 'labId'));
    }

    public function store(
        StoreCitaOverrideRequest $request,
        PagoService $pagoService,
        PriorityEvaluator $priorityEvaluator
    )
    {
        $data = $request->validated();
        $motivoConsulta = $priorityEvaluator->sanitizeMotivo($data['motivo_consulta'] ?? null);
        $labId = $this->laboratorioEspecialidadId();
        $isLab = $labId && (int) $data['especialidad_id'] === (int) $labId;

        $pacienteValido = User::query()
            ->where('id', $data['paciente_id'])
            ->whereHas('roles', fn ($q) => $q->where('name', 'paciente'))
            ->exists();

        if (!$pacienteValido) {
            return back()->withErrors(['paciente_id' => 'El usuario seleccionado no pertenece a pacientes.'])->withInput();
        }

        $rolEsperado = $isLab ? 'laboratorio' : 'doctor';
        $doctorValido = User::query()
            ->where('id', $data['doctor_id'])
            ->whereHas('roles', fn ($q) => $q->where('name', $rolEsperado))
            ->whereHas('especialidades', fn ($q) => $q->where('especialidad_id', $data['especialidad_id']))
            ->exists();

        if (!$doctorValido) {
            return back()->withErrors(['doctor_id' => 'El profesional seleccionado no corresponde a la especialidad.'])->withInput();
        }

        if ($isLab) {
            $request->validate([
                'tipo_examen' => ['required', 'string', 'max:255'],
                'prioridad' => ['required', 'in:normal,urgente'],
                'indicaciones' => ['nullable', 'string', 'max:2000'],
                'preparacion' => ['nullable', 'string', 'max:2000'],
            ]);
        }

        $bloqueado = $pagoService->pacienteTieneBloqueo((int) $data['paciente_id']);
        $forzarBloqueo = $request->boolean('forzar_bloqueo');

        if ($bloqueado && !$forzarBloqueo) {
            return back()->withErrors([
                'forzar_bloqueo' => 'El paciente tiene pagos pendientes. Active el override para continuar.',
            ])->withInput();
        }
        if ($bloqueado && blank($data['override_reason'] ?? null)) {
            return back()->withErrors([
                'override_reason' => 'Debe registrar el motivo de excepción para auditoría.',
            ])->withInput();
        }

        $ahora = now('America/Guayaquil');
        $fechaHora = Carbon::createFromFormat('Y-m-d H:i', $data['fecha'].' '.$data['hora'], 'America/Guayaquil');
        if ($fechaHora->lt($ahora->copy()->addHour())) {
            return back()->withErrors(['fecha' => 'Debe agendar con al menos 1 hora de anticipación.'])->withInput();
        }

        $slot = Carbon::createFromFormat('H:i', $data['hora']);
        $horarioSeleccionado = $this->horarioParaSlot((int) $data['doctor_id'], (string) $data['fecha'], $slot);

        if (!$horarioSeleccionado) {
            return back()->withErrors(['hora' => 'No hay horario configurado para ese profesional en ese dia y hora.'])->withInput();
        }

        if (!$this->slotAlineadoConHorario($horarioSeleccionado, (string) $data['fecha'], $slot)) {
            return back()->withErrors(['hora' => 'La hora seleccionada no coincide con un bloque disponible del horario configurado.'])->withInput();
        }

        $intervalo = $this->intervaloHorario($horarioSeleccionado);

        $citasMismoDia = Cita::query()
            ->where('doctor_id', $data['doctor_id'])
            ->whereDate('fecha', $data['fecha'])
            ->where('activo', true)
            ->get(['hora']);

        $choca = $citasMismoDia->contains(function ($row) use ($slot, $intervalo) {
            $hora = strlen((string) $row->hora) >= 5 ? substr((string) $row->hora, 0, 5) : (string) $row->hora;
            $otro = Carbon::createFromFormat('H:i', $hora);
            return $otro->diffInMinutes($slot) <= ($intervalo - 1);
        });

        if ($choca) {
            return back()->withErrors(['hora' => 'Ya existe una cita en ese horario o intervalo inmediato.'])->withInput();
        }

        try {
            DB::beginTransaction();

            $cita = Cita::query()->create([
                'paciente_id' => $data['paciente_id'],
                'doctor_id' => $data['doctor_id'],
                'especialidad_id' => $data['especialidad_id'],
                'fecha' => $data['fecha'],
                'hora' => $slot->format('H:i:00'),
                'motivo_consulta' => $motivoConsulta,
                'estado' => Cita::ESTADO_PENDIENTE,
                'activo' => true,
            ]);
            $priorityEvaluator->apply($cita);
            $cita->save();

            if ($isLab) {
                LaboratorioOrden::query()->create([
                    'cita_id' => $cita->id,
                    'solicitante_id' => Auth::id(),
                    'origen' => 'doctor',
                    'prioridad' => $request->input('prioridad', 'normal'),
                    'tipo_examen' => $request->input('tipo_examen'),
                    'indicaciones' => $request->input('indicaciones'),
                    'preparacion' => $request->input('preparacion'),
                    'estado' => LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
                ]);
            }

            if ($bloqueado && $forzarBloqueo) {
                $pagoService->registrarOverrideAgendamiento(
                    pacienteId: (int) $data['paciente_id'],
                    actor: $request->user(),
                    citaId: (int) $cita->id,
                    motivo: $data['override_reason'] ?? null
                );
            }

            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();
            return back()->withErrors([
                'hora' => 'El horario seleccionado ya fue ocupado por otra cita.',
            ])->withInput();
        }

        event(new CitaAgendada($cita));
        EnviarConfirmacionCitaJob::dispatch($cita);

        $msg = $bloqueado && $forzarBloqueo
            ? 'Cita creada con override de pagos pendientes. Acción auditada.'
            : 'Cita creada correctamente.';

        return redirect()
            ->route('admin.citas.override.create')
            ->with('success', $msg);
    }
}
