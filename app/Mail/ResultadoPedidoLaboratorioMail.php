<?php

namespace App\Mail;

use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use App\Services\ClinicIdentityService;
use App\Services\LaboratoryResultStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResultadoPedidoLaboratorioMail extends Mailable
{
    use Queueable, SerializesModels;

    public PedidoLaboratorioResultado|PedidoLaboratorio $resultado;

    public function __construct(PedidoLaboratorioResultado|PedidoLaboratorio $resultado)
    {
        $this->resultado = $resultado;
    }

    public function build()
    {
        $subject = app(ClinicIdentityService::class)->subject('Resultados de exámenes de laboratorio listos');

        $mail = $this->subject($subject)
            ->view('emails.resultado_pedido_laboratorio')
            ->with([
                'resultado' => $this->resultado,
                'pedido' => $this->resultado instanceof PedidoLaboratorioResultado ? $this->resultado->pedido : $this->resultado,
                'paciente' => $this->resultado instanceof PedidoLaboratorioResultado ? $this->resultado->pedido?->paciente : $this->resultado->paciente,
                'doctor' => $this->resultado instanceof PedidoLaboratorioResultado ? $this->resultado->pedido?->doctor : $this->resultado->doctor,
            ]);

        $pdfPath = $this->resultado instanceof PedidoLaboratorioResultado
            ? $this->resultado->pdf_path
            : $this->resultado->resultado_path;

        $fileName = $this->resultado instanceof PedidoLaboratorioResultado
            ? 'resultado_laboratorio_'.$this->resultado->pedido_laboratorio_id.'_v'.$this->resultado->version.'.pdf'
            : 'resultado_laboratorio_'.$this->resultado->id.'.pdf';

        $preferredDisk = $this->resultado instanceof PedidoLaboratorioResultado
            ? $this->resultado->pdf_disk
            : null;
        $resultStorage = app(LaboratoryResultStorageService::class);
        $stored = $resultStorage->resolve($pdfPath, $preferredDisk);
        if ($stored) {
            $pdfBytes = $resultStorage->contents($stored);
            $mail->attachData($pdfBytes, $fileName, [
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
