<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CitaEvento extends Model
{
    protected $table = 'cita_eventos';

    protected $fillable = [
        'cita_id',
        'paciente_id',
        'doctor_id',
        'tipo',
        'estado_anterior',
        'estado_nuevo',
        'fecha_anterior',
        'fecha_nueva',
        'hora_anterior',
        'hora_nueva',
        'activo_anterior',
        'activo_nuevo',
        'meta',
    ];

    // Casts para evitar strings crudos y tener Carbon en fechas
    protected $casts = [
        'fecha_anterior'  => 'date',
        'fecha_nueva'     => 'date',
        'activo_anterior' => 'boolean',
        'activo_nuevo'    => 'boolean',
        'meta'            => 'array',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    // Aliases si en tu controlador usas de_* y a_* (ajusta si ya cambiaste nombres)
    public function getDeFechaAttribute()
    {
        return $this->fecha_anterior;
    }
    public function getAFechaAttribute()
    {
        return $this->fecha_nueva;
    }
    public function getDeHoraAttribute()
    {
        return $this->hora_anterior;
    }
    public function getAHoraAttribute()
    {
        return $this->hora_nueva;
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
