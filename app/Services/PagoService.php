<?php

namespace App\Services;

use App\Models\BookingOverrideLog;
use App\Models\Cita;
use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\PaymentReceiptLog;
use App\Models\PaymentStatusLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class PagoService
{
    public const MENSAJE_BLOQUEO = 'Tienes ordenes de pago vencidas de citas concluidas. Regulariza tu cuenta para agendar una nueva cita.';

    private const TRANSACTION_ATTEMPTS = 3;

    private const IDENTIFIER_SAVE_ATTEMPTS = 5;

    private const TOKEN_GENERATION_ATTEMPTS = 20;

    private const FOLIO_COLLISION_ATTEMPTS = 100;

    public function __construct(
        private readonly PagoDocumentoService $documentoService,
        private readonly PaymentReceiptDocumentService $receiptDocumentService,
        private readonly PaymentOrderDocumentService $orderDocumentService
    ) {}

    public function crearParaCita(Cita $cita, ?User $actor = null): Pago
    {
        try {
            return DB::transaction(function () use ($cita, $actor): Pago {
                $citaBloqueada = Cita::query()
                    ->with('doctor:id,precio_consulta,moneda')
                    ->whereKey($cita->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $existente = Pago::query()
                    ->where('cita_id', $citaBloqueada->id)
                    ->lockForUpdate()
                    ->first();

                if ($existente) {
                    return $existente->refresh();
                }

                $pago = Pago::query()->create([
                    'cita_id' => $citaBloqueada->id,
                    'paciente_id' => $citaBloqueada->paciente_id,
                    'monto' => (float) ($citaBloqueada->doctor?->precio_consulta ?? 0),
                    'moneda' => $citaBloqueada->doctor?->moneda ?: 'USD',
                    'metodo_pago' => null,
                    'estado' => Pago::ESTADO_PENDIENTE,
                    'creado_por' => $actor?->id,
                ]);

                $this->registrarLog(
                    pago: $pago,
                    estadoAnterior: null,
                    estadoNuevo: Pago::ESTADO_PENDIENTE,
                    actor: $actor,
                    motivo: 'Creacion automatica de orden de pago tras concluir la cita.'
                );

                $this->invalidatePatientPaymentBlock((int) $pago->paciente_id);

                return $pago->refresh();
            }, self::TRANSACTION_ATTEMPTS);
        } catch (QueryException $e) {
            if ($this->esViolacionUnica($e)) {
                $existente = Pago::query()->where('cita_id', $cita->getKey())->first();
                if ($existente) {
                    return $existente->refresh();
                }
            }

            throw $e;
        }
    }

    public function crearOrdenParaCitaRealizada(Cita $cita, ?User $actor = null): Pago
    {
        $pago = $this->crearParaCita($cita, $actor);
        $pago = $this->asegurarDatosOrden(
            $pago,
            $actor,
            'Orden de cobro generada automaticamente al concluir la cita.'
        );

        return $pago->refresh();
    }

    public function asegurarDatosOrden(Pago $pago, ?User $actor = null, ?string $motivo = null): Pago
    {
        for ($attempt = 1; $attempt <= self::IDENTIFIER_SAVE_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(function () use ($pago, $actor, $motivo): Pago {
                    $pagoBloqueado = Pago::query()
                        ->whereKey($pago->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    $actualizar = [];

                    if (empty($pagoBloqueado->folio_unico)) {
                        $actualizar['folio_unico'] = $this->generarFolioOrden($pagoBloqueado);
                    }
                    if (empty($pagoBloqueado->token_publico)) {
                        $actualizar['token_publico'] = $this->generarTokenPublico($pagoBloqueado);
                    }
                    if (empty($pagoBloqueado->csv)) {
                        $actualizar['csv'] = app(DocumentoCsvService::class)->generateCsv();
                    }

                    if (! empty($actualizar)) {
                        $pagoBloqueado->fill($actualizar);
                        $pagoBloqueado->save();

                        $this->registrarLog(
                            pago: $pagoBloqueado,
                            estadoAnterior: $pagoBloqueado->estado,
                            estadoNuevo: $pagoBloqueado->estado,
                            actor: $actor,
                            motivo: $motivo ?: 'Datos de orden de cobro generados.'
                        );
                    }

                    return $pagoBloqueado->refresh();
                }, self::TRANSACTION_ATTEMPTS);
            } catch (QueryException $e) {
                if (! $this->esViolacionUnica($e) || $attempt === self::IDENTIFIER_SAVE_ATTEMPTS) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('No fue posible asignar identificadores unicos a la orden de cobro.');
    }

    public function obtenerOGenerarOrdenPdf(Pago $pago, ?User $actor = null): string
    {
        $pago = $this->asegurarDatosOrden($pago, $actor);

        if (! empty($pago->orden_pdf_path)) {
            $resolved = $this->orderDocumentService->resolveStorage($pago->orden_pdf_path, $pago->orden_pdf_disk);
            if ($resolved !== null) {
                return $resolved['path'];
            }
        }

        $key = $this->orderDocumentService->generateAndStoreOrderPdf($pago, $this->documentoService);

        $this->registrarLog(
            pago: $pago,
            estadoAnterior: $pago->estado,
            estadoNuevo: $pago->estado,
            actor: $actor,
            motivo: 'PDF de orden de cobro generado en R2.'
        );

        return $key;
    }

    private function withPaymentLock(Pago $pago, callable $callback)
    {
        $connection = DB::connection();
        $lockName = 'payment_receipt_pdf_payment_'.$pago->id;
        $lockTimeoutSeconds = 15;

        $lockResult = $connection->selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$lockName, $lockTimeoutSeconds]);
        $acquired = isset($lockResult->acquired) ? (int) $lockResult->acquired : null;

        if ($acquired !== 1) {
            if ($acquired === null) {
                \Illuminate\Support\Facades\Log::warning('Error en GET_LOCK para operacion de pago: '.$lockName);
            }
            throw new \RuntimeException('No se pudo obtener el bloqueo para procesar la operacion financiera del pago.');
        }

        try {
            return $callback();
        } finally {
            try {
                $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            } catch (\Throwable $releaseErr) {
                \Illuminate\Support\Facades\Log::warning('Error liberando advisory lock MySQL '.$lockName.': '.$releaseErr->getMessage());
            }
        }
    }

    public function emitirReciboParaPago(Pago $pago, ?User $actor = null, ?string $motivo = null): PaymentReceipt
    {
        $pago->refresh();
        if ($pago->estado !== Pago::ESTADO_PAGADO) {
            throw new InvalidArgumentException('Solo se puede emitir recibo para pagos en estado pagado.');
        }

        // Fast-path: si ya tiene recibo completo con PDF válido en almacenamiento, retornarlo sin lock
        /** @var PaymentReceipt|null $existente */
        $existente = $pago->receipt()->first();
        if ($existente && $existente->pdf_path && $this->receiptDocumentService->resolveStorage($existente->pdf_path, $existente->pdf_disk) !== null) {
            return $existente->refresh();
        }

        $pago = $this->asegurarDatosOrden($pago, $actor);

        return $this->withPaymentLock($pago, function () use ($pago, $actor, $motivo): PaymentReceipt {
            $pago->refresh();
            if ($pago->estado !== Pago::ESTADO_PAGADO) {
                throw new InvalidArgumentException('Solo se puede emitir recibo para pagos en estado pagado.');
            }

            return $this->ejecutarEmisionReciboDirecta($pago, $actor, $motivo);
        });
    }

    private function ejecutarEmisionReciboDirecta(
        Pago $pago,
        ?User $actor = null,
        ?string $motivo = null
    ): PaymentReceipt {
        $pago = $this->asegurarDatosOrden($pago, $actor);

        /** @var PaymentReceipt|null $existente */
        $existente = $pago->receipt()->first();
        if ($existente) {
            $hasValidPdf = $existente->pdf_path && $this->receiptDocumentService->resolveStorage($existente->pdf_path, $existente->pdf_disk) !== null;
            if (! $hasValidPdf) {
                $this->receiptDocumentService->generateAndStoreReceiptPdf($pago, $existente, $this->documentoService);

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

        // 1. Preparar PaymentReceipt en memoria (sin persistir en BD antes de tener PDF válido)
        $receipt = new PaymentReceipt([
            'pago_id' => $pago->id,
            'folio_recibo' => $this->generarFolioRecibo($pago),
            'verification_token' => Str::random(40),
            'csv' => app(DocumentoCsvService::class)->generateCsv(),
            'emitido_en' => now(),
            'emitido_por' => $actor?->id,
            'metodo_pago' => $pago->metodo_pago ?: Pago::METODO_EFECTIVO,
            'monto' => $pago->monto,
            'referencia_transaccion' => $pago->referencia_transaccion,
            'comprobante_path' => $pago->comprobante_path,
            'comprobante_disk' => $pago->comprobante_disk,
        ]);
        if ($actor) {
            $receipt->setRelation('emisor', $actor);
        }

        // 2. Generar y almacenar PDF en R2 fuera de cualquier transacción/lock MySQL
        $uploadedKey = null;
        try {
            $uploadedKey = $this->receiptDocumentService->generateAndStoreReceiptPdfContentOnly(
                $pago,
                $receipt,
                $this->documentoService
            );
        } catch (\Throwable $e) {
            throw $e;
        }

        // 3. Persistir en transacción atómica corta
        try {
            return DB::transaction(function () use ($pago, $receipt, $uploadedKey, $actor, $motivo): PaymentReceipt {
                $pagoBloqueado = Pago::query()
                    ->whereKey($pago->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($pagoBloqueado->estado !== Pago::ESTADO_PAGADO) {
                    throw new InvalidArgumentException('Solo se puede emitir recibo para pagos en estado pagado.');
                }

                /** @var PaymentReceipt|null $existente */
                $existente = $pagoBloqueado->receipt()->lockForUpdate()->first();
                if ($existente) {
                    return $existente;
                }

                $receipt->pdf_path = $uploadedKey;
                $receipt->pdf_disk = PaymentReceiptDocumentService::DISK;
                $receipt->save();

                $this->registrarReciboLog(
                    receipt: $receipt,
                    estadoAnterior: null,
                    estadoNuevo: PaymentReceipt::ESTADO_EMITIDO,
                    actor: $actor,
                    motivo: $motivo ?: 'Emision automatica de recibo al confirmar pago.'
                );

                return $receipt->refresh();
            }, self::TRANSACTION_ATTEMPTS);
        } catch (\Throwable $dbEx) {
            if ($uploadedKey) {
                try {
                    Storage::disk(PaymentReceiptDocumentService::DISK)->delete($uploadedKey);
                } catch (\Throwable) {}
            }
            throw $dbEx;
        }
    }

    public function pacienteTieneBloqueo(int $pacienteId): bool
    {
        return Pago::query()
            ->where('paciente_id', $pacienteId)
            ->conBloqueoAgendamiento()
            ->exists();
    }

    public function actualizarMontoAdministrativo(
        Pago $pago,
        float $nuevoMonto,
        ?string $nuevaMoneda = null,
        ?User $actor = null,
        ?string $motivo = null
    ): Pago {
        return $this->withPaymentLock($pago, function () use ($pago, $nuevoMonto, $nuevaMoneda, $actor, $motivo): Pago {
            return DB::transaction(function () use ($pago, $nuevoMonto, $nuevaMoneda, $actor, $motivo): Pago {
                $pagoBloqueado = Pago::query()
                    ->whereKey($pago->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $pagoBloqueado->esEditableFinancieramente()) {
                    throw new InvalidArgumentException(
                        $pagoBloqueado->estado === Pago::ESTADO_PAGADO || $pagoBloqueado->receipt()->exists()
                            ? 'Este pago ya fue aprobado o cuenta con recibo emitido; no admite modificaciones en su monto.'
                            : 'Este pago se encuentra en un estado que no permite modificar el monto.'
                    );
                }

                $montoAnterior = (float) $pagoBloqueado->monto;
                $pagoBloqueado->monto = $nuevoMonto;
                if (! empty($nuevaMoneda)) {
                    $pagoBloqueado->moneda = strtoupper((string) $nuevaMoneda);
                }
                $pagoBloqueado->save();

                $motivoLog = $motivo ?: sprintf(
                    'Monto actualizado por administracion: %s %s -> %s %s.',
                    number_format($montoAnterior, 2),
                    $pagoBloqueado->moneda,
                    number_format($nuevoMonto, 2),
                    $pagoBloqueado->moneda
                );

                $this->registrarLog(
                    pago: $pagoBloqueado,
                    estadoAnterior: $pagoBloqueado->estado,
                    estadoNuevo: $pagoBloqueado->estado,
                    actor: $actor,
                    motivo: $motivoLog
                );

                return $pagoBloqueado->refresh();
            }, self::TRANSACTION_ATTEMPTS);
        });
    }

    public function actualizarMetodoAdministrativo(
        Pago $pago,
        string $nuevoMetodo,
        ?string $observacion = null,
        ?User $actor = null
    ): Pago {
        return $this->withPaymentLock($pago, function () use ($pago, $nuevoMetodo, $observacion, $actor): Pago {
            return DB::transaction(function () use ($pago, $nuevoMetodo, $observacion, $actor): Pago {
                $pagoBloqueado = Pago::query()
                    ->whereKey($pago->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $pagoBloqueado->esEditableFinancieramente()) {
                    throw new InvalidArgumentException(
                        $pagoBloqueado->estado === Pago::ESTADO_PAGADO || $pagoBloqueado->receipt()->exists()
                            ? 'Este pago ya fue aprobado o cuenta con recibo emitido; no admite modificaciones en su método de pago.'
                            : 'Este pago se encuentra en un estado que no permite modificar el método de pago.'
                    );
                }

                $metodoAnterior = $pagoBloqueado->metodo_pago;
                if ($metodoAnterior === $nuevoMetodo) {
                    return $pagoBloqueado;
                }

                $pagoBloqueado->metodo_pago = $nuevoMetodo;
                if ($observacion !== null && trim($observacion) !== '') {
                    $pagoBloqueado->observacion_admin = trim($observacion);
                }
                $pagoBloqueado->save();

                $motivoBase = $metodoAnterior
                    ? 'Cambio de metodo de pago: '.strtoupper($metodoAnterior).' -> '.strtoupper($nuevoMetodo).'.'
                    : 'Asignacion de metodo de pago: '.strtoupper($nuevoMetodo).'.';
                $motivoLog = ($observacion !== null && trim($observacion) !== '')
                    ? $motivoBase.' '.trim($observacion)
                    : $motivoBase;

                $this->registrarLog(
                    pago: $pagoBloqueado,
                    estadoAnterior: $pagoBloqueado->estado,
                    estadoNuevo: $pagoBloqueado->estado,
                    actor: $actor,
                    motivo: $motivoLog
                );

                return $pagoBloqueado->refresh();
            }, self::TRANSACTION_ATTEMPTS);
        });
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

    public function validarElegibilidadAprobacion(Pago $pago): void
    {
        // 1. Validar transiciones administrativas permitidas
        $allowedTargets = array_values(Pago::ADMINISTRATIVE_TRANSITIONS[$pago->estado] ?? []);
        if (! in_array(Pago::ESTADO_PAGADO, $allowedTargets, true)) {
            throw new InvalidArgumentException($pago->administrativeStateMessage());
        }

        // 2. Método de pago obligatorio y válido
        if (empty($pago->metodo_pago)) {
            throw new InvalidArgumentException('Debe asignar un método de pago antes de aprobar.');
        }

        if (! in_array($pago->metodo_pago, Pago::METODOS, true)) {
            throw new InvalidArgumentException('El método de pago asignado no es válido: ' . $pago->metodo_pago);
        }

        // 3. Monto válido
        if ((float) $pago->monto <= 0) {
            throw new InvalidArgumentException('El monto del pago debe ser mayor a cero para ser aprobado.');
        }

        // 4. Regla de comprobante para transferencia
        if ($pago->metodo_pago === Pago::METODO_TRANSFERENCIA) {
            if (empty($pago->comprobante_path)) {
                throw new InvalidArgumentException('No se puede aprobar una transferencia sin comprobante adjunto.');
            }

            $resolved = app(PaymentProofStorageService::class)->resolveStorage($pago->comprobante_path, $pago->comprobante_disk);
            if ($resolved === null) {
                throw new InvalidArgumentException('El comprobante adjunto no fue encontrado en el almacenamiento.');
            }
        }
    }

    public function cambiarEstado(
        Pago $pago,
        string $nuevoEstado,
        ?User $actor = null,
        ?string $motivo = null,
        array $extra = []
    ): Pago {
        if (! in_array($nuevoEstado, Pago::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado de pago invalido: '.$nuevoEstado);
        }

        return $this->withPaymentLock($pago, function () use ($pago, $nuevoEstado, $actor, $motivo, $extra): Pago {
            if ($nuevoEstado === Pago::ESTADO_PAGADO) {
                // 1. Lectura fresca y revalidación bajo el advisory lock
                /** @var Pago $pagoFresco */
                $pagoFresco = Pago::query()->whereKey($pago->getKey())->firstOrFail();
                $esAdmin = $this->esActorAdministrativo($actor);

                if (! $esAdmin && $actor !== null) {
                    throw new InvalidArgumentException('El paciente solo puede enviar pagos a estado pendiente o en verificación.');
                }

                // Si ya está pagado: Inmutabilidad estricta de la aprobación original
                if ($pagoFresco->estado === Pago::ESTADO_PAGADO) {
                    /** @var PaymentReceipt|null $existente */
                    $existente = $pagoFresco->receipt()->first();
                    $hasValidPdf = $existente && $existente->pdf_path && $this->receiptDocumentService->resolveStorage($existente->pdf_path, $existente->pdf_disk) !== null;
                    if ($hasValidPdf) {
                        // Idempotente: YA APROBADO. NO modificar aprobado_por, aprobado_en, logs, ni receipt.
                        return $pagoFresco->refresh();
                    }

                    // Si está pagado pero le faltaba el PDF, regenerar el PDF faltante respetando la autoría original
                    $this->ejecutarEmisionReciboDirecta($pagoFresco, $actor, $motivo);
                    return $pagoFresco->refresh();
                }

                // Validar elegibilidad autoritativa sobre el Pago fresco bajo el mutex
                $this->validarElegibilidadAprobacion($pagoFresco);

                // 2. Asegurar datos de orden de cobro (folio_unico, token_publico, csv)
                $pagoFresco = $this->asegurarDatosOrden($pagoFresco, $actor);

                // 3. Capturar SNAPSHOT exacto de los datos del Pago usados para el recibo y PDF
                $snapshot = [
                    'monto' => (float) $pagoFresco->monto,
                    'metodo_pago' => $pagoFresco->metodo_pago,
                    'moneda' => $pagoFresco->moneda ?: 'USD',
                    'referencia_transaccion' => $pagoFresco->referencia_transaccion,
                    'comprobante_path' => $pagoFresco->comprobante_path,
                    'comprobante_disk' => $pagoFresco->comprobante_disk,
                    'folio_unico' => $pagoFresco->folio_unico,
                ];

                // 4. Preparar PaymentReceipt en MEMORIA (sin persistir en BD antes de tener PDF válido)
                $receipt = new PaymentReceipt([
                    'pago_id' => $pagoFresco->id,
                    'folio_recibo' => $this->generarFolioRecibo($pagoFresco),
                    'verification_token' => Str::random(40),
                    'csv' => app(DocumentoCsvService::class)->generateCsv(),
                    'emitido_en' => now(),
                    'emitido_por' => $actor?->id,
                    'metodo_pago' => $snapshot['metodo_pago'],
                    'monto' => $snapshot['monto'],
                    'referencia_transaccion' => $snapshot['referencia_transaccion'],
                    'comprobante_path' => $snapshot['comprobante_path'],
                    'comprobante_disk' => $snapshot['comprobante_disk'],
                ]);
                if ($actor) {
                    $receipt->setRelation('emisor', $actor);
                }

                $uploadedNewKey = null;

                // 5. Generar y almacenar el PDF en R2 fuera de transacciones de base de datos
                try {
                    $uploadedNewKey = $this->receiptDocumentService->generateAndStoreReceiptPdfContentOnly(
                        $pagoFresco,
                        $receipt,
                        $this->documentoService
                    );
                } catch (\Throwable $docEx) {
                    throw new \RuntimeException(
                        'No se pudo completar la emisión del recibo de pago en R2 (' . $docEx->getMessage() . '). La aprobación no fue realizada.',
                        0,
                        $docEx
                    );
                }

                // 6. Transacción MySQL final atómica:
                // Bloquea el Pago, valida snapshot, asigna pagado + aprobado_por + aprobado_en + receipt con pdf_path + logs
                try {
                    $actualizado = DB::transaction(function () use ($pagoFresco, $receipt, $snapshot, $uploadedNewKey, $actor, $motivo, $extra): Pago {
                        /** @var Pago $pagoBloqueado */
                        $pagoBloqueado = Pago::query()
                            ->whereKey($pagoFresco->getKey())
                            ->lockForUpdate()
                            ->firstOrFail();

                        // Si ya está pagado por otra transacción
                        if ($pagoBloqueado->estado === Pago::ESTADO_PAGADO) {
                            return $pagoBloqueado->refresh();
                        }

                        // Revalidar elegibilidad autoritativa bajo lock
                        $this->validarElegibilidadAprobacion($pagoBloqueado);

                        // DEFENSA CONTRA SNAPSHOT STALE (Regla 6)
                        if (
                            (float) $pagoBloqueado->monto !== $snapshot['monto'] ||
                            $pagoBloqueado->metodo_pago !== $snapshot['metodo_pago'] ||
                            ($pagoBloqueado->moneda ?: 'USD') !== $snapshot['moneda'] ||
                            $pagoBloqueado->referencia_transaccion !== $snapshot['referencia_transaccion'] ||
                            $pagoBloqueado->comprobante_path !== $snapshot['comprobante_path'] ||
                            $pagoBloqueado->comprobante_disk !== $snapshot['comprobante_disk'] ||
                            $pagoBloqueado->folio_unico !== $snapshot['folio_unico']
                        ) {
                            throw new \RuntimeException('Snapshot mismatch: los datos financieros o comprobante del pago fueron modificados durante el proceso de aprobación.');
                        }

                        // Verificar que no apareció otro PaymentReceipt
                        if ($pagoBloqueado->receipt()->exists()) {
                            throw new \RuntimeException('Conflicto: ya existe un recibo persistido para este pago.');
                        }

                        $estadoAnterior = $pagoBloqueado->estado;

                        $payload = array_merge($extra, [
                            'estado' => Pago::ESTADO_PAGADO,
                            'aprobado_por' => $actor?->id,
                            'aprobado_en' => now(),
                        ]);

                        if ($motivo !== null && trim($motivo) !== '') {
                            $payload['observacion_admin'] = trim($motivo);
                        }

                        $pagoBloqueado->fill($payload);
                        $pagoBloqueado->save();

                        // Persistir PaymentReceipt completo con la clave R2 verificada
                        $receipt->pdf_path = $uploadedNewKey;
                        $receipt->pdf_disk = PaymentReceiptDocumentService::DISK;
                        $receipt->save();

                        // Registrar logs
                        $this->registrarLog(
                            pago: $pagoBloqueado,
                            estadoAnterior: $estadoAnterior,
                            estadoNuevo: Pago::ESTADO_PAGADO,
                            actor: $actor,
                            motivo: $motivo
                        );

                        $this->registrarReciboLog(
                            receipt: $receipt,
                            estadoAnterior: null,
                            estadoNuevo: PaymentReceipt::ESTADO_EMITIDO,
                            actor: $actor,
                            motivo: $motivo ?: 'Emision automatica de recibo al confirmar pago.'
                        );

                        return $pagoBloqueado->refresh();
                    }, self::TRANSACTION_ATTEMPTS);
                } catch (\Throwable $dbEx) {
                    // Si la transacción final en BD falla, eliminar el objeto R2 subido para no dejar huérfanos
                    if ($uploadedNewKey) {
                        try {
                            Storage::disk(PaymentReceiptDocumentService::DISK)->delete($uploadedNewKey);
                        } catch (\Throwable $cleanEx) {
                            \Illuminate\Support\Facades\Log::error('Fallo al limpiar objeto R2 tras error en DB final de aprobacion', [
                                'key' => $uploadedNewKey,
                                'error' => $cleanEx->getMessage(),
                            ]);
                        }
                    }

                    throw $dbEx;
                }

                $this->invalidatePatientPaymentBlock((int) $actualizado->paciente_id);

                return $actualizado->refresh();
            }

            // Transiciones no-pagadas
            $estadoAnterior = null;
            $statusLogId = null;

            $actualizado = DB::transaction(function () use ($pago, $nuevoEstado, $actor, $motivo, $extra, &$estadoAnterior, &$statusLogId): Pago {
                $pagoBloqueado = Pago::query()
                    ->whereKey($pago->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $estadoAnterior = $pagoBloqueado->estado;
                $esAdmin = $this->esActorAdministrativo($actor);

                // Revalidación bajo lock para el paciente
                if (! $esAdmin && $actor !== null) {
                    if (! $pagoBloqueado->esEditablePorPaciente()) {
                        $mensaje = $pagoBloqueado->estado === Pago::ESTADO_PAGADO
                            ? 'Este pago ya fue aprobado y no admite más modificaciones.'
                            : ($pagoBloqueado->estado === Pago::ESTADO_EN_VERIFICACION
                                ? 'El pago está en verificación. Debe esperar revisión administrativa antes de cambiar el método.'
                                : 'Este pago no permite modificaciones en su estado actual.');

                        throw new InvalidArgumentException($mensaje);
                    }

                    if (! in_array($nuevoEstado, [Pago::ESTADO_PENDIENTE, Pago::ESTADO_EN_VERIFICACION], true)) {
                        throw new InvalidArgumentException('El paciente solo puede enviar pagos a estado pendiente o en verificación.');
                    }
                }

                // Revalidación bajo lock para administración
                if ($esAdmin && $estadoAnterior !== $nuevoEstado) {
                    $allowedTargets = array_values(Pago::ADMINISTRATIVE_TRANSITIONS[$estadoAnterior] ?? []);
                    if (! in_array($nuevoEstado, $allowedTargets, true)) {
                        throw new InvalidArgumentException($pagoBloqueado->administrativeStateMessage());
                    }
                }

                $payload = array_merge($extra, ['estado' => $nuevoEstado]);

                if ($motivo !== null && trim($motivo) !== '') {
                    $payload['observacion_admin'] = trim($motivo);
                }

                $pagoBloqueado->fill($payload);
                $pagoBloqueado->save();

                if ($estadoAnterior !== $nuevoEstado) {
                    $log = $this->registrarLog(
                        pago: $pagoBloqueado,
                        estadoAnterior: $estadoAnterior,
                        estadoNuevo: $nuevoEstado,
                        actor: $actor,
                        motivo: $motivo
                    );
                    $statusLogId = $log->id;
                }

                return $pagoBloqueado->refresh();
            });

            $this->invalidatePatientPaymentBlock((int) $actualizado->paciente_id);

            return $actualizado->refresh();
        });
    }

    public function compensarAprobacionFallida(
        Pago $pago,
        ?string $estadoAnterior,
        ?int $statusLogId = null,
        ?int $createdReceiptId = null
    ): void {
        if ($estadoAnterior === null) {
            return;
        }

        try {
            DB::transaction(function () use ($pago, $estadoAnterior, $statusLogId, $createdReceiptId) {
                $pagoBloqueado = Pago::query()
                    ->whereKey($pago->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($pagoBloqueado->estado === Pago::ESTADO_PAGADO) {
                    $receipt = PaymentReceipt::query()
                        ->where('pago_id', $pagoBloqueado->id)
                        ->lockForUpdate()
                        ->first();

                    if ($receipt) {
                        $hasValidPdf = $receipt->pdf_path && $this->receiptDocumentService->resolveStorage($receipt->pdf_path, $receipt->pdf_disk) !== null;

                        // Si el recibo ya cuenta con un PDF válido y verificado en R2 (por ejemplo completado legítimamente),
                        // no eliminar el recibo ni el PDF ajeno, ni revertir la aprobación si no corresponde.
                        if ($hasValidPdf) {
                            return;
                        }

                        // Solo eliminar si el recibo no es válido y corresponde al ID creado en este intento
                        if ($createdReceiptId === null || $receipt->id === $createdReceiptId) {
                            if ($receipt->pdf_path) {
                                $disk = $receipt->pdf_disk ?: PaymentReceiptDocumentService::DISK;
                                try {
                                    Storage::disk($disk)->delete($receipt->pdf_path);
                                } catch (\Throwable) {}
                            }
                            $receipt->logs()->delete();
                            $receipt->delete();
                        }
                    }

                    $pagoBloqueado->estado = $estadoAnterior;
                    $pagoBloqueado->aprobado_por = null;
                    $pagoBloqueado->aprobado_en = null;
                    $pagoBloqueado->save();

                    if ($statusLogId) {
                        PaymentStatusLog::query()->whereKey($statusLogId)->delete();
                    }
                }
            }, self::TRANSACTION_ATTEMPTS);
        } catch (\Throwable $compEx) {
            \Illuminate\Support\Facades\Log::critical('Error al compensar aprobacion fallida de pago', [
                'pago_id' => $pago->id,
                'error' => $compEx->getMessage(),
            ]);
        }
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
        if (! $pago->getKey()) {
            throw new RuntimeException('No se puede generar folio para una orden de cobro sin ID persistido.');
        }

        $pago->loadMissing('cita:id,fecha');

        $fecha = $pago->cita?->fecha?->format('Ymd') ?: now('America/Guayaquil')->format('Ymd');
        $base = sprintf('OC-%s-%06d', $fecha, $pago->id);
        $folio = $base;

        $i = 1;
        while (
            Pago::query()
                ->where('folio_unico', $folio)
                ->where('id', '!=', $pago->id)
                ->exists()
        ) {
            if ($i >= self::FOLIO_COLLISION_ATTEMPTS) {
                throw new RuntimeException('No fue posible generar un folio unico para la orden de cobro.');
            }

            $i++;
            $folio = $base.'-'.$i;
        }

        return $folio;
    }

    protected function generarTokenPublico(Pago $pago): string
    {
        for ($attempt = 1; $attempt <= self::TOKEN_GENERATION_ATTEMPTS; $attempt++) {
            $token = Str::lower(Str::random(48));

            $existe = Pago::query()
                ->where('token_publico', $token)
                ->where('id', '!=', $pago->id)
                ->exists();

            if (! $existe) {
                return $token;
            }
        }

        throw new RuntimeException('No fue posible generar un token publico unico para la orden de cobro.');
    }

    protected function generarFolioRecibo(Pago $pago): string
    {
        $base = sprintf('RP-%s-%06d', now('America/Guayaquil')->format('Ymd'), $pago->id);
        $folio = $base;

        $i = 1;
        while (PaymentReceipt::query()->where('folio_recibo', $folio)->exists()) {
            if ($i >= self::FOLIO_COLLISION_ATTEMPTS) {
                throw new RuntimeException('No fue posible generar un folio unico para el recibo de pago.');
            }

            $i++;
            $folio = $base.'-'.$i;
        }

        return $folio;
    }

    protected function resolverRolActor(?User $actor): ?string
    {
        if (! $actor) {
            return 'sistema';
        }

        if ($actor->relationLoaded('roles') && $actor->roles->isNotEmpty()) {
            return (string) $actor->roles->first()->name;
        }

        return $actor->roles()->orderBy('name')->value('name') ?: null;
    }

    protected function esActorAdministrativo(?User $actor): bool
    {
        if (! $actor) {
            return false;
        }

        return $actor->hasRole('administrador') || $actor->hasRole('superadmin');
    }

    protected function esViolacionUnica(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driverCode = (string) ($e->errorInfo[1] ?? '');
        $message = Str::lower($e->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['1062', '1555', '2067'], true)
            || str_contains($message, 'duplicate')
            || str_contains($message, 'unique constraint');
    }

    protected function invalidatePatientPaymentBlock(int $pacienteId): void
    {
        if ($pacienteId < 1) {
            return;
        }

        app(LayoutMetricsService::class)->forgetPatientPaymentBlock($pacienteId);
    }
}
