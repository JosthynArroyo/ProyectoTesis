<?php

namespace App\Mail;

use App\Models\LaboratorioOrden;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ResultadoLaboratorioMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var \App\Models\LaboratorioOrden */
    public $orden;

    public function __construct(LaboratorioOrden $orden)
    {
        $this->orden = $orden;
    }

    public function build()
    {
        $cita = $this->orden->cita;
        $subject = 'Resultado de laboratorio disponible - Clinica Don Bosco';

        $mail = $this->subject($subject)
            ->view('emails.laboratorio_resultado')
            ->with([
                'orden' => $this->orden,
                'cita' => $cita,
                'paciente' => $cita->paciente,
            ]);

        if ($this->orden->resultado_path && Storage::exists($this->orden->resultado_path)) {
            $mail->attach(Storage::path($this->orden->resultado_path), [
                'as' => 'resultado_laboratorio_'.$this->orden->id.'.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
