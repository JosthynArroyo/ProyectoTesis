<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaboratorioOrden extends Model
{
    use HasFactory;

    protected $table = 'laboratorio_ordenes';

    public const ESTADO_ORDEN_CREADA = 'orden_creada';

    public const ESTADO_CITA_PROGRAMADA = 'cita_programada';

    public const ESTADO_MUESTRA_TOMADA = 'muestra_tomada';

    public const ESTADO_RESULTADO_DISPONIBLE = 'resultado_disponible';

    protected $fillable = [
        'cita_id',
        'clinical_record_id',
        'solicitante_id',
        'origen',
        'prioridad',
        'tipo_examen',
        'indicaciones',
        'preparacion',
        'estado',
        'resultado_path',
        'resultado_resumen',
        'resultado_publicado_at',
        'resultado_enviado_at',
    ];

    protected $casts = [
        'resultado_publicado_at' => 'datetime',
        'resultado_enviado_at' => 'datetime',
    ];

    public function cita()
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function clinicalRecord()
    {
        return $this->belongsTo(ClinicalRecord::class);
    }

    public function solicitante()
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }
}
