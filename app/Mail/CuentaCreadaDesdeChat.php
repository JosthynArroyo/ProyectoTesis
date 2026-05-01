<?php

namespace App\Mail;

use App\Models\User;
use App\Services\ClinicIdentityService;
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
        return $this->subject(app(ClinicIdentityService::class)->subject('Tu cuenta ya esta lista'))
            ->view('emails.cuenta_desde_chat');
    }
}
