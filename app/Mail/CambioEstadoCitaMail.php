<?php

namespace App\Mail;

use App\Models\Cita;
use App\Services\ClinicIdentityService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CambioEstadoCitaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $cita;

    public $rolReceptor;

    public $evento;

    public $quien;

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
        $identity = app(ClinicIdentityService::class);
        $esAutor = $this->rolReceptor === $this->quien;

        if ($esAutor) {
            return match ($this->evento) {
                'agendada' => $identity->subject('Agendaste una cita'),
                'reagendada' => $identity->subject('Reagendaste la cita'),
                'cancelada' => $identity->subject('Cancelaste la cita'),
                'aceptada' => $identity->subject('Aceptaste la cita'),
                'prioridad' => $identity->subject('Actualizaste la prioridad de la cita'),
                'no_se_presento' => $identity->subject('Cita marcada como no se presento'),
                default => $identity->subject('Actualizaste la cita'),
            };
        }

        if ($this->rolReceptor === 'paciente') {
            return match ($this->evento) {
                'agendada' => $identity->subject('Tu cita fue agendada'),
                'reagendada' => $identity->subject('Tu cita fue reagendada'),
                'cancelada' => $identity->subject('Tu cita fue cancelada'),
                'aceptada' => $identity->subject('Tu cita fue aceptada'),
                'prioridad' => $identity->subject('La prioridad de tu cita fue actualizada'),
                'no_se_presento' => $identity->subject('Tu cita fue marcada como no se presento'),
                default => $identity->subject('Tu cita fue actualizada'),
            };
        }

        if ($this->rolReceptor === 'doctor') {
            return match ($this->evento) {
                'agendada' => $identity->subject('Se registro una nueva cita en tu agenda'),
                'reagendada' => $identity->subject('Se reagendo una cita en tu agenda'),
                'cancelada' => $identity->subject('Se cancelo una cita en tu agenda'),
                'aceptada' => $identity->subject('Se acepto una cita en tu agenda'),
                'prioridad' => $identity->subject('Se actualizo la prioridad de una cita'),
                'no_se_presento' => $identity->subject('Una cita fue marcada como no se presento'),
                default => $identity->subject('Se actualizo una cita en tu agenda'),
            };
        }

        return $identity->subject('Actualizacion de cita');
    }

    private function resolverMensaje(string $rol, string $evento, bool $esAutor): string
    {
        return match ($evento) {
            'agendada' => $rol === 'doctor'
                ? 'Se registro una nueva cita en tu agenda.'
                : ($esAutor ? 'Agendaste una cita.' : 'Tu cita fue agendada.'),
            'reagendada' => $rol === 'doctor'
                ? ($esAutor ? 'Reagendaste la cita.' : 'Se reagendo una cita en tu agenda.')
                : ($esAutor ? 'Reagendaste la cita.' : 'Tu cita fue reagendada.'),
            'cancelada' => $rol === 'doctor'
                ? ($esAutor ? 'Cancelaste la cita.' : 'Se cancelo una cita en tu agenda.')
                : ($esAutor ? 'Cancelaste la cita.' : 'Tu cita fue cancelada.'),
            'aceptada' => $rol === 'doctor'
                ? ($esAutor ? 'Aceptaste la cita.' : 'Se acepto una cita en tu agenda.')
                : ($esAutor ? 'Aceptaste la cita.' : 'Tu cita fue aceptada.'),
            'prioridad' => $rol === 'doctor'
                ? 'La prioridad de esta cita fue actualizada a '.ucfirst(strtolower((string) ($this->cita->prioridad_nivel ?? 'BAJA'))).'.'
                : 'La prioridad de tu cita fue actualizada a '.ucfirst(strtolower((string) ($this->cita->prioridad_nivel ?? 'BAJA'))).'.',
            'no_se_presento' => $rol === 'doctor'
                ? 'La cita fue marcada como no se presento.'
                : 'Tu cita fue marcada como no se presento.',
            default => $esAutor
                ? 'Actualizaste la cita.'
                : ($rol === 'doctor' ? 'Se actualizo una cita en tu agenda.' : 'Tu cita fue actualizada.'),
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
            'no_se_presento' => 'no se presento',
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
