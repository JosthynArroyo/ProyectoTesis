<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentOrderDocumentService
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

    public function generateAndStoreOrderPdf(Pago $pago, PagoDocumentoService $documentoService): string
    {
        $pago->loadMissing([
            'paciente:id,name,dni,telefono',
            'cita:id,doctor_id,especialidad_id,fecha,hora,estado,dependiente_id',
            'cita.dependiente',
            'cita.doctor:id,name',
            'cita.especialidad:id,nombre',
        ]);

        // 1. Render PDF binary in memory
        $pdfContent = $documentoService->generarOrdenCobroPdfContent($pago);

        // 2. Verify PDF content
        $this->verifyPdfContent($pdfContent);

        // 3. Generate UUID key: documents/payment-orders/{pago_id}/{uuid}.pdf
        $uuid = Str::uuid()->toString();
        $key = "documents/payment-orders/{$pago->id}/{$uuid}.pdf";
        $disk = $this->getDisk();

        // 4. Upload bytes to r2_private
        $uploadSuccess = false;
        try {
            $uploadSuccess = Storage::disk($disk)->put($key, $pdfContent);
        } catch (\Throwable $e) {
            Log::error('Error al subir PDF de orden de cobro a R2', [
                'pago_id' => $pago->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('No se pudo guardar el PDF de la orden de cobro en almacenamiento R2: ' . $e->getMessage(), 0, $e);
        }

        if (! $uploadSuccess) {
            throw new \RuntimeException('No se pudo guardar el PDF de la orden de cobro en almacenamiento R2.');
        }

        // 5. Verify object existence, size and %PDF header
        if (! Storage::disk($disk)->exists($key)) {
            throw new \RuntimeException('El PDF de la orden de cobro guardado no se encuentra en R2.');
        }

        $size = Storage::disk($disk)->size($key);
        if ($size <= 0 || $size !== strlen($pdfContent)) {
            Storage::disk($disk)->delete($key);
            throw new \RuntimeException('Inconsistencia en el tamaño del PDF de la orden de cobro en R2.');
        }

        $readHeader = Storage::disk($disk)->get($key);
        if (! str_starts_with((string) $readHeader, '%PDF')) {
            Storage::disk($disk)->delete($key);
            throw new \RuntimeException('El archivo almacenado en R2 no es un PDF valido.');
        }

        // 6. Update DB atomically in transaction
        try {
            DB::transaction(function () use ($pago, $key, $disk) {
                $pago->orden_pdf_path = $key;
                $pago->orden_pdf_disk = $disk;
                $pago->save();
            });
        } catch (\Throwable $e) {
            // Compensating deletion: delete ONLY newly uploaded R2 object if DB update fails
            try {
                Storage::disk($disk)->delete($key);
            } catch (\Throwable $delEx) {
                Log::warning('Error en borrado de compensacion R2', ['key' => $key, 'error' => $delEx->getMessage()]);
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

    public function userCanViewOrder(?User $user, Pago $pago, string $context = 'paciente'): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('doctor') || $user->hasRole('laboratorio')) {
            return false;
        }

        if ($user->hasRole('administrador') || $user->hasRole('superadmin')) {
            return true;
        }

        if ($user->hasRole('paciente')) {
            $pago->loadMissing(['cita.dependiente']);

            // Titular owner of payment
            if ((int) $pago->paciente_id === (int) $user->id) {
                return true;
            }

            // Titular owner of appointment
            if ($pago->cita && (int) $pago->cita->paciente_id === (int) $user->id) {
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

        return false;
    }

    public function streamOrderResponse(Pago $pago, ?User $user, string $context = 'paciente'): StreamedResponse
    {
        if (! $user) {
            abort(401);
        }

        if (! $this->userCanViewOrder($user, $pago, $context)) {
            abort(403);
        }

        if (! $pago->tieneOrdenCobro()) {
            abort(404);
        }

        $pagoService = app(PagoService::class);
        $path = $pagoService->obtenerOGenerarOrdenPdf($pago, $user);

        $stored = $this->resolveStorage($path, $pago->orden_pdf_disk);
        if (! $stored) {
            abort(404);
        }

        $diskInst = Storage::disk($stored['disk']);
        $resolvedPath = $stored['path'];

        $stream = $diskInst->readStream($resolvedPath);
        if (! is_resource($stream)) {
            abort(404);
        }

        $fileName = 'orden_cobro_' . ($pago->folio_unico ?: $pago->id) . '.pdf';

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
