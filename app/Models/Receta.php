<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receta extends Model
{
    use HasFactory;

    protected $fillable = [
        'cita_id',
        'diagnostico',
        'medicamentos',
        'indicaciones',
        'pdf_path',
        'enviado_en',
    ];

    protected $casts = [
        'enviado_en' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function cita()
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    /**
     * Atributo calculado: se puede editar durante 1 hora desde la última modificación.
     */
    public function getCanEditAttribute(): bool
    {
        $base = $this->updated_at ?? $this->created_at ?? now();
        return now('America/Guayaquil')->diffInMinutes($base) < 60;
    }
}
