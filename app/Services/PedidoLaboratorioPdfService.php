<?php

namespace App\Services;

use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class PedidoLaboratorioPdfService
{
    public function previewHtml(PedidoLaboratorio $pedido, PedidoLaboratorioResultado $resultado): string
    {
        $pedido->loadMissing([
            'cita.paciente',
            'cita.dependiente.responsable',
            'cita.doctor',
            'cita.especialidad',
            'doctor',
            'paciente',
        ]);
        $resultado->loadMissing(['laboratorio', 'pedido.cita.paciente', 'pedido.cita.dependiente.responsable', 'pedido.cita.doctor']);

        $identity = app(ClinicIdentityService::class);
        $csvService = app(DocumentoCsvService::class);
        $csv = $resultado->csv ?: $csvService->ensureCsv($resultado);

        return view('pdf.pedido-laboratorio-resultado', [
            'pedido' => $pedido,
            'resultado' => $resultado,
            'clinica' => $identity->institutionalName(),
            'logoBase64' => $identity->logoBase64ForPdf(),
            'pdfCss' => $this->loadPdfCss('laboratorio/pedido-laboratorio-resultado-pdf.css'),
            'csv' => $csv,
            'verificationUrl' => $csvService->verificationUrl($csv),
            'qrDataUri' => $csvService->qrDataUri($csv),
            'fechaPdf' => now('America/Guayaquil'),
        ])->render();
    }

    public function renderPdfOutput(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function loadPdfCss(string $relativePath): string
    {
        $basePath = resource_path('css/pdf/base.css');
        $specificPath = resource_path('css/'.$relativePath);

        $css = is_file($basePath) ? (file_get_contents($basePath) ?: '') : '';
        $css .= is_file($specificPath) ? "\n".(file_get_contents($specificPath) ?: '') : '';

        return $css;
    }

    public function usuarioAutorizadoParaResultado(PedidoLaboratorio $pedido, ?PedidoLaboratorioResultado $resultado, $user): bool
    {
        if (!$user) {
            return false;
        }

        // Laboratorio emisor
        if ($user->hasRole('laboratorio')) {
            return true;
        }

        // Doctor solicitante
        if ($user->hasRole('doctor')) {
            return (int)$pedido->doctor_id === (int)$user->id
                || ($pedido->cita && (int)$pedido->cita->doctor_id === (int)$user->id);
        }

        // Paciente o Representante
        if ($user->hasRole('paciente')) {
            if ((int)$pedido->paciente_id === (int)$user->id) {
                return true;
            }
            if ($pedido->cita) {
                if ((int)$pedido->cita->paciente_id === (int)$user->id) {
                    return true;
                }
                if ($pedido->cita->dependiente && (int)$pedido->cita->dependiente->responsable_id === (int)$user->id) {
                    return true;
                }
            }
        }

        return false;
    }

    public function streamResultadoFile(PedidoLaboratorioResultado $resultado, string $dispositionType = 'attachment')
    {
        $diskName = $resultado->pdf_disk ?: 'local';
        $disk = Storage::disk($diskName);

        if (! $resultado->pdf_path || ! $disk->exists($resultado->pdf_path)) {
            $pedido = $resultado->pedido ?: PedidoLaboratorio::find($resultado->pedido_laboratorio_id);
            if ($pedido) {
                $html = $this->previewHtml($pedido, $resultado);
                $pdfBytes = $this->renderPdfOutput($html);
                $path = $resultado->pdf_path ?: "documents/laboratory-results/{$resultado->id}/resultado_{$resultado->id}.pdf";
                $diskName = $resultado->pdf_disk ?: 'local';
                $disk = Storage::disk($diskName);
                $disk->put($path, $pdfBytes);
                $resultado->forceFill([
                    'pdf_path' => $path,
                    'pdf_disk' => $diskName,
                ])->saveQuietly();

                if ($pedido->resultado_path !== $path) {
                    $pedido->forceFill([
                        'resultado_path' => $path,
                    ])->saveQuietly();
                }
            } else {
                abort(404, 'Archivo de resultado no encontrado.');
            }
        }

        $filename = 'resultado_laboratorio_' . $resultado->pedido_laboratorio_id . '_v' . $resultado->version . '.pdf';
        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($dispositionType === 'inline' ? 'inline' : 'attachment') . '; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($disk, $resultado) {
            $stream = $disk->readStream($resultado->pdf_path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, $headers);
    }

    public function migrateLocalResultToR2(PedidoLaboratorioResultado $resultado): array
    {
        $localPath = (string) $resultado->getRawOriginal('pdf_path');

        if (empty($localPath) || ! Storage::disk('local')->exists($localPath)) {
            return [
                'success' => false,
                'error' => 'Local PDF file does not exist',
            ];
        }

        $binary = Storage::disk('local')->get($localPath);
        $size = strlen($binary);
        $sha256 = hash('sha256', $binary);

        if (! str_starts_with($binary, '%PDF')) {
            return [
                'success' => false,
                'error' => 'Invalid PDF header',
            ];
        }

        $uuid = (string) \Illuminate\Support\Str::uuid();
        $r2Key = "documents/laboratory-results/{$resultado->id}/{$uuid}.pdf";

        $disk = Storage::disk('r2_private');
        $disk->put($r2Key, $binary, [
            'ContentType' => 'application/pdf',
            'visibility' => 'private',
        ]);

        $r2Binary = $disk->get($r2Key);
        $r2Size = strlen($r2Binary);
        $r2Sha256 = hash('sha256', $r2Binary);

        if ($size !== $r2Size || $sha256 !== $r2Sha256) {
            if ($disk->exists($r2Key)) {
                $disk->delete($r2Key);
            }
            return [
                'success' => false,
                'error' => 'Verification failed: size or SHA-256 mismatch',
            ];
        }

        $resultado->forceFill([
            'pdf_path' => $r2Key,
            'pdf_disk' => 'r2_private',
        ])->saveQuietly();

        $pedido = $resultado->pedido;
        if ($pedido && $pedido->resultado_path === $localPath) {
            $pedido->forceFill([
                'resultado_path' => $r2Key,
            ])->saveQuietly();
        }

        return [
            'success' => true,
            'resultado_id' => $resultado->id,
            'old_path' => $localPath,
            'new_key' => $r2Key,
            'size' => $size,
            'sha256' => $sha256,
        ];
    }
}
