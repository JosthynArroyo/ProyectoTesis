<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Factura extends Model
{
    protected $table = 'facturas';

    public const ESTADO_BORRADOR = 'borrador';
    public const ESTADO_EMITIDA  = 'emitida';
    public const ESTADO_ANULADA  = 'anulada';

    protected $fillable = [
        'cita_id',
        'paciente_id',
        'doctor_id',
        'moneda',
        'subtotal',
        'impuestos',
        'total',
        'estado',
        'pdf_path',
        'emitida_en',
    ];

    protected $casts = [
        'subtotal'   => 'decimal:2',
        'impuestos'  => 'decimal:2',
        'total'      => 'decimal:2',
        'emitida_en' => 'datetime',
    ];

    /** Relaciones */
    public function cita(): BelongsTo
    {
        // Tu tabla de citas es 'citas_medicas' y tu modelo es App\Models\Cita
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paciente_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FacturaItem::class, 'factura_id');
    }

    /** Helpers de negocio */
    public function recalcularTotales(): void
    {
        $subtotal = $this->items->sum('total_linea');
        // Ajusta la lógica de impuestos si aplica (IVA 12%, etc.). De momento 0.
        $impuestos = 0;
        $total = $subtotal + $impuestos;

        $this->update([
            'subtotal'  => $subtotal,
            'impuestos' => $impuestos,
            'total'     => $total,
        ]);
    }

    public function esBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    public function esEmitida(): bool
    {
        return $this->estado === self::ESTADO_EMITIDA;
    }
}
