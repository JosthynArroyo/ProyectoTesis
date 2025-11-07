<?php

namespace App\Listeners;

use App\Events\CitaAgendada;
use App\Models\Factura;
use App\Models\FacturaItem;
use Illuminate\Support\Facades\DB;

class CrearFacturaBorrador
{
    /**
     * Maneja el evento CitaAgendada.
     */
    public function handle(CitaAgendada $event): void
    {
        $cita = $event->cita; // Asegúrate que tu evento expone $cita (App\Models\Cita)

        // Protección: si no hay doctor/paciente válidos, no hacemos nada
        if (!$cita || !$cita->doctor_id || !$cita->paciente_id) {
            return;
        }

        // Idempotencia: si ya existe factura para esta cita, no crear otra
        $existe = Factura::where('cita_id', $cita->id)->exists();
        if ($existe) {
            return;
        }

        // Cargar doctor con tarifa (precio_consulta/moneda)
        $doctor = $cita->doctor()->select(['id','precio_consulta','moneda','name'])->first();
        $pacienteId = $cita->paciente_id;

        // Normalizar tarifa
        $precio = (float) ($doctor->precio_consulta ?? 0);
        $moneda = $doctor->moneda ?: 'USD';
        $descripcion = 'Consulta médica';
        if (!empty($doctor->name)) {
            $descripcion .= ' - Dr(a). ' . $doctor->name;
        }

        DB::transaction(function () use ($cita, $pacienteId, $doctor, $precio, $moneda, $descripcion) {

            // 1) Crear factura en estado "borrador"
            $factura = Factura::create([
                'cita_id'     => $cita->id,
                'paciente_id' => $pacienteId,
                'doctor_id'   => $doctor->id,
                'moneda'      => $moneda,
                'subtotal'    => 0,
                'impuestos'   => 0,
                'total'       => 0,
                'estado'      => Factura::ESTADO_BORRADOR,
                'pdf_path'    => null,
                'emitida_en'  => null,
            ]);

            // 2) Agregar el ítem con la tarifa del doctor
            $item = new FacturaItem([
                'descripcion'     => $descripcion,
                'cantidad'        => 1,
                'precio_unitario' => $precio,
                // 'total_linea' se calcula en el boot() del modelo si cambia cantidad/precio,
                // pero lo seteamos por claridad
                'total_linea'     => $precio * 1,
            ]);
            $factura->items()->save($item);

            // 3) Recalcular totales
            $factura->load('items');
            $factura->recalcularTotales();
        });
    }
}
