<?php

namespace App\Mail;

use App\Models\PedidoLaboratorio;
use App\Services\ClinicIdentityService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ResultadoPedidoLaboratorioMail extends Mailable
{
    use Queueable, SerializesModels;

    public $pedido;

    public function __construct(PedidoLaboratorio $pedido)
    {
        $this->pedido = $pedido;
    }

    public function build()
    {
        $subject = app(ClinicIdentityService::class)->subject('Resultados de exámenes de laboratorio listos');

        $mail = $this->subject($subject)
            ->view('emails.resultado_pedido_laboratorio')
            ->with([
                'pedido' => $this->pedido,
                'paciente' => $this->pedido->paciente,
                'doctor' => $this->pedido->doctor,
            ]);

        if ($this->pedido->resultado_path && Storage::disk('local')->exists($this->pedido->resultado_path)) {
            $mail->attach(Storage::disk('local')->path($this->pedido->resultado_path), [
                'as' => 'resultado_laboratorio_' . $this->pedido->id . '.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
