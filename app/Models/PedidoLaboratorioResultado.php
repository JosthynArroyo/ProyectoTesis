<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoLaboratorioResultado extends Model
{
    use HasFactory;

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_PUBLICADO = 'publicado';

    public const ESTADO_REEMPLAZADO = 'reemplazado';

    public const ESTADO_ANULADO = 'anulado';

    protected $table = 'pedido_laboratorio_resultados';

    protected $fillable = [
        'pedido_laboratorio_id',
        'version',
        'estado',
        'resultado_items',
        'observaciones_generales',
        'laboratorio_id',
        'csv',
        'pdf_path',
        'publicado_at',
        'enviado_a',
        'enviado_en',
        'envio_estado',
        'envio_error',
        'envio_intentos',
        'reemplaza_id',
    ];

    protected $casts = [
        'resultado_items' => 'array',
        'publicado_at' => 'datetime',
        'enviado_en' => 'datetime',
        'envio_intentos' => 'integer',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoLaboratorio::class, 'pedido_laboratorio_id');
    }

    public function laboratorio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'laboratorio_id');
    }

    public function reemplaza(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reemplaza_id');
    }
}
