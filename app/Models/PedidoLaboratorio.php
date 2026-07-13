<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoLaboratorio extends Model
{
    use HasFactory;

    protected $table = 'pedidos_laboratorio';

    protected $fillable = [
        'cita_id',
        'paciente_id',
        'doctor_id',
        'csv',
        'examenes',
        'pdf_path',
        'estado',
        'resultado_path',
        'resultado_resumen',
        'resultado_publicado_at',
        'resultado_enviado_at',
        'enviado_a',
        'enviado_en',
        'envio_estado',
        'envio_error',
        'envio_intentos',
    ];

    protected $casts = [
        'examenes' => 'array',
        'resultado_publicado_at' => 'datetime',
        'resultado_enviado_at' => 'datetime',
        'enviado_en' => 'datetime',
        'envio_intentos' => 'integer',
    ];

    public function cita(): BelongsTo
    {
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
}
