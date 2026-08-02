<?php

namespace App\Services;

use App\Models\CertificadoMedico;
use App\Models\PedidoLaboratorioResultado;
use App\Models\PedidoLaboratorio;
use App\Models\Receta;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DocumentoCsvService
{
    public function generateCsv(): string
    {
        do {
            $csv = $this->buildCsv();
        } while ($this->csvExists($csv));

        return $csv;
    }

    public function ensureCsv(Model $documento): string
    {
        $csv = trim((string) $documento->getAttribute('csv'));

        if ($csv !== '') {
            return strtoupper($csv);
        }

        $csv = $this->generateCsv();
        $documento->forceFill(['csv' => $csv]);

        if ($documento->exists) {
            $documento->saveQuietly();
        }

        return $csv;
    }

    public function verificationUrl(string $csv): string
    {
        return route('documentos.verificar.show', ['csv' => $this->normalizeCsv($csv)], true);
    }

    public function qrDataUri(string $csv): string
    {
        $result = Builder::create()
            ->writer(new PngWriter)
            ->data($this->verificationUrl($csv))
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->size(220)
            ->margin(8)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();

        return 'data:'.$result->getMimeType().';base64,'.base64_encode($result->getString());
    }

    public function findDocumento(string $csv): ?array
    {
        $csv = $this->normalizeCsv($csv);

        if ($csv === '') {
            return null;
        }

        $receta = Receta::query()->where('csv', $csv)->first();
        if ($receta) {
            return [
                'tipo' => 'receta',
                'titulo' => 'Receta médica',
                'pdf_path' => $receta->pdf_path,
                'nombre_descarga' => 'receta_'.$receta->id.'.pdf',
                'receta' => $receta,
            ];
        }

        $certificado = CertificadoMedico::query()->where('csv', $csv)->first();
        if ($certificado) {
            return [
                'tipo' => 'certificado_medico',
                'titulo' => 'Certificado médico',
                'pdf_path' => $certificado->pdf_path,
                'nombre_descarga' => $certificado->nombreDescarga(),
                'certificado' => $certificado,
            ];
        }

        $pedido = PedidoLaboratorio::query()->where('csv', $csv)->first();
        if ($pedido) {
            return [
                'tipo' => 'pedido_laboratorio',
                'titulo' => 'Pedido de laboratorio',
                'pdf_path' => $pedido->pdf_path,
                'nombre_descarga' => 'pedido_laboratorio_'.$pedido->id.'.pdf',
                'pedido' => $pedido,
            ];
        }

        $resultado = PedidoLaboratorioResultado::query()->with(['pedido.paciente', 'pedido.doctor'])->where('csv', $csv)->first();
        if ($resultado) {
            return [
                'tipo' => 'resultado_laboratorio',
                'titulo' => 'Informe de resultados de laboratorio',
                'pdf_path' => $resultado->pdf_path,
                'nombre_descarga' => 'resultado_laboratorio_'.$resultado->pedido_laboratorio_id.'_v'.$resultado->version.'.pdf',
                'resultado' => $resultado,
                'metadata' => [
                    'pedido_id' => $resultado->pedido_laboratorio_id,
                    'version' => $resultado->version,
                    'estado' => $resultado->estado,
                    'publicado_at' => $resultado->publicado_at,
                    'laboratorio_id' => $resultado->laboratorio_id,
                ],
            ];
        }

        return null;
    }

    private function buildCsv(): string
    {
        return Str::upper(Str::random(3))
            .'-'.str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT)
            .'-'.Str::upper(Str::random(3));
    }

    private function csvExists(string $csv): bool
    {
        $csv = $this->normalizeCsv($csv);

        return Receta::query()->where('csv', $csv)->exists()
            || CertificadoMedico::query()->where('csv', $csv)->exists()
            || PedidoLaboratorio::query()->where('csv', $csv)->exists()
            || PedidoLaboratorioResultado::query()->where('csv', $csv)->exists();
    }

    private function normalizeCsv(string $csv): string
    {
        return strtoupper(trim($csv));
    }
}
