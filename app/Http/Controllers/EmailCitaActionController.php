<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Services\CitaComprobanteService;
use App\Services\CitaNoShowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

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
        if (! $request->hasValidSignature()) {
            return redirect('/')->with('error', 'Enlace inválido o expirado.');
        }

        // Reglas de negocio básicas
        if (! in_array($rol, ['paciente', 'doctor'], true)) {
            return redirect('/')->with('error', 'Acceso no permitido.');
        }

        if (! in_array($accion, ['aceptar', 'cancelar'], true)) {
            return redirect('/')->with('error', 'Acción no permitida.');
        }

        if ($accion === 'aceptar' && $rol !== 'doctor') {
            return redirect('/')->with('error', 'Solo el doctor puede aceptar.');
        }

        // Ejecución transaccional bajo row lock pesimista para evitar carreras con cancelaciones/no-shows
        $resultado = DB::transaction(function () use ($cita, $rol, $accion): array {
            /** @var Cita|null $citaBloqueada */
            $citaBloqueada = Cita::query()
                ->whereKey($cita->getKey())
                ->lockForUpdate()
                ->first();

            if (! $citaBloqueada) {
                return ['success' => false, 'message' => 'Cita no encontrada.'];
            }

            if ($rol === 'doctor' && ! $citaBloqueada->doctor?->isActive()) {
                return ['success' => false, 'message' => 'El doctor asignado esta inactivo y no puede gestionar esta cita.'];
            }

            if (app(CitaNoShowService::class)->marcarSiVencio($citaBloqueada)) {
                return ['success' => false, 'message' => 'La cita ya vencio y se marco como no se presento.'];
            }

            // Recargar estado fresco por si fue modificada
            $citaBloqueada->refresh();

            // Verificar estados permitidos por acción
            if ($accion === 'aceptar') {
                if ($citaBloqueada->estado !== Cita::ESTADO_PENDIENTE) {
                    $msg = in_array($citaBloqueada->estado, Cita::ESTADOS_TERMINALES, true)
                        ? 'Esta cita ya no puede ser aceptada porque se encuentra en estado ' . $citaBloqueada->estado . '.'
                        : 'Solo se puede aceptar una cita pendiente.';

                    return ['success' => false, 'message' => $msg];
                }

                $citaBloqueada->estado = Cita::ESTADO_CONFIRMADA;
                $citaBloqueada->activo = true;
                $citaBloqueada->save();

                return ['success' => true, 'action' => 'aceptar', 'cita' => $citaBloqueada];
            }

            if ($accion === 'cancelar') {
                // Paciente o Doctor pueden cancelar si no está en estado terminal
                if (in_array($citaBloqueada->estado, [Cita::ESTADO_CANCELADA, Cita::ESTADO_REALIZADA, Cita::ESTADO_NO_SE_PRESENTO], true)) {
                    return ['success' => false, 'message' => 'Esta cita ya no puede ser cancelada.'];
                }

                $citaBloqueada->estado = Cita::ESTADO_CANCELADA;
                $citaBloqueada->activo = false;
                $citaBloqueada->save();

                return ['success' => true, 'action' => 'cancelar', 'cita' => $citaBloqueada];
            }

            return ['success' => false, 'message' => 'Acción no reconocida.'];
        });

        if (! $resultado['success']) {
            return redirect('/')->with('error', $resultado['message']);
        }

        /** @var Cita $citaActualizada */
        $citaActualizada = $resultado['cita'];

        // Sincronizar comprobante fuera de la transacción para mantenerla corta
        app(CitaComprobanteService::class)->sincronizarComprobante($citaActualizada);

        if ($resultado['action'] === 'aceptar') {
            return redirect()->route('doctor.citas')
                ->with('success', 'Cita aceptada correctamente.');
        }

        $route = $rol === 'doctor' ? 'doctor.citas' : 'paciente.citas';

        return redirect()->route($route)->with('success', 'Cita cancelada.');
    }
}
