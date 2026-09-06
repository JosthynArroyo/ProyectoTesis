<?php

namespace App\Services;

use App\Models\CertificadoMedico;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MedicalCertificateDocumentService
{
    public function getCertificateDiskName(): string
    {
        return (string) (config('private_documents.disk') ?: 'local');
    }

    public function resolveDisk(?string $pdfDisk = null): string
    {
        if ($pdfDisk === 'r2_private') {
            return 'r2_private';
        }

        return 'local';
    }

    public function generatePdfOutput(CertificadoMedico $certificado): array
    {
        $certificado->loadMissing([
            'cita.especialidad',
            'paciente',
            'doctor.especialidades',
            'clinicalRecord',
        ]);

        $identity = app(ClinicIdentityService::class);
        $csvService = app(DocumentoCsvService::class);
        $csv = $csvService->ensureCsv($certificado);

        $html = view('pdf.certificado-medico', [
            'certificado' => $certificado,
            'clinica' => $identity->institutionalName(),
            'logoBase64' => $identity->logoBase64ForPdf(),
            'pdfCss' => $this->loadPdfCss('certificado-medico-pdf.css'),
            'csv' => $csv,
            'verificationUrl' => $csvService->verificationUrl($csv),
            'qrDataUri' => $csvService->qrDataUri($csv),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return [$dompdf->output(), $csv];
    }

    public function storeCertificatePdf(CertificadoMedico $certificado, string $pdfBinary): array
    {
        $targetDisk = $this->getCertificateDiskName();
        $uuid = (string) Str::uuid();
        $key = "documents/medical-certificates/{$certificado->id}/{$uuid}.pdf";

        $disk = Storage::disk($targetDisk);
        $success = $disk->put($key, $pdfBinary, [
            'ContentType' => 'application/pdf',
            'visibility' => 'private',
        ]);

        if (! $success && ! $disk->exists($key)) {
            throw new \RuntimeException("Failed to write medical certificate PDF to {$targetDisk} at key: {$key}");
        }

        return [
            'pdf_path' => $key,
            'pdf_disk' => $targetDisk,
        ];
    }

    public function replaceCertificatePdf(CertificadoMedico $certificado, string $pdfBinary): array
    {
        $oldPath = (string) $certificado->getRawOriginal('pdf_path');
        $oldDisk = (string) $certificado->getRawOriginal('pdf_disk');

        $newStorage = $this->storeCertificatePdf($certificado, $pdfBinary);

        return array_merge($newStorage, [
            'old_pdf_path' => $oldPath,
            'old_pdf_disk' => $oldDisk,
        ]);
    }

    public function cleanupOldPdf(?string $oldPath, ?string $oldDisk): void
    {
        $disk = $oldDisk === 'r2_private' ? 'r2_private' : 'local';
        if (! empty($oldPath)) {
            try {
                if (Storage::disk($disk)->exists($oldPath)) {
                    Storage::disk($disk)->delete($oldPath);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to delete old medical certificate PDF', [
                    'old_pdf_disk' => $disk,
                    'old_pdf_path' => $oldPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function deleteQuietly(?string $path, string $disk = 'r2_private'): void
    {
        if (! empty($path)) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to delete medical certificate object quietly from disk', [
                    'disk' => $disk,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function ensureUserCanView(CertificadoMedico $certificado): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if ($user->hasRole('superadmin') || $user->hasRole('administrador')) {
            return;
        }

        if ((int) $certificado->doctor_id === (int) $user->id) {
            return;
        }

        if ((int) $certificado->paciente_id === (int) $user->id) {
            return;
        }

        if ($certificado->dependiente_id && $certificado->dependiente) {
            if ((int) $certificado->dependiente->user_id === (int) $user->id) {
                return;
            }
        }

        abort(403, 'No autorizado para acceder a este certificado medico.');
    }

    public function streamDownload(CertificadoMedico $certificado, ?string $filename = null)
    {
        $certificado->loadMissing(['paciente', 'dependiente']);
        $this->ensureUserCanView($certificado);

        $filename = $filename ?: $certificado->nombreDescarga();
        $diskName = $this->resolveDisk($certificado->pdf_disk);

        if (! Storage::disk($diskName)->exists($certificado->pdf_path)) {
            return back()->withErrors(['error' => 'El archivo del certificado no existe en el almacenamiento.']);
        }

        if ($diskName === 'local') {
            return Storage::disk('local')->download($certificado->pdf_path, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        $content = Storage::disk('r2_private')->get($certificado->pdf_path);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function streamInline(CertificadoMedico $certificado, ?string $filename = null)
    {
        $filename = $filename ?: $certificado->nombreDescarga();
        $diskName = $this->resolveDisk($certificado->pdf_disk);

        if (! Storage::disk($diskName)->exists($certificado->pdf_path)) {
            abort(404, 'Archivo de certificado no encontrado.');
        }

        if ($diskName === 'local') {
            return response()->file(Storage::disk('local')->path($certificado->pdf_path), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        $content = Storage::disk('r2_private')->get($certificado->pdf_path);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function getMailAttachment(CertificadoMedico $certificado, string $filename, ?string $fallbackBinary = null): ?Attachment
    {
        $diskName = $this->resolveDisk($certificado->pdf_disk);

        if (! empty($certificado->pdf_path) && Storage::disk($diskName)->exists($certificado->pdf_path)) {
            return Attachment::fromStorageDisk($diskName, $certificado->pdf_path)
                ->as($filename)
                ->withMime('application/pdf');
        }

        if (! empty($fallbackBinary)) {
            return Attachment::fromData(fn () => $fallbackBinary, $filename)
                ->withMime('application/pdf');
        }

        return null;
    }

    public function migrateLocalCertificateToR2(CertificadoMedico $certificado): array
    {
        $localPath = (string) $certificado->getRawOriginal('pdf_path');

        if (empty($localPath) || ! Storage::disk('local')->exists($localPath)) {
            return [
                'success' => false,
                'error' => 'Local PDF file does not exist',
            ];
        }

        $binary = Storage::disk('local')->get($localPath);
        $size = strlen($binary);
        $sha256 = hash('sha256', $binary);

        $uuid = (string) Str::uuid();
        $r2Key = "documents/medical-certificates/{$certificado->id}/{$uuid}.pdf";

        $disk = Storage::disk('r2_private');
        $disk->put($r2Key, $binary, [
            'ContentType' => 'application/pdf',
            'visibility' => 'private',
        ]);

        $r2Binary = $disk->get($r2Key);
        $r2Size = strlen($r2Binary);
        $r2Sha256 = hash('sha256', $r2Binary);

        if ($size !== $r2Size || $sha256 !== $r2Sha256) {
            $this->deleteQuietly($r2Key, 'r2_private');
            return [
                'success' => false,
                'error' => 'Verification failed: size or SHA-256 mismatch',
            ];
        }

        $certificado->forceFill([
            'pdf_path' => $r2Key,
            'pdf_disk' => 'r2_private',
        ])->saveQuietly();

        return [
            'success' => true,
            'certificado_id' => $certificado->id,
            'old_path' => $localPath,
            'new_key' => $r2Key,
            'size' => $size,
            'sha256' => $sha256,
        ];
    }

    protected function loadPdfCss(string $relativePath): string
    {
        $basePath = resource_path('css/pdf/base.css');
        $specificPath = resource_path('css/'.$relativePath);

        $css = is_file($basePath) ? (file_get_contents($basePath) ?: '') : '';
        $css .= is_file($specificPath) ? "\n".(file_get_contents($specificPath) ?: '') : '';

        return $css;
    }
}
