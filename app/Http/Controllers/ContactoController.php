<?php
// app/Http/Controllers/ContactoController.php

namespace App\Http\Controllers;

use App\Models\Contacto;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContactoController extends Controller
{
    public function __construct()
    {
        // 5 envíos por minuto por IP
        $this->middleware('throttle:contacto,5,1')->only('enviarFormulario');
    }

    public function mostrarFormulario()
    {
        return view('contacto');
    }

    public function enviarFormulario(Request $request)
    {
        // Honeypot y tiempo mínimo (>= 3s)
        $t0 = (int) $request->input('t0', 0);
        $isBot = filled($request->input('empresa'));
        $tooFast = $t0 > 0 && (now()->timestamp - $t0) < 3;
        if ($isBot || $tooFast) {
            // Finge éxito para no dar feedback al bot
            return back()->with('success', 'Tu mensaje ha sido enviado correctamente.');
        }

        $datos = $request->validate([
            'nombre'   => ['required','string','max:255'],
            'email'    => ['required','email','max:255'],
            'telefono' => ['nullable','string','max:30'],
            'motivo'   => ['nullable','in:consulta_general,agendar_cita,reprogramacion,facturacion,otros'],
            'asunto'   => ['nullable','string','max:255'],
            'mensaje'  => ['required','string','max:1000'],
        ]);

        // Normaliza campos opcionales
        $datos['motivo'] = $datos['motivo'] ?? null;
        $datos['telefono'] = $datos['telefono'] ?? null;

        // Persistencia
        Contacto::create($datos);

        // TODO: Mail::to(config('mail.from.address'))->queue(new ContactoRecibido($datos));
        // Opcional: Log por IP/UA
        // \Log::info('Contacto', ['ip'=>$request->ip(),'ua'=>$request->userAgent(),'id'=>Str::uuid()->toString()]);

        return back()->with('success', 'Tu mensaje ha sido enviado correctamente. ¡Gracias por contactarnos!');
    }
}
