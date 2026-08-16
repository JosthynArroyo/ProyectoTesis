<?php

namespace App\Http\Controllers\Admin;

use App\Events\CitaAgendada;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCitaOverrideRequest;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\LaboratorioOrden;
use App\Models\User;
use App\Services\PagoService;
use App\Services\PriorityEvaluator;
use App\Services\ProfessionalScheduleService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CitaOverrideController extends Controller
{
    protected function laboratorioEspecialidadId(): ?int
    {
        return Especialidad::laboratorioClinicoId();
    }

    public function create()
    {
        $pacientes = User::query()
            ->onlyActive()
            ->whereHas('roles', fn ($query) => $query->where('name', 'paciente'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'dni']);

        $especialidades = Especialidad::orderBy('nombre')->get(['id', 'nombre']);
        $labId = $this->laboratorioEspecialidadId();

        return view('admin.citas.override-create', compact('pacientes', 'especialidades', 'labId'));
    }

    public function store(
        StoreCitaOverrideRequest $request,
        PagoService $pagoService,
        PriorityEvaluator $priorityEvaluator,
        ProfessionalScheduleService $scheduleService
    ) {
        $data = $request->validated();
        $motivoConsulta = $priorityEvaluator->sanitizeMotivo($data['motivo_consulta'] ?? null);
        $labId = $this->laboratorioEspecialidadId();
        $isLab = $labId && (int) $data['especialidad_id'] === (int) $labId;

        $pacienteValido = User::query()
            ->onlyActive()
            ->where('id', $data['paciente_id'])
            ->whereHas('roles', fn ($query) => $query->where('name', 'paciente'))
            ->exists();

        if (! $pacienteValido) {
            return back()->withErrors(['paciente_id' => 'El usuario seleccionado no pertenece a pacientes o esta inactivo.'])->withInput();
        }

        $rolEsperado = $isLab ? 'laboratorio' : 'doctor';
        $doctorValido = User::query()
            ->onlyActive()
            ->where('id', $data['doctor_id'])
            ->whereHas('roles', fn ($query) => $query->where('name', $rolEsperado))
            ->whereHas('especialidades', fn ($query) => $query->where('especialidad_id', $data['especialidad_id']))
            ->exists();

        if (! $doctorValido) {
            return back()->withErrors(['doctor_id' => 'El profesional seleccionado no corresponde a la especialidad o esta inactivo.'])->withInput();
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

        if ($bloqueado && ! $forzarBloqueo) {
            return back()->withErrors([
                'forzar_bloqueo' => 'El paciente tiene pagos pendientes. Active el override para continuar.',
            ])->withInput();
        }
        if ($bloqueado && blank($data['override_reason'] ?? null)) {
            return back()->withErrors([
                'override_reason' => 'Debe registrar el motivo de excepcion para auditoria.',
            ])->withInput();
        }

        $slot = Carbon::createFromFormat('H:i', $data['hora']);
        $validationError = $scheduleService->validateBookingSlot(
            professionalId: (int) $data['doctor_id'],
            date: (string) $data['fecha'],
            slot: $slot,
            messages: [
                'missing_schedule' => 'No hay horario configurado para ese profesional en ese dia y hora.',
                'misaligned' => 'La hora seleccionada no coincide con un bloque disponible del horario configurado.',
                'lead_time' => 'Debe agendar con al menos 1 hora de anticipacion.',
                'lead_time_field' => 'fecha',
                'conflict' => 'Ya existe una cita en ese horario o intervalo inmediato.',
                'conflict_field' => 'hora',
            ]
        );

        if ($validationError) {
            return back()->withErrors([$validationError['field'] => $validationError['message']])->withInput();
        }

        $citaResult = null;
        try {
            $citaResult = DB::transaction(function () use (
                $data, $slot, $priorityEvaluator, $scheduleService, $pagoService,
                $isLab, $motivoConsulta, $bloqueado, $forzarBloqueo, $request
            ) {
                // 1. Bloqueo estable por fila del profesional para serializar solicitudes concurrentes sobre la misma agenda
                User::query()->whereKey((int) $data['doctor_id'])->lockForUpdate()->firstOrFail();

                // 2. Determinar intervalo del horario o usar default 30
                $schedule = $scheduleService->findScheduleForSlot((int) $data['doctor_id'], (string) $data['fecha'], $slot);
                $interval = $schedule ? $scheduleService->intervalMinutes($schedule) : 30;

                // 3. Revalidación definitiva de conflicto con estado fresco de BD bajo lock
                $conflict = $scheduleService->hasConflict(
                    professionalId: (int) $data['doctor_id'],
                    date: (string) $data['fecha'],
                    slot: $slot,
                    interval: $interval
                );

                if ($conflict) {
                    throw new \DomainException(
                        $isLab
                            ? 'El laboratorio ya tiene una cita en ese horario o intervalo inmediato.'
                            : 'El profesional ya tiene una cita en ese horario o intervalo inmediato. Selecciona otro horario disponible.'
                    );
                }

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

                return $cita;
            });
        } catch (\DomainException $e) {
            return back()->withErrors([
                'hora' => $e->getMessage(),
            ])->withInput();
        } catch (QueryException $e) {
            return back()->withErrors([
                'hora' => 'El horario seleccionado ya fue ocupado por otra cita.',
            ])->withInput();
        }

        $cita = $citaResult;

        try {
            event(new CitaAgendada($cita));
            EnviarConfirmacionCitaJob::dispatch($cita);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error al notificar cita agendada en override: ' . $e->getMessage(), [
                'cita_id' => $cita->id,
                'exception' => $e
            ]);
        }

        $msg = $bloqueado && $forzarBloqueo
            ? 'Cita creada con override de pagos pendientes. Accion auditada.'
            : 'Cita creada correctamente.';

        return redirect()
            ->route('admin.citas.override.create')
            ->with('success', $msg);
    }
}
