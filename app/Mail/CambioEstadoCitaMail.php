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

    /** @var string 'agendada'|'reagendada'|'cancelada'|'aceptada'|'prioridad'|'no_se_presento' */
    public $evento;

    /** @var string 'paciente'|'doctor'|'sistema' */
    public $quien;

    /** @var string asunto resuelto */
    public $asuntoResuelto;

    public function __construct(Cita $cita, string $rolReceptor, string $evento, string $quien = 'sistema')
    {
        $this->cita = $cita;
        $this->rolReceptor = $rolReceptor;
        $this->evento = $evento;
        $this->quien = $quien;
        $this->asuntoResuelto = $this->resolverAsunto();
    }

    private function resolverAsunto(): string
    {
        $esAutor = $this->rolReceptor === $this->quien;

        if ($esAutor) {
            return match ($this->evento) {
                'agendada' => 'Agendaste una cita - Clínica Don Bosco',
                'reagendada' => 'Reagendaste la cita - Clínica Don Bosco',
                'cancelada' => 'Cancelaste la cita - Clínica Don Bosco',
                'aceptada' => 'Aceptaste la cita - Clínica Don Bosco',
                'prioridad' => 'Actualizaste la prioridad de la cita - Clínica Don Bosco',
                'no_se_presento' => 'Cita marcada como no se presentó - Clínica Don Bosco',
                default => 'Actualizaste la cita - Clínica Don Bosco',
            };
        }

        if ($this->rolReceptor === 'paciente') {
            return match ($this->evento) {
                'agendada' => 'Tu cita fue agendada - Clínica Don Bosco',
                'reagendada' => 'Tu cita fue reagendada - Clínica Don Bosco',
                'cancelada' => 'Tu cita fue cancelada - Clínica Don Bosco',
                'aceptada' => 'Tu cita fue aceptada - Clínica Don Bosco',
                'prioridad' => 'La prioridad de tu cita fue actualizada - Clínica Don Bosco',
                'no_se_presento' => 'Tu cita fue marcada como no se presentó - Clínica Don Bosco',
                default => 'Tu cita fue actualizada - Clínica Don Bosco',
            };
        }

        if ($this->rolReceptor === 'doctor') {
            return match ($this->evento) {
                'agendada' => 'Se registró una nueva cita en tu agenda - Clínica Don Bosco',
                'reagendada' => 'Se reagendó una cita en tu agenda - Clínica Don Bosco',
                'cancelada' => 'Se canceló una cita en tu agenda - Clínica Don Bosco',
                'aceptada' => 'Se aceptó una cita en tu agenda - Clínica Don Bosco',
                'prioridad' => 'Se actualizó la prioridad de una cita - Clínica Don Bosco',
                'no_se_presento' => 'Una cita fue marcada como no se presentó - Clínica Don Bosco',
                default => 'Se actualizó una cita en tu agenda - Clínica Don Bosco',
            };
        }

        return 'Actualización de cita - Clínica Don Bosco';
    }

    private function resolverMensaje(string $rol, string $evento, bool $esAutor): string
    {
        return match ($evento) {
            'agendada' => $rol === 'doctor'
                ? 'Se registró una nueva cita en tu agenda.'
                : ($esAutor ? 'Agendaste una cita.' : 'Tu cita fue agendada.'),
            'reagendada' => $rol === 'doctor'
                ? ($esAutor ? 'Reagendaste la cita.' : 'Se reagendó una cita en tu agenda.')
                : ($esAutor ? 'Reagendaste la cita.' : 'Tu cita fue reagendada.'),
            'cancelada' => $rol === 'doctor'
                ? ($esAutor ? 'Cancelaste la cita.' : 'Se canceló una cita en tu agenda.')
                : ($esAutor ? 'Cancelaste la cita.' : 'Tu cita fue cancelada.'),
            'aceptada' => $rol === 'doctor'
                ? ($esAutor ? 'Aceptaste la cita.' : 'Se aceptó una cita en tu agenda.')
                : ($esAutor ? 'Aceptaste la cita.' : 'Tu cita fue aceptada.'),
            'prioridad' => $rol === 'doctor'
                ? 'La prioridad de esta cita fue actualizada a '.ucfirst(strtolower((string) ($this->cita->prioridad_nivel ?? 'BAJA'))).'.'
                : 'La prioridad de tu cita fue actualizada a '.ucfirst(strtolower((string) ($this->cita->prioridad_nivel ?? 'BAJA'))).'.',
            'no_se_presento' => $rol === 'doctor'
                ? 'La cita fue marcada como no se presentó.'
                : 'Tu cita fue marcada como no se presentó.',
            default => $esAutor
                ? 'Actualizaste la cita.'
                : ($rol === 'doctor' ? 'Se actualizó una cita en tu agenda.' : 'Tu cita fue actualizada.'),
        };
    }

    public function build()
    {
        $rol = $this->rolReceptor ?: 'paciente';
        $evento = $this->evento ?: 'agendada';
        $quien = $this->quien ?: 'sistema';
        $esAutor = $rol === $quien;

        $nombreReceptor = $rol === 'doctor'
            ? ($this->cita->doctor?->name ?? 'Doctor/a')
            : ($this->cita->paciente?->name ?? 'Paciente');

        $textoEvento = [
            'agendada' => 'agendada',
            'reagendada' => 'reagendada',
            'cancelada' => 'cancelada',
            'aceptada' => 'aceptada',
            'prioridad' => 'prioridad actualizada',
            'no_se_presento' => 'no se presentó',
        ][$evento] ?? 'actualizada';

        $fechaCita = $this->cita->fecha ? $this->cita->fecha->format('d/m/Y') : '';
        $horaCita = $this->cita->hora ? Carbon::parse($this->cita->hora)->format('H:i') : '';

        return $this->subject($this->asuntoResuelto)
            ->view('emails.cita_estado')
            ->with([
                'cita' => $this->cita,
                'rolReceptor' => $rol,
                'evento' => $evento,
                'quien' => $quien,
                'asunto' => $this->asuntoResuelto,
                'nombreReceptor' => $nombreReceptor,
                'mensaje' => $this->resolverMensaje($rol, $evento, $esAutor),
                'textoEvento' => $textoEvento,
                'pillClass' => $evento,
                'fechaCita' => $fechaCita,
                'horaCita' => $horaCita,
            ]);
    }
}
