<?php

namespace App\Mail;

use App\Models\LabOrder;
use App\Models\LaboratorioOrden;
use App\Services\ClinicIdentityService;
use App\Services\LaboratoryResultStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResultadoLaboratorioMail extends Mailable
{
    use Queueable, SerializesModels;

    public $orden;

    public function __construct(LaboratorioOrden|LabOrder $orden)
    {
        $this->orden = $orden;
    }

    public function build()
    {
        $cita = $this->orden instanceof LaboratorioOrden ? $this->orden->cita : null;
        $paciente = $this->orden instanceof LaboratorioOrden ? $cita?->paciente : $this->orden->patient;
        $subject = app(ClinicIdentityService::class)->subject('Resultados de laboratorio disponibles');

        $mail = $this->subject($subject)
            ->view('emails.laboratorio_resultado')
            ->with([
                'orden' => $this->orden,
                'cita' => $cita,
                'paciente' => $paciente,
            ]);

        $resultStorage = app(LaboratoryResultStorageService::class);
        $stored = $resultStorage->resolve($this->orden->resultado_path);
        if ($stored) {
            $mail->attachData($resultStorage->contents($stored), 'resultado_laboratorio_'.$this->orden->id.'.pdf', [
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
