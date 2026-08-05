<?php

namespace App\Services;

use App\Models\Cita;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CitaComprobanteService
{
    public function sincronizarComprobante(Cita $cita): Cita
    {
        return app(AppointmentConfirmationDocumentService::class)->generateAndStoreR2($cita);
    }

    public function obtenerOGenerarPdf(Cita $cita): string
    {
        return app(AppointmentConfirmationDocumentService::class)->obtenerOGenerarComprobantePdf($cita);
    }

    public function asegurarComprobante(Cita $cita): Cita
    {
        return DB::transaction(function () use ($cita): Cita {
            $cita->refresh();
            $actualizar = [];

            if (empty($cita->folio_cita)) {
                $actualizar['folio_cita'] = $this->generarFolioCita($cita);
            }
            if (empty($cita->token_validacion)) {
                $actualizar['token_validacion'] = $this->generarTokenValidacion($cita);
            }
            if (empty($cita->csv)) {
                $actualizar['csv'] = app(DocumentoCsvService::class)->generateCsv();
            }

            $marcaTiempo = now('America/Guayaquil');

            if (empty($cita->comprobante_emitido_en)) {
                $actualizar['comprobante_emitido_en'] = $marcaTiempo;
            }
            if (empty($cita->comprobante_actualizado_en)) {
                $actualizar['comprobante_actualizado_en'] = $marcaTiempo;
            }

            if ($actualizar !== []) {
                $cita->forceFill($actualizar)->saveQuietly();
            }

            return $cita->refresh();
        });
    }

    protected function generarComprobantePdf(Cita $cita): string
    {
        $updatedCita = app(AppointmentConfirmationDocumentService::class)->generateAndStoreR2($cita);
        return (string) $updatedCita->comprobante_pdf_path;
    }

    protected function generarFolioCita(Cita $cita): string
    {
        $fecha = $cita->fecha?->format('Ymd') ?: now('America/Guayaquil')->format('Ymd');
        $base = sprintf('CC-%s-%06d', $fecha, $cita->id);
        $folio = $base;

        $i = 1;
        while (
            Cita::query()
                ->where('folio_cita', $folio)
                ->where('id', '!=', $cita->id)
                ->exists()
        ) {
            $i++;
            $folio = $base.'-'.$i;
        }

        return $folio;
    }

    protected function generarTokenValidacion(Cita $cita): string
    {
        do {
            $token = Str::lower(Str::random(48));
        } while (
            Cita::query()
                ->where('token_validacion', $token)
                ->where('id', '!=', $cita->id)
                ->exists()
        );

        return $token;
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

        return 'data:'.$result->getMimeType().';base64,'.base64_encode($result->getString());
    }

    protected function logoBase64(): ?string
    {
        return app(ClinicIdentityService::class)->logoBase64ForPdf();
    }
}
