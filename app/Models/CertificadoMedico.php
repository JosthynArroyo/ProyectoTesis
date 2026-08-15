<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificadoMedico extends Model
{
    use HasFactory;

    protected $table = 'certificados_medicos';

    public const ESTADO_VIGENTE = 'vigente';
    public const ESTADO_REEMPLAZADO = 'reemplazado';

    protected $fillable = [
        'codigo',
        'estado_version',
        'version',
        'reemplaza_a_id',
        'reemplazado_por_id',
        'motivo_correccion',
        'corregido_por',
        'fecha_correccion',
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
        'pdf_disk',
        'enviado_a',
        'enviado_en',
        'envio_estado',
        'envio_error',
        'envio_intentos',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'fecha_correccion' => 'datetime',
        'reposo_desde' => 'date',
        'reposo_hasta' => 'date',
        'enviado_en' => 'datetime',
        'dias_reposo' => 'integer',
        'version' => 'integer',
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

    public function reemplazaA(): BelongsTo
    {
        return $this->belongsTo(CertificadoMedico::class, 'reemplaza_a_id');
    }

    public function reemplazadoPor(): BelongsTo
    {
        return $this->belongsTo(CertificadoMedico::class, 'reemplazado_por_id');
    }

    public function corregidoPorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corregido_por');
    }

    public function scopeVigente($query)
    {
        return $query->where(function ($q) {
            $q->where('estado_version', self::ESTADO_VIGENTE)
                ->orWhereNull('estado_version')
                ->orWhere('estado_version', '');
        });
    }

    public function isVigente(): bool
    {
        return $this->estado_version === self::ESTADO_VIGENTE || empty($this->estado_version);
    }

    public function isReemplazado(): bool
    {
        return $this->estado_version === self::ESTADO_REEMPLAZADO;
    }

    public function getHistorialCadena()
    {
        return static::query()
            ->where('cita_id', $this->cita_id)
            ->with(['corregidoPorUser', 'doctor'])
            ->orderBy('version', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function nombrePacienteReal(): string
    {
        if ($this->dependiente_id && $this->dependiente) {
            return (string) ($this->dependiente->nombre ?: 'N/D');
        }

        if ($this->cita && $this->cita->dependiente_id && $this->cita->dependiente) {
            return (string) ($this->cita->dependiente->nombre ?: 'N/D');
        }

        return (string) ($this->paciente?->name ?: 'N/D');
    }

    public function dniPacienteReal(): string
    {
        if ($this->dependiente_id && $this->dependiente) {
            return (string) ($this->dependiente->dni ?: 'N/D');
        }

        if ($this->cita && $this->cita->dependiente_id && $this->cita->dependiente) {
            return (string) ($this->cita->dependiente->dni ?: 'N/D');
        }

        return (string) ($this->paciente?->dni ?: 'N/D');
    }

    public function nombreDescarga(): string
    {
        return 'certificado_medico_'.$this->codigo.'.pdf';
    }
}
