<?php

namespace App\Mail;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecetaMedicaMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var \App\Models\Cita */
    public $cita;

    /** Ruta relativa en storage (opcional, referencia) */
    public $relativePath;

    /** Contenido binario del PDF (para adjuntar) */
    public $pdfOutput;

    /** Nombre del archivo PDF */
    public $fileName;

    /**
     * Motivo del envío: 'creacion' | 'actualizacion'
     * Define asunto y texto del cuerpo
     */
    public $motivo;

    public function __construct(
        Cita $cita,
        ?string $relativePath,
        ?string $pdfOutput,
        ?string $fileName,
        string $motivo = 'creacion'
    ) {
        $this->cita         = $cita;
        $this->relativePath = $relativePath;
        $this->pdfOutput    = $pdfOutput;
        $this->fileName     = $fileName ?: ('receta_'.$cita->id.'.pdf');
        $this->motivo       = in_array($motivo, ['creacion','actualizacion'], true) ? $motivo : 'creacion';
    }

    public function build()
    {
        // Asunto
        $subject = $this->motivo === 'actualizacion'
            ? 'Actualización de receta médica - Clínica Don Bosco'
            : 'Nueva receta médica - Clínica Don Bosco';

        $email = $this->subject($subject)
            ->view('emails.receta_medica') // Usa tu vista. En ella puedes mostrar $motivo para el mensaje.
            ->with([
                'cita'   => $this->cita,
                'motivo' => $this->motivo, // para que la vista muestre "Nueva receta..." o "Actualización..."
            ]);

        // Adjuntar PDF si está disponible
        if (!empty($this->pdfOutput)) {
            $email->attachData($this->pdfOutput, $this->fileName, ['mime' => 'application/pdf']);
        }

        return $email;
    }
}
