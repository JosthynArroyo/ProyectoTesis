<?php

namespace Database\Seeders;

use App\Models\Cita;
use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\Role;
use App\Models\User;
use App\Services\PagoDocumentoService;
use App\Services\PaymentReceiptDocumentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoPaymentsSeeder extends Seeder
{
    public function run(): void
    {
        $receiptDocumentService = app(PaymentReceiptDocumentService::class);
        $documentoService = app(PagoDocumentoService::class);
        $adminUser = User::where('email', 'admin@demo-clinigest.test')->first()
            ?? User::whereHas('roles', fn ($q) => $q->where('name', 'administrador'))->first();
        $canonicalPatient = User::where('email', 'paciente@demo-clinigest.test')->first();

        $citas = Cita::with(['doctor.especialidades'])->orderBy('fecha', 'desc')->get();

        if ($citas->isEmpty()) {
            return;
        }

        $otherIndex = 0;

        foreach ($citas as $idx => $cita) {
            $precio = 35.00;
            $isCanonical = ($canonicalPatient && (int) $cita->paciente_id === (int) $canonicalPatient->id);

            if ($isCanonical) {
                // El paciente canónico Javier Espinoza no debe tener deudas vencidas bloqueantes
                $estado = Pago::ESTADO_PAGADO;
                $metodo = ($idx % 2 === 0) ? Pago::METODO_EFECTIVO : Pago::METODO_TRANSFERENCIA;
                $observacion = 'Cobro registrado y verificado correctamente en recepción.';
            } else {
                // Distribución representativa para los otros 2 pacientes demo:
                $pattern = $otherIndex % 4;
                if ($pattern === 0) {
                    $estado = Pago::ESTADO_PAGADO;
                    $metodo = ($otherIndex % 2 === 0) ? Pago::METODO_EFECTIVO : Pago::METODO_TRANSFERENCIA;
                    $observacion = 'Cobro registrado y verificado correctamente en recepción.';
                } elseif ($pattern === 1) {
                    $estado = Pago::ESTADO_EN_VERIFICACION;
                    $metodo = Pago::METODO_TRANSFERENCIA;
                    $observacion = 'Comprobante bancario subido por el paciente, pendiente de cotejo contable.';
                } elseif ($pattern === 2) {
                    $estado = Pago::ESTADO_PENDIENTE;
                    $metodo = Pago::METODO_EFECTIVO;
                    $observacion = 'Cita agendada, pago pendiente de liquidación en caja al llegar a consulta.';
                } else {
                    $estado = Pago::ESTADO_RECHAZADO;
                    $metodo = Pago::METODO_TRANSFERENCIA;
                    $observacion = 'Comprobante no coincide con el número de cuenta institucional.';
                }
                $otherIndex++;
            }

            $existingPayment = Pago::where('cita_id', $cita->id)->first();
            $folioPago = $existingPayment?->folio_unico ?: sprintf('PAG-2026-%05d', $cita->id + 1000);
            $transactionReference = $existingPayment?->referencia_transaccion
                ?: (($metodo === Pago::METODO_TRANSFERENCIA)
                    ? sprintf('TRANSF-%08d', rand(10000000, 99999999))
                    : 'EFECTIVO-CAJA');

            $pago = Pago::updateOrCreate(
                ['cita_id' => $cita->id],
                [
                    'folio_unico' => $folioPago,
                    'token_publico' => $existingPayment?->token_publico ?: Str::random(32),
                    'csv' => $folioPago,
                    'paciente_id' => $cita->paciente_id,
                    'monto' => $precio,
                    'moneda' => 'USD',
                    'metodo_pago' => $metodo,
                    'estado' => $estado,
                    'observacion_admin' => $observacion,
                    'creado_por' => $adminUser?->id,
                    'aprobado_por' => ($estado === Pago::ESTADO_PAGADO) ? $adminUser?->id : null,
                    'aprobado_en' => ($estado === Pago::ESTADO_PAGADO) ? ($cita->updated_at ?? now()) : null,
                    'referencia_transaccion' => $transactionReference,
                    'created_at' => $cita->created_at ?? now()->subDays(5),
                    'updated_at' => $cita->updated_at ?? now()->subDays(5),
                ]
            );

            // Generar recibo para pagos confirmados; eliminar si cambió de estado
            if ($estado === Pago::ESTADO_PAGADO) {
                $existingReceipt = PaymentReceipt::where('pago_id', $pago->id)->first();
                $folioRecibo = $existingReceipt?->folio_recibo ?: sprintf('REC-2026-%05d', $pago->id + 1000);
                $receipt = PaymentReceipt::updateOrCreate(
                    ['pago_id' => $pago->id],
                    [
                        'folio_recibo' => $folioRecibo,
                        'emitido_en' => $pago->updated_at ?? now(),
                        'emitido_por' => $adminUser?->id,
                        'metodo_pago' => $metodo,
                        'monto' => $precio,
                        'referencia_transaccion' => $pago->referencia_transaccion,
                        'verification_token' => $existingReceipt?->verification_token ?: Str::random(32),
                        'csv' => $folioRecibo,
                    ]
                );

                if ($isCanonical && $receiptDocumentService->resolveStorage($receipt->pdf_path, $receipt->pdf_disk) === null) {
                    $receiptDocumentService->generateAndStoreReceiptPdf($pago, $receipt, $documentoService);
                }
            } else {
                PaymentReceipt::where('pago_id', $pago->id)->delete();
            }
        }
    }
}
