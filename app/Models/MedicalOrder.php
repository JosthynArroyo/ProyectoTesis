<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicalOrder extends Model
{
    use HasFactory;

    public const STATUS_PENDIENTE = 'pendiente';

    public const STATUS_USADA = 'usada';

    public const STATUS_CANCELADA = 'cancelada';

    protected $table = 'medical_orders';

    protected $fillable = [
        'patient_id',
        'clinical_record_id',
        'doctor_id',
        'lab_test_id',
        'doctor_notes',
        'status',
    ];

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function clinicalRecord()
    {
        return $this->belongsTo(ClinicalRecord::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function labTest()
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }
}
