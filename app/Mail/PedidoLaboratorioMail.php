<?php

namespace App\Mail;

use App\Models\PedidoLaboratorio;
use App\Services\ClinicIdentityService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class PedidoLaboratorioMail extends Mailable
{
    use Queueable, SerializesModels;

    public $pedido;

    public $relativePath;

    public $fileName;

    public function __construct(PedidoLaboratorio $pedido, string $relativePath, string $fileName = '')
    {
        $this->pedido = $pedido;
        $this->relativePath = $relativePath;
        $this->fileName = $fileName ?: ('pedido_laboratorio_' . $pedido->id . '.pdf');
    }

    public function build()
    {
        $identity = app(ClinicIdentityService::class);
        $subject = $identity->subject('Nuevo pedido de laboratorio');

        $email = $this->subject($subject)
            ->view('emails.pedido_laboratorio')
            ->with([
                'pedido' => $this->pedido,
                'paciente' => $this->pedido->paciente,
                'doctor' => $this->pedido->doctor,
            ]);

        $docService = app(\App\Services\LaboratoryOrderDocumentService::class);
        $attachment = $docService->getMailAttachment($this->pedido, $this->fileName);

        if ($attachment) {
            $email->attach($attachment);
        }

        return $email;
    }
}
