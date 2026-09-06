<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CommercialInquiryReceived extends Mailable
{
    use Queueable, SerializesModels;

    public array $datos;

    public function __construct(array $datos)
    {
        $this->datos = $datos;
    }

    public function build(): static
    {
        $fromAddress = config('mail.from.address', 'alejandroucenriquez@gmail.com');
        $fromName = 'JA MedSys';

        $asuntoClinica = trim((string) ($this->datos['asunto'] ?? ''));
        $subject = $asuntoClinica !== ''
            ? 'Nueva solicitud comercial: ' . $asuntoClinica . ' — JA MedSys'
            : 'Nueva solicitud comercial — JA MedSys';

        $this->from($fromAddress, $fromName)
            ->subject($subject)
            ->view('emails.commercial.contact', [
                'datos' => $this->datos,
            ]);

        // Securely set Reply-To to the visitor's verified email
        $visitorEmail = filter_var($this->datos['email'] ?? null, FILTER_VALIDATE_EMAIL);
        $visitorName = trim(str_replace(["\r", "\n"], '', (string) ($this->datos['nombre'] ?? 'Interesado')));

        if ($visitorEmail) {
            $this->replyTo($visitorEmail, $visitorName ?: null);
        }

        return $this;
    }
}
