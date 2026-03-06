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
        'valor_anterior',
        'valor_nuevo',
        'comentario',
    ];

    protected $casts = [
        'de_fecha' => 'date',
        'a_fecha' => 'date',
        'valor_anterior' => 'string',
        'valor_nuevo' => 'string',
        'comentario' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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
