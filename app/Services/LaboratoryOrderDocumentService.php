<?php

namespace App\Services;

use App\Models\PedidoLaboratorio;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LaboratoryOrderDocumentService
{
    public function getOrderDiskName(): string
    {
        return config('private_documents.disk') ?: 'r2_private';
    }

    public function resolveDisk(?string $pdfDisk = null): string
    {
        if ($pdfDisk === 'r2_private') {
            return 'r2_private';
        }

        return 'local';
    }

    public function generatePdfOutput(PedidoLaboratorio $pedido): array
    {
        $pedido->loadMissing(['cita.doctor', 'cita.especialidad', 'cita.paciente', 'cita.notaSoap']);
        $identity = app(ClinicIdentityService::class);
        $csvService = app(DocumentoCsvService::class);
        $csv = $csvService->ensureCsv($pedido);

        $html = view('pdf.pedido-laboratorio', [
            'pedido' => $pedido,
            'clinica' => $identity->institutionalName(),
            'slogan' => $identity->slogan(),
            'logoBase64' => $identity->logoBase64ForPdf(),
            'pdfCss' => $this->loadPdfCss('doctor/pedido-laboratorio-pdf.css'),
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

    private function loadPdfCss(string $relativePath): string
    {
        $basePath = resource_path('css/pdf/base.css');
        $specificPath = resource_path('css/'.$relativePath);

        $css = is_file($basePath) ? (file_get_contents($basePath) ?: '') : '';
        $css .= is_file($specificPath) ? "\n".(file_get_contents($specificPath) ?: '') : '';

        return $css;
    }

    public function storeOrderPdf(PedidoLaboratorio $pedido, string $pdfBinary): array
    {
        $targetDisk = $this->getOrderDiskName();
        $uuid = (string) Str::uuid();
        $key = "documents/laboratory-orders/{$pedido->id}/{$uuid}.pdf";

        $disk = Storage::disk($targetDisk);
        $success = $disk->put($key, $pdfBinary, [
            'ContentType' => 'application/pdf',
            'visibility' => 'private',
        ]);

        if (! $success && ! $disk->exists($key)) {
            throw new \RuntimeException("Failed to write laboratory order PDF to {$targetDisk} at key: {$key}");
        }

        return [
            'pdf_path' => $key,
            'pdf_disk' => $targetDisk,
        ];
    }

    public function replaceOrderPdf(PedidoLaboratorio $pedido, string $pdfBinary): array
    {
        $oldPath = (string) $pedido->getRawOriginal('pdf_path');
        $oldDisk = (string) $pedido->getRawOriginal('pdf_disk');

        $newStorage = $this->storeOrderPdf($pedido, $pdfBinary);

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
                Log::warning('Failed to delete old R2 laboratory order PDF', [
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
                Log::warning('Failed to delete laboratory order object quietly from disk', [
                    'disk' => $disk,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function ensureUserCanView(PedidoLaboratorio $pedido): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if ($user->hasRole('superadmin') || $user->hasRole('administrador') || $user->hasRole('laboratorio')) {
            return;
        }

        if ((int) $pedido->doctor_id === (int) $user->id) {
            return;
        }

        if ((int) $pedido->paciente_id === (int) $user->id) {
            return;
        }

        if ($pedido->cita && $pedido->cita->dependiente_id && $pedido->cita->dependiente) {
            if ((int) $pedido->cita->dependiente->user_id === (int) $user->id) {
                return;
            }
        }

        abort(403, 'No autorizado para acceder a esta orden de laboratorio.');
    }

    public function streamDownload(PedidoLaboratorio $pedido, ?string $filename = null)
    {
        $pedido->loadMissing(['paciente', 'cita.dependiente']);
        $this->ensureUserCanView($pedido);

        $filename = $filename ?: "pedido_laboratorio_{$pedido->id}.pdf";
        $diskName = $this->resolveDisk($pedido->pdf_disk);

        if (! Storage::disk($diskName)->exists($pedido->pdf_path)) {
            return back()->withErrors(['error' => 'El archivo de la orden de laboratorio no existe en el almacenamiento.']);
        }

        if ($diskName === 'local') {
            return Storage::disk('local')->download($pedido->pdf_path, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        $content = Storage::disk('r2_private')->get($pedido->pdf_path);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function streamInline(PedidoLaboratorio $pedido, ?string $filename = null)
    {
        $filename = $filename ?: "orden_medica_{$pedido->id}.pdf";
        $diskName = $this->resolveDisk($pedido->pdf_disk);

        if (! Storage::disk($diskName)->exists($pedido->pdf_path)) {
            abort(404, 'Archivo de orden de laboratorio no encontrado.');
        }

        if ($diskName === 'local') {
            return response()->file(Storage::disk('local')->path($pedido->pdf_path), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        $content = Storage::disk('r2_private')->get($pedido->pdf_path);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function getMailAttachment(PedidoLaboratorio $pedido, string $filename, ?string $fallbackBinary = null): ?Attachment
    {
        $diskName = $this->resolveDisk($pedido->pdf_disk);

        if (! empty($pedido->pdf_path) && Storage::disk($diskName)->exists($pedido->pdf_path)) {
            return Attachment::fromStorageDisk($diskName, $pedido->pdf_path)
                ->as($filename)
                ->withMime('application/pdf');
        }

        if (! empty($fallbackBinary)) {
            return Attachment::fromData(fn () => $fallbackBinary, $filename)
                ->withMime('application/pdf');
        }

        return null;
    }

    public function migrateLocalOrderToR2(PedidoLaboratorio $pedido): array
    {
        $localPath = (string) $pedido->getRawOriginal('pdf_path');

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
        $r2Key = "documents/laboratory-orders/{$pedido->id}/{$uuid}.pdf";

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

        $pedido->forceFill([
            'pdf_path' => $r2Key,
            'pdf_disk' => 'r2_private',
        ])->saveQuietly();

        return [
            'success' => true,
            'pedido_id' => $pedido->id,
            'old_path' => $localPath,
            'new_key' => $r2Key,
            'size' => $size,
            'sha256' => $sha256,
        ];
    }
}
