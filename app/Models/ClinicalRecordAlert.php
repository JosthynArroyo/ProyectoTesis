<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalRecordAlert extends Model
{
    use HasFactory;

    public const TYPE_CLINICAL = 'clinical';

    public const TYPE_CONTEXT = 'context';

    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_HIGH = 'high';

    protected $fillable = [
        'clinical_record_id',
        'created_by',
        'type',
        'title',
        'description',
        'severity',
        'is_active',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function clinicalRecord(): BelongsTo
    {
        return $this->belongsTo(ClinicalRecord::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
