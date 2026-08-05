<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppointmentConfirmationDocumentService
{
    public const DISK = 'r2_private';

    public function generateConfirmationContent(Cita $cita): string
    {
        $cita->loadMissing([
            'paciente:id,name,dni,telefono',
            'dependiente',
            'doctor:id,name',
            'especialidad:id,nombre',
        ]);

        $qrUrl = $cita->csv
            ? route('documentos.verificar.show', ['csv' => $cita->csv], true)
            : route('citas.comprobante.show', ['token' => $cita->token_validacion], true);
        $qrDataUri = $this->generarQrDataUri($qrUrl);
        $identity = app(ClinicIdentityService::class);

        $html = view('pdf.comprobante-cita', [
            'cita' => $cita,
            'qrUrl' => $qrUrl,
            'qrDataUri' => $qrDataUri,
            'fechaPdf' => now('America/Guayaquil'),
            'logoBase64' => $identity->logoBase64ForPdf(),
            'clinica' => $identity->institutionalName(),
        ])->render();

        return $this->renderizePdfContent($html);
    }

    public function generateAndStoreR2(Cita $cita): Cita
    {
        $lockKey = 'appointment_confirmation_generation_' . $cita->id;
        return Cache::lock($lockKey, 10)->block(5, function () use ($cita): Cita {
            $cita = $cita->fresh();
            $citaService = app(CitaComprobanteService::class);
            $cita = $citaService->asegurarComprobante($cita);

            $pdfBytes = $this->generateConfirmationContent($cita);
            if (! $this->verifyPdfContent($pdfBytes)) {
                throw new \RuntimeException("El PDF generado para la cita #{$cita->id} es inválido.");
            }

            $uuid = (string) Str::uuid();
            $newPath = "documents/appointment-confirmations/{$cita->id}/{$uuid}.pdf";

            $r2Disk = Storage::disk(self::DISK);
            $r2Disk->put($newPath, $pdfBytes);

            // Verify upload integrity
            if (! $r2Disk->exists($newPath)) {
                throw new \RuntimeException("Falló la verificación de existencia en R2 para {$newPath}");
            }

            $uploadedBytes = $r2Disk->get($newPath);
            if (! $this->verifyPdfContent($uploadedBytes)) {
                $r2Disk->delete($newPath);
                throw new \RuntimeException("Contenido corrupto verificado en R2 para {$newPath}");
            }

            $oldPath = $cita->comprobante_pdf_path;
            $oldDisk = $cita->comprobante_pdf_disk;
            $marcaTiempo = now('America/Guayaquil');

            try {
                DB::transaction(function () use ($cita, $newPath, $marcaTiempo) {
                    $cita->forceFill([
                        'comprobante_pdf_path' => $newPath,
                        'comprobante_pdf_disk' => self::DISK,
                        'comprobante_actualizado_en' => $marcaTiempo,
                    ])->saveQuietly();
                });
            } catch (\Throwable $e) {
                // Compensating deletion of new object
                $r2Disk->delete($newPath);
                Log::error("Error actualizando cita #{$cita->id} tras subida a R2: {$e->getMessage()}");
                throw $e;
            }

            // Safe replacement: delete old object only if it was in R2 and differs from new path
            if ($oldDisk === self::DISK && ! empty($oldPath) && $oldPath !== $newPath) {
                try {
                    $r2Disk->delete($oldPath);
                } catch (\Throwable $e) {
                    Log::warning("No se pudo eliminar comprobante previo {$oldPath} en R2: {$e->getMessage()}");
                }
            }

            return $cita->fresh();
        });
    }

    public function obtenerOGenerarComprobantePdf(Cita $cita): string
    {
        $citaService = app(CitaComprobanteService::class);
        $cita = $citaService->asegurarComprobante($cita);

        $stored = $this->resolveStorage($cita->comprobante_pdf_path, $cita->comprobante_pdf_disk);

        $debeRegenerar = ! $stored
            || empty($cita->comprobante_actualizado_en)
            || ($cita->updated_at && $cita->updated_at->gt($cita->comprobante_actualizado_en));

        if ($debeRegenerar) {
            $updatedCita = $this->generateAndStoreR2($cita);
            return (string) $updatedCita->comprobante_pdf_path;
        }

        return (string) $cita->comprobante_pdf_path;
    }

    public function resolveStorage(?string $path, ?string $disk): ?array
    {
        if (empty($path)) {
            return null;
        }

        $normalized = ltrim($path, '/');

        if ($disk === self::DISK) {
            if (Storage::disk(self::DISK)->exists($normalized)) {
                return ['disk' => self::DISK, 'path' => $normalized];
            }
            return null;
        }

        if ($disk === 'local') {
            if (Storage::disk('local')->exists($normalized)) {
                return ['disk' => 'local', 'path' => $normalized];
            }
            return null;
        }

        // Implicit fallback for legacy records without disk set
        if (Storage::disk(self::DISK)->exists($normalized)) {
            return ['disk' => self::DISK, 'path' => $normalized];
        }

        if (Storage::disk('local')->exists($normalized)) {
            return ['disk' => 'local', 'path' => $normalized];
        }

        return null;
    }

    public function userCanViewConfirmation(?User $user, Cita $cita): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('administrador') || $user->hasRole('superadmin')) {
            return true;
        }

        if ($user->hasRole('doctor') || $user->hasRole('laboratorio')) {
            return (int) $cita->doctor_id === (int) $user->id;
        }

        if ($user->hasRole('paciente')) {
            $cita->loadMissing(['dependiente']);

            // Titular owner of appointment
            if ((int) $cita->paciente_id === (int) $user->id) {
                return true;
            }

            // Representative for dependent
            if ($cita->dependiente_id) {
                if ((int) $cita->dependiente?->user_id === (int) $user->id) {
                    return true;
                }

                if ($user->dependientes()->where('id', $cita->dependiente_id)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function streamConfirmationResponse(Cita $cita, ?User $user, string $disposition = 'inline'): StreamedResponse
    {
        if (! $user) {
            abort(401);
        }

        if (! $this->userCanViewConfirmation($user, $cita)) {
            abort(403);
        }

        $path = $this->obtenerOGenerarComprobantePdf($cita);

        $stored = $this->resolveStorage($path, $cita->comprobante_pdf_disk);
        if (! $stored) {
            abort(404);
        }

        $diskInst = Storage::disk($stored['disk']);
        $resolvedPath = $stored['path'];

        $stream = $diskInst->readStream($resolvedPath);
        if (! is_resource($stream)) {
            abort(404);
        }

        $fileName = 'comprobante_cita_' . ($cita->folio_cita ?: $cita->id) . '.pdf';
        $contentDisposition = ($disposition === 'attachment' ? 'attachment' : 'inline') . '; filename="' . $fileName . '"';

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
                'Content-Disposition' => $contentDisposition,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            ]
        );
    }

    public function verifyPdfContent(?string $content): bool
    {
        if (empty($content)) {
            return false;
        }

        if (strlen($content) < 100) {
            return false;
        }

        return str_starts_with($content, '%PDF');
    }

    protected function renderizePdfContent(string $html): string
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

    protected function generarQrDataUri(string $url): string
    {
        $result = Builder::create()
            ->writer(new PngWriter)
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->size(220)
            ->margin(8)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();

        return 'data:' . $result->getMimeType() . ';base64,' . base64_encode($result->getString());
    }
}
