<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CitaRecordatorio extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ENVIADO = 'enviado';

    public const ESTADO_OMITIDO = 'omitido';

    protected $table = 'cita_recordatorios';

    protected $fillable = [
        'cita_id',
        'estado',
        'cita_inicio_at',
        'recordar_en',
        'enviado_at',
        'omitido_at',
        'gestionado_por',
    ];

    protected $casts = [
        'cita_inicio_at' => 'datetime',
        'recordar_en' => 'datetime',
        'enviado_at' => 'datetime',
        'omitido_at' => 'datetime',
    ];

    public function cita()
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function gestionadoPor()
    {
        return $this->belongsTo(User::class, 'gestionado_por');
    }
}
