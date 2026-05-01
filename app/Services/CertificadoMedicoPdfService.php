<?php

namespace App\Services;

use App\Models\CertificadoMedico;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificadoMedicoPdfService
{
    public function obtenerOGenerar(CertificadoMedico $certificado): string
    {
        $path = (string) $certificado->pdf_path;

        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return $this->generarYGuardar($certificado);
        }

        return $path;
    }

    public function generarYGuardar(CertificadoMedico $certificado): string
    {
        $certificado->loadMissing([
            'cita.especialidad',
            'paciente',
            'doctor.especialidades',
            'clinicalRecord',
        ]);

        $identity = app(ClinicIdentityService::class);

        $html = view('pdf.certificado-medico', [
            'certificado' => $certificado,
            'clinica' => $identity->institutionalName(),
            'logoBase64' => $identity->logoBase64(),
            'pdfCss' => $this->loadPdfCss('certificado-medico-pdf.css'),
        ])->render();

        $pdfOutput = $this->renderizarPdf($html);

        $folder = 'certificados-medicos';
        if (! Storage::disk('local')->exists($folder)) {
            Storage::disk('local')->makeDirectory($folder);
        }

        $fileName = Str::slug($certificado->codigo, '_').'.pdf';
        $path = $folder.'/'.$fileName;

        Storage::disk('local')->put($path, $pdfOutput);

        if ($certificado->pdf_path !== $path) {
            $certificado->forceFill(['pdf_path' => $path])->saveQuietly();
        }

        return $path;
    }

    protected function renderizarPdf(string $html): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    protected function logoBase64(): ?string
    {
        return app(ClinicIdentityService::class)->logoBase64();
    }

    protected function loadPdfCss(string $relativePath): string
    {
        $path = resource_path('css/'.$relativePath);

        return is_file($path) ? (file_get_contents($path) ?: '') : '';
    }
}
