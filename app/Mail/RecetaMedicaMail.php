<?php

namespace App\Mail;

use App\Models\Cita;
use App\Models\Receta;
use App\Services\ClinicIdentityService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecetaMedicaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $cita;

    public $relativePath;

    public $pdfOutput;

    public $fileName;

    public $motivo;

    public function __construct(
        Cita $cita,
        ?string $relativePath = '',
        ?string $pdfOutput = '',
        ?string $fileName = '',
        string $motivo = 'creacion',
        ?Receta $receta = null
    ) {
        $this->cita = $cita->loadMissing(['paciente', 'doctor', 'especialidad', 'receta']);
        $this->receta = $receta ?: $this->cita->receta;
        $this->relativePath = (string) $relativePath;
        $this->pdfOutput = (string) $pdfOutput;
        $this->fileName = $fileName ?: ('receta_'.$cita->id.'.pdf');
        $this->motivo = in_array($motivo, ['creacion', 'actualizacion'], true) ? $motivo : 'creacion';
    }

    public function build()
    {
        $identity = app(ClinicIdentityService::class);
        $subject = $this->motivo === 'actualizacion'
            ? $identity->subject('Actualizacion de receta medica')
            : $identity->subject('Nueva receta medica');

        $email = $this->subject($subject)
            ->view('emails.receta_medica')
            ->with([
                'cita' => $this->cita,
                'motivo' => $this->motivo,
            ]);

        if (! empty($this->pdfOutput)) {
            $email->attachData($this->pdfOutput, $this->fileName, ['mime' => 'application/pdf']);
        }

        return $email;
    }
}
