@extends($pageLayout ?? 'layouts.doctor')
@section('title', $pageTitle ?? 'Expediente clínico del paciente')
@section('activeSidebar', $pageActiveSidebar ?? 'pacientes')
@section('header-title', $pageHeaderTitle ?? ($patient->name ?? 'Expediente clínico del paciente'))
@section('header-subtitle', $pageHeaderSubtitle ?? 'Resumen longitudinal del paciente, separado de citas y notas individuales')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/medical-record.css') }}">
    <link rel="stylesheet" href="{{ asset('css/medical-record-v2.css') }}">
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

    $isDependiente = (bool) $record->dependiente_id;
    $patient = (object)[
        'name' => $isDependiente ? $record->dependiente->nombre : ($record->patient->name ?? $paciente->name),
        'dni' => $isDependiente ? $record->dependiente->dni : ($record->patient->dni ?? $paciente->dni),
        'sexo' => $isDependiente ? $record->dependiente->sexo : ($record->patient->sexo ?? $paciente->sexo),
        'telefono' => $isDependiente ? $record->dependiente->telefono_emergencia : ($record->patient->telefono ?? $paciente->telefono),
        'email' => $isDependiente ? null : ($record->patient->email ?? $paciente->email),
        'fecha_nacimiento' => $isDependiente ? $record->dependiente->fecha_nacimiento : ($record->patient->fecha_nacimiento ?? $paciente->fecha_nacimiento),
        'avatar_medium_url' => $isDependiente ? ($record->dependiente?->avatar_medium_url ?? '') : ($record->patient?->avatar_medium_url ?? $paciente->avatar_medium_url),
    ];
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

    $noteUrl = function ($target) use ($noteRouteName) {
        $route = $noteRouteName ?? (Route::has('doctor.citas.soap') ? 'doctor.citas.soap' : null);
        if (! $route || ! $target) {
            return null;
        }

        if ($route === 'admin.historial.nota' || $route === 'historial.nota') {
            if ($target instanceof \App\Models\NotaSoap) {
                return route($route, $target->id);
            }
            if ($target instanceof \App\Models\Cita) {
                $noteId = $target->notaSoap?->id;
                return $noteId ? route($route, $noteId) : null;
            }
            return route($route, $target);
        }

        if ($target instanceof \App\Models\NotaSoap) {
            return route($route, $target->cita_id);
        }
        if ($target instanceof \App\Models\Cita) {
            return route($route, $target->id);
        }

        return route($route, $target);
    };

    $prescriptionUrl = function ($citaId) use ($prescriptionRouteName) {
        $route = $prescriptionRouteName ?? (Route::has('doctor.recetas.show') ? 'doctor.recetas.show' : null);
        return $route && $citaId ? route($route, $citaId) : null;
    };

    $certificateUrl = function ($certificate) use ($certificateRouteName) {
        $route = $certificateRouteName ?? (Route::has('doctor.certificados.show') ? 'doctor.certificados.show' : null);
        return $route && $certificate?->id ? route($route, $certificate) : null;
    };

    $certificateDownloadUrl = function ($certificate) use ($certificateDownloadRouteName) {
        $route = $certificateDownloadRouteName ?? (Route::has('doctor.certificados.pdf') ? 'doctor.certificados.pdf' : null);
        return $route && $certificate?->id ? route($route, $certificate) : null;
    };

    $pdfExportUrl = ($allowActionLinks && Route::has('doctor.pacientes.historial.pdf'))
        ? route('doctor.pacientes.historial.pdf', $paciente).($record->dependiente_id ? '?dependiente_id='.$record->dependiente_id : '')
        : ((!($allowActionLinks ?? true) && Route::has('admin.historial.pdf'))
            ? route('admin.historial.pdf', $paciente).($record->dependiente_id ? '?dependiente_id='.$record->dependiente_id : '')
            : null);

    $lastAttendance = $completedAppointments->first();
    $lastAttendanceDisplay = $lastAttendance
        ? $formatDateTime($lastAttendance->fecha, $lastAttendance->hora) . ' · ' . ($lastAttendance->especialidad?->nombre ?? 'Consulta')
        : null;
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

    @include('doctor.partials.expediente-display')



    @if($recordEditable)
    <x-medical.card title="Actualización de datos maestros del expediente" subtitle="Mantenimiento estructurado del historial clinico longitudinal" icon="ri-edit-2-line" class="medical-editor-card">
        <form method="POST" action="{{ route('doctor.pacientes.historial.update', $paciente).($record->dependiente_id ? '?dependiente_id='.$record->dependiente_id : '') }}" class="medical-editor">
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
