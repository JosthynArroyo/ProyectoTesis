<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Pago extends Model
{
    use HasFactory;

    public const METODO_EFECTIVO = 'efectivo';
    public const METODO_TRANSFERENCIA = 'transferencia';

    public const METODOS = [
        self::METODO_EFECTIVO,
        self::METODO_TRANSFERENCIA,
    ];

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_EN_VERIFICACION = 'en_verificacion';
    public const ESTADO_PAGADO = 'pagado';
    public const ESTADO_RECHAZADO = 'rechazado';
    public const ESTADO_ANULADO = 'anulado';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_EN_VERIFICACION,
        self::ESTADO_PAGADO,
        self::ESTADO_RECHAZADO,
        self::ESTADO_ANULADO,
    ];

    public const ESTADOS_BLOQUEANTES_AGENDAMIENTO = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_EN_VERIFICACION,
    ];

    protected $table = 'pagos';

    protected $fillable = [
        'cita_id',
        'folio_unico',
        'token_publico',
        'paciente_id',
        'monto',
        'moneda',
        'metodo_pago',
        'estado',
        'referencia_transaccion',
        'observacion_admin',
        'comprobante_path',
        'orden_pdf_path',
        'aprobado_por',
        'aprobado_en',
        'creado_por',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'aprobado_en' => 'datetime',
    ];

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paciente_id');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(PaymentStatusLog::class, 'pago_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(PaymentReceipt::class, 'pago_id');
    }

    public function scopeConBloqueoAgendamiento(Builder $query): Builder
    {
        return $query
            ->whereIn('estado', self::ESTADOS_BLOQUEANTES_AGENDAMIENTO)
            ->whereHas('cita', function (Builder $citaQuery) {
                $citaQuery
                    ->where('activo', true)
                    ->where('estado', '!=', Cita::ESTADO_CANCELADA);
            });
    }

    public function scopeConComprobante(Builder $query): Builder
    {
        return $query->whereNotNull('comprobante_path');
    }

    public function requiereComprobante(): bool
    {
        return $this->metodo_pago === self::METODO_TRANSFERENCIA;
    }

    public function comprobanteEsPdf(): bool
    {
        return Str::endsWith(Str::lower((string) $this->comprobante_path), '.pdf');
    }

    public function tieneOrdenCobro(): bool
    {
        return !empty($this->folio_unico) && !empty($this->token_publico);
    }

    public function esEditablePorPaciente(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_PENDIENTE,
            self::ESTADO_RECHAZADO,
        ], true);
    }
}
