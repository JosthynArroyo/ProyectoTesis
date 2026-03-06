<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Services\CitaNoShowService;

class EmailCitaActionController extends Controller
{
    /**
     * Maneja acciones firmadas desde el email.
     * Ruta: /email/cita/{cita}/{rol}/{accion}
     * rol: "paciente" | "doctor"
     * accion: "cancelar" (ambos) | "aceptar" (doctor)
     */
    public function __invoke(Request $request, Cita $cita, string $rol, string $accion)
    {
        // Validar firma de la URL
        if (!$request->hasValidSignature()) {
            return redirect('/')->with('error', 'Enlace inválido o expirado.');
        }

        // Reglas de negocio
        if (!in_array($rol, ['paciente','doctor'])) {
            return redirect('/')->with('error', 'Rol no permitido.');
        }

        if (!in_array($accion, ['aceptar','cancelar'])) {
            return redirect('/')->with('error', 'Acción no permitida.');
        }


        if (app(CitaNoShowService::class)->marcarSiVencio($cita)) {
            return redirect('/')->with('error', 'La cita ya vencio y se marco como no se presento.');
        }


        // Verificar estados permitidos por acción
        if ($accion === 'aceptar') {
            if ($rol !== 'doctor') {
                return redirect('/')->with('error', 'Solo el doctor puede aceptar.');
            }
            if ($cita->estado !== Cita::ESTADO_PENDIENTE) {
                return redirect('/')->with('error', 'Solo se puede aceptar una cita pendiente.');
            }

            $cita->estado = Cita::ESTADO_CONFIRMADA;
            $cita->activo = true;
            $cita->save();

            // Redirección amistosa
            return redirect()->route('doctor.citas')
                ->with('success', 'Cita aceptada correctamente.');
        }

        if ($accion === 'cancelar') {
            // Paciente o Doctor pueden cancelar si no está realizada
            if (in_array($cita->estado, [Cita::ESTADO_CANCELADA, Cita::ESTADO_REALIZADA, Cita::ESTADO_NO_SE_PRESENTO])) {
                return redirect('/')->with('error', 'Esta cita ya no puede ser cancelada.');
            }

            $cita->estado = Cita::ESTADO_CANCELADA;
            $cita->activo = false;
            $cita->save();

            // Según rol, redirigir
            $route = $rol === 'doctor' ? 'doctor.citas' : 'paciente.citas';
            return redirect()->route($route)->with('success', 'Cita cancelada.');
        }

        return redirect('/')->with('error', 'Acción no reconocida.');
    }
}
