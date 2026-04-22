<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ProfessionalScheduleService;

class DoctorSlotController extends Controller
{
    public function __invoke(User $doctor, string $fecha, ProfessionalScheduleService $scheduleService)
    {
        if (! $doctor->isActive() || ! ($doctor->hasRole('doctor') || $doctor->hasRole('laboratorio'))) {
            return response()->json([
                'slots' => [],
                'message' => 'Profesional no disponible.',
            ], 404);
        }

        return response()->json([
            'slots' => $scheduleService->buildSlotsForDate($doctor->id, $fecha, config('app.timezone', 'America/Guayaquil')),
        ]);
    }
}
