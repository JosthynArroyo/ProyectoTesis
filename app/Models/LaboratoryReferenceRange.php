<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaboratoryReferenceRange extends Model
{
    use HasFactory;

    protected $fillable = [
        'component_id',
        'reference_type',
        'method',
        'sex',
        'minimum_age_days',
        'maximum_age_days',
        'pregnancy_stage',
        'lower_limit',
        'upper_limit',
        'critical_lower_limit',
        'critical_upper_limit',
        'reference_text',
        'validation_status',
        'source_note',
        'validated_at',
        'validated_by',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'lower_limit' => 'decimal:4',
        'upper_limit' => 'decimal:4',
        'critical_lower_limit' => 'decimal:4',
        'critical_upper_limit' => 'decimal:4',
        'validated_at' => 'datetime',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(LaboratoryComponent::class, 'component_id');
    }
}
