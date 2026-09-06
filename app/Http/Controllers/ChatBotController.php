<?php

namespace App\Http\Controllers;

use App\Models\Especialidad;
use App\Models\User;
use App\Services\Chatbot\ChatbotAppointmentService;
use App\Services\Chatbot\ChatbotAuthService;
use App\Services\Chatbot\ChatbotCatalogService;
use App\Services\Chatbot\ChatbotProfileService;
use App\Services\PriorityEvaluator;
use App\Services\ProfessionalScheduleService;
use App\Services\SlotHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatBotController extends Controller
{
    public function __construct(
        protected ChatbotCatalogService $catalogService,
        protected ChatbotAuthService $authService,
        protected ChatbotAppointmentService $appointmentService,
        protected ChatbotProfileService $profileService
    ) {}

    public function especialidades(): JsonResponse
    {
        return response()->json($this->catalogService->especialidades());
    }

    public function doctoresPorEspecialidad(Especialidad $especialidad): JsonResponse
    {
        $result = $this->catalogService->doctoresPorEspecialidad($especialidad);

        return response()->json($result, $result['status'] ?? 200);
    }

    public function fechasDisponibles(User $doctor, ProfessionalScheduleService $scheduleService): JsonResponse
    {
        $result = $this->catalogService->fechasDisponibles($doctor);

        return response()->json($result, $result['status'] ?? 200);
    }

    public function verificarPaciente(Request $request): JsonResponse
    {
        $result = $this->authService->verificarPaciente($request);

        return response()->json($result['payload'], $result['status']);
    }

    public function enviarCodigoVerificacion(Request $request): JsonResponse
    {
        $result = $this->authService->enviarCodigoVerificacion($request);
        $response = response()->json($result['payload'], $result['status']);

        if (! empty($result['headers'])) {
            foreach ($result['headers'] as $header => $value) {
                $response->header($header, $value);
            }
        }

        return $response;
    }

    public function verificarCodigo(Request $request): JsonResponse
    {
        $result = $this->authService->verificarCodigo($request);

        return response()->json($result['payload'], $result['status']);
    }

    public function registrarUsuario(Request $request): JsonResponse
    {
        $result = $this->authService->registrarUsuario($request);

        return response()->json($result['payload'], $result['status']);
    }

    public function agendar(
        Request $request,
        PriorityEvaluator $priorityEvaluator,
        ProfessionalScheduleService $scheduleService,
        SlotHoldService $slotHoldService
    ): JsonResponse {
        $result = $this->appointmentService->agendar($request);

        return response()->json($result['payload'], $result['status']);
    }

    public function buscarCitas(Request $request): JsonResponse
    {
        $result = $this->appointmentService->buscarCitas($request);

        return response()->json($result['payload'], $result['status']);
    }

    public function cancelar(Request $request): JsonResponse
    {
        $result = $this->appointmentService->cancelar($request);

        return response()->json($result['payload'], $result['status']);
    }

    public function reagendar(
        Request $request,
        PriorityEvaluator $priorityEvaluator,
        ProfessionalScheduleService $scheduleService
    ): JsonResponse {
        $result = $this->appointmentService->reagendar($request);

        return response()->json($result['payload'], $result['status']);
    }

    public function perfil(Request $request): JsonResponse
    {
        $result = $this->profileService->perfil($request);

        return response()->json($result['payload'], $result['status']);
    }

    public function actualizarPerfil(Request $request): JsonResponse
    {
        $result = $this->profileService->actualizarPerfil($request);

        return response()->json($result['payload'], $result['status']);
    }

    public function finalizar(Request $request): JsonResponse
    {
        $result = $this->appointmentService->finalizar($request);

        return response()->json($result['payload'], $result['status']);
    }

    /*
    |--------------------------------------------------------------------------
    | Protected Compatibility Proxies
    |--------------------------------------------------------------------------
    | Preserves internal helper method signatures for extensions or tests.
    */

    protected function enviarCredencialesChatbot(User $user, string $passwordPlano): array
    {
        return $this->authService->enviarCredencialesChatbot($user, $passwordPlano);
    }

    protected function codigoCacheKey(string $cedula, string $email): string
    {
        return $this->authService->codigoCacheKey($cedula, $email);
    }

    protected function pendingRegistrationCacheKey(string $cedula, string $email): string
    {
        return $this->authService->pendingRegistrationCacheKey($cedula, $email);
    }

    protected function otpSendCooldownKey(Request $request, string $email): string
    {
        return $this->authService->otpSendCooldownKey($request, $email);
    }

    protected function otpSendQuotaKey(Request $request, string $email): string
    {
        return $this->authService->otpSendQuotaKey($request, $email);
    }

    protected function otpSendSessionBucket(Request $request): string
    {
        return $this->authService->otpSendSessionBucket($request);
    }

    protected function clearChatbotIdentitySession(Request $request): void
    {
        $this->authService->clearChatbotIdentitySession($request);
    }

    protected function calcularSlotsDisponibles(int $doctorId, string $fecha, $bloques = null): array
    {
        return $this->catalogService->calcularSlotsDisponibles($doctorId, $fecha, $bloques);
    }
}
