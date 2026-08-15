<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\Receta;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecipeDocumentService
{
    public function getRecipeDiskName(): string
    {
        return config('private_documents.recipe_disk') ?: 'r2_private';
    }

    public function resolveDisk(?string $pdfDisk = null): string
    {
        if ($pdfDisk === 'r2_private') {
            return 'r2_private';
        }

        return 'local';
    }

    public function generatePdfOutput(
        Cita $cita,
        string $diagnostico,
        string $medicamentos,
        string $indicaciones = '',
        ?string $csv = null,
        ?DocumentoCsvService $csvService = null
    ): array {
        $csvService = $csvService ?: app(DocumentoCsvService::class);
        $csv = $csv ? strtoupper(trim($csv)) : $csvService->generateCsv();

        $identity = app(ClinicIdentityService::class);

        $viewData = [
            'cita' => $cita,
            'diagnostico' => $diagnostico,
            'medicamentos' => $medicamentos,
            'indicaciones' => $indicaciones,
            'fechaPdf' => now('America/Guayaquil'),
            'logoBase64' => $identity->logoBase64ForPdf(),
            'pdfCss' => $this->loadPdfCss('doctor/receta-pdf.css'),
            'csv' => $csv,
            'verificationUrl' => $csvService->verificationUrl($csv),
            'qrDataUri' => $csvService->qrDataUri($csv),
        ];

        $html = view('pdf.receta', $viewData)->render();

        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        try {
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4');
            $dompdf->render();
            return [$dompdf->output(), $csv];
        } catch (\DivisionByZeroError $e) {
            $cleanHtml = preg_replace('/<img[^>]+>/i', '', $html);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($cleanHtml, 'UTF-8');
            $dompdf->setPaper('A4');
            $dompdf->render();
            return [$dompdf->output(), $csv];
        }
    }

    public function storeRecipePdf(Receta $receta, string $pdfBinary): array
    {
        $targetDisk = $this->getRecipeDiskName();
        $uuid = (string) Str::uuid();
        $key = "documents/recipes/{$receta->id}/{$uuid}.pdf";

        $disk = Storage::disk($targetDisk);
        $success = $disk->put($key, $pdfBinary, [
            'ContentType' => 'application/pdf',
            'visibility' => 'private',
        ]);

        if (! $success && ! $disk->exists($key)) {
            throw new \RuntimeException("Failed to write recipe PDF to {$targetDisk} at key: {$key}");
        }

        return [
            'pdf_path' => $key,
            'pdf_disk' => $targetDisk,
        ];
    }

    public function replaceRecipePdf(Receta $receta, string $pdfBinary): array
    {
        $oldPath = (string) $receta->getRawOriginal('pdf_path');
        $oldDisk = (string) $receta->getRawOriginal('pdf_disk');

        $newStorage = $this->storeRecipePdf($receta, $pdfBinary);

        return array_merge($newStorage, [
            'old_pdf_path' => $oldPath,
            'old_pdf_disk' => $oldDisk,
        ]);
    }

    public function cleanupOldPdf(?string $oldPath, ?string $oldDisk): void
    {
        if ($oldDisk === 'r2_private' && ! empty($oldPath)) {
            try {
                if (Storage::disk('r2_private')->exists($oldPath)) {
                    Storage::disk('r2_private')->delete($oldPath);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to delete old R2 recipe PDF', [
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
                Log::warning('Failed to delete object quietly from disk', [
                    'disk' => $disk,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function ensureUserCanView(Cita $cita): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if ($user->hasRole('superadmin') || $user->hasRole('administrador')) {
            return;
        }

        if ((int) $cita->doctor_id === (int) $user->id) {
            return;
        }

        if ((int) $cita->paciente_id === (int) $user->id) {
            return;
        }

        if ($cita->dependiente_id && $cita->dependiente) {
            if ((int) $cita->dependiente->user_id === (int) $user->id) {
                return;
            }
        }

        abort(403, 'No autorizado para acceder a esta receta medica.');
    }

    public function streamDownload(Receta $receta, ?string $filename = null)
    {
        $receta->loadMissing(['cita.paciente', 'cita.dependiente']);
        $this->ensureUserCanView($receta->cita);

        $filename = $filename ?: ('receta_'.$receta->cita_id.'.pdf');
        $diskName = $this->resolveDisk($receta->pdf_disk);

        if (! Storage::disk($diskName)->exists($receta->pdf_path)) {
            return back()->withErrors(['error' => 'El archivo de la receta no existe en el almacenamiento.']);
        }

        if ($diskName === 'local') {
            return Storage::disk('local')->download($receta->pdf_path, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        $content = Storage::disk('r2_private')->get($receta->pdf_path);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function streamInline(Receta $receta, ?string $filename = null)
    {
        $filename = $filename ?: ('receta_'.$receta->cita_id.'.pdf');
        $diskName = $this->resolveDisk($receta->pdf_disk);

        if (! Storage::disk($diskName)->exists($receta->pdf_path)) {
            abort(404, 'Archivo de receta no encontrado.');
        }

        if ($diskName === 'local') {
            return response()->file(Storage::disk('local')->path($receta->pdf_path), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        $content = Storage::disk('r2_private')->get($receta->pdf_path);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function getMailAttachment(Receta $receta, string $filename, ?string $fallbackBinary = null): ?Attachment
    {
        $diskName = $this->resolveDisk($receta->pdf_disk);

        if (! empty($receta->pdf_path) && Storage::disk($diskName)->exists($receta->pdf_path)) {
            return Attachment::fromStorageDisk($diskName, $receta->pdf_path)
                ->as($filename)
                ->withMime('application/pdf');
        }

        if (! empty($fallbackBinary)) {
            return Attachment::fromData(fn () => $fallbackBinary, $filename)
                ->withMime('application/pdf');
        }

        return null;
    }

    public function migrateLocalRecipeToR2(Receta $receta): array
    {
        $localPath = (string) $receta->getRawOriginal('pdf_path');

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
        $r2Key = "documents/recipes/{$receta->id}/{$uuid}.pdf";

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

        $receta->forceFill([
            'pdf_path' => $r2Key,
            'pdf_disk' => 'r2_private',
        ])->saveQuietly();

        return [
            'success' => true,
            'receta_id' => $receta->id,
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
