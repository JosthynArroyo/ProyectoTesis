<?php

namespace App\Mail;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CambioEstadoCitaMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var \App\Models\Cita */
    public $cita;

    /** @var string 'paciente'|'doctor' */
    public $rolReceptor;

    /** @var string 'agendada'|'reagendada'|'cancelada'|'aceptada' */
    public $evento;

    /** @var string 'paciente'|'doctor'|'sistema' */
    public $quien;

    /** @var string asunto resuelto */
    public $asuntoResuelto;

    public function __construct(Cita $cita, string $rolReceptor, string $evento, string $quien = 'sistema')
    {
        $this->cita        = $cita;
        $this->rolReceptor = $rolReceptor;
        $this->evento      = $evento;
        $this->quien       = $quien;

        $this->asuntoResuelto = $this->resolverAsunto();
    }

    private function resolverAsunto(): string
    {
        $esAutor = ($this->rolReceptor === $this->quien);

        // Si el receptor fue quien hizo el cambio → 1ª persona
        if ($esAutor) {
            switch ($this->evento) {
                case 'agendada':   return 'Agendaste una cita - Clínica Don Bosco';
                case 'reagendada': return 'Reagendaste la cita - Clínica Don Bosco';
                case 'cancelada':  return 'Cancelaste la cita - Clínica Don Bosco';
                case 'aceptada':   return 'Aceptaste la cita - Clínica Don Bosco';
                default:           return 'Actualizaste la cita - Clínica Don Bosco';
            }
        }

        // Si es paciente y NO es autor → "Tu cita fue..."
        if ($this->rolReceptor === 'paciente') {
            switch ($this->evento) {
                case 'agendada':   return 'Tu cita fue agendada - Clínica Don Bosco';
                case 'reagendada': return 'Tu cita fue reagendada - Clínica Don Bosco';
                case 'cancelada':  return 'Tu cita fue cancelada - Clínica Don Bosco';
                case 'aceptada':   return 'Tu cita fue aceptada - Clínica Don Bosco';
                default:           return 'Tu cita fue actualizada - Clínica Don Bosco';
            }
        }

        // Si es doctor y NO es autor → estilo agenda
        if ($this->rolReceptor === 'doctor') {
            switch ($this->evento) {
                case 'agendada':   return 'Se registró una nueva cita en tu agenda - Clínica Don Bosco';
                case 'reagendada': return 'Se reagendó una cita en tu agenda - Clínica Don Bosco';
                case 'cancelada':  return 'Se canceló una cita en tu agenda - Clínica Don Bosco';
                case 'aceptada':   return 'Se aceptó una cita en tu agenda - Clínica Don Bosco';
                default:           return 'Se actualizó una cita en tu agenda - Clínica Don Bosco';
            }
        }

        return 'Actualización de cita - Clínica Don Bosco';
    }

    public function build()
    {
        return $this->subject($this->asuntoResuelto)
            ->view('emails.cita_estado')
            ->with([
                'cita'        => $this->cita,
                'rolReceptor' => $this->rolReceptor,
                'evento'      => $this->evento,
                'quien'       => $this->quien,
                'asunto'      => $this->asuntoResuelto,
            ]);
    }
}
