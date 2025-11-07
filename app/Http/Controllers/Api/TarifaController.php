<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;

class TarifaController extends Controller
{
    /**
     * Devuelve la tarifa (precio + moneda) del doctor.
     * GET /api/tarifa/doctor/{id}
     */
    public function precioDoctor(int $id)
    {
        $doctor = User::query()
            ->where('id', $id)
            ->where('active', true)
            ->first();

        if (!$doctor || !$doctor->hasRole('doctor')) {
            return response()->json([
                'ok'      => false,
                'message' => 'Doctor no encontrado o inactivo.',
            ], 404);
        }

        $precio  = $doctor->precio_consulta; // puede ser null si no configurado aún
        $moneda  = $doctor->moneda ?: 'USD';
        $definido = !is_null($precio);

        return response()->json([
            'ok'             => true,
            'doctor_id'      => $doctor->id,
            'doctor_nombre'  => $doctor->name,
            'precio'         => $definido ? (float) $precio : null,
            'moneda'         => $moneda,
            'definido'       => $definido,
            'precio_format'  => $definido ? ('$' . number_format((float)$precio, 2) . ' ' . $moneda) : null,
            'label'          => $definido
                ? ('Tarifa: $' . number_format((float)$precio, 2) . ' ' . $moneda)
                : 'Tarifa no configurada',
        ], 200);
    }
}
