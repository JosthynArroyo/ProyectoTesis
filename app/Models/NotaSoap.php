<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotaSoap extends Model
{
    use HasFactory;

    protected $table = 'notas_soap';

    public const ESTADO_BORRADOR = 'draft';
    public const ESTADO_FIRMADA = 'signed';

    protected $fillable = [
        'cita_id',
        'estado',
        'signed_at',
        'signed_by',
        'subjetivo_motivo',
        'subjetivo_hpi',
        'subjetivo_ros',
        'subjetivo_notas',
        'signos_vitales',
        'examen_fisico',
        'notas_objetivas',
        'assessment',
        'plan_general',
        'plan_seguimiento',
        'plan_notas',
    ];

    protected $casts = [
        'subjetivo_ros' => 'array',
        'signos_vitales' => 'array',
        'signed_at' => 'datetime',
    ];

    public function cita()
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function diagnosticos()
    {
        return $this->hasMany(NotaSoapDiagnostico::class, 'nota_soap_id');
    }

    public function enmiendas()
    {
        return $this->hasMany(NotaSoapEnmienda::class, 'nota_soap_id');
    }

    public function firmadaPor()
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function isSigned(): bool
    {
        return $this->estado === self::ESTADO_FIRMADA;
    }
}
