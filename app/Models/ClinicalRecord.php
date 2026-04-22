<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicalRecord extends Model
{
    use HasFactory;

    public const ALLERGIES_UNKNOWN = 'unknown';

    public const ALLERGIES_NONE = 'none';

    public const ALLERGIES_DOCUMENTED = 'documented';

    protected $fillable = [
        'patient_id',
        'allergies_status',
        'clinical_summary',
        'last_reviewed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'last_reviewed_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(ClinicalRecordAllergy::class)->orderBy('allergen');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ClinicalRecordHistory::class)->orderBy('category')->orderBy('title');
    }

    public function problems(): HasMany
    {
        return $this->hasMany(ClinicalRecordProblem::class)->orderByDesc('is_chronic')->orderBy('name');
    }

    public function medications(): HasMany
    {
        return $this->hasMany(ClinicalRecordMedication::class)->orderByDesc('started_at')->orderBy('name');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(ClinicalRecordAlert::class)->orderByDesc('is_active')->orderByDesc('updated_at');
    }

    public function soapNotes(): HasMany
    {
        return $this->hasMany(NotaSoap::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Receta::class);
    }

    public function medicalCertificates(): HasMany
    {
        return $this->hasMany(CertificadoMedico::class);
    }

    public function legacyLabOrders(): HasMany
    {
        return $this->hasMany(LaboratorioOrden::class);
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    public function medicalOrders(): HasMany
    {
        return $this->hasMany(MedicalOrder::class);
    }
}
