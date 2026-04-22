<?php

namespace App\Mail;

use App\Models\LabOrder;
use App\Models\LaboratorioOrden;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ResultadoLaboratorioMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var \App\Models\LaboratorioOrden|\App\Models\LabOrder */
    public $orden;

    public function __construct(LaboratorioOrden|LabOrder $orden)
    {
        $this->orden = $orden;
    }

    public function build()
    {
        $cita = $this->orden instanceof LaboratorioOrden ? $this->orden->cita : null;
        $paciente = $this->orden instanceof LaboratorioOrden ? $cita?->paciente : $this->orden->patient;
        $subject = 'Resultados de laboratorio disponibles - Clínica Don Bosco';

        $mail = $this->subject($subject)
            ->view('emails.laboratorio_resultado')
            ->with([
                'orden' => $this->orden,
                'cita' => $cita,
                'paciente' => $paciente,
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
