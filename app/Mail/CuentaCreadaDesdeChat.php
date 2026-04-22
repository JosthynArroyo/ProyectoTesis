<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CuentaCreadaDesdeChat extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public $passwordPlano;

    public function __construct(User $user, string $passwordPlano)
    {
        $this->user = $user;
        $this->passwordPlano = $passwordPlano;
    }

    public function build()
    {
        return $this->subject('Tu cuenta ya está lista - Clínica Don Bosco')
            ->view('emails.cuenta_desde_chat');
    }
}
