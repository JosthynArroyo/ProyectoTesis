<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalRecordProblem extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_MONITORING = 'monitoring';

    protected $fillable = [
        'clinical_record_id',
        'source_nota_soap_id',
        'name',
        'cie10',
        'status',
        'is_chronic',
        'started_at',
        'resolved_at',
        'notes',
    ];

    protected $casts = [
        'is_chronic' => 'boolean',
        'started_at' => 'date',
        'resolved_at' => 'date',
    ];

    public function clinicalRecord(): BelongsTo
    {
        return $this->belongsTo(ClinicalRecord::class);
    }

    public function sourceNote(): BelongsTo
    {
        return $this->belongsTo(NotaSoap::class, 'source_nota_soap_id');
    }
}
