<?php
// app/Http/Controllers/ContactoController.php

namespace App\Http\Controllers;

use App\Models\Contacto;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;

class ContactoController extends Controller
{
    public function __construct()
    {
        // Usa el limiter nombrado "contacto"
        $this->middleware('throttle:contacto')->only('enviarFormulario');
        // Alternativa sin limiter nombrado:
        // $this->middleware('throttle:5,1')->only('enviarFormulario');
    }

    public function mostrarFormulario()
    {
        return view('contacto');
    }

    public function enviarFormulario(Request $request)
    {
        // Honeypot y tiempo mínimo (>= 3s)
        $t0 = (int) $request->input('t0', 0);
        $isBot   = filled($request->input('empresa'));
        $tooFast = $t0 > 0 && (now()->timestamp - $t0) < 3;
        if ($isBot || $tooFast) {
            return back()->with('success', 'Tu mensaje ha sido enviado correctamente.');
        }

        $datos = $request->validate([
            'nombre'         => ['required','string','max:255'],
            'email'          => ['required','email','max:255'],
            'telefono'       => ['nullable','regex:/^[0-9]{10}$/'],
            'motivo'         => ['nullable','in:consulta_general,agendar_cita,reprogramacion,facturacion,otros'],
            'asunto'         => ['nullable','string','max:255'],
            'mensaje'        => ['required','string','max:1000'],
            'consentimiento' => ['required','in:si,no'],
            'empresa'        => ['nullable','prohibited'], 
            't0'             => ['required','integer'],
        ]);

        $payload = Arr::only($datos, ['nombre','email','telefono','motivo','asunto','mensaje']);
        Contacto::create($payload);

        // Enviar email al administrador
        Mail::to('josthynarroyo627@gmail.com')->send(
            new \App\Mail\ContactoRecibido($datos) // ver clase abajo
        );

        return back()->with('success', 'Tu mensaje ha sido enviado correctamente.');
    }
}
