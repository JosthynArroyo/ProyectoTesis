<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SlotHoldService;
use Illuminate\Http\Request;

class SlotHoldController extends Controller
{
    public function store(Request $request, SlotHoldService $slotHoldService)
    {
        $data = $request->validate([
            'doctor_id' => ['required', 'integer', 'exists:users,id'],
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora' => ['required', 'date_format:H:i'],
            'token' => ['nullable', 'string', 'max:80'],
            'paciente_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $doctor = User::query()
            ->onlyActive()
            ->whereKey($data['doctor_id'])
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['doctor', 'laboratorio']))
            ->first();

        if (! $doctor) {
            return response()->json([
                'ok' => false,
                'message' => 'Profesional no disponible.',
            ], 404);
        }

        $patientId = $request->user() && $request->user()->hasRole('paciente')
            ? (int) $request->user()->id
            : (isset($data['paciente_id']) ? (int) $data['paciente_id'] : null);

        $result = $slotHoldService->acquire(
            professionalId: (int) $doctor->id,
            date: (string) $data['fecha'],
            time: (string) $data['hora'],
            token: $data['token'] ?? null,
            patientId: $patientId,
            sessionId: $request->session()->getId(),
            messages: [
                'missing_schedule' => 'No existe disponibilidad configurada para ese horario.',
                'misaligned' => 'La hora seleccionada no coincide con un bloque disponible.',
                'lead_time' => 'Debes agendar con al menos 1 hora de anticipacion.',
                'conflict' => 'El horario seleccionado ya no esta disponible.',
            ],
            timezone: config('app.timezone', 'America/Guayaquil')
        );

        if (! $result['ok']) {
            return response()->json([
                'ok' => false,
                'message' => $result['message'],
                'field' => $result['field'] ?? 'hora',
                'hold_token' => $result['token'] ?? null,
            ], $result['status'] ?? 422);
        }

        return response()->json([
            'ok' => true,
            'hold_token' => $result['token'],
            'expires_at' => $result['hold']->expires_at?->toIso8601String(),
            'status' => $result['hold']->status,
        ]);
    }
}
