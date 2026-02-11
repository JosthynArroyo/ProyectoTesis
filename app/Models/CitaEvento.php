<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CitaEvento extends Model
{
    protected $table = 'cita_eventos';

    protected $fillable = [
        'cita_id',
        'user_id',
        'tipo',
        'de_estado',
        'a_estado',
        'de_fecha',
        'a_fecha',
        'de_hora',
        'a_hora',
    ];

    // Casts para evitar strings crudos y tener Carbon en fechas
    protected $casts = [
        'de_fecha'        => 'date',
        'a_fecha'         => 'date',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function getDeFechaAttribute()
    {
        return $this->getAttributeFromArray('de_fecha');
    }
    public function getAFechaAttribute()
    {
        return $this->getAttributeFromArray('a_fecha');
    }
    public function getDeHoraAttribute()
    {
        return $this->getAttributeFromArray('de_hora');
    }
    public function getAHoraAttribute()
    {
        return $this->getAttributeFromArray('a_hora');
    }

    // Relaciones mínimas
    public function cita()
    {
        return $this->belongsTo(\App\Models\Cita::class, 'cita_id');
    }
    public function paciente()
    {
        return $this->belongsTo(\App\Models\User::class, 'paciente_id');
    }
    public function doctor()
    {
        return $this->belongsTo(\App\Models\User::class, 'doctor_id');
    }
}
