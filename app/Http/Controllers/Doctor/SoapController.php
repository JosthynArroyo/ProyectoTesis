<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\SignSoapRequest;
use App\Http\Requests\SoapEnmiendaRequest;
use App\Http\Requests\StoreSoapDraftRequest;
use App\Models\Cita;
use App\Models\CitaEvento;
use App\Models\NotaSoap;
use App\Models\NotaSoapDiagnostico;
use App\Models\NotaSoapEnmienda;
use App\Services\ClinicalRecordService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SoapController extends Controller
{
    public function show(Cita $cita)
    {
        $this->ensureDoctorAccess($cita);
        if (! $this->citaEsAptaParaSoap($cita)) {
            return redirect()->route('doctor.citas')
                ->with('error', 'No puedes registrar una nota clínica para esta cita.');
        }

        $nota = NotaSoap::with(['diagnosticos', 'enmiendas.autor'])
            ->where('cita_id', $cita->id)
            ->first();
        $persistenciaClinica = $this->resolvePersistenciaClinica($cita, $nota);
        $signosPrevios = $this->obtenerSignosVitalesPrevios($cita, $nota);
        $controlCita = $this->controlPosteriorActivo($cita);

        return view('doctor.soap', [
            'cita' => $cita->load(['paciente', 'especialidad']),
            'nota' => $nota,
            'persistenciaClinica' => $persistenciaClinica,
            'signosPrevios' => $signosPrevios,
            'controlCita' => $controlCita,
        ]);
    }

    public function store(StoreSoapDraftRequest $request, Cita $cita)
    {
        $this->ensureDoctorAccess($cita);
        if (! $this->citaEsAptaParaSoap($cita)) {
            return redirect()->route('doctor.citas')
                ->with('error', 'No puedes registrar una nota clínica para esta cita.');
        }

        $nota = NotaSoap::where('cita_id', $cita->id)->first();
        if ($nota && $nota->isSigned()) {
            return back()->with('error', 'La nota clínica ya está firmada y no puede editarse.');
        }

        DB::transaction(function () use ($request, $cita, $nota): void {
            $clinicalRecords = app(ClinicalRecordService::class);
            $record = $clinicalRecords->ensureForPatient($cita->paciente_id, Auth::id());

            $nota = $nota ?? new NotaSoap(['cita_id' => $cita->id]);
            $nota->fill($this->mapSoapData($request));
            $nota->clinical_record_id = $record->id;
            $nota->estado = NotaSoap::ESTADO_BORRADOR;
            $nota->save();

            $this->syncDiagnosticos($nota, $request->input('diagnosticos', []));
            $nota->load(['diagnosticos', 'cita']);
            $clinicalRecords->syncFromSoap($cita, $nota, $request->all() + [
                'clinical_summary' => $request->input('assessment'),
            ], Auth::id());
            $this->logEvento($cita, 'soap_guardada');
        });

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('doctor.citas.soap', $cita->id)
            ->with('success', 'Borrador guardado correctamente.');
    }

    public function firmar(SignSoapRequest $request, Cita $cita)
    {
        $this->ensureDoctorAccess($cita);
        if (! $this->citaEsAptaParaSoap($cita)) {
            return redirect()->route('doctor.citas')
                ->with('error', 'No puedes firmar una nota clínica para esta cita.');
        }

        $nota = NotaSoap::where('cita_id', $cita->id)->first();
        if ($nota && $nota->isSigned()) {
            return back()->with('error', 'La nota clínica ya fue firmada.');
        }

        DB::transaction(function () use ($request, $cita, $nota): void {
            $clinicalRecords = app(ClinicalRecordService::class);
            $record = $clinicalRecords->ensureForPatient($cita->paciente_id, Auth::id());

            $nota = $nota ?? new NotaSoap(['cita_id' => $cita->id]);
            $nota->fill($this->mapSoapData($request));
            $nota->clinical_record_id = $record->id;
            $nota->estado = NotaSoap::ESTADO_FIRMADA;
            $nota->signed_at = now();
            $nota->signed_by = Auth::id();
            $nota->save();

            $this->syncDiagnosticos($nota, $request->input('diagnosticos', []));
            $nota->load(['diagnosticos', 'cita']);
            $clinicalRecords->syncFromSoap($cita, $nota, $request->all() + [
                'clinical_summary' => $request->input('assessment'),
            ], Auth::id());
            $this->logEvento($cita, 'soap_firmada');
        });

        return redirect()->route('doctor.citas.soap', $cita->id)
            ->with('success', 'Nota clínica firmada correctamente.');
    }

    public function enmienda(SoapEnmiendaRequest $request, Cita $cita)
    {
        $this->ensureDoctorAccess($cita);

        $nota = NotaSoap::with('diagnosticos')->where('cita_id', $cita->id)->first();
        if (! $nota || ! $nota->isSigned()) {
            return back()->with('error', 'Solo puedes enmendar una nota clínica firmada.');
        }

        $snapshot = [
            'nota' => $nota->only([
                'estado',
                'signed_at',
                'signed_by',
                'subjetivo_motivo',
                'subjetivo_hpi',
                'subjetivo_ros',
                'subjetivo_notas',
                'signos_vitales',
                'examen_fisico',
                'notas_objetivas',
                'assessment',
                'plan_general',
                'plan_seguimiento',
                'follow_up_date',
                'follow_up_notes',
                'plan_notas',
            ]),
            'diagnosticos' => $nota->diagnosticos->map(function (NotaSoapDiagnostico $diag) {
                return $diag->only(['tipo', 'texto', 'cie10']);
            })->values()->all(),
        ];

        DB::transaction(function () use ($nota, $request, $snapshot, $cita): void {
            NotaSoapEnmienda::create([
                'nota_soap_id' => $nota->id,
                'user_id' => Auth::id(),
                'motivo' => $request->input('motivo'),
                'contenido' => $request->input('contenido'),
                'snapshot' => $snapshot,
            ]);

            $this->logEvento($cita, 'soap_enmienda');
        });

        return redirect()->route('doctor.citas.soap', $cita->id)
            ->with('success', 'Enmienda registrada correctamente.');
    }

    protected function ensureDoctorAccess(Cita $cita): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        if ($user->hasRole('superadmin')) {
            return;
        }

        if (! $user->hasRole('doctor') || $cita->doctor_id !== $user->id) {
            abort(403);
        }
    }

    protected function citaEsAptaParaSoap(Cita $cita): bool
    {
        if (in_array($cita->estado, [Cita::ESTADO_CANCELADA, Cita::ESTADO_NO_SE_PRESENTO], true)) {
            return false;
        }

        return in_array($cita->estado, [Cita::ESTADO_CONFIRMADA, Cita::ESTADO_REALIZADA], true);
    }

    protected function controlPosteriorActivo(Cita $cita): ?Cita
    {
        $inicio = $this->fechaHoraCita($cita);

        return Cita::query()
            ->where('id', '<>', $cita->id)
            ->where('paciente_id', $cita->paciente_id)
            ->where('doctor_id', $cita->doctor_id)
            ->where('especialidad_id', $cita->especialidad_id)
            ->where('activo', true)
            ->whereNotIn('estado', [Cita::ESTADO_CANCELADA, Cita::ESTADO_REALIZADA, Cita::ESTADO_NO_SE_PRESENTO])
            ->where(function ($query) use ($inicio) {
                $query->whereDate('fecha', '>', $inicio->toDateString())
                    ->orWhere(function ($sameDay) use ($inicio) {
                        $sameDay->whereDate('fecha', $inicio->toDateString())
                            ->whereTime('hora', '>', $inicio->format('H:i:s'));
                    });
            })
            ->orderBy('fecha')
            ->orderBy('hora')
            ->first();
    }

    protected function fechaHoraCita(Cita $cita): Carbon
    {
        return Carbon::parse(
            Carbon::parse($cita->fecha)->toDateString().' '.substr((string) $cita->hora, 0, 8),
            'America/Guayaquil'
        );
    }

    protected function mapSoapData(Request $request): array
    {
        $ros = trim((string) $request->input('subjetivo_ros', ''));
        if (str_starts_with($ros, 'Registro estructurado')) {
            $ros = '';
        }
        $signos = $this->buildSignosVitales($request);
        $alergias = $this->buildAlergias($request);
        $antecedentes = $this->buildAntecedentes($request);

        $subjetivoHpi = trim((string) $request->input('subjetivo_hpi', ''));
        if ($subjetivoHpi === '' || str_starts_with($subjetivoHpi, 'Registro estructurado')) {
            $subjetivoHpi = $this->buildAntecedentesResumen($antecedentes) ?: 'Sin antecedentes adicionales.';
        }

        $subjetivoNotas = trim((string) $request->input('subjetivo_notas', ''));
        if ($subjetivoNotas === '' || str_starts_with($subjetivoNotas, 'Registro estructurado')) {
            $subjetivoNotas = $this->buildAlergiasResumen($alergias);
        }

        $rosPayload = [];
        if ($ros !== '') {
            $rosPayload['texto'] = $ros;
        }
        if ($alergias !== null) {
            $rosPayload['alergias'] = $alergias;
        }
        if ($antecedentes !== null) {
            $rosPayload['antecedentes'] = $antecedentes;
        }

        $followUpDate = $this->resolveFollowUpDate($request);
        $legacyFollowUp = trim((string) $request->input('plan_seguimiento', ''));
        $followUpNotes = $this->resolveFollowUpNotes($request);

        if ($legacyFollowUp === '' && $followUpDate) {
            $legacyFollowUp = 'Control previsto para '.$followUpDate;
        }

        return [
            'subjetivo_motivo' => $request->input('subjetivo_motivo'),
            'subjetivo_hpi' => $subjetivoHpi,
            'subjetivo_ros' => ! empty($rosPayload) ? $rosPayload : null,
            'subjetivo_notas' => $subjetivoNotas,
            'signos_vitales' => $signos ?: null,
            'examen_fisico' => $request->input('examen_fisico'),
            'notas_objetivas' => $request->input('notas_objetivas'),
            'assessment' => $request->input('assessment'),
            'plan_general' => $request->input('plan_general'),
            'plan_seguimiento' => $legacyFollowUp !== '' ? $legacyFollowUp : null,
            'follow_up_date' => $followUpDate,
            'follow_up_notes' => $followUpNotes,
            'plan_notas' => $request->input('plan_notas'),
        ];
    }

    protected function resolveFollowUpDate(Request $request): ?string
    {
        $explicit = trim((string) $request->input('follow_up_date', ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $legacy = trim((string) $request->input('plan_seguimiento', ''));
        if ($legacy === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($legacy, config('app.timezone', 'America/Guayaquil'))
                ->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveFollowUpNotes(Request $request): ?string
    {
        $notes = trim((string) $request->input('follow_up_notes', ''));
        if ($notes !== '') {
            return $notes;
        }

        $legacy = trim((string) $request->input('plan_seguimiento', ''));

        return $legacy !== '' ? $legacy : null;
    }

    protected function resolvePersistenciaClinica(Cita $cita, ?NotaSoap $nota): array
    {
        $context = app(ClinicalRecordService::class)->buildSoapContext($cita, $nota);

        return [
            'alergias' => $context['alergias'] ?? null,
            'antecedentes' => $context['antecedentes'] ?? null,
            'problemas_activos' => $context['problemas_activos'] ?? collect(),
            'medicacion_actual' => $context['medicacion_actual'] ?? collect(),
            'alertas' => $context['alertas'] ?? collect(),
        ];
    }

    protected function obtenerSignosVitalesPrevios(Cita $cita, ?NotaSoap $nota): ?array
    {
        return app(ClinicalRecordService::class)->getPreviousVitalSigns($cita, $nota);
    }

    protected function buildAlergias(Request $request): ?array
    {
        $noConocidas = $request->boolean('alergias_no_conocidas');
        $detalle = trim((string) $request->input('alergias_detalle', ''));

        if (! $noConocidas && $detalle === '') {
            return null;
        }

        return [
            'no_conocidas' => $noConocidas,
            'detalle' => $detalle !== '' ? $detalle : null,
        ];
    }

    protected function buildAntecedentes(Request $request): ?array
    {
        $antecedentes = [
            'cronicas' => trim((string) $request->input('antecedentes_cronicas', '')),
            'cirugias' => trim((string) $request->input('antecedentes_cirugias', '')),
            'hospitalizaciones' => trim((string) $request->input('antecedentes_hospitalizaciones', '')),
            'diabetes' => trim((string) $request->input('antecedentes_diabetes', '')),
            'hipertension' => trim((string) $request->input('antecedentes_hipertension', '')),
            'otros' => trim((string) $request->input('antecedentes_otros', '')),
        ];

        $hasAnyValue = false;
        foreach ($antecedentes as $key => $value) {
            if ($value === '') {
                $antecedentes[$key] = null;

                continue;
            }
            $hasAnyValue = true;
        }

        return $hasAnyValue ? $antecedentes : null;
    }

    protected function buildAlergiasResumen(?array $alergias): string
    {
        if (! $alergias) {
            return 'Alergias sin registro.';
        }

        if (! empty($alergias['no_conocidas'])) {
            return 'Alergias: no conocidas.';
        }

        $detalle = trim((string) ($alergias['detalle'] ?? ''));

        return $detalle !== '' ? 'Alergias: '.$detalle : 'Alergias sin registro.';
    }

    protected function buildAntecedentesResumen(?array $antecedentes): ?string
    {
        if (! $antecedentes) {
            return null;
        }

        $labels = [
            'cronicas' => 'Cronicas',
            'cirugias' => 'Cirugias',
            'hospitalizaciones' => 'Hospitalizaciones',
            'diabetes' => 'Diabetes',
            'hipertension' => 'Hipertension',
            'otros' => 'Otros',
        ];

        $chunks = [];
        foreach ($labels as $key => $label) {
            $value = trim((string) ($antecedentes[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $chunks[] = $label.': '.$value;
        }

        return ! empty($chunks) ? implode(' | ', $chunks) : null;
    }

    protected function buildSignosVitales(Request $request): array
    {
        $signos = [
            'ta' => $request->input('sv_ta'),
            'fc' => $request->input('sv_fc'),
            'fr' => $request->input('sv_fr'),
            'temp' => $request->input('sv_temp'),
            'spo2' => $request->input('sv_spo2'),
            'peso' => $request->input('sv_peso'),
            'talla' => $request->input('sv_talla'),
        ];

        return array_filter($signos, function ($value) {
            return ! is_null($value) && $value !== '';
        });
    }

    protected function syncDiagnosticos(NotaSoap $nota, array $diagnosticos): void
    {
        $clean = collect($diagnosticos)
            ->map(function ($diag) {
                return [
                    'tipo' => $diag['tipo'] ?? 'principal',
                    'texto' => trim((string) ($diag['texto'] ?? '')),
                    'cie10' => trim((string) ($diag['cie10'] ?? '')) ?: null,
                ];
            })
            ->filter(fn ($diag) => $diag['texto'] !== '')
            ->values()
            ->all();

        $nota->diagnosticos()->delete();
        if (! empty($clean)) {
            $nota->diagnosticos()->createMany($clean);
        }
    }

    protected function logEvento(Cita $cita, string $tipo): void
    {
        CitaEvento::create([
            'cita_id' => $cita->id,
            'user_id' => Auth::id(),
            'tipo' => $tipo,
        ]);
    }
}
