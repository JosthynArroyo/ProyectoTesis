<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Services\CitaComprobanteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CitaComprobanteController extends Controller
{
    public function showByToken(Request $request, string $token)
    {
        $cita = Cita::query()
            ->with([
                'paciente:id,name,dni,email,telefono',
                'doctor:id,name',
                'especialidad:id,nombre',
            ])
            ->where('token_validacion', $token)
            ->firstOrFail();

        $user = $request->user();
        abort_unless($user, 403);

        $esSuperadmin = $user->hasRole('superadmin');
        $esAdmin = $user->hasRole('administrador');
        $esPacientePropietario = $user->hasRole('paciente') && (int) $cita->paciente_id === (int) $user->id;
        $esProfesionalResponsable = ($user->hasRole('doctor') || $user->hasRole('laboratorio'))
            && (int) $cita->doctor_id === (int) $user->id;

        if (! $esSuperadmin && ! $esAdmin && ! $esPacientePropietario && ! $esProfesionalResponsable) {
            abort(403);
        }

        return view('citas.comprobante-show', [
            'cita' => $cita,
            'esSuperadmin' => $esSuperadmin,
            'esAdmin' => $esAdmin,
            'esPacientePropietario' => $esPacientePropietario,
            'esProfesionalResponsable' => $esProfesionalResponsable,
        ]);
    }

    public function pdfPaciente(Request $request, Cita $cita, \App\Services\AppointmentConfirmationDocumentService $confirmationDocumentService)
    {
        return $confirmationDocumentService->streamConfirmationResponse($cita, $request->user(), 'inline');
    }
}
