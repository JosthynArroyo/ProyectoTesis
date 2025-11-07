<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaItem extends Model
{
    protected $table = 'factura_items';

    protected $fillable = [
        'factura_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'total_linea',
    ];

    protected $casts = [
        'cantidad'        => 'integer',
        'precio_unitario' => 'decimal:2',
        'total_linea'     => 'decimal:2',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'factura_id');
    }

    /** Boot: calcular total_linea si no viene seteado */
    protected static function booted(): void
    {
        static::saving(function (FacturaItem $item) {
            if ($item->isDirty(['cantidad', 'precio_unitario']) && ($item->cantidad !== null) && ($item->precio_unitario !== null)) {
                $item->total_linea = $item->cantidad * $item->precio_unitario;
            }
        });
    }
}
