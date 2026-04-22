<?php

namespace App\Http\Requests;

use App\Models\ClinicalRecord;
use App\Models\ClinicalRecordAlert;
use App\Models\ClinicalRecordAllergy;
use App\Models\ClinicalRecordMedication;
use App\Models\ClinicalRecordProblem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClinicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allergies_status' => ['required', Rule::in([
                ClinicalRecord::ALLERGIES_UNKNOWN,
                ClinicalRecord::ALLERGIES_NONE,
                ClinicalRecord::ALLERGIES_DOCUMENTED,
            ])],
            'clinical_summary' => ['nullable', 'string', 'max:5000'],

            'allergies' => ['nullable', 'array'],
            'allergies.*.allergen' => ['nullable', 'string', 'max:255'],
            'allergies.*.reaction' => ['nullable', 'string', 'max:2000'],
            'allergies.*.severity' => ['nullable', Rule::in([
                ClinicalRecordAllergy::SEVERITY_UNKNOWN,
                ClinicalRecordAllergy::SEVERITY_MILD,
                ClinicalRecordAllergy::SEVERITY_MODERATE,
                ClinicalRecordAllergy::SEVERITY_SEVERE,
            ])],
            'allergies.*.status' => ['nullable', Rule::in([
                ClinicalRecordAllergy::STATUS_ACTIVE,
                ClinicalRecordAllergy::STATUS_RESOLVED,
            ])],
            'allergies.*.notes' => ['nullable', 'string', 'max:2000'],
            'allergies.*.noted_at' => ['nullable', 'date'],

            'personal_histories' => ['nullable', 'array'],
            'personal_histories.*.title' => ['nullable', 'string', 'max:255'],
            'personal_histories.*.description' => ['nullable', 'string', 'max:2000'],
            'personal_histories.*.occurred_on' => ['nullable', 'date'],
            'personal_histories.*.notes' => ['nullable', 'string', 'max:2000'],
            'family_histories' => ['nullable', 'array'],
            'family_histories.*.title' => ['nullable', 'string', 'max:255'],
            'family_histories.*.relation_label' => ['nullable', 'string', 'max:255'],
            'family_histories.*.description' => ['nullable', 'string', 'max:2000'],
            'family_histories.*.occurred_on' => ['nullable', 'date'],
            'family_histories.*.notes' => ['nullable', 'string', 'max:2000'],
            'surgeries' => ['nullable', 'array'],
            'surgeries.*.title' => ['nullable', 'string', 'max:255'],
            'surgeries.*.description' => ['nullable', 'string', 'max:2000'],
            'surgeries.*.occurred_on' => ['nullable', 'date'],
            'surgeries.*.notes' => ['nullable', 'string', 'max:2000'],
            'hospitalizations' => ['nullable', 'array'],
            'hospitalizations.*.title' => ['nullable', 'string', 'max:255'],
            'hospitalizations.*.description' => ['nullable', 'string', 'max:2000'],
            'hospitalizations.*.occurred_on' => ['nullable', 'date'],
            'hospitalizations.*.notes' => ['nullable', 'string', 'max:2000'],
            'immunizations' => ['nullable', 'array'],
            'immunizations.*.title' => ['nullable', 'string', 'max:255'],
            'immunizations.*.description' => ['nullable', 'string', 'max:2000'],
            'immunizations.*.occurred_on' => ['nullable', 'date'],
            'immunizations.*.notes' => ['nullable', 'string', 'max:2000'],

            'problems' => ['nullable', 'array'],
            'problems.*.name' => ['nullable', 'string', 'max:255'],
            'problems.*.cie10' => ['nullable', 'string', 'max:20'],
            'problems.*.status' => ['nullable', Rule::in([
                ClinicalRecordProblem::STATUS_ACTIVE,
                ClinicalRecordProblem::STATUS_MONITORING,
                ClinicalRecordProblem::STATUS_RESOLVED,
            ])],
            'problems.*.is_chronic' => ['nullable', 'boolean'],
            'problems.*.started_at' => ['nullable', 'date'],
            'problems.*.resolved_at' => ['nullable', 'date'],
            'problems.*.notes' => ['nullable', 'string', 'max:2000'],

            'medications' => ['nullable', 'array'],
            'medications.*.name' => ['nullable', 'string', 'max:255'],
            'medications.*.presentation' => ['nullable', 'string', 'max:255'],
            'medications.*.dosage' => ['nullable', 'string', 'max:255'],
            'medications.*.frequency' => ['nullable', 'string', 'max:255'],
            'medications.*.route' => ['nullable', 'string', 'max:255'],
            'medications.*.instructions' => ['nullable', 'string', 'max:2000'],
            'medications.*.status' => ['nullable', Rule::in([
                ClinicalRecordMedication::STATUS_ACTIVE,
                ClinicalRecordMedication::STATUS_SUSPENDED,
                ClinicalRecordMedication::STATUS_COMPLETED,
            ])],
            'medications.*.started_at' => ['nullable', 'date'],
            'medications.*.ended_at' => ['nullable', 'date'],

            'alerts' => ['nullable', 'array'],
            'alerts.*.title' => ['nullable', 'string', 'max:255'],
            'alerts.*.description' => ['nullable', 'string', 'max:2000'],
            'alerts.*.severity' => ['nullable', Rule::in([
                ClinicalRecordAlert::SEVERITY_INFO,
                ClinicalRecordAlert::SEVERITY_WARNING,
                ClinicalRecordAlert::SEVERITY_HIGH,
            ])],
            'alerts.*.type' => ['nullable', Rule::in([
                ClinicalRecordAlert::TYPE_CLINICAL,
                ClinicalRecordAlert::TYPE_CONTEXT,
            ])],
            'alerts.*.is_active' => ['nullable', 'boolean'],
        ];
    }
}
