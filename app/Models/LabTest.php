<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabTest extends Model
{
    use HasFactory;

    protected $table = 'lab_tests';

    protected $fillable = [
        'nombre',
        'tipo',
        'categoria',
        'requiere_orden',
        'es_rutina',
        'preparacion_default',
        'indicaciones_default',
        'ayuno_horas',
        'restricciones',
        'duracion_minutos',
        'activo',
    ];

    protected $casts = [
        'es_rutina' => 'boolean',
        'requiere_orden' => 'boolean',
        'duracion_minutos' => 'integer',
        'ayuno_horas' => 'integer',
        'activo' => 'boolean',
    ];

    public function orderItems()
    {
        return $this->hasMany(LabOrderItem::class, 'lab_test_id');
    }
}
