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

    public const ADMIN_ACTION_APROBAR = 'aprobar';

    public const ADMIN_ACTION_RECHAZAR = 'rechazar';

    public const ADMIN_ACTION_ANULAR = 'anular';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_EN_VERIFICACION,
        self::ESTADO_PAGADO,
        self::ESTADO_RECHAZADO,
        self::ESTADO_ANULADO,
    ];

    public const ADMINISTRATIVE_TRANSITIONS = [
        self::ESTADO_PENDIENTE => [
            self::ADMIN_ACTION_APROBAR => self::ESTADO_PAGADO,
            self::ADMIN_ACTION_RECHAZAR => self::ESTADO_RECHAZADO,
            self::ADMIN_ACTION_ANULAR => self::ESTADO_ANULADO,
        ],
        self::ESTADO_EN_VERIFICACION => [
            self::ADMIN_ACTION_APROBAR => self::ESTADO_PAGADO,
            self::ADMIN_ACTION_RECHAZAR => self::ESTADO_RECHAZADO,
            self::ADMIN_ACTION_ANULAR => self::ESTADO_ANULADO,
        ],
        self::ESTADO_PAGADO => [],
        self::ESTADO_RECHAZADO => [],
        self::ESTADO_ANULADO => [],
    ];

    public const ADMIN_ACTION_LABELS = [
        self::ADMIN_ACTION_APROBAR => 'Aprobar pago',
        self::ADMIN_ACTION_RECHAZAR => 'Rechazar pago',
        self::ADMIN_ACTION_ANULAR => 'Anular pago',
    ];

    public const ADMIN_ACTION_TONES = [
        self::ADMIN_ACTION_APROBAR => 'primary',
        self::ADMIN_ACTION_RECHAZAR => 'danger',
        self::ADMIN_ACTION_ANULAR => 'ghost',
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
        'csv',
        'paciente_id',
        'monto',
        'moneda',
        'metodo_pago',
        'estado',
        'referencia_transaccion',
        'observacion_admin',
        'comprobante_path',
        'comprobante_disk',
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
        $limite = now('America/Guayaquil')->subDay();

        return $query
            ->whereIn('estado', self::ESTADOS_BLOQUEANTES_AGENDAMIENTO)
            ->whereHas('cita', function (Builder $citaQuery) use ($limite) {
                $citaQuery
                    ->where('estado', Cita::ESTADO_REALIZADA)
                    ->where(function (Builder $fechaQuery) use ($limite) {
                        $fechaQuery
                            ->whereDate('fecha', '<', $limite->toDateString())
                            ->orWhere(function (Builder $sameDateQuery) use ($limite) {
                                $sameDateQuery
                                    ->whereDate('fecha', $limite->toDateString())
                                    ->whereTime('hora', '<=', $limite->format('H:i:s'));
                            });
                    });
            });
    }

    public function scopeConComprobante(Builder $query): Builder
    {
        return $query->whereNotNull('comprobante_path');
    }

    public function scopeConOrdenCobroReal(Builder $query): Builder
    {
        return $query
            ->whereNotNull('folio_unico')
            ->where('folio_unico', '!=', '')
            ->whereNotNull('token_publico')
            ->where('token_publico', '!=', '');
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
        return ! empty($this->folio_unico) && ! empty($this->token_publico);
    }

    public function esEditablePorPaciente(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_PENDIENTE,
            self::ESTADO_RECHAZADO,
        ], true);
    }

    public function availableAdministrativeActions(): array
    {
        return array_keys(self::ADMINISTRATIVE_TRANSITIONS[$this->estado] ?? []);
    }

    public function canPerformAdministrativeAction(string $action): bool
    {
        return array_key_exists($action, self::ADMINISTRATIVE_TRANSITIONS[$this->estado] ?? []);
    }

    public function administrativeTargetState(string $action): ?string
    {
        return self::ADMINISTRATIVE_TRANSITIONS[$this->estado][$action] ?? null;
    }

    public function isAdministrativeTerminalState(): bool
    {
        return empty($this->availableAdministrativeActions());
    }

    public function administrativeStateMessage(): string
    {
        return match ($this->estado) {
            self::ESTADO_PAGADO => 'Este pago ya fue aprobado y no admite más acciones administrativas.',
            self::ESTADO_RECHAZADO => 'Este pago ya fue rechazado y no admite más acciones administrativas.',
            self::ESTADO_ANULADO => 'Este pago ya fue anulado y no admite más acciones administrativas.',
            default => 'Este pago todavía admite revisión administrativa.',
        };
    }

    public static function administrativeActionLabel(string $action): string
    {
        return self::ADMIN_ACTION_LABELS[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }

    public static function administrativeActionTone(string $action): string
    {
        return self::ADMIN_ACTION_TONES[$action] ?? 'primary';
    }
}
