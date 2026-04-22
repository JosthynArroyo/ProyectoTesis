@extends($pageLayout ?? 'layouts.doctor')
@section('title', $pageTitle ?? 'Expediente clínico del paciente')
@section('activeSidebar', $pageActiveSidebar ?? 'pacientes')
@section('header-title', $pageHeaderTitle ?? ($patient->name ?? 'Expediente clínico del paciente'))
@section('header-subtitle', $pageHeaderSubtitle ?? 'Resumen longitudinal del paciente, separado de citas y notas individuales')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/medical-record.css') }}">
@endpush

@if($recordEditable ?? true)
    @push('scripts')
        @vite('resources/js/doctor/clinical-record-editor.js')
    @endpush
@endif

@php
    use App\Models\Cita;
    use App\Models\ClinicalRecord;
    use App\Models\ClinicalRecordAlert;
    use App\Models\ClinicalRecordAllergy;
    use App\Models\ClinicalRecordHistory;
    use App\Models\ClinicalRecordMedication;
    use App\Models\ClinicalRecordProblem;
    use Carbon\Carbon;
    use Illuminate\Support\Str;

    $recordEditable = $recordEditable ?? true;
    $allowActionLinks = $allowActionLinks ?? true;
    $backUrl = $backUrl ?? route('doctor.citas');
    $backLabel = $backLabel ?? 'Volver a citas';
    $noteRouteName = $noteRouteName ?? ($allowActionLinks ? 'doctor.citas.soap' : null);
    $prescriptionRouteName = $prescriptionRouteName ?? ($allowActionLinks ? 'doctor.recetas.edit' : null);
    $certificateRouteName = $certificateRouteName ?? ($allowActionLinks ? 'doctor.certificados.show' : null);
    $certificateDownloadRouteName = $certificateDownloadRouteName ?? ($allowActionLinks ? 'doctor.certificados.download' : null);
    $certificates = $certificates ?? collect();

    $patient = $record->patient ?? $paciente;
    $age = $patient->fecha_nacimiento ? Carbon::parse($patient->fecha_nacimiento)->age : null;
    $patientInitials = collect(preg_split('/\s+/', trim((string) $patient->name)))
        ->filter()
        ->take(2)
        ->map(fn ($segment) => Str::upper(Str::substr($segment, 0, 1)))
        ->implode('');

    $formatDate = function ($date, string $fallback = 'Sin fecha registrada') {
        if (! $date) {
            return $fallback;
        }

        return $date instanceof Carbon
            ? $date->format('d/m/Y')
            : Carbon::parse($date)->format('d/m/Y');
    };

    $formatDateTime = function ($date, $time = null, string $fallback = 'Sin fecha registrada') use ($formatDate) {
        if (! $date) {
            return $fallback;
        }

        $value = $formatDate($date, $fallback);
        if ($time) {
            $value .= ' '.substr((string) $time, 0, 5);
        }

        return trim($value);
    };

    $appointmentStateLabels = [
        Cita::ESTADO_PENDIENTE => 'Pendiente',
        Cita::ESTADO_CONFIRMADA => 'Confirmada',
        Cita::ESTADO_CANCELADA => 'Cancelada',
        Cita::ESTADO_REALIZADA => 'Realizada',
        Cita::ESTADO_NO_SE_PRESENTO => 'No se presento',
    ];

    $problemStatusLabels = [
        ClinicalRecordProblem::STATUS_ACTIVE => 'Activo',
        ClinicalRecordProblem::STATUS_MONITORING => 'En seguimiento',
        ClinicalRecordProblem::STATUS_RESOLVED => 'Resuelto',
    ];

    $medicationStatusLabels = [
        ClinicalRecordMedication::STATUS_ACTIVE => 'Activa',
        ClinicalRecordMedication::STATUS_SUSPENDED => 'Suspendida',
        ClinicalRecordMedication::STATUS_COMPLETED => 'Finalizada',
    ];

    $alertSeverityLabels = [
        ClinicalRecordAlert::SEVERITY_INFO => 'Informativa',
        ClinicalRecordAlert::SEVERITY_WARNING => 'Advertencia',
        ClinicalRecordAlert::SEVERITY_HIGH => 'Alta prioridad',
    ];

    $allergySeverityLabels = [
        ClinicalRecordAllergy::SEVERITY_UNKNOWN => 'Sin severidad definida',
        ClinicalRecordAllergy::SEVERITY_MILD => 'Leve',
        ClinicalRecordAllergy::SEVERITY_MODERATE => 'Moderada',
        ClinicalRecordAllergy::SEVERITY_SEVERE => 'Severa',
    ];

    $allergyStatusLabels = [
        ClinicalRecordAllergy::STATUS_ACTIVE => 'Activa',
        ClinicalRecordAllergy::STATUS_RESOLVED => 'Resuelta',
    ];

    $allergiesSummary = match ($record->allergies_status) {
        ClinicalRecord::ALLERGIES_DOCUMENTED => ['label' => 'Alergias documentadas', 'tone' => 'danger'],
        ClinicalRecord::ALLERGIES_NONE => ['label' => 'Sin alergias conocidas', 'tone' => 'info'],
        default => ['label' => 'Alergias sin confirmar', 'tone' => 'warning'],
    };

    $historyDisplayGroups = [
        [
            'title' => 'Antecedentes personales',
            'items' => $historiesByCategory[ClinicalRecordHistory::CATEGORY_PERSONAL] ?? collect(),
            'empty' => 'No hay antecedentes personales consolidados.',
            'show_relation' => false,
        ],
        [
            'title' => 'Antecedentes familiares',
            'items' => $historiesByCategory[ClinicalRecordHistory::CATEGORY_FAMILY] ?? collect(),
            'empty' => 'No hay antecedentes familiares consolidados.',
            'show_relation' => true,
        ],
        [
            'title' => 'Cirugias previas',
            'items' => $historiesByCategory[ClinicalRecordHistory::CATEGORY_SURGERY] ?? collect(),
            'empty' => 'No hay cirugias previas registradas.',
            'show_relation' => false,
        ],
        [
            'title' => 'Hospitalizaciones',
            'items' => $historiesByCategory[ClinicalRecordHistory::CATEGORY_HOSPITALIZATION] ?? collect(),
            'empty' => 'No hay hospitalizaciones documentadas.',
            'show_relation' => false,
        ],
        [
            'title' => 'Inmunizaciones',
            'items' => $historiesByCategory[ClinicalRecordHistory::CATEGORY_IMMUNIZATION] ?? collect(),
            'empty' => 'No hay inmunizaciones registradas.',
            'show_relation' => false,
        ],
    ];

    $padRows = function (array $rows, array $blank, int $minimum = 1): array {
        $rows = array_values($rows);
        $target = max($minimum, count($rows));

        while (count($rows) < $target) {
            $rows[] = $blank;
        }

        return $rows;
    };

    $mapHistoryRows = function ($items) {
        return collect($items ?? [])->map(function ($item) {
            return [
                'title' => $item->title ?? '',
                'relation_label' => $item->relation_label ?? '',
                'description' => $item->description ?? '',
                'occurred_on' => optional($item->occurred_on)->format('Y-m-d'),
                'notes' => $item->notes ?? '',
            ];
        })->toArray();
    };

    $blankAllergyRow = [
        'allergen' => '',
        'reaction' => '',
        'severity' => ClinicalRecordAllergy::SEVERITY_UNKNOWN,
        'status' => ClinicalRecordAllergy::STATUS_ACTIVE,
        'notes' => '',
        'noted_at' => '',
    ];

    $blankHistoryRow = [
        'title' => '',
        'description' => '',
        'occurred_on' => '',
        'notes' => '',
    ];

    $blankFamilyHistoryRow = [
        'title' => '',
        'relation_label' => '',
        'description' => '',
        'occurred_on' => '',
        'notes' => '',
    ];

    $blankProblemRow = [
        'name' => '',
        'cie10' => '',
        'status' => ClinicalRecordProblem::STATUS_ACTIVE,
        'is_chronic' => '0',
        'started_at' => '',
        'resolved_at' => '',
        'notes' => '',
    ];

    $blankMedicationRow = [
        'name' => '',
        'presentation' => '',
        'dosage' => '',
        'frequency' => '',
        'route' => '',
        'instructions' => '',
        'status' => ClinicalRecordMedication::STATUS_ACTIVE,
        'started_at' => '',
        'ended_at' => '',
    ];

    $blankAlertRow = [
        'title' => '',
        'description' => '',
        'severity' => ClinicalRecordAlert::SEVERITY_WARNING,
        'type' => ClinicalRecordAlert::TYPE_CLINICAL,
        'is_active' => '1',
    ];

    $allergyRows = $padRows(old('allergies', $record->allergies->map(fn ($allergy) => [
        'allergen' => $allergy->allergen,
        'reaction' => $allergy->reaction,
        'severity' => $allergy->severity,
        'status' => $allergy->status,
        'notes' => $allergy->notes,
        'noted_at' => optional($allergy->noted_at)->format('Y-m-d'),
    ])->toArray()), $blankAllergyRow);

    $personalRows = $padRows(old('personal_histories', $mapHistoryRows($historiesByCategory[ClinicalRecordHistory::CATEGORY_PERSONAL] ?? collect())), $blankHistoryRow);
    $familyRows = $padRows(old('family_histories', $mapHistoryRows($historiesByCategory[ClinicalRecordHistory::CATEGORY_FAMILY] ?? collect())), $blankFamilyHistoryRow);
    $surgeryRows = $padRows(old('surgeries', $mapHistoryRows($historiesByCategory[ClinicalRecordHistory::CATEGORY_SURGERY] ?? collect())), $blankHistoryRow);
    $hospitalRows = $padRows(old('hospitalizations', $mapHistoryRows($historiesByCategory[ClinicalRecordHistory::CATEGORY_HOSPITALIZATION] ?? collect())), $blankHistoryRow);
    $immunizationRows = $padRows(old('immunizations', $mapHistoryRows($historiesByCategory[ClinicalRecordHistory::CATEGORY_IMMUNIZATION] ?? collect())), $blankHistoryRow);

    $problemRows = $padRows(old('problems', $record->problems->map(fn ($problem) => [
        'name' => $problem->name,
        'cie10' => $problem->cie10,
        'status' => $problem->status,
        'is_chronic' => $problem->is_chronic ? '1' : '0',
        'started_at' => optional($problem->started_at)->format('Y-m-d'),
        'resolved_at' => optional($problem->resolved_at)->format('Y-m-d'),
        'notes' => $problem->notes,
    ])->toArray()), $blankProblemRow);

    $medicationRows = $padRows(old('medications', $record->medications->map(fn ($medication) => [
        'name' => $medication->name,
        'presentation' => $medication->presentation,
        'dosage' => $medication->dosage,
        'frequency' => $medication->frequency,
        'route' => $medication->route,
        'instructions' => $medication->instructions,
        'status' => $medication->status,
        'started_at' => optional($medication->started_at)->format('Y-m-d'),
        'ended_at' => optional($medication->ended_at)->format('Y-m-d'),
    ])->toArray()), $blankMedicationRow);

    $alertRows = $padRows(old('alerts', $record->alerts->whereNull('source_type')->map(fn ($alert) => [
        'title' => $alert->title,
        'description' => $alert->description,
        'severity' => $alert->severity,
        'type' => $alert->type,
        'is_active' => $alert->is_active ? '1' : '0',
    ])->toArray()), $blankAlertRow);

    $editorHistoryGroups = [
        'personal_histories' => ['title' => 'Antecedentes personales', 'item_title' => 'Antecedente personal', 'add_label' => 'Agregar antecedente personal', 'rows' => $personalRows, 'blank_row' => $blankHistoryRow, 'show_relation' => false],
        'family_histories' => ['title' => 'Antecedentes familiares', 'item_title' => 'Antecedente familiar', 'add_label' => 'Agregar antecedente familiar', 'rows' => $familyRows, 'blank_row' => $blankFamilyHistoryRow, 'show_relation' => true],
        'surgeries' => ['title' => 'Cirugias previas', 'item_title' => 'Cirugia previa', 'add_label' => 'Agregar cirugia', 'rows' => $surgeryRows, 'blank_row' => $blankHistoryRow, 'show_relation' => false],
        'hospitalizations' => ['title' => 'Hospitalizaciones', 'item_title' => 'Hospitalizacion', 'add_label' => 'Agregar hospitalizacion', 'rows' => $hospitalRows, 'blank_row' => $blankHistoryRow, 'show_relation' => false],
        'immunizations' => ['title' => 'Inmunizaciones', 'item_title' => 'Inmunizacion', 'add_label' => 'Agregar inmunizacion', 'rows' => $immunizationRows, 'blank_row' => $blankHistoryRow, 'show_relation' => false],
    ];

    $latestVitalsAt = $latestNote?->cita
        ? $formatDateTime($latestNote->cita->fecha, $latestNote->cita->hora)
        : 'Sin referencia de consulta';

    $errorKeys = collect($errors->keys());

    $hasErrorPrefix = function (array $prefixes) use ($errorKeys): bool {
        return $errorKeys->contains(function ($key) use ($prefixes) {
            foreach ($prefixes as $prefix) {
                if (Str::startsWith($key, $prefix)) {
                    return true;
                }
            }

            return false;
        });
    };

    $countFilledRows = function (array $rows, array $fields): int {
        return collect($rows)->filter(function ($row) use ($fields) {
            foreach ($fields as $field) {
                if (filled($row[$field] ?? null)) {
                    return true;
                }
            }

            return false;
        })->count();
    };

    $entriesLabel = function (int $count): string {
        return $count === 1 ? '1 registro' : $count.' registros';
    };

    $editorCounts = [
        'summary' => (filled(old('clinical_summary', $record->clinical_summary)) ? 1 : 0)
            + $countFilledRows($allergyRows, ['allergen', 'reaction', 'notes']),
        'history' => $countFilledRows($personalRows, ['title', 'description', 'notes'])
            + $countFilledRows($familyRows, ['title', 'relation_label', 'description', 'notes'])
            + $countFilledRows($surgeryRows, ['title', 'description', 'notes'])
            + $countFilledRows($hospitalRows, ['title', 'description', 'notes'])
            + $countFilledRows($immunizationRows, ['title', 'description', 'notes']),
        'problems' => $countFilledRows($problemRows, ['name', 'cie10', 'notes']),
        'medications' => $countFilledRows($medicationRows, ['name', 'presentation', 'dosage', 'frequency', 'instructions'])
            + $countFilledRows($alertRows, ['title', 'description']),
    ];

    $editorAccordionState = [
        'summary' => ! $errors->any() || $hasErrorPrefix(['clinical_summary', 'allergies_status', 'allergies']),
        'history' => $hasErrorPrefix(['personal_histories', 'family_histories', 'surgeries', 'hospitalizations', 'immunizations']),
        'problems' => $hasErrorPrefix(['problems']),
        'medications' => $hasErrorPrefix(['medications', 'alerts']),
    ];

    $historyGroupState = [
        'personal_histories' => ! $errors->any() || $hasErrorPrefix(['personal_histories']),
        'family_histories' => $hasErrorPrefix(['family_histories']),
        'surgeries' => $hasErrorPrefix(['surgeries']),
        'hospitalizations' => $hasErrorPrefix(['hospitalizations']),
        'immunizations' => $hasErrorPrefix(['immunizations']),
    ];

    $medicationGroupState = [
        'medications' => ! $errors->any() || $hasErrorPrefix(['medications']),
        'alerts' => $hasErrorPrefix(['alerts']),
    ];

    $noteUrl = function ($citaId) use ($noteRouteName) {
        return $noteRouteName && $citaId ? route($noteRouteName, $citaId) : null;
    };

    $prescriptionUrl = function ($citaId) use ($prescriptionRouteName) {
        return $prescriptionRouteName && $citaId ? route($prescriptionRouteName, $citaId) : null;
    };

    $certificateUrl = function ($certificate) use ($certificateRouteName) {
        return $certificateRouteName && $certificate?->id ? route($certificateRouteName, $certificate) : null;
    };

    $certificateDownloadUrl = function ($certificate) use ($certificateDownloadRouteName) {
        return $certificateDownloadRouteName && $certificate?->id ? route($certificateDownloadRouteName, $certificate) : null;
    };
@endphp

@section('main')
<div class="space-y-6 medical-record-shell">
    @if (session('success'))
        <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert tone="error">{{ session('error') }}</x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert tone="error">
            <div class="space-y-1">
                <p class="font-semibold">No se pudo actualizar el expediente clinico.</p>
                <ul class="list-disc pl-5 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </x-ui.alert>
    @endif

    <section class="card medical-toolbar">
        <div class="medical-toolbar__content">
            <div class="medical-toolbar__stats">
                <article class="medical-stat">
                    <span class="medical-stat__label">Notas clinicas firmadas</span>
                    <strong class="medical-stat__value">{{ $notes->count() }}</strong>
                    <small class="medical-stat__hint">Consultas con nota individual firmada</small>
                </article>
                <article class="medical-stat">
                    <span class="medical-stat__label">Problemas activos</span>
                    <strong class="medical-stat__value">{{ $activeProblems->count() }}</strong>
                    <small class="medical-stat__hint">Diagnosticos longitudinales en seguimiento</small>
                </article>
                <article class="medical-stat">
                    <span class="medical-stat__label">Medicacion actual</span>
                    <strong class="medical-stat__value">{{ $currentMedications->count() }}</strong>
                    <small class="medical-stat__hint">Tratamiento habitual activo</small>
                </article>
                <article class="medical-stat">
                    <span class="medical-stat__label">Proximas citas</span>
                    <strong class="medical-stat__value">{{ $futureAppointments->count() }}</strong>
                    <small class="medical-stat__hint">Agenda futura del paciente</small>
                </article>
            </div>

            <div class="medical-toolbar__actions">
                <x-ui.badge :tone="$allergiesSummary['tone']">{{ $allergiesSummary['label'] }}</x-ui.badge>
                @if($nextAppointment)
                    <span class="medical-chip medical-chip--strong">
                        <i class="ri-calendar-check-line"></i>
                        Proxima cita: {{ $formatDateTime($nextAppointment->fecha, $nextAppointment->hora) }}
                    </span>
                @endif
                @php
                    $latestNoteUrl = $latestNote?->cita_id ? $noteUrl($latestNote->cita_id) : null;
                @endphp
                @if($latestNoteUrl)
                    <a href="{{ $latestNoteUrl }}" class="btn btn-outline">
                        Abrir ultima nota clinica
                    </a>
                @endif
                <a href="{{ $backUrl }}" class="btn btn-ghost">{{ $backLabel }}</a>
            </div>
        </div>
    </section>

    <section class="medical-record">
        <aside class="medical-column medical-column--left">
            <x-medical.card title="Resumen general del paciente" subtitle="Identificacion, contacto y contexto basico" icon="ri-user-heart-line">
                <div class="medical-profile">
                    <div class="medical-profile__avatar-fallback" aria-hidden="true">{{ $patientInitials ?: 'P' }}</div>
                    <div class="medical-profile__content">
                        <h2>{{ $patient->name }}</h2>
                        <dl class="medical-kv-list">
                            <div>
                                <dt>Documento</dt>
                                <dd>{{ $patient->dni ?: 'Sin identificacion registrada' }}</dd>
                            </div>
                            <div>
                                <dt>Telefono</dt>
                                <dd>{{ $patient->telefono ?: 'Sin telefono registrado' }}</dd>
                            </div>
                            <div>
                                <dt>Correo</dt>
                                <dd>{{ $patient->email ?: 'Sin correo registrado' }}</dd>
                            </div>
                            <div>
                                <dt>Edad / sexo</dt>
                                <dd>
                                    {{ $age !== null ? $age.' anos' : 'Edad no registrada' }}
                                    @if($patient->sexo)
                                        | {{ $patient->sexo }}
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt>Fecha de nacimiento</dt>
                                <dd>{{ $formatDate($patient->fecha_nacimiento, 'Sin fecha registrada') }}</dd>
                            </div>
                            <div>
                                <dt>Ultima revision del expediente</dt>
                        <dd>{{ $record->last_reviewed_at ? $record->last_reviewed_at->format('d/m/Y H:i') : 'Aún no revisado manualmente' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </x-medical.card>

            <x-medical.card title="Alergias" subtitle="Alertas biologicas y reacciones conocidas" icon="ri-alarm-warning-line" :tone="$record->allergies->isNotEmpty() ? 'danger' : 'default'">
                @if($record->allergies->isNotEmpty())
                    <div class="medical-stack">
                        @foreach($record->allergies as $allergy)
                            <article class="medical-activity medical-activity--danger">
                                <strong>{{ $allergy->allergen }}</strong>
                                <p>{{ $allergy->reaction ?: 'Sin reaccion especificada.' }}</p>
                                <span>
                                    {{ $allergySeverityLabels[$allergy->severity] ?? 'Sin severidad definida' }}
                                    | {{ $allergyStatusLabels[$allergy->status] ?? 'Sin estado' }}
                                    @if($allergy->noted_at)
                                        | Registrada: {{ $allergy->noted_at->format('d/m/Y') }}
                                    @endif
                                </span>
                                @if($allergy->notes)
                                    <p>{{ Str::limit($allergy->notes, 160) }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="medical-empty">
                        {{ $record->allergies_status === ClinicalRecord::ALLERGIES_NONE ? 'El expediente indica que no existen alergias conocidas.' : 'Todavia no hay alergias estructuradas en el expediente.' }}
                    </p>
                @endif
            </x-medical.card>

            <x-medical.card title="Alertas clinicas" subtitle="Riesgos, contexto y banderas relevantes" icon="ri-error-warning-line" :tone="$activeAlerts->isNotEmpty() ? 'warning' : 'default'">
                @if($activeAlerts->isNotEmpty())
                    <div class="medical-stack">
                        @foreach($activeAlerts as $alert)
                            <article class="medical-activity">
                                <strong>{{ $alert->title }}</strong>
                                <p>{{ $alert->description ?: 'Sin descripcion adicional.' }}</p>
                                <span>
                                    {{ $alertSeverityLabels[$alert->severity] ?? ucfirst($alert->severity) }}
                                    | {{ $alert->type === ClinicalRecordAlert::TYPE_CONTEXT ? 'Contexto clinico' : 'Alerta clinica' }}
                                </span>
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="medical-empty">No hay alertas clinicas activas registradas.</p>
                @endif
            </x-medical.card>

            <x-medical.card title="Seguimiento y proximos controles" subtitle="Controles recomendados y agenda futura" icon="ri-calendar-event-line" tone="info">
                <div class="medical-stack">
                    <article class="medical-highlight">
                        <h4>Proximo control sugerido</h4>
                        <p>
                            @if($nextFollowUp)
                                {{ $nextFollowUp->follow_up_date?->format('d/m/Y') }}
                                @if($nextFollowUp->follow_up_notes)
                                    | {{ Str::limit($nextFollowUp->follow_up_notes, 140) }}
                                @endif
                            @else
                                No hay control clinico pendiente documentado en las notas.
                            @endif
                        </p>
                    </article>

                    <article class="medical-highlight">
                        <h4>Proxima cita agendada</h4>
                        <p>
                            @if($nextAppointment)
                                {{ $formatDateTime($nextAppointment->fecha, $nextAppointment->hora) }}
                                | {{ $nextAppointment->especialidad?->nombre ?? 'Consulta' }}
                                | {{ $nextAppointment->doctor?->name ?? 'Profesional no asignado' }}
                            @else
                                No hay citas futuras registradas.
                            @endif
                        </p>
                    </article>

                    @if($followUps->isNotEmpty())
                        <div class="medical-list-wrap">
                            <h4 class="medical-subheading">Seguimientos registrados</h4>
                            <ul class="medical-list">
                                @foreach($followUps->take(5) as $followUp)
                                    <li>
                                        <i class="ri-flag-line"></i>
                                        <div>
                                            <strong>{{ $followUp->follow_up_date?->format('d/m/Y') ?? 'Sin fecha' }}</strong>
                                            <div>{{ $followUp->follow_up_notes ?: 'Sin observaciones de seguimiento.' }}</div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </x-medical.card>
        </aside>

        <section class="medical-column medical-column--center">
            <x-medical.card title="Resumen clínico" subtitle="Síntesis longitudinal del expediente" icon="ri-file-text-line">
                <div class="medical-summary-grid">
                    <article class="medical-summary-block medical-summary-block--full">
                        <h4>Resumen general del caso</h4>
                        <p>{{ $record->clinical_summary ?: 'Todavía no se ha consolidado un resumen clínico general para este paciente.' }}</p>
                    </article>

                    <article class="medical-summary-block">
                        <h4>Última nota clínica firmada</h4>
                        <p>
                            @if($latestNote)
                                {{ $latestVitalsAt }}
                                | {{ $latestNote->cita?->doctor?->name ?? 'Profesional no registrado' }}
                            @else
                                No hay notas clínicas firmadas en el expediente.
                            @endif
                        </p>
                    </article>

                    <article class="medical-summary-block">
                        <h4>Últimos signos vitales</h4>
                        <p>{{ $latestNote ? 'Tomados en '.$latestVitalsAt : 'Sin signos vitales estructurados todavía.' }}</p>
                    </article>
                </div>

                <div class="medical-vitals-snapshot">
                    @foreach($vitalSnapshot as $metric)
                        <article class="medical-vitals-snapshot__item">
                            <span>{{ $metric['label'] }}</span>
                            <strong>{{ $metric['value'] }}</strong>
                        </article>
                    @endforeach
                </div>
            </x-medical.card>

            <x-medical.card title="Antecedentes clinicos" subtitle="Categorias longitudinales del expediente" icon="ri-file-history-line">
                <div class="medical-grid-details medical-grid-details--tall">
                    @foreach($historyDisplayGroups as $group)
                        <article>
                            <h4>{{ $group['title'] }}</h4>
                            @if($group['items']->isNotEmpty())
                                <div class="medical-stack medical-stack--compact">
                                    @foreach($group['items'] as $item)
                                        <div class="medical-detail-line">
                                            <strong>{{ $item->title }}</strong>
                                            @if($group['show_relation'] && $item->relation_label)
                                                <span>Parentesco: {{ $item->relation_label }}</span>
                                            @endif
                                            @if($item->description)
                                                <p>{{ Str::limit($item->description, 150) }}</p>
                                            @endif
                                            <span>
                                                @if($item->occurred_on)
                                                    Fecha: {{ $item->occurred_on->format('d/m/Y') }}
                                                @else
                                                    Fecha no especificada
                                                @endif
                                                @if($item->notes)
                                                    | {{ Str::limit($item->notes, 110) }}
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p>{{ $group['empty'] }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </x-medical.card>

            <x-medical.card title="Problemas clinicos longitudinales" subtitle="Lista de problemas activos y resueltos del paciente" icon="ri-heart-pulse-line">
                <div class="medical-two-up">
                    <section>
                        <h4 class="medical-subheading">Problemas activos / en seguimiento</h4>
                        @if($activeProblems->isNotEmpty())
                            <div class="medical-stack">
                                @foreach($activeProblems as $problem)
                                    <article class="medical-entry medical-entry--compact">
                                        <header class="medical-entry__header">
                                            <div>
                                                <strong>{{ $problem->name }}</strong>
                                                <small>
                                                    {{ $problemStatusLabels[$problem->status] ?? ucfirst($problem->status) }}
                                                    @if($problem->cie10)
                                                        | CIE-10 {{ $problem->cie10 }}
                                                    @endif
                                                    @if($problem->is_chronic)
                                                        | Cronico
                                                    @endif
                                                </small>
                                            </div>
                                        </header>
                                        <dl>
                                            <div>
                                                <dt>Inicio</dt>
                                                <dd>{{ $formatDate($problem->started_at, 'Sin fecha registrada') }}</dd>
                                            </div>
                                            <div>
                                                <dt>Observaciones</dt>
                                                <dd>{{ $problem->notes ?: 'Sin observaciones clinicas.' }}</dd>
                                            </div>
                                        </dl>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <p class="medical-empty">No hay problemas activos consolidados.</p>
                        @endif
                    </section>

                    <section>
                        <h4 class="medical-subheading">Problemas resueltos</h4>
                        @if($resolvedProblems->isNotEmpty())
                            <div class="medical-stack">
                                @foreach($resolvedProblems as $problem)
                                    <article class="medical-entry medical-entry--compact">
                                        <header class="medical-entry__header">
                                            <div>
                                                <strong>{{ $problem->name }}</strong>
                                                <small>
                                                    {{ $problemStatusLabels[$problem->status] ?? ucfirst($problem->status) }}
                                                    @if($problem->cie10)
                                                        | CIE-10 {{ $problem->cie10 }}
                                                    @endif
                                                </small>
                                            </div>
                                        </header>
                                        <dl>
                                            <div>
                                                <dt>Resolucion</dt>
                                                <dd>{{ $formatDate($problem->resolved_at, 'Sin fecha registrada') }}</dd>
                                            </div>
                                            <div>
                                                <dt>Observaciones</dt>
                                                <dd>{{ $problem->notes ?: 'Sin observaciones clinicas.' }}</dd>
                                            </div>
                                        </dl>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <p class="medical-empty">Todavia no hay problemas marcados como resueltos.</p>
                        @endif
                    </section>
                </div>
            </x-medical.card>

            <x-medical.card title="Medicacion habitual" subtitle="Tratamientos consolidados dentro del expediente" icon="ri-medicine-bottle-line">
                <div class="medical-two-up">
                    <section>
                        <h4 class="medical-subheading">Medicacion activa</h4>
                        @if($currentMedications->isNotEmpty())
                            <div class="medical-stack">
                                @foreach($currentMedications as $medication)
                                    <article class="medical-activity">
                                        <strong>{{ $medication->name }}</strong>
                                        <p>{{ collect([$medication->presentation, $medication->dosage, $medication->frequency, $medication->route])->filter()->implode(' | ') ?: 'Sin pauta detallada.' }}</p>
                                        <span>
                                            {{ $medicationStatusLabels[$medication->status] ?? ucfirst($medication->status) }}
                                            @if($medication->started_at)
                                                | Desde {{ $medication->started_at->format('d/m/Y') }}
                                            @endif
                                        </span>
                                        @if($medication->instructions)
                                            <p>{{ Str::limit($medication->instructions, 160) }}</p>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <p class="medical-empty">No hay medicacion habitual activa registrada.</p>
                        @endif
                    </section>

                    <section>
                        <h4 class="medical-subheading">Medicacion suspendida o finalizada</h4>
                        @if($inactiveMedications->isNotEmpty())
                            <div class="medical-stack">
                                @foreach($inactiveMedications as $medication)
                                    <article class="medical-activity">
                                        <strong>{{ $medication->name }}</strong>
                                        <p>{{ collect([$medication->dosage, $medication->frequency, $medication->route])->filter()->implode(' | ') ?: 'Sin pauta detallada.' }}</p>
                                        <span>
                                            {{ $medicationStatusLabels[$medication->status] ?? ucfirst($medication->status) }}
                                            @if($medication->ended_at)
                                                | Hasta {{ $medication->ended_at->format('d/m/Y') }}
                                            @endif
                                        </span>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <p class="medical-empty">No hay registros inactivos de medicacion.</p>
                        @endif
                    </section>
                </div>
            </x-medical.card>

        </section>

        <aside class="medical-column medical-column--right">
            <x-medical.card title="Diagnosticos relevantes" subtitle="Diagnosticos recientes vistos dentro del expediente" icon="ri-health-book-line">
                @if($recentDiagnoses->isNotEmpty())
                    <div class="medical-stack medical-stack--compact">
                        @foreach($recentDiagnoses->take(8) as $diagnosis)
                            <article class="medical-activity">
                                <strong>{{ $diagnosis['text'] }}</strong>
                                <p>
                                    {{ ucfirst((string) $diagnosis['type']) }}
                                    @if($diagnosis['cie10'])
                                        | CIE-10 {{ $diagnosis['cie10'] }}
                                    @endif
                                </p>
                                <span>
                                    {{ $diagnosis['at'] ? Carbon::parse($diagnosis['at'])->format('d/m/Y H:i') : 'Sin fecha' }}
                                    @if($diagnosis['doctor'])
                                        | {{ $diagnosis['doctor'] }}
                                    @endif
                                </span>
                                @php
                                    $diagnosisUrl = $diagnosis['cita_id'] ? $noteUrl($diagnosis['cita_id']) : null;
                                @endphp
                                @if($diagnosisUrl)
                                    <a href="{{ $diagnosisUrl }}" class="medical-chip">
                                        <i class="ri-file-search-line"></i> Ver nota
                                    </a>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="medical-empty">No hay diagnosticos recientes consolidados.</p>
                @endif
            </x-medical.card>

            <x-medical.card title="Proximas citas" subtitle="Agenda futura del paciente" icon="ri-calendar-check-line" tone="info">
                @if($futureAppointments->isNotEmpty())
                    <div class="medical-stack medical-stack--compact">
                        @foreach($futureAppointments->take(6) as $appointment)
                            <article class="medical-activity">
                                <strong>{{ $formatDateTime($appointment->fecha, $appointment->hora) }}</strong>
                                <p>{{ $appointment->especialidad?->nombre ?? 'Consulta' }}</p>
                                <span>
                                    {{ $appointmentStateLabels[$appointment->estado] ?? ucfirst($appointment->estado) }}
                                    | {{ $appointment->doctor?->name ?? 'Profesional no asignado' }}
                                </span>
                                @php
                                    $futureAppointmentUrl = $noteUrl($appointment->id);
                                @endphp
                                @if($futureAppointmentUrl)
                                    <a href="{{ $futureAppointmentUrl }}" class="medical-chip">
                                        <i class="ri-file-edit-line"></i> Abrir consulta
                                    </a>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="medical-empty">No hay citas futuras registradas para este paciente.</p>
                @endif
            </x-medical.card>

            <x-medical.card title="Recetas vinculadas" subtitle="Indicaciones farmacologicas asociadas al expediente" icon="ri-capsule-line">
                @if($prescriptions->isNotEmpty())
                    <div class="medical-stack medical-stack--compact">
                        @foreach($prescriptions->take(6) as $prescription)
                            <article class="medical-activity">
                                <strong>{{ $prescription->created_at?->format('d/m/Y') ?? 'Sin fecha registrada' }}</strong>
                                <p>{{ $prescription->diagnostico ?: 'Sin diagnostico consignado.' }}</p>
                                <span>
                                    {{ Str::limit($prescription->medicamentos ?: 'Sin medicamentos descritos.', 120) }}
                                </span>
                                @php
                                    $prescriptionDetailUrl = $prescription->cita_id ? $prescriptionUrl($prescription->cita_id) : null;
                                @endphp
                                @if($prescriptionDetailUrl)
                                    <a href="{{ $prescriptionDetailUrl }}" class="medical-chip">
                                        <i class="ri-file-list-3-line"></i> Ver receta
                                    </a>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="medical-empty">No hay recetas vinculadas al expediente.</p>
                @endif
            </x-medical.card>

            <x-medical.card title="Certificados medicos" subtitle="Constancias emitidas y vinculadas al expediente" icon="ri-file-shield-2-line">
                @if($certificates->isNotEmpty())
                    <div class="medical-stack medical-stack--compact">
                        @foreach($certificates->take(6) as $certificate)
                            @php
                                $certificateDetailUrl = $certificateUrl($certificate);
                                $certificatePdfUrl = $certificateDownloadUrl($certificate);
                            @endphp
                            <article class="medical-activity">
                                <strong>{{ $certificate->codigo }}</strong>
                                <p>
                                    {{ $certificate->dias_reposo > 0 ? 'Reposo por '.$certificate->dias_reposo.' dia(s).' : Str::limit($certificate->texto_constancia, 120) }}
                                </p>
                                <span>
                                    {{ $certificate->fecha_emision?->format('d/m/Y H:i') ?? 'Sin fecha registrada' }}
                                    | {{ $certificate->doctor?->name ?? $certificate->cita?->doctor?->name ?? 'Profesional no registrado' }}
                                </span>
                                @if($certificateDetailUrl || $certificatePdfUrl)
                                    <div class="flex flex-wrap gap-2">
                                        @if($certificateDetailUrl)
                                            <a href="{{ $certificateDetailUrl }}" class="medical-chip">
                                                <i class="ri-eye-line"></i> Ver certificado
                                            </a>
                                        @endif
                                        @if($certificatePdfUrl)
                                            <a href="{{ $certificatePdfUrl }}" class="medical-chip">
                                                <i class="ri-download-2-line"></i> Descargar
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="medical-empty">No hay certificados medicos vinculados al expediente.</p>
                @endif
            </x-medical.card>

            <x-medical.card title="Laboratorios y resultados" subtitle="Ordenes y resultados integrados" icon="ri-flask-line">
                @if($laboratoryEntries->isNotEmpty())
                    <div class="medical-stack medical-stack--compact">
                        @foreach($laboratoryEntries->take(8) as $lab)
                            <article class="medical-activity">
                                <strong>{{ $lab->title }}</strong>
                                <p>{{ $lab->summary ?: 'Sin resumen ni observaciones registradas.' }}</p>
                                <span>
                                    {{ $lab->ordered_at ? Carbon::parse($lab->ordered_at)->format('d/m/Y H:i') : 'Sin fecha registrada' }}
                                    | {{ Str::headline(str_replace('_', ' ', (string) $lab->status)) }}
                                    @if($lab->doctor_name)
                                        | {{ $lab->doctor_name }}
                                    @endif
                                </span>
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="medical-empty">No hay laboratorios integrados al expediente.</p>
                @endif
            </x-medical.card>

            <x-medical.card title="Consultas realizadas" subtitle="Atenciones cerradas dentro de la continuidad clinica" icon="ri-checkbox-circle-line" tone="success">
                @if($completedAppointments->isNotEmpty())
                    <div class="medical-stack medical-stack--compact">
                        @foreach($completedAppointments->take(6) as $appointment)
                            <article class="medical-activity">
                                <strong>{{ $formatDateTime($appointment->fecha, $appointment->hora) }}</strong>
                                <p>{{ $appointment->especialidad?->nombre ?? 'Consulta realizada' }}</p>
                                <span>{{ $appointment->doctor?->name ?? 'Profesional no asignado' }}</span>
                                @php
                                    $completedAppointmentUrl = $noteUrl($appointment->id);
                                @endphp
                                @if($completedAppointmentUrl)
                                    <a href="{{ $completedAppointmentUrl }}" class="medical-chip">
                                        <i class="ri-eye-line"></i> Ver atencion
                                    </a>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="medical-empty">No hay consultas cerradas registradas todavia.</p>
                @endif
            </x-medical.card>
        </aside>
    </section>

    <section class="medical-record-expanded">
        <div class="medical-record-expanded__main">
            <x-medical.card title="Consultas y notas medicas" subtitle="Atenciones individuales registradas dentro del expediente" icon="ri-stethoscope-line">
                @if($notes->isNotEmpty())
                    <div class="medical-stack">
                        @foreach($notes->take(8) as $note)
                            @php
                                $diagnosisText = $note->diagnosticos->pluck('texto')->filter()->implode(', ');
                                $noteDate = $note->cita ? $formatDateTime($note->cita->fecha, $note->cita->hora) : 'Sin fecha de consulta';
                                $noteDetailUrl = $note->cita_id ? $noteUrl($note->cita_id) : null;
                            @endphp
                            <article class="medical-entry">
                                <header class="medical-entry__header">
                                    <div>
                                        <strong>{{ $noteDate }}</strong>
                                        <small>
                                            {{ $note->cita?->especialidad?->nombre ?? 'Consulta' }}
                                            | {{ $note->cita?->doctor?->name ?? 'Profesional no registrado' }}
                                        </small>
                                    </div>
                                    @if($noteDetailUrl)
                                        <div class="medical-entry__actions">
                                            <a href="{{ $noteDetailUrl }}" class="medical-chip medical-chip--strong">
                                                <i class="ri-file-list-3-line"></i> Ver nota
                                            </a>
                                        </div>
                                    @endif
                                </header>

                                <dl class="medical-entry__grid">
                                    <div>
                                        <dt>Motivo de consulta</dt>
                                        <dd>{{ $note->subjetivo_motivo ?: 'Sin motivo consignado.' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Evaluacion clinica</dt>
                                        <dd>{{ $note->assessment ?: 'Sin evaluacion registrada.' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Diagnosticos de la nota</dt>
                                        <dd>{{ $diagnosisText ?: 'Sin diagnosticos consignados.' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Plan terapeutico</dt>
                                        <dd>{{ $note->plan_general ?: 'Sin plan general registrado.' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Seguimiento</dt>
                                        <dd>
                                            @if($note->follow_up_date)
                                                {{ $note->follow_up_date->format('d/m/Y') }}
                                                @if($note->follow_up_notes)
                                                    | {{ $note->follow_up_notes }}
                                                @endif
                                            @else
                                                {{ $note->follow_up_notes ?: ($note->plan_seguimiento ?: 'Sin seguimiento documentado.') }}
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Enmiendas</dt>
                                        <dd>{{ $note->enmiendas->count() }} registradas</dd>
                                    </div>
                                </dl>
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="medical-empty">Todavia no existen notas clinicas firmadas vinculadas al expediente.</p>
                @endif
            </x-medical.card>
        </div>

        <div class="medical-record-expanded__side">
            <x-medical.card title="Cronologia clinica" subtitle="Consultas, recetas y laboratorios vinculados" icon="ri-time-line">
                @if($chronology->isNotEmpty())
                    <ol class="medical-timeline">
                        @foreach($chronology as $event)
                            @php
                                $eventUrl = !empty($event['cita_id']) ? $noteUrl($event['cita_id']) : null;
                            @endphp
                            <li>
                                <span class="medical-timeline__date">
                                    {{ $event['at'] ? Carbon::parse($event['at'])->format('d/m/Y H:i') : 'Sin fecha' }}
                                </span>
                                <p class="medical-timeline__title">
                                    <i class="{{ match ($event['type']) {
                                        'certificado' => 'ri-file-shield-2-line',
                                        'receta' => 'ri-capsule-line',
                                        'laboratorio' => 'ri-flask-line',
                                        default => 'ri-stethoscope-line',
                                    } }}"></i>
                                    {{ ucfirst($event['type']) }}: {{ $event['title'] }}
                                </p>
                                <p>{{ $event['detail'] ?: 'Sin detalle adicional.' }}</p>
                                @if(!empty($event['doctor']))
                                    <p class="medical-timeline__meta">Profesional: {{ $event['doctor'] }}</p>
                                @endif
                                @if($eventUrl)
                                    <a href="{{ $eventUrl }}" class="medical-chip">
                                        <i class="ri-external-link-line"></i> Abrir consulta
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="medical-empty">No hay eventos clinicos consolidados en la cronologia.</p>
                @endif
            </x-medical.card>
        </div>
    </section>

    @if($recordEditable)
    <x-medical.card title="Actualización de datos maestros del expediente" subtitle="Mantenimiento estructurado del historial clinico longitudinal" icon="ri-edit-2-line" class="medical-editor-card">
        <form method="POST" action="{{ route('doctor.pacientes.historial.update', $paciente) }}" class="medical-editor">
            @csrf
            @method('PUT')

            <div class="medical-editor__grid">
                <details class="medical-editor__accordion" @if($editorAccordionState['summary']) open @endif>
                    <summary class="medical-editor__accordion-summary">
                        <div class="medical-editor__accordion-copy">
                            <span class="medical-editor__accordion-icon" aria-hidden="true"><i class="ri-shield-cross-line"></i></span>
                            <div>
                                <h4>Resumen general y alergias</h4>
                                <p>Estos datos se reutilizan en todo el expediente y dan contexto a futuras consultas.</p>
                            </div>
                        </div>
                        <div class="medical-editor__accordion-meta">
                            <span class="medical-editor__counter">{{ $entriesLabel($editorCounts['summary']) }}</span>
                            <i class="ri-arrow-down-s-line medical-editor__chevron" aria-hidden="true"></i>
                        </div>
                    </summary>
                    <section class="medical-editor__panel medical-editor__panel--accordion">
                        <div class="medical-field medical-field--full">
                            <label class="form-label" for="clinical_summary">Resumen clinico general del paciente</label>
                            <textarea id="clinical_summary" name="clinical_summary" class="form-textarea" rows="5">{{ old('clinical_summary', $record->clinical_summary) }}</textarea>
                        </div>

                        <div class="medical-field">
                            <label class="form-label" for="allergies_status">Estado general de alergias</label>
                            <select id="allergies_status" name="allergies_status" class="form-select">
                                <option value="{{ ClinicalRecord::ALLERGIES_UNKNOWN }}" @selected(old('allergies_status', $record->allergies_status) === ClinicalRecord::ALLERGIES_UNKNOWN)>Sin confirmar</option>
                                <option value="{{ ClinicalRecord::ALLERGIES_NONE }}" @selected(old('allergies_status', $record->allergies_status) === ClinicalRecord::ALLERGIES_NONE)>Sin alergias conocidas</option>
                                <option value="{{ ClinicalRecord::ALLERGIES_DOCUMENTED }}" @selected(old('allergies_status', $record->allergies_status) === ClinicalRecord::ALLERGIES_DOCUMENTED)>Alergias documentadas</option>
                            </select>
                        </div>

                        <div class="medical-editor__collection" data-repeatable-group data-next-index="{{ count($allergyRows) }}">
                            <div class="medical-editor__rows" data-repeatable-target>
                                @foreach($allergyRows as $index => $row)
                                    @include('doctor.partials.clinical-record-editor.allergy-item', ['index' => $index, 'row' => $row])
                                @endforeach
                            </div>
                            <div class="medical-editor__add-row">
                                <button type="button" class="btn btn-ghost btn-sm" data-repeatable-add>
                                    <i class="ri-add-line"></i> Agregar alergia
                                </button>
                            </div>
                            <template data-repeatable-template>
                                @include('doctor.partials.clinical-record-editor.allergy-item', ['index' => '__INDEX__', 'row' => $blankAllergyRow])
                            </template>
                        </div>
                    </section>
                </details>

                <details class="medical-editor__accordion" @if($editorAccordionState['history']) open @endif>
                    <summary class="medical-editor__accordion-summary">
                        <div class="medical-editor__accordion-copy">
                            <span class="medical-editor__accordion-icon" aria-hidden="true"><i class="ri-file-history-line"></i></span>
                            <div>
                                <h4>Antecedentes e inmunizaciones</h4>
                                <p>Mantiene la informacion maestra que ya no debe depender de texto libre dentro de una sola consulta.</p>
                            </div>
                        </div>
                        <div class="medical-editor__accordion-meta">
                            <span class="medical-editor__counter">{{ $entriesLabel($editorCounts['history']) }}</span>
                            <i class="ri-arrow-down-s-line medical-editor__chevron" aria-hidden="true"></i>
                        </div>
                    </summary>
                    <section class="medical-editor__panel medical-editor__panel--accordion">
                        @foreach($editorHistoryGroups as $field => $config)
                            <details class="medical-editor__subgroup" @if($historyGroupState[$field] ?? false) open @endif>
                                <summary class="medical-editor__subgroup-summary">
                                    <div>
                                        <h5>{{ $config['title'] }}</h5>
                                        <p>Registros estructurados del expediente clinico del paciente.</p>
                                    </div>
                                    <div class="medical-editor__accordion-meta">
                                        <span class="medical-editor__counter">{{ $entriesLabel($countFilledRows($config['rows'], ['title', 'relation_label', 'description', 'notes'])) }}</span>
                                        <i class="ri-arrow-down-s-line medical-editor__chevron" aria-hidden="true"></i>
                                    </div>
                                </summary>
                                <div class="medical-editor__subgroup-body">
                                    <div class="medical-editor__collection" data-repeatable-group data-next-index="{{ count($config['rows']) }}">
                                        <div class="medical-editor__rows" data-repeatable-target>
                                            @foreach($config['rows'] as $index => $row)
                                                @include('doctor.partials.clinical-record-editor.history-item', [
                                                    'field' => $field,
                                                    'index' => $index,
                                                    'row' => $row,
                                                    'showRelation' => $config['show_relation'],
                                                    'itemTitle' => $config['item_title'],
                                                ])
                                            @endforeach
                                        </div>
                                        <div class="medical-editor__add-row">
                                            <button type="button" class="btn btn-ghost btn-sm" data-repeatable-add>
                                                <i class="ri-add-line"></i> {{ $config['add_label'] }}
                                            </button>
                                        </div>
                                        <template data-repeatable-template>
                                            @include('doctor.partials.clinical-record-editor.history-item', [
                                                'field' => $field,
                                                'index' => '__INDEX__',
                                                'row' => $config['blank_row'],
                                                'showRelation' => $config['show_relation'],
                                                'itemTitle' => $config['item_title'],
                                            ])
                                        </template>
                                    </div>
                                </div>
                            </details>
                        @endforeach
                    </section>
                </details>

                <details class="medical-editor__accordion" @if($editorAccordionState['problems']) open @endif>
                    <summary class="medical-editor__accordion-summary">
                        <div class="medical-editor__accordion-copy">
                            <span class="medical-editor__accordion-icon" aria-hidden="true"><i class="ri-heart-pulse-line"></i></span>
                            <div>
                                <h4>Problemas clinicos longitudinales</h4>
                                <p>Permite que los diagnosticos importantes no queden perdidos dentro de una sola nota medica.</p>
                            </div>
                        </div>
                        <div class="medical-editor__accordion-meta">
                            <span class="medical-editor__counter">{{ $entriesLabel($editorCounts['problems']) }}</span>
                            <i class="ri-arrow-down-s-line medical-editor__chevron" aria-hidden="true"></i>
                        </div>
                    </summary>
                    <section class="medical-editor__panel medical-editor__panel--accordion">
                        <div class="medical-editor__collection" data-repeatable-group data-next-index="{{ count($problemRows) }}">
                            <div class="medical-editor__rows" data-repeatable-target>
                                @foreach($problemRows as $index => $row)
                                    @include('doctor.partials.clinical-record-editor.problem-item', ['index' => $index, 'row' => $row])
                                @endforeach
                            </div>
                            <div class="medical-editor__add-row">
                                <button type="button" class="btn btn-ghost btn-sm" data-repeatable-add>
                                    <i class="ri-add-line"></i> Agregar problema clinico
                                </button>
                            </div>
                            <template data-repeatable-template>
                                @include('doctor.partials.clinical-record-editor.problem-item', ['index' => '__INDEX__', 'row' => $blankProblemRow])
                            </template>
                        </div>
                    </section>
                </details>

                <details class="medical-editor__accordion" @if($editorAccordionState['medications']) open @endif>
                    <summary class="medical-editor__accordion-summary">
                        <div class="medical-editor__accordion-copy">
                            <span class="medical-editor__accordion-icon" aria-hidden="true"><i class="ri-medicine-bottle-line"></i></span>
                            <div>
                                <h4>Medicacion habitual y alertas manuales</h4>
                                <p>Control estructurado de tratamiento vigente y advertencias clinicas registradas manualmente.</p>
                            </div>
                        </div>
                        <div class="medical-editor__accordion-meta">
                            <span class="medical-editor__counter">{{ $entriesLabel($editorCounts['medications']) }}</span>
                            <i class="ri-arrow-down-s-line medical-editor__chevron" aria-hidden="true"></i>
                        </div>
                    </summary>
                    <section class="medical-editor__panel medical-editor__panel--accordion">
                        <details class="medical-editor__subgroup" @if($medicationGroupState['medications']) open @endif>
                            <summary class="medical-editor__subgroup-summary">
                                <div>
                                    <h5>Medicacion habitual</h5>
                                    <p>Tratamientos cronicos, suspendidos o completados vinculados al expediente.</p>
                                </div>
                                <div class="medical-editor__accordion-meta">
                                    <span class="medical-editor__counter">{{ $entriesLabel($countFilledRows($medicationRows, ['name', 'presentation', 'dosage', 'frequency', 'instructions'])) }}</span>
                                    <i class="ri-arrow-down-s-line medical-editor__chevron" aria-hidden="true"></i>
                                </div>
                            </summary>
                            <div class="medical-editor__subgroup-body">
                                <div class="medical-editor__collection" data-repeatable-group data-next-index="{{ count($medicationRows) }}">
                                    <div class="medical-editor__rows" data-repeatable-target>
                                        @foreach($medicationRows as $index => $row)
                                            @include('doctor.partials.clinical-record-editor.medication-item', ['index' => $index, 'row' => $row])
                                        @endforeach
                                    </div>
                                    <div class="medical-editor__add-row">
                                        <button type="button" class="btn btn-ghost btn-sm" data-repeatable-add>
                                            <i class="ri-add-line"></i> Agregar medicacion
                                        </button>
                                    </div>
                                    <template data-repeatable-template>
                                        @include('doctor.partials.clinical-record-editor.medication-item', ['index' => '__INDEX__', 'row' => $blankMedicationRow])
                                    </template>
                                </div>
                            </div>
                        </details>

                        <details class="medical-editor__subgroup" @if($medicationGroupState['alerts']) open @endif>
                            <summary class="medical-editor__subgroup-summary">
                                <div>
                                    <h5>Alertas clinicas manuales</h5>
                                    <p>Advertencias clinicas o de contexto agregadas manualmente al expediente.</p>
                                </div>
                                <div class="medical-editor__accordion-meta">
                                    <span class="medical-editor__counter">{{ $entriesLabel($countFilledRows($alertRows, ['title', 'description'])) }}</span>
                                    <i class="ri-arrow-down-s-line medical-editor__chevron" aria-hidden="true"></i>
                                </div>
                            </summary>
                            <div class="medical-editor__subgroup-body">
                                <div class="medical-editor__collection" data-repeatable-group data-next-index="{{ count($alertRows) }}">
                                    <div class="medical-editor__rows" data-repeatable-target>
                                        @foreach($alertRows as $index => $row)
                                            @include('doctor.partials.clinical-record-editor.alert-item', ['index' => $index, 'row' => $row])
                                        @endforeach
                                    </div>
                                    <div class="medical-editor__add-row">
                                        <button type="button" class="btn btn-ghost btn-sm" data-repeatable-add>
                                            <i class="ri-add-line"></i> Agregar alerta
                                        </button>
                                    </div>
                                    <template data-repeatable-template>
                                        @include('doctor.partials.clinical-record-editor.alert-item', ['index' => '__INDEX__', 'row' => $blankAlertRow])
                                    </template>
                                </div>
                            </div>
                        </details>
                    </section>
                </details>
            </div>

            <div class="medical-editor__footer">
                <p class="medical-editor__footnote">
                    Esta seccion actualiza el expediente longitudinal del paciente. No reemplaza la nota clinica individual de cada consulta.
                </p>
                <div class="medical-editor__actions">
                    <a href="{{ $backUrl }}" class="btn btn-ghost">{{ $backLabel }}</a>
                    <button type="submit" class="btn btn-primary">Guardar expediente clinico</button>
                </div>
            </div>
        </form>
    </x-medical.card>
    @endif
</div>
@endsection
