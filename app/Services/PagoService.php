<?php

namespace App\Services;

use App\Models\BookingOverrideLog;
use App\Models\Cita;
use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptLog;
use App\Models\PaymentStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PagoService
{
    public const MENSAJE_BLOQUEO = 'Tiene pagos pendientes. Regularice su cuenta para agendar una nueva cita.';

    public function __construct(private readonly PagoDocumentoService $documentoService)
    {
    }

    public function crearParaCita(Cita $cita, ?User $actor = null): Pago
    {
        return DB::transaction(function () use ($cita, $actor): Pago {
            $existente = Pago::query()->where('cita_id', $cita->id)->first();
            if ($existente) {
                return $existente;
            }

            if (!$cita->relationLoaded('doctor')) {
                $cita->load('doctor:id,precio_consulta,moneda');
            }

            $pago = Pago::query()->create([
                'cita_id' => $cita->id,
                'paciente_id' => $cita->paciente_id,
                'monto' => (float) ($cita->doctor?->precio_consulta ?? 0),
                'moneda' => $cita->doctor?->moneda ?: 'USD',
                'metodo_pago' => null,
                'estado' => Pago::ESTADO_PENDIENTE,
                'creado_por' => $actor?->id,
            ]);

            $this->registrarLog(
                pago: $pago,
                estadoAnterior: null,
                estadoNuevo: Pago::ESTADO_PENDIENTE,
                actor: $actor,
                motivo: 'Creacion automatica de obligacion de pago.'
            );

            return $pago;
        });
    }

    public function crearOrdenParaCitaRealizada(Cita $cita, ?User $actor = null): Pago
    {
        $pago = $this->crearParaCita($cita, $actor);
        $pago = $this->asegurarDatosOrden(
            $pago,
            $actor,
            'Orden de cobro generada automaticamente al marcar la cita como realizada.'
        );

        $this->obtenerOGenerarOrdenPdf($pago, $actor);

        return $pago->refresh();
    }

    public function asegurarDatosOrden(Pago $pago, ?User $actor = null, ?string $motivo = null): Pago
    {
        return DB::transaction(function () use ($pago, $actor, $motivo): Pago {
            $pago->refresh();
            $actualizar = [];

            if (empty($pago->folio_unico)) {
                $actualizar['folio_unico'] = $this->generarFolioOrden($pago);
            }
            if (empty($pago->token_publico)) {
                $actualizar['token_publico'] = $this->generarTokenPublico($pago);
            }

            if (!empty($actualizar)) {
                $pago->fill($actualizar);
                $pago->save();

                $this->registrarLog(
                    pago: $pago,
                    estadoAnterior: $pago->estado,
                    estadoNuevo: $pago->estado,
                    actor: $actor,
                    motivo: $motivo ?: 'Datos de orden de cobro generados.'
                );
            }

            return $pago->refresh();
        });
    }

    public function obtenerOGenerarOrdenPdf(Pago $pago, ?User $actor = null): string
    {
        $pago = $this->asegurarDatosOrden($pago, $actor);

        if ($pago->orden_pdf_path && Storage::disk('local')->exists($pago->orden_pdf_path)) {
            return $pago->orden_pdf_path;
        }

        $path = $this->documentoService->generarOrdenCobroPdf($pago);
        $pago->orden_pdf_path = $path;
        $pago->save();

        $this->registrarLog(
            pago: $pago,
            estadoAnterior: $pago->estado,
            estadoNuevo: $pago->estado,
            actor: $actor,
            motivo: 'PDF de orden de cobro generado.'
        );

        return $path;
    }

    public function emitirReciboParaPago(Pago $pago, ?User $actor = null, ?string $motivo = null): PaymentReceipt
    {
        $pago->refresh();
        if ($pago->estado !== Pago::ESTADO_PAGADO) {
            throw new InvalidArgumentException('Solo se puede emitir recibo para pagos en estado pagado.');
        }

        $pago = $this->asegurarDatosOrden($pago, $actor);

        /** @var PaymentReceipt|null $existente */
        $existente = $pago->receipt()->first();
        if ($existente) {
            if (!$existente->pdf_path || !Storage::disk('local')->exists($existente->pdf_path)) {
                $path = $this->documentoService->generarReciboPagoPdf($pago, $existente);
                $existente->pdf_path = $path;
                $existente->save();

                $this->registrarReciboLog(
                    receipt: $existente,
                    estadoAnterior: PaymentReceipt::ESTADO_EMITIDO,
                    estadoNuevo: PaymentReceipt::ESTADO_REEMITIDO,
                    actor: $actor,
                    motivo: $motivo ?: 'Reemision de recibo por falta de archivo PDF.'
                );
            }

            return $existente->refresh();
        }

        return DB::transaction(function () use ($pago, $actor, $motivo): PaymentReceipt {
            $receipt = PaymentReceipt::query()->create([
                'pago_id' => $pago->id,
                'folio_recibo' => $this->generarFolioRecibo($pago),
                'emitido_en' => now(),
                'emitido_por' => $actor?->id,
                'metodo_pago' => $pago->metodo_pago ?: Pago::METODO_EFECTIVO,
                'monto' => $pago->monto,
                'referencia_transaccion' => $pago->referencia_transaccion,
                'comprobante_path' => $pago->comprobante_path,
            ]);

            $path = $this->documentoService->generarReciboPagoPdf($pago, $receipt);
            $receipt->pdf_path = $path;
            $receipt->save();

            $this->registrarReciboLog(
                receipt: $receipt,
                estadoAnterior: null,
                estadoNuevo: PaymentReceipt::ESTADO_EMITIDO,
                actor: $actor,
                motivo: $motivo ?: 'Emision automatica de recibo al confirmar pago.'
            );

            return $receipt->refresh();
        });
    }

    public function pacienteTieneBloqueo(int $pacienteId): bool
    {
        return Pago::query()
            ->where('paciente_id', $pacienteId)
            ->conBloqueoAgendamiento()
            ->exists();
    }

    public function idsPagosBloqueantes(int $pacienteId): array
    {
        return Pago::query()
            ->where('paciente_id', $pacienteId)
            ->conBloqueoAgendamiento()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function cambiarEstado(
        Pago $pago,
        string $nuevoEstado,
        ?User $actor = null,
        ?string $motivo = null,
        array $extra = []
    ): Pago {
        if (!in_array($nuevoEstado, Pago::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado de pago invalido: '.$nuevoEstado);
        }

        $actualizado = DB::transaction(function () use ($pago, $nuevoEstado, $actor, $motivo, $extra): Pago {
            $pago->refresh();

            $estadoAnterior = $pago->estado;
            $payload = array_merge($extra, ['estado' => $nuevoEstado]);

            if ($motivo !== null && trim($motivo) !== '') {
                $payload['observacion_admin'] = trim($motivo);
            }

            if ($this->esActorAdministrativo($actor) && $nuevoEstado === Pago::ESTADO_PAGADO) {
                $payload['aprobado_por'] = $actor?->id;
                $payload['aprobado_en'] = now();
            }

            $pago->fill($payload);
            $pago->save();

            if ($estadoAnterior !== $nuevoEstado) {
                $this->registrarLog(
                    pago: $pago,
                    estadoAnterior: $estadoAnterior,
                    estadoNuevo: $nuevoEstado,
                    actor: $actor,
                    motivo: $motivo
                );
            }

            return $pago->refresh();
        });

        if ($nuevoEstado === Pago::ESTADO_PAGADO) {
            $this->emitirReciboParaPago($actualizado, $actor, $motivo);
        }

        return $actualizado->refresh();
    }

    public function registrarOverrideAgendamiento(
        int $pacienteId,
        ?User $actor = null,
        ?int $citaId = null,
        ?string $motivo = null
    ): BookingOverrideLog {
        return BookingOverrideLog::query()->create([
            'paciente_id' => $pacienteId,
            'actor_id' => $actor?->id,
            'cita_id' => $citaId,
            'motivo' => $motivo,
            'created_at' => now(),
        ]);
    }

    public function registrarLog(
        Pago $pago,
        ?string $estadoAnterior,
        string $estadoNuevo,
        ?User $actor = null,
        ?string $motivo = null
    ): PaymentStatusLog {
        return PaymentStatusLog::query()->create([
            'pago_id' => $pago->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'actor_id' => $actor?->id,
            'actor_rol' => $this->resolverRolActor($actor),
            'motivo' => $motivo,
            'created_at' => now(),
        ]);
    }

    public function registrarReciboLog(
        PaymentReceipt $receipt,
        ?string $estadoAnterior,
        string $estadoNuevo,
        ?User $actor = null,
        ?string $motivo = null
    ): PaymentReceiptLog {
        return PaymentReceiptLog::query()->create([
            'payment_receipt_id' => $receipt->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'actor_id' => $actor?->id,
            'actor_rol' => $this->resolverRolActor($actor),
            'motivo' => $motivo,
            'created_at' => now(),
        ]);
    }

    protected function generarFolioOrden(Pago $pago): string
    {
        $pago->loadMissing('cita:id,fecha');

        $fecha = $pago->cita?->fecha?->format('Ymd') ?: now()->format('Ymd');
        $base = sprintf('OC-%s-%06d', $fecha, $pago->id);
        $folio = $base;

        $i = 1;
        while (
            Pago::query()
                ->where('folio_unico', $folio)
                ->where('id', '!=', $pago->id)
                ->exists()
        ) {
            $i++;
            $folio = $base.'-'.$i;
        }

        return $folio;
    }

    protected function generarTokenPublico(Pago $pago): string
    {
        do {
            $token = Str::lower(Str::random(48));
        } while (
            Pago::query()
                ->where('token_publico', $token)
                ->where('id', '!=', $pago->id)
                ->exists()
        );

        return $token;
    }

    protected function generarFolioRecibo(Pago $pago): string
    {
        $base = sprintf('RP-%s-%06d', now()->format('Ymd'), $pago->id);
        $folio = $base;

        $i = 1;
        while (PaymentReceipt::query()->where('folio_recibo', $folio)->exists()) {
            $i++;
            $folio = $base.'-'.$i;
        }

        return $folio;
    }

    protected function resolverRolActor(?User $actor): ?string
    {
        if (!$actor) {
            return 'sistema';
        }

        if ($actor->relationLoaded('roles') && $actor->roles->isNotEmpty()) {
            return (string) $actor->roles->first()->name;
        }

        return $actor->roles()->orderBy('name')->value('name') ?: null;
    }

    protected function esActorAdministrativo(?User $actor): bool
    {
        if (!$actor) {
            return false;
        }

        return $actor->hasRole('administrador') || $actor->hasRole('superadmin');
    }
}
