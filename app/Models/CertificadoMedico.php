<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificadoMedico extends Model
{
    use HasFactory;

    protected $table = 'certificados_medicos';

    protected $fillable = [
        'codigo',
        'cita_id',
        'paciente_id',
        'dependiente_id',
        'doctor_id',
        'clinical_record_id',
        'fecha_emision',
        'texto_constancia',
        'dias_reposo',
        'reposo_desde',
        'reposo_hasta',
        'observaciones',
        'csv',
        'pdf_path',
        'enviado_a',
        'enviado_en',
        'envio_estado',
        'envio_error',
        'envio_intentos',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'reposo_desde' => 'date',
        'reposo_hasta' => 'date',
        'enviado_en' => 'datetime',
        'dias_reposo' => 'integer',
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

    public function dependiente(): BelongsTo
    {
        return $this->belongsTo(Dependiente::class, 'dependiente_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function clinicalRecord(): BelongsTo
    {
        return $this->belongsTo(ClinicalRecord::class);
    }

    public function nombreDescarga(): string
    {
        return 'certificado_medico_'.$this->codigo.'.pdf';
    }
}
