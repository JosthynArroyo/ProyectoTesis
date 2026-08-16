<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentProofStorageService
{
    public const DISK = 'r2_private';
    public const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB

    public function validateUpload(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new \InvalidArgumentException('El archivo cargado no es valido o esta corrupto.');
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new \InvalidArgumentException('Formatos permitidos: JPG, JPEG y PNG. Tamaño maximo: 5 MB.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            throw new \InvalidArgumentException('Formatos permitidos: JPG, JPEG y PNG. Tamaño maximo: 5 MB.');
        }

        $mime = strtolower((string) $file->getMimeType());
        if (! in_array($mime, ['image/jpeg', 'image/pjpeg', 'image/png'], true)) {
            throw new \InvalidArgumentException('El tipo de contenido no corresponde a una imagen JPG o PNG valida.');
        }

        // Deep binary inspection: check if image can be decoded
        $imageInfo = @getimagesize($file->getPathname());
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            throw new \InvalidArgumentException('El archivo no pudo ser decodificado como una imagen valida.');
        }

        $detectedMime = strtolower((string) ($imageInfo['mime'] ?? ''));
        if (! in_array($detectedMime, ['image/jpeg', 'image/pjpeg', 'image/png'], true)) {
            throw new \InvalidArgumentException('El contenido real de la imagen no coincide con su extension.');
        }
    }

    public function storeUploadedProofFile(UploadedFile $file, int $pagoId): array
    {
        $this->validateUpload($file);

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $uuid = Str::uuid()->toString();
        $key = "documents/payment-proofs/{$pagoId}/{$uuid}.{$ext}";
        $disk = self::DISK;

        $filePath = $file->getPathname();
        $stream = @fopen($filePath, 'r');
        if ($stream === false) {
            throw new \RuntimeException('No se pudo abrir el archivo para lectura.');
        }

        try {
            $uploaded = Storage::disk($disk)->put($key, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $uploaded) {
            throw new \RuntimeException('Error al escribir el comprobante en almacenamiento privado.');
        }

        // Verify object existence
        if (! Storage::disk($disk)->exists($key)) {
            throw new \RuntimeException('El comprobante subido no se encuentra en el almacenamiento.');
        }

        // Verify size
        $uploadedSize = Storage::disk($disk)->size($key);
        $originalSize = $file->getSize();

        if ($uploadedSize <= 0 || $uploadedSize !== $originalSize) {
            Storage::disk($disk)->delete($key);
            throw new \RuntimeException('Inconsistencia en el tamaño del comprobante tras la subida.');
        }

        return [
            'path' => $key,
            'disk' => $disk,
        ];
    }

    public function uploadAndStoreProof(UploadedFile $file, Pago $pago): array
    {
        $oldPath = $pago->comprobante_path;
        $oldDisk = $pago->comprobante_disk;

        $stored = $this->storeUploadedProofFile($file, $pago->id);
        $key = $stored['path'];
        $disk = $stored['disk'];

        // Atomic DB update inside transaction
        try {
            DB::transaction(function () use ($pago, $key, $disk) {
                $pago->comprobante_path = $key;
                $pago->comprobante_disk = $disk;
                $pago->save();
            });
        } catch (\Throwable $e) {
            // Delete ONLY newly uploaded object on DB transaction failure
            Storage::disk($disk)->delete($key);
            throw $e;
        }

        // Delete old proof ONLY after commit, if not referenced elsewhere
        if ($oldPath && $oldPath !== $key) {
            $this->deleteOldProofIfSafe($oldPath, $oldDisk);
        }

        return [
            'path' => $key,
            'disk' => $disk,
        ];
    }

    public function deleteOldProofIfSafe(?string $oldPath, ?string $oldDisk): void
    {
        $normalized = trim((string) $oldPath);
        if ($normalized === '') {
            return;
        }

        // Check if referenced by payment_receipts snapshot
        $referencedByReceipt = PaymentReceipt::query()
            ->where('comprobante_path', $normalized)
            ->exists();

        if ($referencedByReceipt) {
            return;
        }

        // Check if referenced by another pago
        $referencedByPago = Pago::query()
            ->where('comprobante_path', $normalized)
            ->exists();

        if ($referencedByPago) {
            return;
        }

        // Safe to delete from disk
        $stored = $this->resolveStorage($normalized, $oldDisk);
        if ($stored) {
            try {
                Storage::disk($stored['disk'])->delete($stored['path']);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Error al eliminar comprobante antiguo de almacenamiento: ' . $e->getMessage(), [
                    'path' => $stored['path'],
                    'disk' => $stored['disk'],
                ]);
            }
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

        // Check explicit disk if provided
        if ($disk && Storage::disk($disk)->exists($normalized)) {
            return ['disk' => $disk, 'path' => $normalized];
        }

        // Check r2_private first
        if (Storage::disk(self::DISK)->exists($normalized)) {
            return ['disk' => self::DISK, 'path' => $normalized];
        }

        // Check local disk
        if (Storage::disk('local')->exists($normalized)) {
            return ['disk' => 'local', 'path' => $normalized];
        }

        // Check public disk & candidates
        $publicCandidates = [$normalized];
        if (Str::startsWith($normalized, 'storage/')) {
            $publicCandidates[] = ltrim(substr($normalized, 8), '/');
        }

        foreach ($publicCandidates as $candidate) {
            if ($candidate !== '' && Storage::disk('public')->exists($candidate)) {
                return ['disk' => 'public', 'path' => $candidate];
            }
        }

        return null;
    }

    public function userCanViewProof(?User $user, Pago $pago, string $context = 'paciente'): bool
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
                // Titular owner
                if ((int) $pago->paciente_id === (int) $user->id) {
                    return true;
                }

                // Representative for dependent
                $pago->loadMissing(['cita.dependiente']);
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

    public function streamProofResponse(Pago $pago, ?User $user, string $context = 'paciente')
    {
        if (! $user) {
            abort(401);
        }

        if (! $this->userCanViewProof($user, $pago, $context)) {
            abort(403);
        }

        $stored = $this->resolveStorage($pago->comprobante_path, $pago->comprobante_disk);
        if (! $stored) {
            abort(404);
        }

        $disk = Storage::disk($stored['disk']);
        $path = $stored['path'];

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'webp' => 'image/webp',
            default => $disk->mimeType($path) ?: 'application/octet-stream',
        };

        $fileName = 'comprobante_pago_' . $pago->id . '.' . ($ext ?: 'bin');
        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            abort(404);
        }

        return response()->stream(
            function () use ($stream) {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            ]
        );
    }
}
