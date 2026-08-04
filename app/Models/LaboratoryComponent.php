<?php

namespace App\Models;

use App\Enums\LabResultType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaboratoryComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'result_type',
        'default_unit',
        'decimal_places',
        'option_set_id',
        'default_method',
        'authorized_methods',
        'formula',
        'validation_status',
        'source_note',
        'validated_at',
        'validated_by',
        'active',
    ];

    protected $casts = [
        'result_type' => LabResultType::class,
        'authorized_methods' => 'array',
        'active' => 'boolean',
        'validated_at' => 'datetime',
    ];

    public function optionSet(): BelongsTo
    {
        return $this->belongsTo(LaboratoryResultOptionSet::class, 'option_set_id');
    }

    public function referenceRanges(): HasMany
    {
        return $this->hasMany(LaboratoryReferenceRange::class, 'component_id');
    }

    public function getMethodsListAttribute(): array
    {
        if (!empty($this->authorized_methods) && is_array($this->authorized_methods)) {
            return array_values($this->authorized_methods);
        }

        if ($this->default_method) {
            return [$this->default_method];
        }

        return ['Método institucional por determinar'];
    }
}
