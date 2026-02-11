<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\User;
use App\Models\WhatsappMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Twilio\Rest\Client;
use Throwable;

class WhatsAppService
{
    public function sendCitaAceptada(Cita $cita, User $recipient, string $rol): WhatsappMessage
    {
        $mensaje = $this->buildAceptadaMessage($cita, $recipient, $rol);

        return $this->sendCitaMessage(
            $cita,
            $recipient,
            $rol,
            'aceptada',
            $mensaje
        );
    }

    public function sendRecordatorio6h(Cita $cita, User $recipient, string $rol, Carbon $inicio): WhatsappMessage
    {
        $mensaje = $this->buildRecordatorioMessage($cita, $recipient, $rol, $inicio);

        return $this->sendCitaMessage(
            $cita,
            $recipient,
            $rol,
            'recordatorio_6h',
            $mensaje,
            ['inicio' => $inicio->toDateTimeString()]
        );
    }

    private function sendCitaMessage(
        Cita $cita,
        User $recipient,
        string $rol,
        string $evento,
        string $mensaje,
        array $meta = []
    ): WhatsappMessage {
        if (!config('services.whatsapp.enabled', true)) {
            Log::info("WhatsApp: envio desactivado (evento={$evento}, cita={$cita->id}).");
            return null;
        }

        $record = WhatsappMessage::firstOrNew([
            'cita_id' => $cita->id,
            'evento' => $evento,
            'rol_receptor' => $rol,
        ]);

        if ($record->exists && $record->estado === 'enviado') {
            return $record;
        }

        $telefono = $this->normalizePhone($recipient->telefono ?? '');
        $from = $this->formatWhatsAppAddress((string) config('services.twilio.whatsapp_from', ''));

        $record->fill([
            'user_id' => $recipient->id,
            'telefono' => $telefono,
            'mensaje' => Str::limit($mensaje, 2000, ''),
            'estado' => 'pendiente',
            'provider' => 'twilio',
            'payload' => [
                'to' => $telefono,
                'from' => $from,
                'body' => $mensaje,
                'meta' => $meta,
            ],
        ]);
        try {
            $record->save();
        } catch (Throwable $e) {
            $existing = WhatsappMessage::where([
                'cita_id' => $cita->id,
                'evento' => $evento,
                'rol_receptor' => $rol,
            ])->first();
            if ($existing && $existing->estado === 'enviado') {
                return $existing;
            }
            Log::error("WhatsApp: error guardando log (evento={$evento}, cita={$cita->id}). {$e->getMessage()}");
            return $existing;
        }

        if (!$telefono) {
            $record->estado = 'fallido';
            $record->error = 'telefono_invalido';
            $record->save();
            Log::warning("WhatsApp: telefono invalido para user_id={$recipient->id} (cita={$cita->id}).");
            return $record;
        }

        $sid = (string) config('services.twilio.sid', '');
        $token = (string) config('services.twilio.auth_token', '');
        if ($sid === '' || $token === '' || $from === 'whatsapp:') {
            $record->estado = 'fallido';
            $record->error = 'twilio_config_incomplete';
            $record->save();
            Log::error("WhatsApp: configuracion Twilio incompleta (evento={$evento}, cita={$cita->id}).");
            return $record;
        }

        try {
            $client = new Client($sid, $token);
            $message = $client->messages->create(
                $this->formatWhatsAppAddress($telefono),
                [
                    'from' => $from,
                    'body' => $mensaje,
                ]
            );

            $record->estado = 'enviado';
            $record->provider_message_id = $message->sid ?? null;
            $record->error = null;
            $record->enviado_at = now();
            $record->save();

            Log::info("WhatsApp: mensaje enviado (evento={$evento}, cita={$cita->id}, to={$telefono}).");
        } catch (Throwable $e) {
            $record->estado = 'fallido';
            $record->error = Str::limit($e->getMessage(), 500, '');
            $record->save();

            Log::error("WhatsApp: error enviando mensaje (evento={$evento}, cita={$cita->id}). {$e->getMessage()}");
        }

        return $record;
    }

    private function buildAceptadaMessage(Cita $cita, User $recipient, string $rol): string
    {
        [$labelProfesional, $profesional, $especialidad, $fechaTxt, $horaTxt] = $this->citaResumen($cita);
        $nombre = $recipient->name ?? ($rol === 'doctor' ? 'Doctor/a' : 'Paciente');
        $url = $this->panelUrl($rol);

        $lineas = [];
        $lineas[] = 'Hola '.$nombre.',';
        if ($rol === 'doctor') {
            $lineas[] = 'Aceptaste una cita.';
            $lineas[] = 'Paciente: '.(optional($cita->paciente)->name ?? 'Paciente');
        } else {
            $lineas[] = 'Tu cita fue aceptada.';
            $lineas[] = $labelProfesional.': '.$profesional;
        }
        $lineas[] = 'Especialidad: '.$especialidad;
        $lineas[] = 'Fecha: '.$fechaTxt;
        $lineas[] = 'Hora: '.$horaTxt;
        $lineas[] = 'Revisa tu panel: '.$url;

        return implode("\n", array_filter($lineas));
    }

    private function buildRecordatorioMessage(Cita $cita, User $recipient, string $rol, Carbon $inicio): string
    {
        [$labelProfesional, $profesional, $especialidad] = $this->citaResumen($cita);
        $nombre = $recipient->name ?? ($rol === 'doctor' ? 'Doctor/a' : 'Paciente');
        $url = $this->panelUrl($rol);
        $fechaTxt = $inicio->format('d/m/Y');
        $horaTxt = $inicio->format('H:i');
        $horas = (int) config('services.whatsapp.reminder_hours', 6);

        $lineas = [];
        $lineas[] = 'Hola '.$nombre.',';
        $lineas[] = 'Recordatorio: tu cita es en aproximadamente '.$horas.' horas.';
        if ($rol === 'doctor') {
            $lineas[] = 'Paciente: '.(optional($cita->paciente)->name ?? 'Paciente');
        } else {
            $lineas[] = $labelProfesional.': '.$profesional;
        }
        $lineas[] = 'Especialidad: '.$especialidad;
        $lineas[] = 'Fecha: '.$fechaTxt;
        $lineas[] = 'Hora: '.$horaTxt;
        $lineas[] = 'Revisa tu panel: '.$url;

        return implode("\n", array_filter($lineas));
    }

    private function citaResumen(Cita $cita): array
    {
        $especialidad = $cita->especialidad?->nombre ?? 'Especialidad';
        $isLab = strtolower((string) ($cita->especialidad?->nombre ?? '')) === 'laboratorio clinico';
        $labelProfesional = $isLab ? 'Laboratorio' : 'Doctor';
        $profesional = $cita->doctor?->name ?? $labelProfesional;

        $fechaTxt = $cita->fecha ? $cita->fecha->format('d/m/Y') : '';
        $horaTxt = $cita->hora ? substr((string) $cita->hora, 0, 5) : '';

        return [$labelProfesional, $profesional, $especialidad, $fechaTxt, $horaTxt];
    }

    private function panelUrl(string $rol): string
    {
        return $rol === 'doctor'
            ? route('doctor.citas')
            : route('paciente.citas');
    }

    private function normalizePhone(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($raw, '+')) {
            return $this->isValidE164($digits) ? '+'.$digits : null;
        }

        $country = strtoupper((string) config('services.whatsapp.default_country', 'EC'));
        if ($country === 'EC') {
            if (str_starts_with($digits, '5930') && strlen($digits) === 13) {
                return '+593'.substr($digits, 4);
            }
            if (str_starts_with($digits, '593') && strlen($digits) === 12) {
                return '+'.$digits;
            }
            if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
                return '+593'.substr($digits, 1);
            }
            if (strlen($digits) === 9) {
                return '+593'.$digits;
            }
        }

        return $this->isValidE164($digits) ? '+'.$digits : null;
    }

    private function isValidE164(string $digits): bool
    {
        $len = strlen($digits);
        return $len >= 10 && $len <= 15;
    }

    private function formatWhatsAppAddress(string $number): string
    {
        $number = trim($number);
        if ($number === '') {
            return 'whatsapp:';
        }
        if (str_starts_with($number, 'whatsapp:')) {
            return $number;
        }
        return 'whatsapp:'.$number;
    }
}
