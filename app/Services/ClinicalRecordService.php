<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\CertificadoMedico;
use App\Models\ClinicalRecord;
use App\Models\ClinicalRecordAlert;
use App\Models\ClinicalRecordAllergy;
use App\Models\ClinicalRecordHistory;
use App\Models\ClinicalRecordMedication;
use App\Models\ClinicalRecordProblem;
use App\Models\LabOrder;
use App\Models\LaboratorioOrden;
use App\Models\NotaSoap;
use App\Models\NotaSoapDiagnostico;
use App\Models\PatientFlag;
use App\Models\Receta;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ClinicalRecordService
{
    public function ensureForPatient(User|int $patient, ?int $actorId = null, ?int $dependienteId = null): ClinicalRecord
    {
        $patientId = $patient instanceof User ? $patient->id : (int) $patient;

        if ($dependienteId) {
            $record = ClinicalRecord::firstOrCreate(
                ['dependiente_id' => $dependienteId],
                [
                    'patient_id' => null,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]
            );
        } else {
            $record = ClinicalRecord::firstOrCreate(
                ['patient_id' => $patientId, 'dependiente_id' => null],
                [
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]
            );
        }

        if ($actorId && (! $record->created_by || ! $record->updated_by)) {
            $record->forceFill([
                'created_by' => $record->created_by ?: $actorId,
                'updated_by' => $actorId,
            ])->save();
        }

        return $record;
    }

    public function canView(User $viewer, User $patient): bool
    {
        if ($viewer->id === $patient->id) {
            return true;
        }

        if ($viewer->hasRole('superadmin') || $viewer->hasRole('administrador')) {
            return true;
        }

        if ($viewer->hasRole('doctor')) {
            return Cita::query()
                ->where('paciente_id', $patient->id)
                ->where('doctor_id', $viewer->id)
                ->exists();
        }

        return false;
    }

    public function buildSoapContext(Cita $cita, ?NotaSoap $nota = null): array
    {
        $record = $this->ensureForPatient($cita->paciente_id, null, $cita->dependiente_id);
        $this->hydrateLegacyData($record);

        $record->load(['allergies', 'histories', 'problems', 'medications', 'alerts']);

        return [
            'record' => $record,
            'alergias' => $this->mapAllergiesToSoap($record),
            'antecedentes' => $this->mapAntecedentsToSoap($record),
            'problemas_activos' => $record->problems
                ->whereIn('status', [ClinicalRecordProblem::STATUS_ACTIVE, ClinicalRecordProblem::STATUS_MONITORING])
                ->values(),
            'medicacion_actual' => $record->medications
                ->where('status', ClinicalRecordMedication::STATUS_ACTIVE)
                ->values(),
            'alertas' => $record->alerts
                ->where('is_active', true)
                ->values(),
        ];
    }

    public function getPreviousVitalSigns(Cita $cita, ?NotaSoap $nota = null): ?array
    {
        $record = $this->ensureForPatient($cita->paciente_id, null, $cita->dependiente_id);

        $notaPrevia = NotaSoap::query()
            ->select('signos_vitales', 'id', 'cita_id')
            ->where('clinical_record_id', $record->id)
            ->where('estado', NotaSoap::ESTADO_FIRMADA)
            ->whereNotNull('signos_vitales')
            ->when($nota?->id, fn ($query) => $query->where('id', '!=', $nota->id))
            ->with('cita:id,fecha,hora')
            ->get()
            ->sortByDesc(function (NotaSoap $item) {
                return $item->cita?->inicioProgramado(config('app.timezone', 'America/Guayaquil'))->timestamp ?? 0;
            })
            ->first();

        $signos = is_array($notaPrevia?->signos_vitales) ? $notaPrevia->signos_vitales : null;

        return ! empty($signos) ? $signos : null;
    }

    public function syncFromSoap(Cita $cita, NotaSoap $nota, array $payload, ?int $actorId = null): ClinicalRecord
    {
        $record = $this->ensureForPatient($cita->paciente_id, $actorId, $cita->dependiente_id);

        $this->syncSoapMasterData($record, $nota, $payload);
        $this->attachExistingArtifacts($cita, $record);
        $this->updateRecordMeta($record, $payload['clinical_summary'] ?? null, $actorId);

        return $record->fresh(['allergies', 'histories', 'problems', 'medications', 'alerts']);
    }

    public function syncMasterData(ClinicalRecord $record, array $payload, ?int $actorId = null): ClinicalRecord
    {
        $record->fill([
            'allergies_status' => $payload['allergies_status'] ?? $record->allergies_status,
            'clinical_summary' => $this->normalizeText($payload['clinical_summary'] ?? $record->clinical_summary),
            'last_reviewed_at' => now(),
            'updated_by' => $actorId ?? $record->updated_by,
        ]);

        if (! $record->created_by && $actorId) {
            $record->created_by = $actorId;
        }

        $record->save();

        if (array_key_exists('allergies', $payload)) {
            $this->replaceAllergies($record, $payload['allergies'] ?? []);
        }
        if (array_key_exists('personal_histories', $payload)) {
            $this->replaceHistories($record, ClinicalRecordHistory::CATEGORY_PERSONAL, $payload['personal_histories'] ?? []);
        }
        if (array_key_exists('family_histories', $payload)) {
            $this->replaceHistories($record, ClinicalRecordHistory::CATEGORY_FAMILY, $payload['family_histories'] ?? []);
        }
        if (array_key_exists('surgeries', $payload)) {
            $this->replaceHistories($record, ClinicalRecordHistory::CATEGORY_SURGERY, $payload['surgeries'] ?? []);
        }
        if (array_key_exists('hospitalizations', $payload)) {
            $this->replaceHistories($record, ClinicalRecordHistory::CATEGORY_HOSPITALIZATION, $payload['hospitalizations'] ?? []);
        }
        if (array_key_exists('immunizations', $payload)) {
            $this->replaceHistories($record, ClinicalRecordHistory::CATEGORY_IMMUNIZATION, $payload['immunizations'] ?? []);
        }
        if (array_key_exists('problems', $payload)) {
            $this->replaceProblems($record, $payload['problems'] ?? []);
        }
        if (array_key_exists('medications', $payload)) {
            $this->replaceMedications($record, $payload['medications'] ?? [], $actorId);
        }
        if (array_key_exists('alerts', $payload)) {
            $this->replaceAlerts($record, $payload['alerts'] ?? [], $actorId);
        }

        return $record->fresh(['allergies', 'histories', 'problems', 'medications', 'alerts']);
    }

    public function buildRecordViewData(ClinicalRecord $record): array
    {
        $this->hydrateLegacyData($record);

        $record->load([
            'patient.roles',
            'patient.patientFlag',
            'dependiente.responsable',
            'allergies',
            'histories',
            'problems',
            'medications',
            'alerts',
        ]);

        $subject = $this->subjectData($record);

        $notes = NotaSoap::query()
            ->with(['cita.doctor', 'cita.especialidad', 'followUpCita.doctor', 'followUpCita.especialidad', 'diagnosticos', 'enmiendas.autor'])
            ->where('clinical_record_id', $record->id)
            ->where('estado', NotaSoap::ESTADO_FIRMADA)
            ->get()
            ->sortByDesc(fn (NotaSoap $nota) => $nota->cita?->inicioProgramado(config('app.timezone', 'America/Guayaquil'))->timestamp ?? 0)
            ->values();

        $appointments = Cita::query()
            ->with(['doctor', 'especialidad', 'notaSoap'])
            ->when(
                $record->dependiente_id,
                fn ($query) => $query->where('dependiente_id', $record->dependiente_id),
                fn ($query) => $query
                    ->where('paciente_id', $record->patient_id)
                    ->whereNull('dependiente_id')
            )
            ->orderByDesc('fecha')
            ->orderByDesc('hora')
            ->get();

        $prescriptions = Receta::query()
            ->with(['cita.doctor', 'cita.especialidad'])
            ->where('clinical_record_id', $record->id)
            ->orderByDesc('created_at')
            ->get();

        $certificates = CertificadoMedico::query()
            ->vigente()
            ->with(['doctor', 'cita.doctor', 'cita.especialidad'])
            ->where('clinical_record_id', $record->id)
            ->orderByDesc('fecha_emision')
            ->get();

        $legacyLabs = LaboratorioOrden::query()
            ->with(['cita.doctor', 'cita.especialidad'])
            ->where('clinical_record_id', $record->id)
            ->orderByDesc('created_at')
            ->get();

        $selfServiceLabs = LabOrder::query()
            ->with(['doctor', 'laboratorio', 'items.test'])
            ->where('clinical_record_id', $record->id)
            ->orderByDesc('created_at')
            ->get();

        $futureAppointments = $appointments
            ->whereIn('estado', [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA])
            ->filter(fn (Cita $cita) => $cita->inicioProgramado(config('app.timezone', 'America/Guayaquil'))->greaterThanOrEqualTo(now(config('app.timezone', 'America/Guayaquil'))))
            ->sortBy(fn (Cita $cita) => $cita->inicioProgramado(config('app.timezone', 'America/Guayaquil'))->timestamp)
            ->values();

        $scheduledFollowUps = $notes
            ->pluck('followUpCita')
            ->filter(function ($cita) {
                return $cita instanceof Cita
                    && in_array($cita->estado, [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA], true)
                    && $cita->inicioProgramado(config('app.timezone', 'America/Guayaquil'))->greaterThanOrEqualTo(now(config('app.timezone', 'America/Guayaquil')));
            })
            ->sortBy(fn (Cita $cita) => $cita->inicioProgramado(config('app.timezone', 'America/Guayaquil'))->timestamp)
            ->values();

        $completedAppointments = $appointments
            ->where('estado', Cita::ESTADO_REALIZADA)
            ->sortByDesc(fn (Cita $cita) => $cita->inicioProgramado(config('app.timezone', 'America/Guayaquil'))->timestamp)
            ->values();

        $followUps = $notes
            ->filter(fn (NotaSoap $nota) => $nota->follow_up_date !== null)
            ->sortBy(fn (NotaSoap $nota) => optional($nota->follow_up_date)->timestamp ?? PHP_INT_MAX)
            ->values();

        return [
            'record' => $record,
            'subject' => $subject,
            'historiesByCategory' => $record->histories->groupBy('category'),
            'activeProblems' => $record->problems
                ->whereIn('status', [ClinicalRecordProblem::STATUS_ACTIVE, ClinicalRecordProblem::STATUS_MONITORING])
                ->values(),
            'resolvedProblems' => $record->problems
                ->where('status', ClinicalRecordProblem::STATUS_RESOLVED)
                ->values(),
            'currentMedications' => $record->medications
                ->where('status', ClinicalRecordMedication::STATUS_ACTIVE)
                ->values(),
            'inactiveMedications' => $record->medications
                ->where('status', '!=', ClinicalRecordMedication::STATUS_ACTIVE)
                ->values(),
            'activeAlerts' => $record->alerts
                ->where('is_active', true)
                ->values(),
            'inactiveAlerts' => $record->alerts
                ->where('is_active', false)
                ->values(),
            'notes' => $notes,
            'appointments' => $appointments,
            'futureAppointments' => $futureAppointments,
            'completedAppointments' => $completedAppointments,
            'nextAppointment' => $futureAppointments->first() ?? $scheduledFollowUps->first(),
            'scheduledFollowUps' => $scheduledFollowUps,
            'nextScheduledFollowUp' => $scheduledFollowUps->first(),
            'prescriptions' => $prescriptions,
            'certificates' => $certificates,
            'laboratoryEntries' => $this->mergeLaboratories($legacyLabs, $selfServiceLabs),
            'chronology' => $this->buildChronology($notes, $prescriptions, $certificates, $legacyLabs, $selfServiceLabs),
            'followUps' => $followUps,
            'nextFollowUp' => $followUps
                ->first(fn (NotaSoap $nota) => optional($nota->follow_up_date)?->greaterThanOrEqualTo(today(config('app.timezone', 'America/Guayaquil')))),
            'latestNote' => $notes->first(),
            'vitalSnapshot' => $this->buildVitalSnapshot($notes),
            'recentDiagnoses' => $this->buildRecentDiagnoses($notes),
        ];
    }

    public function subjectData(ClinicalRecord $record): object
    {
        $record->loadMissing(['patient', 'dependiente.responsable']);

        if ($record->dependiente_id && $record->dependiente) {
            return (object) [
                'name' => $record->dependiente->nombre,
                'dni' => $record->dependiente->dni,
                'sexo' => $record->dependiente->sexo,
                'telefono' => $record->dependiente->telefono_emergencia,
                'email' => null,
                'fecha_nacimiento' => $record->dependiente->fecha_nacimiento,
                'representante' => $record->dependiente->responsable?->name,
                'representante_dni' => $record->dependiente->responsable?->dni,
            ];
        }

        $patient = $record->patient;

        return (object) [
            'name' => $patient?->name ?? 'N/D',
            'dni' => $patient?->dni ?? null,
            'sexo' => $patient?->sexo ?? null,
            'telefono' => $patient?->telefono ?? null,
            'email' => $patient?->email ?? null,
            'fecha_nacimiento' => $patient?->fecha_nacimiento ?? null,
            'representante' => null,
            'representante_dni' => null,
        ];
    }

    public function hydrateLegacyData(ClinicalRecord $record): void
    {
        $record->loadMissing([
            'patient.patientFlag',
            'allergies',
            'histories',
            'problems',
            'alerts',
        ]);

        if ($record->allergies->isNotEmpty() || $record->histories->isNotEmpty() || $record->problems->isNotEmpty() || $record->alerts->isNotEmpty()) {
            return;
        }

        $latestNote = NotaSoap::query()
            ->with(['diagnosticos', 'cita'])
            ->where('clinical_record_id', $record->id)
            ->where('estado', NotaSoap::ESTADO_FIRMADA)
            ->whereNotNull('subjetivo_ros')
            ->orderByDesc('signed_at')
            ->first();

        if ($latestNote) {
            $this->syncSoapMasterData($record, $latestNote, [
                'alergias_no_conocidas' => (bool) data_get($latestNote->subjetivo_ros, 'alergias.no_conocidas'),
                'alergias_detalle' => data_get($latestNote->subjetivo_ros, 'alergias.detalle'),
                'antecedentes_cronicas' => data_get($latestNote->subjetivo_ros, 'antecedentes.cronicas'),
                'antecedentes_cirugias' => data_get($latestNote->subjetivo_ros, 'antecedentes.cirugias'),
                'antecedentes_hospitalizaciones' => data_get($latestNote->subjetivo_ros, 'antecedentes.hospitalizaciones'),
                'antecedentes_diabetes' => data_get($latestNote->subjetivo_ros, 'antecedentes.diabetes'),
                'antecedentes_hipertension' => data_get($latestNote->subjetivo_ros, 'antecedentes.hipertension'),
                'antecedentes_otros' => data_get($latestNote->subjetivo_ros, 'antecedentes.otros'),
                'clinical_summary' => $latestNote->assessment,
            ]);
        }

        $this->importDiagnosesAsProblems($record, NotaSoapDiagnostico::query()
            ->whereHas('nota', function ($query) use ($record) {
                $query->where('clinical_record_id', $record->id)
                    ->where('estado', NotaSoap::ESTADO_FIRMADA);
            })
            ->get());

        $this->syncAlertsFromPatientFlags($record);
    }

    public function attachExistingArtifacts(Cita $cita, ClinicalRecord $record): void
    {
        $cita->loadMissing(['notaSoap', 'receta', 'certificadoMedico', 'laboratorioOrden']);

        if ($cita->notaSoap && (int) $cita->notaSoap->clinical_record_id !== (int) $record->id) {
            $cita->notaSoap->forceFill(['clinical_record_id' => $record->id])->save();
        }
        if ($cita->receta && (int) $cita->receta->clinical_record_id !== (int) $record->id) {
            $cita->receta->forceFill(['clinical_record_id' => $record->id])->save();
        }
        if ($cita->certificadoMedico && (int) $cita->certificadoMedico->clinical_record_id !== (int) $record->id) {
            $cita->certificadoMedico->forceFill(['clinical_record_id' => $record->id])->save();
        }
        if ($cita->laboratorioOrden && (int) $cita->laboratorioOrden->clinical_record_id !== (int) $record->id) {
            $cita->laboratorioOrden->forceFill(['clinical_record_id' => $record->id])->save();
        }
    }

    protected function syncSoapMasterData(ClinicalRecord $record, NotaSoap $nota, array $payload): void
    {
        $allergyLines = $this->normalizeLines($payload['alergias_detalle'] ?? '');
        $allergiesStatus = ClinicalRecord::ALLERGIES_UNKNOWN;

        if (($payload['alergias_no_conocidas'] ?? false) && empty($allergyLines)) {
            $allergiesStatus = ClinicalRecord::ALLERGIES_NONE;
        } elseif (! empty($allergyLines)) {
            $allergiesStatus = ClinicalRecord::ALLERGIES_DOCUMENTED;
        }

        $record->forceFill([
            'allergies_status' => $allergiesStatus,
            'last_reviewed_at' => now(),
        ])->save();

        if ($allergiesStatus !== ClinicalRecord::ALLERGIES_UNKNOWN || $record->allergies()->exists()) {
            $this->replaceAllergies($record, collect($allergyLines)->map(fn ($line) => ['allergen' => $line])->all());
        }

        $personal = $this->normalizeLines($payload['antecedentes_otros'] ?? '');
        $surgeries = $this->normalizeLines($payload['antecedentes_cirugias'] ?? '');
        $hospitalizations = $this->normalizeLines($payload['antecedentes_hospitalizaciones'] ?? '');
        $chronicFromText = $this->normalizeLines($payload['antecedentes_cronicas'] ?? '');
        $diabetes = $this->normalizeText($payload['antecedentes_diabetes'] ?? null);
        $hypertension = $this->normalizeText($payload['antecedentes_hipertension'] ?? null);
        $familiares = $this->normalizeLines($payload['antecedentes_familiares'] ?? '');
        $inmunizaciones = $this->normalizeLines($payload['antecedentes_inmunizaciones'] ?? '');

        $this->replaceHistories($record, ClinicalRecordHistory::CATEGORY_PERSONAL, collect($personal)
            ->map(fn ($line) => ['title' => $line])
            ->all());
        $this->replaceHistories($record, ClinicalRecordHistory::CATEGORY_SURGERY, collect($surgeries)
            ->map(fn ($line) => ['title' => $line])
            ->all());
        $this->replaceHistories($record, ClinicalRecordHistory::CATEGORY_HOSPITALIZATION, collect($hospitalizations)
            ->map(fn ($line) => ['title' => $line])
            ->all());
        $this->replaceHistories($record, ClinicalRecordHistory::CATEGORY_FAMILY, collect($familiares)
            ->map(fn ($line) => ['title' => $line])
            ->all());
        $this->replaceHistories($record, ClinicalRecordHistory::CATEGORY_IMMUNIZATION, collect($inmunizaciones)
            ->map(fn ($line) => ['title' => $line])
            ->all());

        $problemRows = collect($chronicFromText)
            ->map(fn ($line) => [
                'name' => $line,
                'status' => ClinicalRecordProblem::STATUS_ACTIVE,
                'is_chronic' => true,
                'started_at' => optional($nota->cita)->fecha?->format('Y-m-d'),
                'notes' => 'Antecedente documentado durante nota médica.',
            ]);

        if ($diabetes && ! in_array(mb_strtolower($diabetes), ['no', 'no aplica', 'no documentado'], true)) {
            $problemRows->push([
                'name' => Str::startsWith(mb_strtolower($diabetes), 'tipo') ? 'Diabetes '.$diabetes : 'Diabetes',
                'status' => ClinicalRecordProblem::STATUS_ACTIVE,
                'is_chronic' => true,
                'started_at' => optional($nota->cita)->fecha?->format('Y-m-d'),
                'notes' => 'Antecedente documentado durante nota médica.',
            ]);
        }

        if ($hypertension && ! in_array(mb_strtolower($hypertension), ['no', 'no documentado'], true)) {
            $problemRows->push([
                'name' => 'Hipertensión arterial',
                'status' => ClinicalRecordProblem::STATUS_ACTIVE,
                'is_chronic' => true,
                'started_at' => optional($nota->cita)->fecha?->format('Y-m-d'),
                'notes' => 'Antecedente documentado durante nota médica.',
            ]);
        }

        $problemRows = $problemRows
            ->concat($nota->diagnosticos->map(function (NotaSoapDiagnostico $diag) use ($nota) {
                return [
                    'name' => $diag->texto,
                    'cie10' => $diag->cie10,
                    'status' => ClinicalRecordProblem::STATUS_ACTIVE,
                    'is_chronic' => false,
                    'started_at' => optional($nota->cita)->fecha?->format('Y-m-d'),
                    'notes' => 'Diagnóstico clínico vinculado a nota médica.',
                    'source_nota_soap_id' => $nota->id,
                ];
            }))
            ->filter(fn ($row) => filled($row['name'] ?? null))
            ->values();

        $existingProblems = $record->problems()
            ->get()
            ->map(fn (ClinicalRecordProblem $problem) => $problem->only([
                'source_nota_soap_id',
                'name',
                'cie10',
                'status',
                'is_chronic',
                'started_at',
                'resolved_at',
                'notes',
            ]));

        $this->replaceProblems($record, $existingProblems->concat($problemRows)->all());
        $this->syncAlertsFromPatientFlags($record);
    }

    protected function updateRecordMeta(ClinicalRecord $record, ?string $summary, ?int $actorId): void
    {
        $normalizedSummary = $this->normalizeText($summary);

        $record->forceFill([
            'clinical_summary' => $normalizedSummary ?: $record->clinical_summary,
            'last_reviewed_at' => now(),
            'updated_by' => $actorId ?: $record->updated_by,
            'created_by' => $record->created_by ?: $actorId,
        ])->save();
    }

    protected function importDiagnosesAsProblems(ClinicalRecord $record, EloquentCollection $diagnoses): void
    {
        foreach ($diagnoses as $diag) {
            $name = $this->normalizeText($diag->texto);
            if (! $name) {
                continue;
            }

            $existing = $record->problems()
                ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
                ->first();

            if ($existing) {
                continue;
            }

            $record->problems()->create([
                'source_nota_soap_id' => $diag->nota_soap_id,
                'name' => $name,
                'cie10' => $this->normalizeText($diag->cie10),
                'status' => ClinicalRecordProblem::STATUS_ACTIVE,
                'started_at' => optional(optional($diag->nota)->cita)->fecha,
                'notes' => 'Problema importado desde diagnósticos históricos.',
            ]);
        }
    }

    protected function syncAlertsFromPatientFlags(ClinicalRecord $record): void
    {
        $flag = $record->patient?->patientFlag;
        if (! $flag) {
            return;
        }

        $definitions = [
            'adulto_mayor' => ['Paciente adulto mayor', ClinicalRecordAlert::SEVERITY_INFO],
            'embarazo' => ['Embarazo', ClinicalRecordAlert::SEVERITY_HIGH],
            'discapacidad' => ['Discapacidad relevante para la atención', ClinicalRecordAlert::SEVERITY_WARNING],
            'cronico' => ['Condición crónica reportada', ClinicalRecordAlert::SEVERITY_WARNING],
        ];

        foreach ($definitions as $attribute => [$title, $severity]) {
            $record->alerts()->updateOrCreate(
                [
                    'source_type' => PatientFlag::class,
                    'source_id' => $flag->id,
                    'title' => $title,
                ],
                [
                    'type' => ClinicalRecordAlert::TYPE_CONTEXT,
                    'severity' => $severity,
                    'is_active' => (bool) $flag->{$attribute},
                    'description' => 'Sincronizado desde banderas clínicas/administrativas del paciente.',
                ]
            );
        }
    }

    protected function replaceAllergies(ClinicalRecord $record, array $rows): void
    {
        $clean = collect($rows)
            ->map(fn ($row) => [
                'allergen' => $this->normalizeText($row['allergen'] ?? null),
                'reaction' => $this->normalizeText($row['reaction'] ?? null),
                'severity' => $row['severity'] ?? ClinicalRecordAllergy::SEVERITY_UNKNOWN,
                'status' => $row['status'] ?? ClinicalRecordAllergy::STATUS_ACTIVE,
                'notes' => $this->normalizeText($row['notes'] ?? null),
                'noted_at' => $this->normalizeDate($row['noted_at'] ?? null),
            ])
            ->filter(fn ($row) => filled($row['allergen']))
            ->values();

        $record->allergies()->delete();

        if ($clean->isNotEmpty()) {
            $record->allergies()->createMany($clean->all());
        }
    }

    protected function replaceHistories(ClinicalRecord $record, string $category, array $rows): void
    {
        $clean = collect($rows)
            ->map(fn ($row) => [
                'category' => $category,
                'title' => $this->normalizeText($row['title'] ?? null),
                'relation_label' => $this->normalizeText($row['relation_label'] ?? null),
                'description' => $this->normalizeText($row['description'] ?? null),
                'occurred_on' => $this->normalizeDate($row['occurred_on'] ?? null),
                'notes' => $this->normalizeText($row['notes'] ?? null),
            ])
            ->filter(fn ($row) => filled($row['title']))
            ->values();

        $record->histories()->where('category', $category)->delete();

        if ($clean->isNotEmpty()) {
            $record->histories()->createMany($clean->all());
        }
    }

    protected function replaceProblems(ClinicalRecord $record, array $rows): void
    {
        $clean = collect($rows)
            ->map(fn ($row) => [
                'source_nota_soap_id' => $row['source_nota_soap_id'] ?? null,
                'name' => $this->normalizeText($row['name'] ?? null),
                'cie10' => $this->normalizeText($row['cie10'] ?? null),
                'status' => $row['status'] ?? ClinicalRecordProblem::STATUS_ACTIVE,
                'is_chronic' => (bool) ($row['is_chronic'] ?? false),
                'started_at' => $this->normalizeDate($row['started_at'] ?? null),
                'resolved_at' => $this->normalizeDate($row['resolved_at'] ?? null),
                'notes' => $this->normalizeText($row['notes'] ?? null),
            ])
            ->filter(fn ($row) => filled($row['name']))
            ->unique(fn ($row) => Str::lower($row['name']).'|'.($row['cie10'] ?? ''))
            ->values();

        $record->problems()->delete();

        if ($clean->isNotEmpty()) {
            $record->problems()->createMany($clean->all());
        }
    }

    protected function replaceMedications(ClinicalRecord $record, array $rows, ?int $actorId): void
    {
        $clean = collect($rows)
            ->map(fn ($row) => [
                'source_receta_id' => $row['source_receta_id'] ?? null,
                'prescribed_by' => $row['prescribed_by'] ?? $actorId,
                'name' => $this->normalizeText($row['name'] ?? null),
                'presentation' => $this->normalizeText($row['presentation'] ?? null),
                'dosage' => $this->normalizeText($row['dosage'] ?? null),
                'frequency' => $this->normalizeText($row['frequency'] ?? null),
                'route' => $this->normalizeText($row['route'] ?? null),
                'instructions' => $this->normalizeText($row['instructions'] ?? null),
                'status' => $row['status'] ?? ClinicalRecordMedication::STATUS_ACTIVE,
                'started_at' => $this->normalizeDate($row['started_at'] ?? null),
                'ended_at' => $this->normalizeDate($row['ended_at'] ?? null),
            ])
            ->filter(fn ($row) => filled($row['name']))
            ->values();

        $record->medications()->delete();

        if ($clean->isNotEmpty()) {
            $record->medications()->createMany($clean->all());
        }
    }

    protected function replaceAlerts(ClinicalRecord $record, array $rows, ?int $actorId): void
    {
        $clean = collect($rows)
            ->map(fn ($row) => [
                'created_by' => $row['created_by'] ?? $actorId,
                'type' => $row['type'] ?? ClinicalRecordAlert::TYPE_CLINICAL,
                'title' => $this->normalizeText($row['title'] ?? null),
                'description' => $this->normalizeText($row['description'] ?? null),
                'severity' => $row['severity'] ?? ClinicalRecordAlert::SEVERITY_WARNING,
                'is_active' => (bool) ($row['is_active'] ?? false),
                'source_type' => $row['source_type'] ?? null,
                'source_id' => $row['source_id'] ?? null,
            ])
            ->filter(fn ($row) => filled($row['title']))
            ->values();

        $record->alerts()
            ->whereNull('source_type')
            ->delete();

        if ($clean->isNotEmpty()) {
            $record->alerts()->createMany($clean->all());
        }
    }

    protected function mapAllergiesToSoap(ClinicalRecord $record): ?array
    {
        if ($record->allergies_status === ClinicalRecord::ALLERGIES_NONE) {
            return ['no_conocidas' => true, 'detalle' => null];
        }

        $detalle = $record->allergies
            ->map(function (ClinicalRecordAllergy $allergy) {
                $segments = [$allergy->allergen];
                if ($allergy->reaction) {
                    $segments[] = $allergy->reaction;
                }

                return implode(' - ', $segments);
            })
            ->implode("\n");

        if ($detalle === '') {
            return null;
        }

        return ['no_conocidas' => false, 'detalle' => $detalle];
    }

    protected function mapAntecedentsToSoap(ClinicalRecord $record): ?array
    {
        $histories = $record->histories->groupBy('category');
        $activeProblems = $record->problems
            ->whereIn('status', [ClinicalRecordProblem::STATUS_ACTIVE, ClinicalRecordProblem::STATUS_MONITORING])
            ->values();

        $diabetes = $activeProblems->first(fn (ClinicalRecordProblem $problem) => str_contains(mb_strtolower($problem->name), 'diabet'));
        $hypertension = $activeProblems->first(fn (ClinicalRecordProblem $problem) => str_contains(mb_strtolower($problem->name), 'hipert'));

        $cronicas = $activeProblems
            ->filter(fn (ClinicalRecordProblem $problem) => $problem->is_chronic)
            ->pluck('name')
            ->implode("\n");

        $otros = ($histories[ClinicalRecordHistory::CATEGORY_PERSONAL] ?? collect())
            ->map(fn (ClinicalRecordHistory $history) => $history->title)
            ->implode("\n");

        $payload = [
            'cronicas' => $cronicas ?: null,
            'cirugias' => ($histories[ClinicalRecordHistory::CATEGORY_SURGERY] ?? collect())
                ->map(fn (ClinicalRecordHistory $history) => $history->title)
                ->implode("\n") ?: null,
            'hospitalizaciones' => ($histories[ClinicalRecordHistory::CATEGORY_HOSPITALIZATION] ?? collect())
                ->map(fn (ClinicalRecordHistory $history) => $history->title)
                ->implode("\n") ?: null,
            'diabetes' => $diabetes?->name,
            'hipertension' => $hypertension?->name,
            'otros' => $otros ?: null,
            'familiares' => ($histories[ClinicalRecordHistory::CATEGORY_FAMILY] ?? collect())
                ->map(fn (ClinicalRecordHistory $history) => $history->title)
                ->implode("\n") ?: null,
            'inmunizaciones' => ($histories[ClinicalRecordHistory::CATEGORY_IMMUNIZATION] ?? collect())
                ->map(fn (ClinicalRecordHistory $history) => $history->title)
                ->implode("\n") ?: null,
        ];

        return collect($payload)->filter()->isNotEmpty() ? $payload : null;
    }

    protected function mergeLaboratories(EloquentCollection $legacyLabs, EloquentCollection $selfServiceLabs): Collection
    {
        $legacy = $legacyLabs->map(function (LaboratorioOrden $orden) {
            return (object) [
                'source' => 'legacy',
                'title' => $orden->tipo_examen ?: 'Examen de laboratorio',
                'status' => $orden->estado,
                'summary' => $orden->resultado_resumen ?: $orden->indicaciones,
                'ordered_at' => $orden->resultado_publicado_at ?: optional($orden->cita)->fecha ?: $orden->created_at,
                'doctor_name' => optional(optional($orden->cita)->doctor)->name,
            ];
        });

        $selfService = $selfServiceLabs->map(function (LabOrder $orden) {
            return (object) [
                'source' => 'self_service',
                'title' => $orden->tipo_examen ?: 'Examen de laboratorio',
                'status' => $orden->status,
                'summary' => $orden->resultado_resumen ?: $orden->doctor_notes,
                'ordered_at' => $orden->resultado_publicado_at ?: $orden->scheduled_at ?: $orden->created_at,
                'doctor_name' => $orden->doctor?->name,
            ];
        });

        return $legacy
            ->concat($selfService)
            ->sortByDesc(fn ($entry) => $entry->ordered_at instanceof Carbon ? $entry->ordered_at->timestamp : strtotime((string) $entry->ordered_at))
            ->values();
    }

    protected function buildChronology(
        EloquentCollection $notes,
        EloquentCollection $prescriptions,
        EloquentCollection $certificates,
        EloquentCollection $legacyLabs,
        EloquentCollection $selfServiceLabs
    ): Collection {
        $events = collect();

        foreach ($notes as $note) {
            $events->push([
                'type' => 'consulta',
                'at' => $note->cita?->inicioProgramado(config('app.timezone', 'America/Guayaquil')),
                'title' => 'Consulta médica',
                'detail' => $note->assessment ?: $note->subjetivo_motivo,
                'doctor' => $note->cita?->doctor?->name,
                'cita_id' => $note->cita_id,
            ]);
        }

        foreach ($prescriptions as $prescription) {
            $events->push([
                'type' => 'receta',
                'at' => $prescription->created_at,
                'title' => 'Receta médica',
                'detail' => $prescription->diagnostico,
                'doctor' => $prescription->cita?->doctor?->name,
                'cita_id' => $prescription->cita_id,
            ]);
        }

        foreach ($certificates as $certificate) {
            $events->push([
                'type' => 'certificado',
                'at' => $certificate->fecha_emision,
                'title' => 'Certificado medico',
                'detail' => $certificate->dias_reposo > 0
                    ? 'Reposo por '.$certificate->dias_reposo.' dia(s).'
                    : $certificate->texto_constancia,
                'doctor' => $certificate->doctor?->name ?? $certificate->cita?->doctor?->name,
                'cita_id' => $certificate->cita_id,
            ]);
        }

        foreach ($legacyLabs as $lab) {
            $events->push([
                'type' => 'laboratorio',
                'at' => $lab->resultado_publicado_at ?: optional($lab->cita)->fecha ?: $lab->created_at,
                'title' => 'Laboratorio',
                'detail' => $lab->tipo_examen ?: $lab->resultado_resumen,
                'doctor' => optional(optional($lab->cita)->doctor)->name,
                'cita_id' => $lab->cita_id,
            ]);
        }

        foreach ($selfServiceLabs as $lab) {
            $events->push([
                'type' => 'laboratorio',
                'at' => $lab->resultado_publicado_at ?: $lab->scheduled_at ?: $lab->created_at,
                'title' => 'Laboratorio',
                'detail' => $lab->tipo_examen ?: $lab->resultado_resumen,
                'doctor' => $lab->doctor?->name,
                'cita_id' => null,
            ]);
        }

        return $events
            ->sortByDesc(fn ($event) => $event['at'] instanceof Carbon ? $event['at']->timestamp : strtotime((string) $event['at']))
            ->values();
    }

    protected function buildVitalSnapshot(EloquentCollection $notes): Collection
    {
        $latestNoteWithVitals = $notes->first(fn (NotaSoap $note) => is_array($note->signos_vitales) && ! empty($note->signos_vitales));
        $vitals = is_array($latestNoteWithVitals?->signos_vitales) ? $latestNoteWithVitals->signos_vitales : [];

        $definitions = [
            'ta' => ['label' => 'Presión arterial', 'unit' => 'mmHg'],
            'fc' => ['label' => 'Frecuencia cardíaca', 'unit' => 'lpm'],
            'fr' => ['label' => 'Frecuencia respiratoria', 'unit' => 'rpm'],
            'temp' => ['label' => 'Temperatura corporal', 'unit' => '°C'],
            'spo2' => ['label' => 'Saturación de oxígeno', 'unit' => '%'],
            'peso' => ['label' => 'Peso', 'unit' => 'kg'],
            'talla' => ['label' => 'Talla', 'unit' => 'cm'],
        ];

        return collect($definitions)->map(function (array $definition, string $key) use ($vitals) {
            $value = data_get($vitals, $key);
            $normalized = filled($value) ? trim((string) $value) : null;

            // For talla (cm): always display as integer to avoid float artifacts (e.g. 162.98 → 163)
            if ($normalized !== null && $key === 'talla' && is_numeric($normalized)) {
                $normalized = (string) (int) round((float) $normalized);
            }

            return [
                'key' => $key,
                'label' => $definition['label'],
                'value' => $normalized,
                'unit' => $definition['unit'],
                'display' => $normalized !== null ? $normalized.' '.$definition['unit'] : 'Sin registro',
            ];
        })->values();
    }

    protected function buildRecentDiagnoses(EloquentCollection $notes): Collection
    {
        return $notes
            ->flatMap(function (NotaSoap $note) {
                return $note->diagnosticos->map(function (NotaSoapDiagnostico $diagnosis) use ($note) {
                    return [
                        'text' => $diagnosis->texto,
                        'cie10' => $diagnosis->cie10,
                        'type' => $diagnosis->tipo,
                        'at' => $note->cita?->inicioProgramado(config('app.timezone', 'America/Guayaquil')),
                        'doctor' => $note->cita?->doctor?->name,
                        'cita_id' => $note->cita_id,
                    ];
                });
            })
            ->filter(fn (array $diagnosis) => filled($diagnosis['text']))
            ->unique(fn (array $diagnosis) => Str::lower(trim((string) $diagnosis['text'])).'|'.trim((string) ($diagnosis['cie10'] ?? '')))
            ->values();
    }

    protected function normalizeLines(mixed $value): array
    {
        $text = $this->normalizeText($value);
        if (! $text) {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n|,|;/', $text))
            ->map(fn ($line) => $this->normalizeText($line))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    protected function normalizeDate(mixed $value): ?string
    {
        $text = $this->normalizeText($value);
        if (! $text) {
            return null;
        }

        try {
            return Carbon::parse($text)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
