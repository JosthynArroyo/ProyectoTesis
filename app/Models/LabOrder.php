<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'source',
        'doctor_id',
        'medical_order_id',
        'priority',
        'status',
        'scheduled_at',
        'doctor_notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function medicalOrder()
    {
        return $this->belongsTo(MedicalOrder::class, 'medical_order_id');
    }

    public function items()
    {
        return $this->hasMany(LabOrderItem::class, 'lab_order_id');
    }
}
