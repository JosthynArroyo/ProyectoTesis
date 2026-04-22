<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabOrder extends Model
{
    use HasFactory;

    public const SOURCE_MEDICAL_ORDER = 'MEDICAL_ORDER';

    public const SOURCE_ROUTINE = 'ROUTINE';

    public const STATUS_PENDIENTE_TOMA = 'pendiente_toma';

    public const STATUS_MUESTRA_TOMADA = 'muestra_tomada';

    public const STATUS_EN_ANALISIS = 'en_analisis';

    public const STATUS_RESULTADO_LISTO = 'resultado_listo';

    public const STATUS_CANCELADO = 'cancelado';

    public const STATUS_NO_SE_PRESENTO = 'no_se_presento';

    protected $table = 'lab_orders';

    protected $fillable = [
        'patient_id',
        'clinical_record_id',
        'source',
        'doctor_id',
        'medical_order_id',
        'laboratorio_id',
        'priority',
        'status',
        'scheduled_at',
        'doctor_notes',
        'resultado_path',
        'resultado_resumen',
        'resultado_publicado_at',
        'resultado_enviado_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'resultado_publicado_at' => 'datetime',
        'resultado_enviado_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function clinicalRecord(): BelongsTo
    {
        return $this->belongsTo(ClinicalRecord::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function medicalOrder(): BelongsTo
    {
        return $this->belongsTo(MedicalOrder::class, 'medical_order_id');
    }

    public function laboratorio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'laboratorio_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabOrderItem::class, 'lab_order_id');
    }

    public function getTipoExamenAttribute(): ?string
    {
        $item = $this->relationLoaded('items')
            ? $this->items->first()
            : $this->items()->with('test:id,nombre')->first();

        return $item?->test?->nombre
            ?? $this->medicalOrder?->labTest?->nombre;
    }

    public function getPreparacionAttribute(): ?string
    {
        $item = $this->relationLoaded('items')
            ? $this->items->first()
            : $this->items()->first();

        return $item?->preparacion_snapshot;
    }

    public function getIndicacionesAttribute(): ?string
    {
        $item = $this->relationLoaded('items')
            ? $this->items->first()
            : $this->items()->first();

        return $item?->indicaciones_snapshot;
    }

    public function getPrioridadAttribute(): ?string
    {
        return $this->priority;
    }

    public function hasResultadoDisponible(): bool
    {
        return $this->status === self::STATUS_RESULTADO_LISTO && filled($this->resultado_path);
    }
}
