<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ProfessionalScheduleService;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;

class DoctorSlotController extends Controller
{
    public function __invoke(Request $request, User $doctor, string $fecha, ProfessionalScheduleService $scheduleService)
    {
        try {
            if (! $doctor->isActive() || ! ($doctor->hasRole('doctor') || $doctor->hasRole('laboratorio'))) {
                return response()->json([
                    'slots' => [],
                    'message' => 'Profesional no disponible.',
                ], 404);
            }

            return response()->json([
                'slots' => $scheduleService->buildSlotsForDate(
                    $doctor->id,
                    $fecha,
                    config('app.timezone', 'America/Guayaquil'),
                    $request->query('hold_token')
                ),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en API al consultar slots de doctor', [
                'doctor_id' => $doctor->id ?? null,
                'fecha' => $fecha,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'slots' => [],
                'message' => 'Ocurrió un error inesperado al cargar los horarios.',
            ], 500);
        }
    }
}
