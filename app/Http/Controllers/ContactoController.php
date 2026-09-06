<?php

namespace App\Http\Controllers;

use App\Mail\ContactoRecibido;
use App\Models\ContactMessage;
use App\Services\ApplicationModeService;
use App\Services\CommercialContactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ContactoController extends Controller
{
    public function __construct(
        private readonly ApplicationModeService $appMode
    ) {
        $this->middleware('throttle:contacto')->only(['storeContacto', 'enviarFormulario']);
    }

    public function mostrarFormulario()
    {
        return view('contacto');
    }

    public function storeContacto(Request $request, CommercialContactService $commercialService)
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

        if ($commercialService->isAuthorizedCommercialRequest($request)) {
            try {
                $commercialService->send($datos);

                return back()->with('success', 'Tu mensaje ha sido enviado correctamente.');
            } catch (Throwable $exception) {
                Log::error('Fallo en la entrega SMTP del formulario comercial JA MedSys', [
                    'error' => $exception->getMessage(),
                ]);

                return back()->withInput()->with('error', 'En este momento no se pudo enviar el correo comercial. Por favor contáctanos directamente por WhatsApp.');
            }
        }

        $contactMessage = ContactMessage::create([
            'nombre' => $datos['nombre'],
            'correo' => $datos['email'],
            'telefono' => $datos['telefono'],
            'asunto' => $datos['asunto'],
            'mensaje' => $datos['mensaje'],
            'estado' => 'nuevo',
        ]);

        try {
            $recipient = $this->appMode->isDemo()
                ? 'contacto@demo-clinigest.test'
                : config('mail.contact_to');

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

    public function enviarFormulario(Request $request, CommercialContactService $commercialService)
    {
        return $this->storeContacto($request, $commercialService);
    }
}
