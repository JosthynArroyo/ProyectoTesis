<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalRecordHistory extends Model
{
    use HasFactory;

    public const CATEGORY_PERSONAL = 'personal';

    public const CATEGORY_FAMILY = 'family';

    public const CATEGORY_SURGERY = 'surgery';

    public const CATEGORY_HOSPITALIZATION = 'hospitalization';

    public const CATEGORY_IMMUNIZATION = 'immunization';

    protected $fillable = [
        'clinical_record_id',
        'category',
        'title',
        'relation_label',
        'description',
        'occurred_on',
        'notes',
    ];

    protected $casts = [
        'occurred_on' => 'date',
    ];

    public function clinicalRecord(): BelongsTo
    {
        return $this->belongsTo(ClinicalRecord::class);
    }
}
