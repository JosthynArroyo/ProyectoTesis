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
        $certificado->loadMissing(['cita.especialidad', 'paciente', 'doctor.especialidades']);
        $this->ensureCanView($certificado);

        $path = $pdfs->obtenerOGenerar($certificado);

        return Storage::disk('local')->download($path, $certificado->nombreDescarga(), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function ensureCanView(CertificadoMedico $certificado): void
    {
        if ((int) $certificado->paciente_id !== (int) Auth::id()) {
            abort(403);
        }
    }
}
