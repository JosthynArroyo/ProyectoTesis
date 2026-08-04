<?php

namespace App\Mail;

use App\Models\DatabaseBackup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DatabaseBackupFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DatabaseBackup $backup,
        public string $errorMessage
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "ALERTA: Fallo en Respaldo de Base de Datos [{$this->backup->type}]",
        );
    }

    public function content(): Content
    {
        $escapedError = htmlspecialchars($this->errorMessage, ENT_QUOTES, 'UTF-8');

        return new Content(
            htmlString: "<p><strong>ALERTA SISTEMA CLÍNICA:</strong> El respaldo de base de datos ID {$this->backup->uuid} (Tipo: {$this->backup->type}) ha fallado.</p><p><strong>Detalles sanitizados del error:</strong><br><pre>{$escapedError}</pre></p>",
        );
    }
}
