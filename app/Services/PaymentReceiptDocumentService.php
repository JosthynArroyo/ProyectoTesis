<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentReceiptDocumentService
{
    public const DISK = 'r2_private';

    public function getDisk(): string
    {
        $disk = (string) config('private_documents.disk', self::DISK);

        if (app()->environment('testing') && $disk === self::DISK) {
            try {
                $adapterClass = get_class(Storage::disk(self::DISK)->getAdapter());
                if (! str_contains($adapterClass, 'Local')) {
                    Storage::fake(self::DISK);
                }
            } catch (\Throwable $e) {
                // Fallback
            }
        }

        return $disk;
    }

    public function generateAndStoreReceiptPdfContentOnly(Pago $pago, PaymentReceipt $receipt, PagoDocumentoService $documentoService): string
    {
        // 1. Generate PDF in memory using existing PagoDocumentoService
        $pago->loadMissing([
            'paciente:id,name,dni,telefono',
            'cita:id,doctor_id,especialidad_id,fecha,hora,estado,dependiente_id',
            'cita.dependiente',
            'cita.doctor:id,name',
            'cita.especialidad:id,nombre',
            'aprobador:id,name',
        ]);
        $receipt->loadMissing('emisor:id,name');

        // Render PDF binary in memory
        $pdfContent = $documentoService->generarReciboPagoPdfContent($pago, $receipt);

        // Verify PDF content
        $this->verifyPdfContent($pdfContent);

        // 2. Generate UUID key: documents/payment-receipts/{pago_id}/{uuid}.pdf
        $uuid = Str::uuid()->toString();
        $key = "documents/payment-receipts/{$pago->id}/{$uuid}.pdf";
        $disk = $this->getDisk();

        // 3. Upload bytes to r2_private
        $uploadSuccess = false;
        try {
            $uploadSuccess = Storage::disk($disk)->put($key, $pdfContent);
        } catch (\Throwable $e) {
            throw new \RuntimeException('No se pudo guardar el PDF del recibo en almacenamiento R2: ' . $e->getMessage(), 0, $e);
        }
        if (! $uploadSuccess) {
            throw new \RuntimeException('No se pudo guardar el PDF del recibo en almacenamiento R2.');
        }

        // 4. Desde que put() retorna true, $key está potencialmente materializada.
        // Toda verificación posterior debe realizar cleanup directo e incondicional de $key ante cualquier fallo.
        try {
            if (! Storage::disk($disk)->exists($key)) {
                throw new \RuntimeException('El PDF del recibo guardado no se encuentra en R2.');
            }

            $size = Storage::disk($disk)->size($key);
            if ($size <= 0 || $size !== strlen($pdfContent)) {
                throw new \RuntimeException('Inconsistencia en el tamaño del PDF del recibo en R2.');
            }

            $readHeader = Storage::disk($disk)->get($key);
            if (! str_starts_with((string) $readHeader, '%PDF')) {
                throw new \RuntimeException('El archivo almacenado en R2 no es un PDF valido.');
            }
        } catch (\Throwable $verifyEx) {
            try {
                Storage::disk($disk)->delete($key);
            } catch (\Throwable $cleanupEx) {
                \Illuminate\Support\Facades\Log::error('Fallo en cleanup de R2 tras verificación fallida de recibo de pago', [
                    'pago_id' => $pago->id,
                    'key' => $key,
                    'disk' => $disk,
                    'primary_error' => $verifyEx->getMessage(),
                    'cleanup_error' => $cleanupEx->getMessage(),
                ]);
            }

            throw $verifyEx;
        }

        return $key;
    }

    public function generateAndStoreReceiptPdf(Pago $pago, PaymentReceipt $receipt, PagoDocumentoService $documentoService): string
    {
        $key = $this->generateAndStoreReceiptPdfContentOnly($pago, $receipt, $documentoService);
        $disk = $this->getDisk();

        // Update DB atomically in transaction
        try {
            DB::transaction(function () use ($receipt, $key, $disk) {
                $receipt->pdf_path = $key;
                $receipt->pdf_disk = $disk;
                $receipt->save();
            });
        } catch (\Throwable $e) {
            // Compensating deletion: delete ONLY newly uploaded R2 object if DB update fails
            try {
                Storage::disk($disk)->delete($key);
            } catch (\Throwable $cleanupEx) {
                \Illuminate\Support\Facades\Log::error('Fallo en cleanup de R2 tras error en transacción DB de recibo', [
                    'pago_id' => $pago->id,
                    'key' => $key,
                    'disk' => $disk,
                    'primary_error' => $e->getMessage(),
                    'cleanup_error' => $cleanupEx->getMessage(),
                ]);
            }
            throw $e;
        }

        return $key;
    }

    public function verifyPdfContent(string $content): void
    {
        if (strlen($content) <= 0) {
            throw new \InvalidArgumentException('El contenido del PDF esta vacio.');
        }

        if (! str_starts_with($content, '%PDF')) {
            throw new \InvalidArgumentException('El contenido proporcionado no es un documento PDF valido.');
        }
    }

    /**
     * @return array{disk:string,path:string}|null
     */
    public function resolveStorage(?string $path, ?string $disk = null): ?array
    {
        $normalized = trim((string) $path);
        if ($normalized === '') {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $normalized), '/');

        if ($disk && Storage::disk($disk)->exists($normalized)) {
            return ['disk' => $disk, 'path' => $normalized];
        }

        if (Storage::disk(self::DISK)->exists($normalized)) {
            return ['disk' => self::DISK, 'path' => $normalized];
        }

        if (Storage::disk('local')->exists($normalized)) {
            return ['disk' => 'local', 'path' => $normalized];
        }

        if (Storage::disk('public')->exists($normalized)) {
            return ['disk' => 'public', 'path' => $normalized];
        }

        return null;
    }

    public function userCanViewReceipt(?User $user, PaymentReceipt $receipt, string $context = 'paciente'): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('doctor') || $user->hasRole('laboratorio')) {
            return false;
        }

        if ($context === 'admin') {
            return $user->hasRole('administrador') || $user->hasRole('superadmin');
        }

        if ($context === 'paciente') {
            if ($user->hasRole('paciente')) {
                $receipt->loadMissing(['pago.cita.dependiente']);
                $pago = $receipt->pago;

                if (! $pago) {
                    return false;
                }

                // Titular owner
                if ((int) $pago->paciente_id === (int) $user->id) {
                    return true;
                }

                // Representative for dependent
                if ($pago->cita && $pago->cita->dependiente_id) {
                    if ((int) $pago->cita->dependiente?->user_id === (int) $user->id) {
                        return true;
                    }

                    if ($user->dependientes()->where('id', $pago->cita->dependiente_id)->exists()) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function streamReceiptResponse(PaymentReceipt $receipt, ?User $user, string $context = 'paciente'): StreamedResponse
    {
        if (! $user) {
            abort(401);
        }

        if (! $this->userCanViewReceipt($user, $receipt, $context)) {
            abort(403);
        }

        $stored = $this->resolveStorage($receipt->pdf_path, $receipt->pdf_disk);
        if (! $stored) {
            abort(404);
        }

        $diskInst = Storage::disk($stored['disk']);
        $path = $stored['path'];

        $stream = $diskInst->readStream($path);
        if (! is_resource($stream)) {
            abort(404);
        }

        $fileName = 'recibo_pago_' . ($receipt->folio_recibo ?: $receipt->id) . '.pdf';

        return response()->stream(
            function () use ($stream) {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            ]
        );
    }
}
