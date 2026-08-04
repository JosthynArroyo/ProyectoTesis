<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\PaymentReceipt;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PagoDocumentoService
{
    public function generarOrdenCobroPdf(Pago $pago): string
    {
        $pago->loadMissing([
            'paciente:id,name,dni,telefono',
            'cita:id,doctor_id,especialidad_id,fecha,hora,estado,dependiente_id',
            'cita.dependiente',
            'cita.doctor:id,name',
            'cita.especialidad:id,nombre',
        ]);

        $csvService = app(DocumentoCsvService::class);
        if (! empty($pago->csv)) {
            $qrUrl = $csvService->verificationUrl($pago->csv);
            $qrDataUri = $csvService->qrDataUri($pago->csv);
        } else {
            $qrUrl = route('pagos.token.show', ['token' => $pago->token_publico], true);
            $qrDataUri = $this->generarQrDataUri($qrUrl);
        }

        $html = view('pdf.orden-cobro', [
            'pago' => $pago,
            'qrUrl' => $qrUrl,
            'qrDataUri' => $qrDataUri,
            'fechaPdf' => now('America/Guayaquil'),
            'logoBase64' => $this->logoBase64(),
        ])->render();

        $pdfOutput = $this->renderizarPdf($html);
        $folder = 'pagos/ordenes';
        if (! Storage::disk('local')->exists($folder)) {
            Storage::disk('local')->makeDirectory($folder);
        }

        $fileName = 'orden_'.Str::slug((string) $pago->folio_unico, '_').'.pdf';
        $path = $folder.'/'.$fileName;
        Storage::disk('local')->put($path, $pdfOutput);

        return $path;
    }

    public function generarReciboPagoPdfContent(Pago $pago, PaymentReceipt $receipt): string
    {
        $pago->loadMissing([
            'paciente:id,name,dni,telefono',
            'cita:id,doctor_id,especialidad_id,fecha,hora,estado',
            'cita.doctor:id,name',
            'cita.especialidad:id,nombre',
            'aprobador:id,name',
        ]);
        $receipt->loadMissing('emisor:id,name');

        $csvService = app(DocumentoCsvService::class);
        $qrUrl = null;
        $qrDataUri = null;
        if (! empty($receipt->csv)) {
            $qrUrl = $csvService->verificationUrl($receipt->csv);
            $qrDataUri = $csvService->qrDataUri($receipt->csv);
        } elseif (! empty($receipt->verification_token)) {
            $qrUrl = route('recibos.verificar', ['token' => $receipt->verification_token], true);
            $qrDataUri = $this->generarQrDataUri($qrUrl);
        }

        $html = view('pdf.recibo-pago', [
            'pago' => $pago,
            'receipt' => $receipt,
            'qrUrl' => $qrUrl,
            'qrDataUri' => $qrDataUri,
            'fechaPdf' => now('America/Guayaquil'),
            'logoBase64' => $this->logoBase64(),
        ])->render();

        return $this->renderizarPdf($html);
    }

    public function generarReciboPagoPdf(Pago $pago, PaymentReceipt $receipt): string
    {
        $pdfOutput = $this->generarReciboPagoPdfContent($pago, $receipt);
        $folder = 'pagos/recibos';
        if (! Storage::disk('local')->exists($folder)) {
            Storage::disk('local')->makeDirectory($folder);
        }

        $fileName = 'recibo_'.Str::slug((string) $receipt->folio_recibo, '_').'.pdf';
        $path = $folder.'/'.$fileName;
        Storage::disk('local')->put($path, $pdfOutput);

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
