<?php

namespace App\Http\Controllers;

use App\Mail\ContactoRecibido;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ContactoController extends Controller
{
    public function __construct()
    {
        $this->middleware('throttle:contacto')->only(['storeContacto', 'enviarFormulario']);
    }

    public function mostrarFormulario()
    {
        return view('contacto');
    }

    public function storeContacto(Request $request)
    {
        $t0 = (int) $request->input('t0', 0);
        $isBot = filled($request->input('empresa'));
        $tooFast = $t0 > 0 && (now()->timestamp - $t0) < 3;

        if ($isBot || $tooFast) {
            return back()->with('success', 'Tu mensaje ha sido enviado correctamente.');
        }

        $datos = $request->validateWithBag('contacto', [
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telefono' => ['required', 'digits:10'],
            'asunto' => ['required', 'string', 'max:255'],
            'mensaje' => ['required', 'string', 'max:1000'],
            'empresa' => ['nullable', 'prohibited'],
            't0' => ['required', 'integer'],
        ]);

        $contactMessage = ContactMessage::create([
            'nombre' => $datos['nombre'],
            'correo' => $datos['email'],
            'telefono' => $datos['telefono'],
            'asunto' => $datos['asunto'],
            'mensaje' => $datos['mensaje'],
            'estado' => 'nuevo',
        ]);

        try {
            $recipient = config('mail.contact_to');

            if ($recipient) {
                Mail::to($recipient)->send(new ContactoRecibido($datos));
            }
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar el correo de contacto.', [
                'contact_message_id' => $contactMessage->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', 'Tu mensaje ha sido enviado correctamente.');
    }

    public function enviarFormulario(Request $request)
    {
        return $this->storeContacto($request);
    }
}
