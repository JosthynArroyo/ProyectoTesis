<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\User;
use App\Services\Appointments\DoctorAppointmentService;
use App\Services\Appointments\PatientAppointmentService;
use App\Services\PriorityEvaluator;
use App\Services\ProfessionalScheduleService;
use App\Services\SlotHoldService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CitaController extends Controller
{
    public function __construct(
        protected PatientAppointmentService $patientService,
        protected DoctorAppointmentService $doctorService
    ) {}

    /** ======================= PACIENTE: LISTADO ======================= */
    public function index(Request $request)
    {
        $data = $this->patientService->index($request, (int) Auth::id());

        return view('paciente.citas', $data);
    }

    /** ======================= PACIENTE: CREAR ======================= */
    public function create(Request $request)
    {
        $data = $this->patientService->create($request, Auth::user());

        return view('paciente.crear-cita', $data);
    }

    public function store(
        Request $request,
        PriorityEvaluator $priorityEvaluator,
        ProfessionalScheduleService $scheduleService,
        SlotHoldService $slotHoldService
    ) {
        $result = $this->patientService->store($request, Auth::user());

        if (! $result['ok']) {
            return back()->withErrors($result['errors'])->withInput();
        }

        $cita = $result['cita'];

        return redirect()->route('paciente.citas')
            ->with('success', 'Cita creada con éxito. Confirmación enviada y doctor notificado.')
            ->with('success_action_url', route('paciente.citas'))
            ->with('success_action_label', 'Ver mis citas')
            ->with('highlight_cita', $cita->id);
    }

    public function cancelar($id)
    {
        $result = $this->patientService->cancelar((int) $id, (int) Auth::id());

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        $cita = $result['cita'];

        return back()
            ->with('success', 'Cita cancelada.')
            ->with('success_action_url', route('paciente.citas'))
            ->with('success_action_label', 'Ver mis citas')
            ->with('highlight_cita', $cita->id);
    }

    /** ======================= PACIENTE: EDITAR/ACTUALIZAR ======================= */
    public function edit($id)
    {
        $result = $this->patientService->edit((int) $id, (int) Auth::id());

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        return view('paciente.editar-cita', ['cita' => $result['cita']]);
    }

    public function actualizar(
        Request $request,
        $id,
        PriorityEvaluator $priorityEvaluator,
        ProfessionalScheduleService $scheduleService
    ) {
        $result = $this->patientService->actualizar($request, (int) $id, (int) Auth::id());

        if (! $result['ok']) {
            if (($result['type'] ?? '') === 'flash') {
                return back()->with('error', $result['message']);
            }

            return back()->withErrors($result['errors'])->withInput();
        }

        $cita = $result['cita'];

        return redirect()->route('paciente.citas')
            ->with('success', 'Cita reagendada.')
            ->with('success_action_url', route('paciente.citas'))
            ->with('success_action_label', 'Ver mis citas')
            ->with('highlight_cita', $cita->id);
    }

    /** ======================= DOCTOR: LISTADO ======================= */
    public function indexDoctor(Request $request)
    {
        $data = $this->doctorService->indexDoctor($request, (int) Auth::id());

        return view('doctor.citas', $data);
    }

    public function aceptar($id)
    {
        try {
            $this->doctorService->aceptar((int) $id, (int) Auth::id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cita confirmada.');
    }

    public function rechazar($id)
    {
        try {
            $this->doctorService->rechazar((int) $id, (int) Auth::id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cita rechazada.');
    }

    public function realizar($id)
    {
        $result = $this->doctorService->realizar((int) $id, (int) Auth::id());

        if (! $result['ok']) {
            if (! empty($result['redirect'])) {
                return redirect($result['redirect'])->with('error', $result['message']);
            }

            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Cita marcada como realizada.');
    }

    public function exportarDoctorExcel(Request $request)
    {
        return $this->doctorService->exportarDoctorExcel($request, (int) Auth::id());
    }

    public function exportarDoctorPdf(Request $request)
    {
        return $this->doctorService->exportarDoctorPdf($request, (int) Auth::id(), Auth::user());
    }

    /** ======================= DOCTOR: DISPONIBILIDAD + PROXIMA CITA ======================= */
    public function checkDisponibilidad(Request $r): JsonResponse
    {
        $result = $this->doctorService->checkDisponibilidad($r, (int) Auth::id());

        return response()->json($result);
    }

    public function cancelarControlPlanificado(Cita $cita, Cita $control)
    {
        try {
            $this->doctorService->cancelarControlPlanificado($cita, $control, (int) Auth::id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('doctor.citas.soap', $cita)
            ->with('success', 'Control cancelado correctamente.');
    }

    public function proximaPlanificada(
        Request $request,
        Cita $cita,
        PriorityEvaluator $priorityEvaluator,
        ProfessionalScheduleService $scheduleService
    ): JsonResponse {
        $result = $this->doctorService->proximaPlanificada($request, $cita, (int) Auth::id());

        return response()->json($result['payload'], $result['status'] ?? 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Protected Compatibility Proxies
    |--------------------------------------------------------------------------
    | Preserves internal helper method signatures for extensions or tests.
    */

    protected function laboratorioEspecialidadId(): ?int
    {
        return $this->patientService->laboratorioEspecialidadId();
    }

    protected function activeProfessionalForSpecialty(int $professionalId, int $especialidadId, bool $isLab): ?User
    {
        return $this->patientService->activeProfessionalForSpecialty($professionalId, $especialidadId, $isLab);
    }

    protected function activeProfessionalError(bool $isLab): string
    {
        return $this->patientService->activeProfessionalError($isLab);
    }

    protected function laboratorioPreparacionGenerica(): array
    {
        return $this->patientService->laboratorioPreparacionGenerica();
    }

    protected function laboratorioCatalogoExamenes(): array
    {
        return $this->patientService->laboratorioCatalogoExamenes();
    }

    protected function laboratorioPreparacionPorTipo(string $tipoExamen): ?string
    {
        return $this->patientService->laboratorioPreparacionPorTipo($tipoExamen);
    }

    protected function formatearPreparacion(array $prep): string
    {
        return $this->patientService->formatearPreparacion($prep);
    }

    protected function obtenerEstadoFiltroDoctor(Request $request): string
    {
        return $this->doctorService->obtenerEstadoFiltroDoctor($request);
    }

    protected function obtenerPrioridadFiltroDoctor(Request $request): string
    {
        return $this->doctorService->obtenerPrioridadFiltroDoctor($request);
    }

    protected function normalizeSearchTerm(mixed $value, int $maxLength = 100): string
    {
        return $this->patientService->normalizeSearchTerm($value, $maxLength);
    }

    protected function etiquetaEstadoCita(string $estado): string
    {
        return $this->doctorService->etiquetaEstadoCita($estado);
    }

    protected function queryDoctorCitas(int $doctorId, string $estado = '', string $prioridad = '')
    {
        return $this->doctorService->queryDoctorCitas($doctorId, $estado, $prioridad);
    }

    protected function fechaHoraCita(Cita $cita): Carbon
    {
        return $this->doctorService->fechaHoraCita($cita);
    }

    protected function controlPosteriorActivo(Cita $cita): ?Cita
    {
        return $this->doctorService->controlPosteriorActivo($cita);
    }

    protected function controlPerteneceACita(Cita $cita, Cita $control): bool
    {
        return $this->doctorService->controlPerteneceACita($cita, $control);
    }
}
