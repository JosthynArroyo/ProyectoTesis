<?php

namespace App\Http\Controllers\Doctor;

use App\Events\CitaAgendada;
use App\Http\Controllers\Controller;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\LaboratorioOrden;
use App\Models\User;
use App\Services\ClinicalRecordService;
use App\Services\PagoService;
use App\Services\PriorityEvaluator;
use App\Services\ProfessionalScheduleService;
use App\Support\ValidationRules;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LaboratorioController extends Controller
{
    protected function laboratorioEspecialidadId(): ?int
    {
        return Especialidad::laboratorioClinicoId();
    }

    public function create(Request $request)
    {
        $labId = $this->laboratorioEspecialidadId();
        $pacientes = User::query()
            ->onlyActive()
            ->whereHas('roles', fn ($query) => $query->where('name', 'paciente'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $doctoresLab = $labId
            ? User::query()
                ->onlyActive()
                ->whereHas('roles', fn ($query) => $query->where('name', 'laboratorio'))
                ->whereHas('especialidades', fn ($query) => $query->where('especialidad_id', $labId))
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $prefPaciente = $request->get('paciente_id');
        $defaultLabId = $doctoresLab->count() >= 1 ? $doctoresLab->first()->id : null;
        $defaultLabName = $defaultLabId
            ? (optional($doctoresLab->firstWhere('id', $defaultLabId))->name ?? 'Laboratorio Clinico')
            : null;

        return view('doctor.laboratorio.crear', compact('pacientes', 'doctoresLab', 'prefPaciente', 'labId', 'defaultLabId', 'defaultLabName'));
    }

    public function store(Request $request, PriorityEvaluator $priorityEvaluator, ProfessionalScheduleService $scheduleService)
    {
        $data = $request->validate(
            [
                'paciente_id' => 'required|exists:users,id',
                'doctor_id' => 'required|exists:users,id',
                'fecha' => 'required|date',
                'hora' => 'required|date_format:H:i',
                'motivo_consulta' => ValidationRules::motivoConsulta(),
                'tipo_examen' => 'required|string|max:255',
                'prioridad' => 'required|in:normal,urgente',
                'indicaciones' => 'required|string|max:2000',
                'preparacion' => 'required|string|max:2000',
            ],
            [
                'paciente_id.required' => 'Selecciona un paciente.',
                'doctor_id.required' => 'Selecciona el laboratorio.',
                'tipo_examen.required' => 'Indica el tipo de examen.',
                'motivo_consulta.required' => 'El motivo de consulta es obligatorio.',
            ]
        );

        $motivoConsulta = $priorityEvaluator->sanitizeMotivo($data['motivo_consulta']);

        if (app(PagoService::class)->pacienteTieneBloqueo((int) $data['paciente_id'])) {
            return back()->withErrors(['error' => PagoService::MENSAJE_BLOQUEO])->withInput();
        }

        $labId = $this->laboratorioEspecialidadId();
        if (! $labId) {
            return back()->withErrors(['error' => 'No existe la especialidad de laboratorio en el sistema.'])->withInput();
        }

        $doctorLabOk = User::query()
            ->onlyActive()
            ->where('id', $data['doctor_id'])
            ->whereHas('roles', fn ($query) => $query->where('name', 'laboratorio'))
            ->whereHas('especialidades', fn ($query) => $query->where('especialidad_id', $labId))
            ->exists();

        if (! $doctorLabOk) {
            return back()->withErrors(['doctor_id' => 'El usuario seleccionado no pertenece a laboratorio o esta inactivo.'])->withInput();
        }

        $slot = Carbon::createFromFormat('H:i', $data['hora']);
        $validationError = $scheduleService->validateBookingSlot(
            professionalId: (int) $data['doctor_id'],
            date: (string) $data['fecha'],
            slot: $slot,
            messages: [
                'missing_schedule' => 'No hay horario configurado para ese laboratorio en ese dia y hora.',
                'misaligned' => 'La hora seleccionada no coincide con un bloque disponible del horario configurado.',
                'lead_time' => 'Debes agendar con al menos 1 hora de anticipacion.',
                'conflict' => 'El laboratorio ya tiene una cita en ese horario o en un bloque inmediato del horario configurado.',
            ]
        );

        if ($validationError) {
            return back()->withErrors([$validationError['field'] => $validationError['message']])->withInput();
        }

        try {
            DB::beginTransaction();

            $cita = Cita::create([
                'paciente_id' => $data['paciente_id'],
                'doctor_id' => $data['doctor_id'],
                'especialidad_id' => $labId,
                'fecha' => $data['fecha'],
                'hora' => $slot->format('H:i:00'),
                'motivo_consulta' => $motivoConsulta,
                'estado' => Cita::ESTADO_PENDIENTE,
                'activo' => true,
            ]);
            $priorityEvaluator->apply($cita);
            $cita->save();

            $record = app(ClinicalRecordService::class)->ensureForPatient($cita->paciente_id, Auth::id());

            LaboratorioOrden::create([
                'cita_id' => $cita->id,
                'clinical_record_id' => $record->id,
                'solicitante_id' => Auth::id(),
                'origen' => 'doctor',
                'prioridad' => $data['prioridad'],
                'tipo_examen' => $data['tipo_examen'],
                'indicaciones' => $data['indicaciones'] ?? null,
                'preparacion' => $data['preparacion'] ?? null,
                'estado' => LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
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
