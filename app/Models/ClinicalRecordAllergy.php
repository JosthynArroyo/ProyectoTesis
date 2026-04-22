<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalRecordAllergy extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RESOLVED = 'resolved';

    public const SEVERITY_UNKNOWN = 'unknown';

    public const SEVERITY_MILD = 'mild';

    public const SEVERITY_MODERATE = 'moderate';

    public const SEVERITY_SEVERE = 'severe';

    protected $fillable = [
        'clinical_record_id',
        'allergen',
        'reaction',
        'severity',
        'status',
        'noted_at',
        'notes',
    ];

    protected $casts = [
        'noted_at' => 'date',
    ];

    public function clinicalRecord(): BelongsTo
    {
        return $this->belongsTo(ClinicalRecord::class);
    }
}
