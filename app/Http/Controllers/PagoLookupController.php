<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use Illuminate\Http\Request;

class PagoLookupController extends Controller
{
    public function showByToken(Request $request, string $token)
    {
        $pago = Pago::query()
            ->with([
                'paciente:id,name,dni,email',
                'cita:id,doctor_id,especialidad_id,fecha,hora,estado',
                'cita.doctor:id,name',
                'cita.especialidad:id,nombre',
                'receipt:id,pago_id,folio_recibo,emitido_en,pdf_path',
            ])
            ->where('token_publico', $token)
            ->firstOrFail();

        $user = $request->user();
        $esAdmin = $user->hasRole('administrador') || $user->hasRole('superadmin');
        $esPacientePropietario = $user->hasRole('paciente') && (int) $pago->paciente_id === (int) $user->id;

        if (! $esAdmin && ! $esPacientePropietario) {
            abort(403);
        }

        return view('pagos.token-show', [
            'pago' => $pago,
            'esAdmin' => $esAdmin,
            'esPacientePropietario' => $esPacientePropietario,
        ]);
    }
}
