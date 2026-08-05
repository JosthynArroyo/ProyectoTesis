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
                'dependiente',
            ])
            ->where('token_validacion', $token)
            ->first();

        if (! $cita) {
            abort(404);
        }

        $clinic = app(\App\Services\ClinicIdentityService::class);

        $estadoClean = match($cita->estado) {
            'pendiente' => 'Pendiente',
            'confirmada' => 'Confirmada',
            'cancelada' => 'Cancelada',
            'realizada' => 'Realizada',
            'no_se_presento' => 'No se presentó',
            default => ucfirst((string) $cita->estado),
        };

        $fechaStr = $cita->fecha instanceof \Carbon\Carbon
            ? $cita->fecha->format('Y-m-d')
            : (string) $cita->fecha;

        $citaDateTime = \Carbon\Carbon::parse($fechaStr . ' ' . $cita->hora);

        $pacienteName = $cita->dependiente?->nombre ?? $cita->paciente?->name;
        $protectedPaciente = null;
        if ($pacienteName) {
            $parts = preg_split('/\s+/', trim($pacienteName), -1, PREG_SPLIT_NO_EMPTY);
            if (! empty($parts)) {
                $firstName = $parts[0];
                $lastInitial = isset($parts[1]) ? strtoupper(mb_substr($parts[1], 0, 1)).'.' : null;
                $protectedPaciente = $lastInitial ? "{$firstName} {$lastInitial}" : $firstName;
            }
        }

        return view('documentos.verificacion-show', [
            'csv' => $cita->csv ?: 'HEREDADO',
            'tipo' => 'comprobante_cita',
            'titulo' => 'Comprobante de cita',
            'clinica' => $clinic->institutionalName(),
            'doctor' => $cita->doctor?->name,
            'especialidad' => $cita->especialidad?->nombre,
            'paciente' => $protectedPaciente,
            'emitido_en' => $citaDateTime,
            'estado' => $estadoClean,
            'version' => null,
            'folio' => $cita->folio_cita ?: 'Sin Folio',
            'monto' => null,
            'metodo_pago' => null,
        ]);
    }

    public function pdfPaciente(Request $request, Cita $cita, \App\Services\AppointmentConfirmationDocumentService $confirmationDocumentService)
    {
        return $confirmationDocumentService->streamConfirmationResponse($cita, $request->user(), 'inline');
    }
}
