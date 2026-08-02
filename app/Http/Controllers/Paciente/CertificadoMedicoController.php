<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Models\CertificadoMedico;
use App\Services\CertificadoMedicoPdfService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CertificadoMedicoController extends Controller
{
    public function show(CertificadoMedico $certificado)
    {
        $certificado->loadMissing(['cita.especialidad', 'paciente', 'doctor.especialidades']);
        $this->ensureCanView($certificado);

        return view('paciente.certificados.show', [
            'certificado' => $certificado,
        ]);
    }

    public function download(CertificadoMedico $certificado, CertificadoMedicoPdfService $pdfs)
    {
        $docService = app(\App\Services\MedicalCertificateDocumentService::class);
        $docService->ensureUserCanView($certificado);

        $pdfs->obtenerOGenerar($certificado);

        return $docService->streamDownload($certificado);
    }

    private function ensureCanView(CertificadoMedico $certificado): void
    {
        if ((int) $certificado->paciente_id !== (int) Auth::id()) {
            abort(403);
        }
    }
}
