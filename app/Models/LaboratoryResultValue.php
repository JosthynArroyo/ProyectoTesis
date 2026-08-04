<?php

namespace App\Models;

use App\Enums\LabResultClassification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaboratoryResultValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'result_id',
        'order_item_id',
        'component_id',
        'value_numeric',
        'comparator',
        'value_code',
        'value_text',
        'titer_denominator',
        'unit_snapshot',
        'method_snapshot',
        'reference_snapshot',
        'reference_type_snapshot',
        'classification',
        'observation',
        'is_calculated',
        'extra_data',
        'subject_type',
        'subject_id',
        'patient_sex_snapshot',
        'patient_birth_date_snapshot',
        'patient_age_days_snapshot',
        'age_calculation_date_snapshot',
        'age_calculation_source',
        'reference_rule_id',
        'reference_was_overridden',
        'method_was_overridden',
        'unit_was_overridden',
        'override_reason',
        'reference_confirmed_for_result',
        'reference_confirmed_by',
        'reference_confirmed_at',
        'entered_by',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'value_numeric' => 'decimal:4',
        'classification' => LabResultClassification::class,
        'is_calculated' => 'boolean',
        'reference_was_overridden' => 'boolean',
        'method_was_overridden' => 'boolean',
        'unit_was_overridden' => 'boolean',
        'reference_confirmed_for_result' => 'boolean',
        'extra_data' => 'array',
        'patient_birth_date_snapshot' => 'date',
        'age_calculation_date_snapshot' => 'datetime',
        'reference_confirmed_at' => 'datetime',
        'validated_at' => 'datetime',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(LaboratoryComponent::class, 'component_id');
    }

    public function resultado(): BelongsTo
    {
        return $this->belongsTo(PedidoLaboratorioResultado::class, 'result_id');
    }

    public function referenceRule(): BelongsTo
    {
        return $this->belongsTo(LaboratoryReferenceRange::class, 'reference_rule_id');
    }

    public static function allowedComparators(): array
    {
        return ['=', '<', '>', '≤', '≥'];
    }

    public static function normalizeComparator(?string $comparator): string
    {
        $comparator = trim((string) $comparator);

        return match ($comparator) {
            '<', 'lt' => '<',
            '>', 'gt' => '>',
            '<=', '≤', 'lte' => '≤',
            '>=', '≥', 'gte' => '≥',
            '=', 'eq', '' => '=',
            default => '=',
        };
    }

    public static function formatNumericResult(float|int|string|null $value, ?string $comparator = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $valStr = trim((string) $value);

        if (preg_match('/^(=|<|>|<=|>=|≤|≥)\s*(.+)$/u', $valStr, $matches)) {
            if ($comparator === null || $comparator === '') {
                $comparator = $matches[1];
            }
            $valStr = trim($matches[2]);
        }

        $op = self::normalizeComparator($comparator);
        $cleanValue = is_numeric($valStr) ? (string) (float) $valStr : $valStr;

        return "{$op} {$cleanValue}";
    }
}
