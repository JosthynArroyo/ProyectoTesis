<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoLaboratorioResultadoAudit extends Model
{
    use HasFactory;

    protected $table = 'pedido_laboratorio_resultado_audits';

    protected $fillable = [
        'pedido_laboratorio_id',
        'pedido_laboratorio_resultado_id',
        'user_id',
        'accion',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoLaboratorio::class, 'pedido_laboratorio_id');
    }

    public function resultado(): BelongsTo
    {
        return $this->belongsTo(PedidoLaboratorioResultado::class, 'pedido_laboratorio_resultado_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
