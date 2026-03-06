<?php

namespace App\Mail;

use App\Models\Cita;
use Carbon\Carbon;
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

    /** @var string 'agendada'|'reagendada'|'cancelada'|'aceptada'|'prioridad' */
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
                case 'prioridad':  return 'Actualizaste la prioridad - Clinica Don Bosco';
                case 'no_se_presento': return 'Cita marcada como no se presentó - Clínica Don Bosco';
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
                case 'prioridad':  return 'La prioridad de tu cita fue actualizada - Clinica Don Bosco';
                case 'no_se_presento': return 'Tu cita fue marcada como no se presentó - Clínica Don Bosco';
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
                case 'prioridad':  return 'Se actualizo la prioridad de una cita - Clinica Don Bosco';
                case 'no_se_presento': return 'Una cita fue marcada como no se presentó - Clínica Don Bosco';
                default:           return 'Se actualizó una cita en tu agenda - Clínica Don Bosco';
            }
        }

        return 'Actualización de cita - Clínica Don Bosco';
    }

    public function build()
    {
        $rol = $this->rolReceptor ?? 'paciente';
        $evento = $this->evento ?? 'agendada';
        $quien = $this->quien ?? 'sistema';

        $nombreReceptor = $rol === 'doctor'
            ? ($this->cita->doctor?->name ?? 'Doctor/a')
            : ($this->cita->paciente?->name ?? 'Paciente');

        $esAutor = ($rol === $quien);

        $textoEvento = [
            'agendada'   => 'agendada',
            'reagendada' => 'reagendada',
            'cancelada'  => 'cancelada',
            'aceptada'   => 'aceptada',
            'prioridad'  => 'prioridad actualizada',
            'no_se_presento' => 'no se presentó',
        ][$evento] ?? 'actualizada';

        $mensaje = '';

        if ($evento === 'agendada') {
            if ($rol === 'paciente' && $esAutor)       $mensaje = 'Agendaste una cita.';
            elseif ($rol === 'doctor')                 $mensaje = 'Se registró una nueva cita en tu agenda.';
            else                                       $mensaje = 'Tu cita fue agendada.';
        } elseif ($evento === 'reagendada') {
            if ($rol === 'doctor') {
                $mensaje = $esAutor ? 'Reagendaste la cita.' : 'Se reagendó una cita en tu agenda.';
            } else {
                $mensaje = $esAutor ? 'Reagendaste la cita.' : 'Tu cita fue reagendada.';
            }
        } elseif ($evento === 'cancelada') {
            if ($rol === 'doctor') {
                $mensaje = $esAutor ? 'Cancelaste la cita.' : 'Se canceló una cita en tu agenda.';
            } else {
                $mensaje = $esAutor ? 'Cancelaste la cita.' : 'Tu cita fue cancelada.';
            }
        } elseif ($evento === 'aceptada') {
            if ($rol === 'doctor') {
                $mensaje = $esAutor ? 'Aceptaste la cita.' : 'Se aceptó una cita en tu agenda.';
            } else {
                $mensaje = $esAutor ? 'Aceptaste la cita.' : 'Tu cita fue aceptada.';
            }
        } elseif ($evento === 'prioridad') {
            $nivel = ucfirst(strtolower((string) ($this->cita->prioridad_nivel ?? 'BAJA')));
            if ($rol === 'doctor') {
                $mensaje = 'La prioridad de esta cita se actualizo a '.$nivel.'.';
            } else {
                $mensaje = 'La prioridad de tu cita se actualizo a '.$nivel.'.';
            }
        } elseif ($evento === 'no_se_presento') {
            $mensaje = $rol === 'doctor'
                ? 'La cita fue marcada como no se presentó.'
                : 'Tu cita fue marcada como no se presentó.';
        } else {
            $mensaje = $esAutor ? 'Actualizaste la cita.' : ($rol === 'doctor' ? 'Se actualizó una cita en tu agenda.' : 'Tu cita fue actualizada.');
        }

        $fechaCita = $this->cita->fecha ? $this->cita->fecha->format('d/m/Y') : '';
        $horaCita  = $this->cita->hora ? Carbon::parse($this->cita->hora)->format('H:i') : '';
        $pillClass = $evento;

        return $this->subject($this->asuntoResuelto)
            ->view('emails.cita_estado')
            ->with([
                'cita'           => $this->cita,
                'rolReceptor'    => $rol,
                'evento'         => $evento,
                'quien'          => $quien,
                'asunto'         => $this->asuntoResuelto,
                'nombreReceptor' => $nombreReceptor,
                'mensaje'        => $mensaje,
                'textoEvento'    => $textoEvento,
                'pillClass'      => $pillClass,
                'fechaCita'      => $fechaCita,
                'horaCita'       => $horaCita,
            ]);
    }
}
