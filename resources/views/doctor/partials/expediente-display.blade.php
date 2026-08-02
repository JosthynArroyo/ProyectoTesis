@php use Carbon\Carbon; @endphp

{{-- ===== PATIENT HEADER ===== --}}
<div class="mr-patient-header">
    @if(!empty($patient->avatar_medium_url))
        <img src="{{ $patient->avatar_medium_url }}" alt="{{ $patient->name }}" class="mr-patient-header__avatar object-cover border border-gray-200" loading="lazy" decoding="async">
    @else
        <div class="mr-patient-header__avatar" aria-hidden="true">{{ $patientInitials ?: 'P' }}</div>
    @endif
    <div class="mr-patient-header__info">
        <h2 class="mr-patient-header__name">{{ $patient->name }}</h2>
        <div class="mr-patient-header__meta">
            @if($age !== null)
                <span class="mr-patient-header__meta-item"><i class="ri-calendar-line"></i> <strong>{{ $age }} años</strong></span>
            @endif
            @if($patient->sexo)
                <span class="mr-patient-header__meta-item"><i class="ri-user-line"></i> <strong>{{ $patient->sexo }}</strong></span>
            @endif
            @if($patient->dni)
                <span class="mr-patient-header__meta-item"><i class="ri-id-card-line"></i> <strong>{{ $patient->dni }}</strong></span>
            @endif
            @if($patient->telefono)
                <span class="mr-patient-header__meta-item"><i class="ri-phone-line"></i> <strong>{{ $patient->telefono }}</strong></span>
            @endif
        </div>
    </div>
    <div class="mr-patient-header__dates">
        <div class="mr-patient-header__date-pill">
            <i class="ri-history-line"></i>
            <span class="label">Última atención</span>
            @if($lastAttendanceDisplay)
                <span class="value">{{ $formatDate($lastAttendance->fecha) }}</span>
                <span class="sub">{{ $lastAttendance->especialidad?->nombre ?? 'Consulta' }}</span>
            @else
                <span class="value">—</span>
            @endif
        </div>
        <div class="mr-patient-header__date-pill">
            <i class="ri-calendar-check-line"></i>
            <span class="label">Próxima cita</span>
            @if($nextAppointment)
                <span class="value">{{ $formatDateTime($nextAppointment->fecha, $nextAppointment->hora) }}</span>
                <span class="sub">{{ $nextAppointment->especialidad?->nombre ?? 'Control' }}{{ $nextAppointment->doctor?->name ? ' · '.$nextAppointment->doctor->name : '' }}</span>
            @else
                <span class="value">—</span>
            @endif
        </div>
    </div>
    @php $hasAllergies = $record->allergies->isNotEmpty(); @endphp
    <div class="mr-patient-header__alerts {{ !$hasAllergies ? 'mr-patient-header__alerts--ok' : '' }}">
        <div class="alerts-title">
            <i class="ri-alarm-warning-line"></i>
            {{ $hasAllergies ? 'Alergias / Alertas' : 'Sin alergias conocidas' }}
        </div>
        @if($hasAllergies)
            <ul class="alerts-list">
                @foreach($record->allergies->take(3) as $allergy)
                    <li>{{ $allergy->allergen }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

{{-- ===== STATS ROW ===== --}}
<div class="mr-stats-row">
    <div class="mr-stat-card">
        <div class="mr-stat-card__icon mr-stat-card__icon--teal"><i class="ri-heart-pulse-line"></i></div>
        <div class="mr-stat-card__body">
            <div class="mr-stat-card__value">{{ $activeProblems->count() }}</div>
            <div class="mr-stat-card__label">Problemas activos</div>
        </div>
    </div>
    <div class="mr-stat-card">
        <div class="mr-stat-card__icon mr-stat-card__icon--amber"><i class="ri-medicine-bottle-line"></i></div>
        <div class="mr-stat-card__body">
            <div class="mr-stat-card__value">{{ $currentMedications->count() }}</div>
            <div class="mr-stat-card__label">Medicación actual</div>
        </div>
    </div>
    <div class="mr-stat-card">
        <div class="mr-stat-card__icon mr-stat-card__icon--rose"><i class="ri-alarm-warning-line"></i></div>
        <div class="mr-stat-card__body">
            <div class="mr-stat-card__value">{{ $record->allergies->count() + $activeAlerts->count() }}</div>
            <div class="mr-stat-card__label">Alergias y alertas</div>
        </div>
    </div>
    <div class="mr-stat-card">
        <div class="mr-stat-card__icon mr-stat-card__icon--blue"><i class="ri-calendar-event-line"></i></div>
        <div class="mr-stat-card__body">
            <div class="mr-stat-card__value">{{ $notes->count() }}</div>
            <div class="mr-stat-card__label">Notas clínicas</div>
        </div>
    </div>
</div>



{{-- ===== CENTER GRID: Summary + Vitals (SIDE BY SIDE, NO STRETCHING) ===== --}}
<div class="mr-center-grid">
    <div class="mr-clinical-summary">
        <h3 class="mr-clinical-summary__title"><i class="ri-file-text-line"></i> Resumen clínico</h3>
        <p class="mr-clinical-summary__text">
            {{ $record->clinical_summary ?: 'Sin resumen clínico consolidado para este paciente.' }}
        </p>
        @if($latestNote)
            <div class="mr-clinical-note">
                <div class="mr-clinical-note__left">
                    <h4 class="mr-clinical-note__title">
                        <i class="ri-history-line"></i>
                        <span>Última nota clínica<br>firmada</span>
                    </h4>
                </div>
                <div class="mr-clinical-note__middle">
                    <p class="mr-clinical-note__meta">
                        {{ $latestNote->cita ? $formatDateTime($latestNote->cita->fecha, $latestNote->cita->hora) : '—' }}
                        · {{ $latestNote->cita?->especialidad?->nombre ?? 'Consulta' }}
                        · {{ $latestNote->cita?->doctor?->name ?? '—' }}
                    </p>
                </div>
                <div class="mr-clinical-note__right">
                    @if($latestNote->assessment)
                        <p class="mr-clinical-note__body">{{ Str::limit($latestNote->assessment, 150) }}</p>
                    @endif
                </div>
            </div>
        @endif

        @php $latestNoteUrl = $latestNote?->cita_id ? $noteUrl($latestNote->cita_id) : null; @endphp
        @if($latestNoteUrl)
            <div class="mr-clinical-actions">
                <a href="{{ $latestNoteUrl }}" class="btn btn-primary btn-sm"><i class="ri-stethoscope-line"></i> Abrir consulta</a>
                <a href="{{ $latestNoteUrl }}" class="btn btn-outline btn-sm"><i class="ri-file-text-line"></i> Ver última nota</a>
            </div>
        @endif
    </div>
    <div class="mr-vitals-block">
        <div class="mr-vitals-block__title">
            <h3><i class="ri-heart-pulse-line"></i> Últimos signos vitales</h3>
            <span>{{ $latestNote?->cita ? $formatDateTime($latestNote->cita->fecha, $latestNote->cita->hora) : '' }}</span>
        </div>
        <div class="mr-vitals-grid">
            @php
                $vitalIcons = [
                    'ta' => 'ri-heart-line', 'fc' => 'ri-pulse-line', 'fr' => 'ri-lungs-line',
                    'temp' => 'ri-temp-hot-line', 'spo2' => 'ri-drop-line', 'peso' => 'ri-scales-3-line',
                    'talla' => 'ri-ruler-line',
                ];
            @endphp
            @foreach($vitalSnapshot as $metric)
                <div class="mr-vital-item">
                    <i class="{{ $vitalIcons[$metric['key']] ?? 'ri-heart-line' }}"></i>
                    <div class="mr-vital-item__body">
                        <div class="mr-vital-item__label">{{ $metric['label'] }}</div>
                        <div class="mr-vital-item__value">{{ $metric['display'] }}</div>
                    </div>
                </div>
            @endforeach
            @if($vitalSnapshot->contains('key', 'peso') && $vitalSnapshot->contains('key', 'talla'))
                @php
                    $pesoVal = (float) ($vitalSnapshot->firstWhere('key','peso')['value'] ?? 0);
                    $tallaVal = (float) ($vitalSnapshot->firstWhere('key','talla')['value'] ?? 0);
                    $imc = ($pesoVal > 0 && $tallaVal > 0) ? round($pesoVal / (($tallaVal/100) ** 2), 1) : null;
                @endphp
                @if($imc)
                    <div class="mr-vital-item">
                        <i class="ri-body-scan-line"></i>
                        <div class="mr-vital-item__body">
                            <div class="mr-vital-item__label">IMC</div>
                            <div class="mr-vital-item__value">{{ $imc }} kg/m²</div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>

{{-- ===== ANTECEDENTES ===== --}}
<div class="mr-antecedentes">
    <h3 class="mr-antecedentes__title"><i class="ri-file-history-line"></i> Antecedentes clínicos</h3>
    <div class="mr-antecedentes__grid">
        @php
            $antIcons = ['ri-user-heart-line','ri-parent-line','ri-surgical-mask-line','ri-hospital-line','ri-syringe-line'];
        @endphp
        @foreach($historyDisplayGroups as $i => $group)
            <div class="mr-antecedente-card">
                <h4 class="mr-antecedente-card__title"><i class="{{ $antIcons[$i] ?? 'ri-file-line' }}"></i> {{ $group['title'] }}</h4>
                <div class="mr-antecedente-card__body">
                    @if($group['items']->isNotEmpty())
                        @foreach($group['items']->take(3) as $item)
                            <div class="detail">{{ $item->title }}{{ $item->occurred_on ? ' · '.$item->occurred_on->format('d/m/Y') : '' }}</div>
                        @endforeach
                        @if($group['items']->count() > 3)
                            <span class="link" style="cursor:default">+{{ $group['items']->count() - 3 }} más</span>
                        @endif
                    @else
                        Sin registros.
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- ===== BOTTOM GRID: Notes + Sidebar ===== --}}
<div class="mr-bottom-grid">
    <div>
        <x-medical.card title="Consultas y notas médicas" subtitle="Atenciones individuales del expediente" icon="ri-stethoscope-line">
            @if($notes->isNotEmpty())
                <div class="medical-stack">
                    @foreach($notes->take(6) as $note)
                        @php
                            $noteDate = $note->cita ? $formatDateTime($note->cita->fecha, $note->cita->hora) : 'Sin fecha';
                            $noteDetailUrl = $note->cita_id ? $noteUrl($note->cita_id) : null;
                        @endphp
                        <article class="medical-entry medical-entry--compact">
                            <header class="medical-entry__header">
                                <div>
                                    <strong>{{ $noteDate }}</strong>
                                    <small>{{ $note->cita?->especialidad?->nombre ?? 'Consulta' }} · {{ $note->cita?->doctor?->name ?? '—' }}</small>
                                </div>
                                @if($noteDetailUrl)
                                    <div class="medical-entry__actions">
                                        <a href="{{ $noteDetailUrl }}" class="medical-chip medical-chip--strong"><i class="ri-file-list-3-line"></i> Ver nota</a>
                                    </div>
                                @endif
                            </header>
                            <dl class="medical-entry__grid">
                                <div><dt>Motivo</dt><dd>{{ Str::limit($note->subjetivo_motivo, 100) ?: '—' }}</dd></div>
                                <div><dt>Evaluación</dt><dd>{{ Str::limit($note->assessment, 100) ?: '—' }}</dd></div>
                            </dl>
                        </article>
                    @endforeach
                </div>
            @else
                <p class="medical-empty">Sin registros.</p>
            @endif
        </x-medical.card>

        @if($activeProblems->isNotEmpty() || $resolvedProblems->isNotEmpty())
        <x-medical.card title="Problemas clínicos" subtitle="Diagnósticos longitudinales" icon="ri-heart-pulse-line" style="margin-top:.75rem">
            <div class="medical-stack medical-stack--compact">
                @foreach($activeProblems as $problem)
                    <article class="medical-activity">
                        <strong>{{ $problem->name }} <span class="mr-diag-badge mr-diag-badge--active">{{ $problemStatusLabels[$problem->status] ?? 'Activo' }}</span></strong>
                        <p>{{ collect([$problem->cie10 ? 'CIE-10 '.$problem->cie10 : null, $problem->is_chronic ? 'Crónico' : null, $problem->started_at ? 'Desde '.$problem->started_at->format('d/m/Y') : null])->filter()->implode(' · ') ?: '—' }}</p>
                        @if($problem->notes)<span>{{ Str::limit($problem->notes, 120) }}</span>@endif
                    </article>
                @endforeach
                @foreach($resolvedProblems->take(3) as $problem)
                    <article class="medical-activity">
                        <strong>{{ $problem->name }} <span class="mr-diag-badge mr-diag-badge--resolved">Resuelto</span></strong>
                        <span>{{ $problem->resolved_at ? 'Resuelto '.$problem->resolved_at->format('d/m/Y') : '' }} {{ $problem->cie10 ? '· CIE-10 '.$problem->cie10 : '' }}</span>
                    </article>
                @endforeach
            </div>
        </x-medical.card>
        @endif

        @if($currentMedications->isNotEmpty() || $inactiveMedications->isNotEmpty())
        <x-medical.card title="Medicación habitual" subtitle="Tratamientos del expediente" icon="ri-medicine-bottle-line" style="margin-top:.75rem">
            <div class="medical-stack medical-stack--compact">
                @foreach($currentMedications as $med)
                    <article class="medical-activity">
                        <strong>{{ $med->name }}</strong>
                        <p>{{ collect([$med->presentation, $med->dosage, $med->frequency, $med->route])->filter()->implode(' · ') ?: '—' }}</p>
                        <span>{{ $medicationStatusLabels[$med->status] ?? 'Activa' }}{{ $med->started_at ? ' · Desde '.$med->started_at->format('d/m/Y') : '' }}</span>
                    </article>
                @endforeach
                @foreach($inactiveMedications->take(2) as $med)
                    <article class="medical-activity" style="opacity:.7">
                        <strong>{{ $med->name }}</strong>
                        <span>{{ $medicationStatusLabels[$med->status] ?? ucfirst($med->status) }}{{ $med->ended_at ? ' · Hasta '.$med->ended_at->format('d/m/Y') : '' }}</span>
                    </article>
                @endforeach
            </div>
        </x-medical.card>
        @endif
    </div>

    <div>
        <div class="mr-sidebar-card">
            <div class="mr-sidebar-card__header">
                <h4 class="mr-sidebar-card__title"><i class="ri-health-book-line"></i> Diagnósticos recientes</h4>
            </div>
            @if($recentDiagnoses->isNotEmpty())
                @foreach($recentDiagnoses->take(5) as $diag)
                    <div class="mr-sidebar-item">
                        <span class="mr-sidebar-item__title">
                            {{ $diag['at'] ? $formatDate($diag['at']) : '' }}
                            <span class="mr-diag-badge mr-diag-badge--active">{{ ucfirst((string) $diag['type']) }}</span>
                        </span>
                        <span class="mr-sidebar-item__meta">{{ $diag['text'] }}{{ $diag['cie10'] ? ' · '.$diag['cie10'] : '' }}</span>
                    </div>
                @endforeach
            @else
                <p class="medical-empty">Sin registros.</p>
            @endif
        </div>
        <div class="mr-sidebar-card">
            <div class="mr-sidebar-card__header">
                <h4 class="mr-sidebar-card__title"><i class="ri-capsule-line"></i> Recetas vinculadas</h4>
                <span class="mr-sidebar-card__count">{{ $prescriptions->count() > 0 ? $prescriptions->count().' receta(s)' : '' }}</span>
            </div>
            @if($prescriptions->isNotEmpty())
                @foreach($prescriptions->take(3) as $rx)
                    @php $rxUrl = $rx->cita_id ? $prescriptionUrl($rx->cita_id) : null; @endphp
                    <div class="mr-sidebar-item">
                        <span class="mr-sidebar-item__title">{{ $rx->created_at?->format('d/m/Y') ?? '—' }} · {{ $rx->cita?->especialidad?->nombre ?? 'Consulta' }}</span>
                        <span class="mr-sidebar-item__meta">{{ Str::limit($rx->diagnostico, 80) ?: '—' }}</span>
                        @if($rxUrl)
                            <div class="mr-sidebar-item__actions">
                                <a href="{{ $rxUrl }}" class="mr-sidebar-link"><i class="ri-arrow-right-s-line"></i> Ver receta</a>
                            </div>
                        @endif
                    </div>
                @endforeach
            @else
                <p class="medical-empty">Sin registros.</p>
            @endif
        </div>
        <div class="mr-sidebar-card">
            <div class="mr-sidebar-card__header">
                <h4 class="mr-sidebar-card__title"><i class="ri-file-shield-2-line"></i> Certificados médicos</h4>
            </div>
            @if($certificates->isNotEmpty())
                @foreach($certificates->take(3) as $cert)
                    @php $certUrl = $certificateUrl($cert); $certPdf = $certificateDownloadUrl($cert); @endphp
                    <div class="mr-sidebar-item">
                        <span class="mr-sidebar-item__title">{{ $cert->codigo }} · {{ $cert->fecha_emision?->format('d/m/Y') ?? '—' }}</span>
                        <span class="mr-sidebar-item__meta">{{ $cert->dias_reposo > 0 ? 'Reposo '.$cert->dias_reposo.' día(s)' : Str::limit($cert->texto_constancia, 60) }}</span>
                        <div class="mr-sidebar-item__actions">
                            @if($certUrl)<a href="{{ $certUrl }}" class="mr-sidebar-link"><i class="ri-eye-line"></i> Ver</a>@endif
                            @if($certPdf)<a href="{{ $certPdf }}" class="mr-sidebar-link"><i class="ri-download-2-line"></i> PDF</a>@endif
                        </div>
                    </div>
                @endforeach
            @else
                <p class="medical-empty">Sin registros.</p>
            @endif
        </div>
        <div class="mr-sidebar-card">
            <div class="mr-sidebar-card__header">
                <h4 class="mr-sidebar-card__title"><i class="ri-flask-line"></i> Laboratorios y resultados</h4>
            </div>
            @if($laboratoryEntries->isNotEmpty())
                @foreach($laboratoryEntries->take(4) as $lab)
                    <div class="mr-sidebar-item">
                        <span class="mr-sidebar-item__title">{{ $lab->title }}</span>
                        <span class="mr-sidebar-item__meta">
                            {{ $lab->ordered_at ? $formatDate($lab->ordered_at) : '—' }}
                            · {{ Str::headline(str_replace('_',' ',(string)$lab->status)) }}
                            {{ $lab->doctor_name ? ' · '.$lab->doctor_name : '' }}
                        </span>
                    </div>
                @endforeach
            @else
                <p class="medical-empty">Sin registros.</p>
            @endif
        </div>
    </div>
</div>
