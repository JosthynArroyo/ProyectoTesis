<?php

namespace App\Mail;

use App\Services\ClinicIdentityService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactoRecibido extends Mailable
{
    use Queueable, SerializesModels;

    public array $datos;
    public ?string $asuntoPersonalizado;

    public function __construct(array $datos, ?string $asuntoPersonalizado = null)
    {
        $this->datos = $datos;
        $this->asuntoPersonalizado = $asuntoPersonalizado;
    }

    public function build()
    {
        $subject = $this->asuntoPersonalizado
            ?: app(ClinicIdentityService::class)->subject('Nuevo mensaje de contacto');

        return $this->subject($subject)
            ->replyTo($this->datos['email'], $this->datos['nombre'])
            ->view('emails.contacto_recibido');
    }
}
