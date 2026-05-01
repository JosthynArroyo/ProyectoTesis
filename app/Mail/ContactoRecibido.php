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

    public function __construct(array $datos)
    {
        $this->datos = $datos;
    }

    public function build()
    {
        return $this->subject(app(ClinicIdentityService::class)->subject('Nuevo mensaje de contacto'))
            ->view('emails.contacto_recibido');
    }
}
