<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cita extends Model
{
    use HasFactory;

    protected $table = 'citas_medicas';

    protected $fillable = [
        'paciente_id',
        'doctor_id',
        'especialidad_id',
        'fecha',
        'hora',
        'estado',
        'activo',
    ];

    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_CONFIRMADA = 'confirmada';
    public const ESTADO_CANCELADA  = 'cancelada';
    public const ESTADO_REALIZADA  = 'realizada';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_CONFIRMADA,
        self::ESTADO_CANCELADA,
        self::ESTADO_REALIZADA,
    ];

    protected $casts = [
        'fecha'  => 'date',
        'hora'   => 'string',
        'activo' => 'boolean',
    ];

    public function paciente()
    {
        return $this->belongsTo(User::class, 'paciente_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class);
    }

    /** Relación con factura (una factura por cita) */
    public function factura()
    {
        return $this->hasOne(Factura::class, 'cita_id');
    }

    /** Relación con receta (una receta por cita) */
    public function receta()
    {
        return $this->hasOne(Receta::class, 'cita_id');
    }
}
