<?php

namespace App\Mail;

use App\Models\CertificadoMedico;
use App\Services\ClinicIdentityService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CertificadoMedicoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CertificadoMedico $certificado,
        public string $relativePath = '',
        public string $fileName = '',
    ) {
        $this->fileName = $this->fileName ?: $certificado->nombreDescarga();
    }

    public function build()
    {
        $subject = app(ClinicIdentityService::class)->subject('Certificado medico emitido');

        $mail = $this->subject($subject)
            ->view('emails.certificado_medico')
            ->with([
                'certificado' => $this->certificado,
                'paciente' => $this->certificado->paciente,
                'doctor' => $this->certificado->doctor,
            ]);

        if ($this->relativePath && Storage::disk('local')->exists($this->relativePath)) {
            $mail->attachData(
                Storage::disk('local')->get($this->relativePath),
                $this->fileName,
                ['mime' => 'application/pdf']
            );
        }

        return $mail;
    }
}
