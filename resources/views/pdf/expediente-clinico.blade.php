@php
    use App\Models\ClinicalRecord;
    use App\Models\ClinicalRecordAllergy;
    use App\Models\ClinicalRecordHistory;
    use App\Models\ClinicalRecordProblem;
    use App\Models\ClinicalRecordMedication;
    use App\Models\ClinicalRecordAlert;
    use Carbon\Carbon;
    use Illuminate\Support\Str;

    $formatDate = function ($date, string $fallback = '—') {
        if (! $date) return $fallback;
        return $date instanceof Carbon ? $date->format('d/m/Y') : Carbon::parse($date)->format('d/m/Y');
    };

    $formatDateTime = function ($date, $time = null, string $fallback = '—') use ($formatDate) {
        if (! $date) return $fallback;
        $value = $formatDate($date, $fallback);
        if ($time) $value .= ' ' . substr((string) $time, 0, 5);
        return trim($value);
    };

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

    $allergySeverityLabels = [
        ClinicalRecordAllergy::SEVERITY_UNKNOWN => 'Sin severidad definida',
        ClinicalRecordAllergy::SEVERITY_MILD => 'Leve',
        ClinicalRecordAllergy::SEVERITY_MODERATE => 'Moderada',
        ClinicalRecordAllergy::SEVERITY_SEVERE => 'Severa',
    ];

    $historyDisplayGroups = [
        ['title' => 'Personales', 'items' => $historiesByCategory[ClinicalRecordHistory::CATEGORY_PERSONAL] ?? collect()],
        ['title' => 'Familiares', 'items' => $historiesByCategory[ClinicalRecordHistory::CATEGORY_FAMILY] ?? collect(), 'show_relation' => true],
        ['title' => 'Quirúrgicos', 'items' => $historiesByCategory[ClinicalRecordHistory::CATEGORY_SURGERY] ?? collect()],
        ['title' => 'Hospitalizaciones', 'items' => $historiesByCategory[ClinicalRecordHistory::CATEGORY_HOSPITALIZATION] ?? collect()],
        ['title' => 'Inmunizaciones', 'items' => $historiesByCategory[ClinicalRecordHistory::CATEGORY_IMMUNIZATION] ?? collect()],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Expediente Clínico — {{ $patient->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1a1a2e; line-height: 1.5; }

        .page-header { text-align: center; border-bottom: 2px solid #1e7a87; padding-bottom: 12px; margin-bottom: 16px; }
        .page-header h1 { font-size: 16px; color: #1e7a87; margin-bottom: 2px; }
        .page-header p { font-size: 9px; color: #6a808c; }

        .patient-block { background: #f5fafb; border: 1px solid #d6e2e8; border-radius: 6px; padding: 10px 12px; margin-bottom: 14px; }
        .patient-block h2 { font-size: 13px; color: #0f2a36; margin-bottom: 6px; }
        .patient-grid { display: table; width: 100%; }
        .patient-grid .row { display: table-row; }
        .patient-grid .cell { display: table-cell; padding: 2px 8px 2px 0; font-size: 9.5px; width: 33%; }
        .patient-grid .label { color: #6a808c; font-weight: bold; text-transform: uppercase; font-size: 8px; letter-spacing: 0.5px; }
        .patient-grid .value { color: #0f2a36; }

        .section { margin-bottom: 14px; page-break-inside: avoid; }
        .section-title { font-size: 11px; font-weight: bold; color: #1e7a87; border-bottom: 1px solid #d6e2e8; padding-bottom: 4px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th { background: #eef5f7; color: #0f2a36; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.4px; padding: 5px 6px; text-align: left; border-bottom: 1px solid #d6e2e8; }
        td { padding: 4px 6px; font-size: 9.5px; border-bottom: 1px solid #eef5f7; color: #1a1a2e; vertical-align: top; }
        tr:nth-child(even) td { background: #fafcfd; }

        .empty { color: #94a3b8; font-style: italic; font-size: 9px; padding: 4px 0; }

        .summary-text { font-size: 10px; line-height: 1.6; color: #1a1a2e; background: #f9fcfd; border: 1px solid #e8eef2; border-radius: 4px; padding: 8px 10px; margin-bottom: 6px; }

        .vitals-grid { display: table; width: 100%; }
        .vitals-grid .vital-row { display: table-row; }
        .vitals-grid .vital-cell { display: table-cell; padding: 4px 6px; border: 1px solid #e8eef2; text-align: center; width: 14%; }
        .vital-label { font-size: 7.5px; color: #6a808c; text-transform: uppercase; letter-spacing: 0.3px; }
        .vital-value { font-size: 11px; font-weight: bold; color: #0f2a36; }

        .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; }
        .badge-danger { background: #fff1f1; color: #cc3b3b; border: 1px solid #f1b4b4; }
        .badge-info { background: #e7f0fb; color: #2f5f9c; border: 1px solid #b4cde8; }
        .badge-success { background: #e6f7ee; color: #2f7b4b; border: 1px solid #b4dcc4; }
        .badge-warning { background: #fff6de; color: #b17a07; border: 1px solid #e8d5a0; }

        .footer { margin-top: 20px; padding-top: 8px; border-top: 1px solid #d6e2e8; text-align: center; color: #94a3b8; font-size: 8px; }

        .alert-box { background: #fff6f6; border: 1px solid #f1b4b4; border-radius: 4px; padding: 6px 10px; margin-bottom: 8px; }
        .alert-box strong { color: #cc3b3b; font-size: 9.5px; }
        .alert-box p { color: #0f2a36; font-size: 9px; margin-top: 2px; }

        .note-block { background: #f9fcfd; border: 1px solid #e2eaef; border-radius: 4px; padding: 8px 10px; margin-bottom: 6px; }
        .note-block strong { font-size: 10px; color: #0f2a36; }
        .note-block .meta { font-size: 8.5px; color: #6a808c; margin-top: 2px; }
    </style>
</head>
<body>
    <div class="page-header">
        <h1>Expediente Clínico del Paciente</h1>
        <p>Vista longitudinal · Generado el {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    {{-- Patient Info --}}
    <div class="patient-block">
        <h2>{{ $patient->name }}</h2>
        <div class="patient-grid">
            <div class="row">
                <div class="cell">
                    <div class="label">Documento</div>
                    <div class="value">{{ $patient->dni ?: '—' }}</div>
                </div>
                <div class="cell">
                    <div class="label">Edad / Sexo</div>
                    <div class="value">{{ $age !== null ? $age . ' años' : '—' }}{{ $patient->sexo ? ' | ' . $patient->sexo : '' }}</div>
                </div>
                <div class="cell">
                    <div class="label">Teléfono</div>
                    <div class="value">{{ $patient->telefono ?: '—' }}</div>
                </div>
            </div>
            <div class="row">
                <div class="cell">
                    <div class="label">Correo</div>
                    <div class="value">{{ $patient->email ?: '—' }}</div>
                </div>
                <div class="cell">
                    <div class="label">Fecha de nacimiento</div>
                    <div class="value">{{ $formatDate($patient->fecha_nacimiento) }}</div>
                </div>
                <div class="cell">
                    <div class="label">Última revisión del expediente</div>
                    <div class="value">{{ $record->last_reviewed_at ? $record->last_reviewed_at->format('d/m/Y H:i') : '—' }}</div>
                </div>
            </div>
        </div>
        @if(!empty($representativeName))
            <div style="margin-top:8px; font-size:9.5px; color:#5b6b78;">
                <strong>Representante:</strong> {{ $representativeName }}
            </div>
        @endif
    </div>

    {{-- Allergies --}}
    @if($record->allergies->isNotEmpty())
        <div class="section">
            <div class="section-title">⚠ Alergias documentadas</div>
            <table>
                <thead><tr><th>Alérgeno</th><th>Reacción</th><th>Severidad</th><th>Notas</th></tr></thead>
                <tbody>
                    @foreach($record->allergies as $allergy)
                        <tr>
                            <td><strong>{{ $allergy->allergen }}</strong></td>
                            <td>{{ $allergy->reaction ?: '—' }}</td>
                            <td><span class="badge badge-danger">{{ $allergySeverityLabels[$allergy->severity] ?? '—' }}</span></td>
                            <td>{{ Str::limit($allergy->notes, 80) ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif($record->allergies_status === ClinicalRecord::ALLERGIES_NONE)
        <div class="section">
            <div class="section-title">Alergias</div>
            <p class="empty">Sin alergias conocidas.</p>
        </div>
    @endif

    {{-- Active Alerts --}}
    @if($activeAlerts->isNotEmpty())
        <div class="section">
            <div class="section-title">Alertas clínicas activas</div>
            @foreach($activeAlerts as $alert)
                <div class="alert-box">
                    <strong>{{ $alert->title }}</strong>
                    @if($alert->description)<p>{{ $alert->description }}</p>@endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Clinical Summary --}}
    <div class="section">
        <div class="section-title">Resumen clínico</div>
        @if(filled($record->clinical_summary))
            <div class="summary-text">{{ $record->clinical_summary }}</div>
        @else
            <p class="empty">Sin resumen clínico consolidado.</p>
        @endif
    </div>

    {{-- Latest Clinical Note --}}
    @if($latestNote)
        <div class="section">
            <div class="section-title">Última nota clínica firmada</div>
            <div class="note-block">
                <strong>
                    {{ $latestNote->cita ? $formatDateTime($latestNote->cita->fecha, $latestNote->cita->hora) : '—' }}
                </strong>
                <div class="meta">
                    {{ $latestNote->cita?->especialidad?->nombre ?? 'Consulta' }}
                    · {{ $latestNote->cita?->doctor?->name ?? '—' }}
                </div>
                @if($latestNote->assessment)
                    <p style="margin-top: 4px; font-size: 9.5px;">{{ Str::limit($latestNote->assessment, 300) }}</p>
                @endif
            </div>
        </div>
    @endif

    {{-- Vital Signs --}}
    @if($vitalSnapshot->filter(fn($m) => $m['value'] !== 'Sin registro')->isNotEmpty())
        <div class="section">
            <div class="section-title">Últimos signos vitales</div>
            <div class="vitals-grid">
                <div class="vital-row">
                    @foreach($vitalSnapshot as $metric)
                        <div class="vital-cell">
                            <div class="vital-label">{{ $metric['label'] }}</div>
                            <div class="vital-value">{{ $metric['value'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Active Problems --}}
    <div class="section">
        <div class="section-title">Problemas activos</div>
        @if($activeProblems->isNotEmpty())
            <table>
                <thead><tr><th>Problema</th><th>Estado</th><th>CIE-10</th><th>Inicio</th><th>Observaciones</th></tr></thead>
                <tbody>
                    @foreach($activeProblems as $problem)
                        <tr>
                            <td><strong>{{ $problem->name }}</strong>@if($problem->is_chronic) <span class="badge badge-warning">Crónico</span>@endif</td>
                            <td>{{ $problemStatusLabels[$problem->status] ?? ucfirst($problem->status) }}</td>
                            <td>{{ $problem->cie10 ?: '—' }}</td>
                            <td>{{ $formatDate($problem->started_at) }}</td>
                            <td>{{ Str::limit($problem->notes, 80) ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">Sin registros.</p>
        @endif
    </div>

    {{-- Current Medications --}}
    <div class="section">
        <div class="section-title">Medicación actual</div>
        @if($currentMedications->isNotEmpty())
            <table>
                <thead><tr><th>Medicamento</th><th>Dosis / Frecuencia</th><th>Vía</th><th>Desde</th><th>Indicaciones</th></tr></thead>
                <tbody>
                    @foreach($currentMedications as $med)
                        <tr>
                            <td><strong>{{ $med->name }}</strong>{{ $med->presentation ? ' (' . $med->presentation . ')' : '' }}</td>
                            <td>{{ collect([$med->dosage, $med->frequency])->filter()->implode(' — ') ?: '—' }}</td>
                            <td>{{ $med->route ?: '—' }}</td>
                            <td>{{ $formatDate($med->started_at) }}</td>
                            <td>{{ Str::limit($med->instructions, 80) ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">Sin registros.</p>
        @endif
    </div>

    {{-- Clinical History --}}
    <div class="section">
        <div class="section-title">Antecedentes clínicos</div>
        @foreach($historyDisplayGroups as $group)
            @if($group['items']->isNotEmpty())
                <p style="font-weight: bold; font-size: 9.5px; color: #1e7a87; margin: 6px 0 3px;">{{ $group['title'] }}</p>
                <table>
                    <thead>
                        <tr>
                            <th>Título</th>
                            @if($group['show_relation'] ?? false)<th>Parentesco</th>@endif
                            <th>Descripción</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($group['items'] as $item)
                            <tr>
                                <td><strong>{{ $item->title }}</strong></td>
                                @if($group['show_relation'] ?? false)<td>{{ $item->relation_label ?: '—' }}</td>@endif
                                <td>{{ Str::limit($item->description, 100) ?: '—' }}</td>
                                <td>{{ $item->occurred_on ? $item->occurred_on->format('d/m/Y') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach
        @if(collect($historyDisplayGroups)->every(fn($g) => $g['items']->isEmpty()))
            <p class="empty">Sin registros de antecedentes.</p>
        @endif
    </div>

    {{-- Recent Diagnoses --}}
    @if($recentDiagnoses->isNotEmpty())
        <div class="section">
            <div class="section-title">Diagnósticos recientes</div>
            <table>
                <thead><tr><th>Diagnóstico</th><th>Tipo</th><th>CIE-10</th><th>Fecha</th><th>Profesional</th></tr></thead>
                <tbody>
                    @foreach($recentDiagnoses->take(12) as $diag)
                        <tr>
                            <td><strong>{{ $diag['text'] }}</strong></td>
                            <td>{{ ucfirst((string) $diag['type']) }}</td>
                            <td>{{ $diag['cie10'] ?: '—' }}</td>
                            <td>{{ $diag['at'] ? Carbon::parse($diag['at'])->format('d/m/Y') : '—' }}</td>
                            <td>{{ $diag['doctor'] ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Prescriptions --}}
    @if($prescriptions->isNotEmpty())
        <div class="section">
            <div class="section-title">Recetas vinculadas</div>
            <table>
                <thead><tr><th>Fecha</th><th>Diagnóstico</th><th>Medicamentos</th></tr></thead>
                <tbody>
                    @foreach($prescriptions->take(10) as $rx)
                        <tr>
                            <td>{{ $rx->created_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $rx->diagnostico ?: '—' }}</td>
                            <td>{{ Str::limit($rx->medicamentos, 120) ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Certificates --}}
    @if($certificates->isNotEmpty())
        <div class="section">
            <div class="section-title">Certificados médicos</div>
            <table>
                <thead><tr><th>Código</th><th>Detalle</th><th>Fecha</th><th>Profesional</th></tr></thead>
                <tbody>
                    @foreach($certificates->take(10) as $cert)
                        <tr>
                            <td>{{ $cert->codigo }}</td>
                            <td>{{ $cert->dias_reposo > 0 ? 'Reposo ' . $cert->dias_reposo . ' día(s)' : Str::limit($cert->texto_constancia, 100) }}</td>
                            <td>{{ $cert->fecha_emision?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $cert->doctor?->name ?? $cert->cita?->doctor?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Laboratories --}}
    @if($laboratoryEntries->isNotEmpty())
        <div class="section">
            <div class="section-title">Laboratorios y resultados</div>
            <table>
                <thead><tr><th>Examen</th><th>Estado</th><th>Resumen</th><th>Fecha</th><th>Profesional</th></tr></thead>
                <tbody>
                    @foreach($laboratoryEntries->take(10) as $lab)
                        <tr>
                            <td><strong>{{ $lab->title }}</strong></td>
                            <td>{{ Str::headline(str_replace('_', ' ', (string) $lab->status)) }}</td>
                            <td>{{ Str::limit($lab->summary, 80) ?: '—' }}</td>
                            <td>{{ $lab->ordered_at ? Carbon::parse($lab->ordered_at)->format('d/m/Y') : '—' }}</td>
                            <td>{{ $lab->doctor_name ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="footer">
        Expediente clínico generado automáticamente · {{ now()->format('d/m/Y H:i') }} · Documento confidencial
    </div>
</body>
</html>
