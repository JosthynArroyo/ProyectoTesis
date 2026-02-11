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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        return view('doctor.soap', [
            'cita' => $cita->load(['paciente', 'especialidad']),
            'nota' => $nota,
            'persistenciaClinica' => $persistenciaClinica,
            'signosPrevios' => $signosPrevios,
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

        $nota = $nota ?? new NotaSoap(['cita_id' => $cita->id]);
        $nota->fill($this->mapSoapData($request));
        $nota->estado = NotaSoap::ESTADO_BORRADOR;
        $nota->save();

        $this->syncDiagnosticos($nota, $request->input('diagnosticos', []));
        $this->logEvento($cita, 'soap_guardada');

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

        $nota = $nota ?? new NotaSoap(['cita_id' => $cita->id]);
        $nota->fill($this->mapSoapData($request));
        $nota->estado = NotaSoap::ESTADO_FIRMADA;
        $nota->signed_at = now();
        $nota->signed_by = Auth::id();
        $nota->save();

        $this->syncDiagnosticos($nota, $request->input('diagnosticos', []));
        $this->logEvento($cita, 'soap_firmada');

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
                'plan_notas',
            ]),
            'diagnosticos' => $nota->diagnosticos->map(function (NotaSoapDiagnostico $diag) {
                return $diag->only(['tipo', 'texto', 'cie10']);
            })->values()->all(),
        ];

        NotaSoapEnmienda::create([
            'nota_soap_id' => $nota->id,
            'user_id' => Auth::id(),
            'motivo' => $request->input('motivo'),
            'contenido' => $request->input('contenido'),
            'snapshot' => $snapshot,
        ]);

        $this->logEvento($cita, 'soap_enmienda');

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

        return [
            'subjetivo_motivo' => $request->input('subjetivo_motivo'),
            'subjetivo_hpi' => $subjetivoHpi,
            'subjetivo_ros' => !empty($rosPayload) ? $rosPayload : null,
            'subjetivo_notas' => $subjetivoNotas,
            'signos_vitales' => $signos ?: null,
            'examen_fisico' => $request->input('examen_fisico'),
            'notas_objetivas' => $request->input('notas_objetivas'),
            'assessment' => $request->input('assessment'),
            'plan_general' => $request->input('plan_general'),
            'plan_seguimiento' => $request->input('plan_seguimiento'),
            'plan_notas' => $request->input('plan_notas'),
        ];
    }

    protected function resolvePersistenciaClinica(Cita $cita, ?NotaSoap $nota): array
    {
        $rosActual = is_array($nota?->subjetivo_ros) ? $nota->subjetivo_ros : [];
        $alergias = is_array($rosActual['alergias'] ?? null) ? $rosActual['alergias'] : null;
        $antecedentes = is_array($rosActual['antecedentes'] ?? null) ? $rosActual['antecedentes'] : null;

        if ($alergias && $antecedentes) {
            return [
                'alergias' => $alergias,
                'antecedentes' => $antecedentes,
            ];
        }

        $previas = NotaSoap::query()
            ->select('notas_soap.subjetivo_ros')
            ->join('citas_medicas', 'notas_soap.cita_id', '=', 'citas_medicas.id')
            ->where('notas_soap.estado', NotaSoap::ESTADO_FIRMADA)
            ->where('citas_medicas.paciente_id', $cita->paciente_id)
            ->where('citas_medicas.doctor_id', $cita->doctor_id)
            ->where('citas_medicas.id', '!=', $cita->id)
            ->orderByDesc('citas_medicas.fecha')
            ->orderByDesc('citas_medicas.hora')
            ->limit(30)
            ->get();

        foreach ($previas as $previa) {
            $rosPrevio = is_array($previa->subjetivo_ros) ? $previa->subjetivo_ros : [];
            if (! $alergias) {
                $alergiasPrevias = is_array($rosPrevio['alergias'] ?? null) ? $rosPrevio['alergias'] : null;
                if ($alergiasPrevias) {
                    $alergias = $alergiasPrevias;
                }
            }

            if (! $antecedentes) {
                $antecedentesPrevios = is_array($rosPrevio['antecedentes'] ?? null) ? $rosPrevio['antecedentes'] : null;
                if ($antecedentesPrevios) {
                    $antecedentes = $antecedentesPrevios;
                }
            }

            if ($alergias && $antecedentes) {
                break;
            }
        }

        return [
            'alergias' => $alergias,
            'antecedentes' => $antecedentes,
        ];
    }

    protected function obtenerSignosVitalesPrevios(Cita $cita, ?NotaSoap $nota): ?array
    {
        $notaPrevia = NotaSoap::query()
            ->select('notas_soap.signos_vitales')
            ->join('citas_medicas', 'notas_soap.cita_id', '=', 'citas_medicas.id')
            ->where('notas_soap.estado', NotaSoap::ESTADO_FIRMADA)
            ->where('citas_medicas.paciente_id', $cita->paciente_id)
            ->where('citas_medicas.doctor_id', $cita->doctor_id)
            ->whereNotNull('notas_soap.signos_vitales')
            ->where('citas_medicas.id', '!=', $cita->id)
            ->orderByDesc('citas_medicas.fecha')
            ->orderByDesc('citas_medicas.hora')
            ->first();

        $signos = is_array($notaPrevia?->signos_vitales) ? $notaPrevia->signos_vitales : null;

        return ! empty($signos) ? $signos : null;
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

        return $detalle !== '' ? 'Alergias: ' . $detalle : 'Alergias sin registro.';
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
            $chunks[] = $label . ': ' . $value;
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
            return !is_null($value) && $value !== '';
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
        if (!empty($clean)) {
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
