@extends('layouts.doctor')
@section('title', 'Historial clínico profesional')
@section('activeSidebar', 'citas')
@section('header-title', 'Historial clínico profesional')
@section('header-subtitle', 'Expediente médico completo del paciente')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/medical-record.css') }}">
@endpush

@section('main')
    @php
        use App\Models\Cita;
        use App\Models\LaboratorioOrden;
        use App\Models\NotaSoap;
        use Carbon\Carbon;
        use Illuminate\Pagination\AbstractPaginator;
        use Illuminate\Support\Collection;
        use Illuminate\Support\Str;

        $doctorId = auth()->id();
        $timezone = config('app.timezone', 'America/Guayaquil');
        $ahora = Carbon::now($timezone);

        $notasPaginadas = $notas instanceof AbstractPaginator ? $notas : null;
        $notasColeccion = $notasPaginadas ? $notasPaginadas->getCollection() : collect($notas ?? []);

        $consultas = Cita::query()
            ->where('paciente_id', $paciente->id)
            ->where('doctor_id', $doctorId)
            ->with([
                'doctor:id,name',
                'especialidad:id,nombre',
                'receta:id,cita_id,diagnostico,medicamentos,indicaciones,created_at',
                'receta.cita:id,fecha,hora',
                'laboratorioOrden:id,cita_id,tipo_examen,estado,prioridad,resultado_path,resultado_publicado_at,resultado_resumen,created_at',
                'laboratorioOrden.cita:id,fecha,hora',
                'notaSoap' => function ($query) {
                    $query->with('diagnosticos:id,nota_soap_id,tipo,texto,cie10');
                },
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('hora')
            ->get();

        $toCitaDateTime = static function (Cita $cita) use ($timezone): Carbon {
            $fecha = $cita->fecha instanceof Carbon
                ? $cita->fecha->format('Y-m-d')
                : Carbon::parse((string) $cita->fecha, $timezone)->format('Y-m-d');
            $hora = $cita->hora ? substr((string) $cita->hora, 0, 5) : '00:00';
            return Carbon::parse($fecha . ' ' . $hora, $timezone);
        };

        $resolveRegistroAction = static function (Cita $cita): array {
            $soapDisponible = in_array($cita->estado, [Cita::ESTADO_CONFIRMADA, Cita::ESTADO_REALIZADA], true);
            $nota = $cita->notaSoap;
            if (! $soapDisponible) {
                return ['label' => null, 'hint' => 'Registro no habilitado por estado de cita.'];
            }
            if (! $nota) {
                return ['label' => 'Registrar', 'hint' => 'Sin registro clínico.'];
            }
            if ($nota->estado === NotaSoap::ESTADO_BORRADOR) {
                return ['label' => 'Editar registro', 'hint' => 'Registro en borrador.'];
            }
            return ['label' => 'Ver registro', 'hint' => 'Registro firmado.'];
        };

        $confirmadas = $consultas->filter(fn ($cita) => $cita->estado === Cita::ESTADO_CONFIRMADA)->values();
        $citaConfirmadaProxima = $confirmadas
            ->filter(fn ($cita) => $toCitaDateTime($cita)->greaterThanOrEqualTo($ahora))
            ->sortBy(fn ($cita) => $toCitaDateTime($cita)->timestamp)
            ->first();

        if (! $citaConfirmadaProxima) {
            $citaConfirmadaProxima = $confirmadas
                ->filter(fn ($cita) => $toCitaDateTime($cita)->isSameDay($ahora))
                ->sortBy(fn ($cita) => abs($toCitaDateTime($cita)->diffInMinutes($ahora, false)))
                ->first();
        }

        $registroHoyUrl = $citaConfirmadaProxima ? route('doctor.citas.soap', $citaConfirmadaProxima->id) : null;
        $registroHoyLabel = 'Registrar consulta de hoy';
        if ($citaConfirmadaProxima) {
            $registroHoyAction = $resolveRegistroAction($citaConfirmadaProxima);
            $registroHoyLabel = match ($registroHoyAction['label']) {
                'Editar registro' => 'Editar consulta de hoy',
                'Ver registro' => 'Ver registro de hoy',
                default => 'Registrar consulta de hoy',
            };
        }

        $consultasAgendadas = $consultas
            ->filter(fn ($cita) => in_array($cita->estado, [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA], true))
            ->values();
        $consultasRealizadas = $consultas->filter(fn ($cita) => $cita->estado === Cita::ESTADO_REALIZADA)->values();
        $laboratorios = $consultas->filter(fn ($cita) => $cita->laboratorioOrden !== null)->map->laboratorioOrden->values();
        $resultadosLaboratorio = $laboratorios
            ->filter(fn ($orden) => $orden->estado === LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE || !empty($orden->resultado_path) || !empty($orden->resultado_resumen))
            ->values();
        $recetas = $consultas->filter(fn ($cita) => $cita->receta !== null)->map->receta->values();

        $ultimaConsulta = $consultasRealizadas->first() ?: $consultas->first();
        $edadPaciente = $paciente->fecha_nacimiento ? Carbon::parse($paciente->fecha_nacimiento)->age : null;
        $avatarPaciente = $imageUrl->variants($paciente->avatar, 'patients', 'patient');

        $historialSignos = NotaSoap::query()
            ->select('notas_soap.signos_vitales', 'citas_medicas.fecha', 'citas_medicas.hora')
            ->join('citas_medicas', 'notas_soap.cita_id', '=', 'citas_medicas.id')
            ->where('notas_soap.estado', NotaSoap::ESTADO_FIRMADA)
            ->where('citas_medicas.paciente_id', $paciente->id)
            ->where('citas_medicas.doctor_id', $doctorId)
            ->orderBy('citas_medicas.fecha')
            ->orderBy('citas_medicas.hora')
            ->limit(24)
            ->get();

        $toNumber = static function ($value): ?float {
            if (is_numeric($value)) return (float) $value;
            if (!is_string($value)) return null;
            if (!preg_match('/([0-9]+(?:[.,][0-9]+)?)/', $value, $match)) return null;
            return (float) str_replace(',', '.', $match[1]);
        };

        $toSystolic = static function ($value) use ($toNumber): ?float {
            if (is_string($value) && preg_match('/(\d{2,3})\s*\/\s*\d{2,3}/', $value, $match)) return (float) $match[1];
            return $toNumber($value);
        };

        $toHeightCm = static fn (?float $value): ?float => $value === null ? null : ($value <= 3 ? $value * 100 : $value);

        $serieTalla = collect();
        $seriePeso = collect();
        $seriePresion = collect();
        $serieFc = collect();
        $serieFr = collect();
        $serieSpo2 = collect();
        $serieTemp = collect();

        foreach ($historialSignos as $filaSigno) {
            $signos = is_array($filaSigno->signos_vitales) ? $filaSigno->signos_vitales : [];
            $talla = $toNumber($signos['talla'] ?? null);
            $peso = $toNumber($signos['peso'] ?? null);
            $presion = $toSystolic($signos['ta'] ?? null);
            $fc = $toNumber($signos['fc'] ?? null);
            $fr = $toNumber($signos['fr'] ?? null);
            $spo2 = $toNumber($signos['spo2'] ?? null);
            $temp = $toNumber($signos['temp'] ?? null);
            if ($talla !== null) $serieTalla->push($talla);
            if ($peso !== null) $seriePeso->push($peso);
            if ($presion !== null) $seriePresion->push($presion);
            if ($fc !== null) $serieFc->push($fc);
            if ($fr !== null) $serieFr->push($fr);
            if ($spo2 !== null) $serieSpo2->push($spo2);
            if ($temp !== null) $serieTemp->push($temp);
        }

        $serieTallaCm = $serieTalla->map(fn ($value) => $toHeightCm($value))->filter(fn ($value) => $value !== null)->values();
        $ultimoSigno = is_array(optional($historialSignos->last())->signos_vitales) ? $historialSignos->last()->signos_vitales : [];
        $buildSparkline = static function (Collection $serie): ?string {
            $serie = $serie->values();
            $count = $serie->count();
            if ($count < 2) return null;
            $min = (float) $serie->min();
            $max = (float) $serie->max();
            $range = max($max - $min, 0.01);
            return $serie->map(function ($value, $index) use ($count, $min, $range) {
                $x = $count === 1 ? 0 : ($index / ($count - 1)) * 100;
                $y = 100 - ((((float) $value) - $min) / $range) * 100;
                return round($x, 2) . ',' . round($y, 2);
            })->implode(' ');
        };

        $deltaFromLastTwo = static function (Collection $serie): ?float {
            $serie = $serie->values();
            if ($serie->count() < 2) return null;
            return (float) $serie->last() - (float) $serie->slice(-2, 1)->first();
        };

        $formatDecimal = static fn ($value, int $dec = 1): ?string => ($value === null || $value === '') ? null : number_format((float) $value, $dec, '.', '');
        $formatWithUnit = static fn (?string $value, string $unit = ''): string => ($value === null || $value === '') ? 'Sin registro' : trim($value . ' ' . $unit);
        $formatDelta = static function (?float $value, string $unit, int $dec = 1): string {
            if ($value === null) return 'Sin datos comparables';
            $sign = $value > 0 ? '+' : '';
            return $sign . number_format($value, $dec, '.', '') . ' ' . $unit;
        };

        $metricasSignos = [
            [
                'icon' => 'ri-ruler-line',
                'label' => 'Altura',
                'value' => $formatWithUnit($formatDecimal($toNumber($ultimoSigno['talla'] ?? null), 2), 'm'),
                'sparkline' => $buildSparkline($serieTalla),
                'delta' => $formatDelta($deltaFromLastTwo($serieTallaCm), 'cm', 1),
            ],
            [
                'icon' => 'ri-weight-line',
                'label' => 'Peso',
                'value' => $formatWithUnit($formatDecimal($toNumber($ultimoSigno['peso'] ?? null), 1), 'kg'),
                'sparkline' => $buildSparkline($seriePeso),
                'delta' => $formatDelta($deltaFromLastTwo($seriePeso), 'kg', 1),
            ],
            [
                'icon' => 'ri-heart-pulse-line',
                'label' => 'Presión arterial',
                'value' => $formatWithUnit(data_get($ultimoSigno, 'ta'), 'mmHg'),
                'sparkline' => $buildSparkline($seriePresion),
                'delta' => $formatDelta($deltaFromLastTwo($seriePresion), 'mmHg', 0),
            ],
            [
                'icon' => 'ri-heart-line',
                'label' => 'Frecuencia cardíaca',
                'value' => $formatWithUnit($formatDecimal($toNumber($ultimoSigno['fc'] ?? null), 0), 'lpm'),
                'sparkline' => $buildSparkline($serieFc),
                'delta' => $formatDelta($deltaFromLastTwo($serieFc), 'lpm', 0),
            ],
            [
                'icon' => 'ri-lungs-line',
                'label' => 'Respiración',
                'value' => $formatWithUnit($formatDecimal($toNumber($ultimoSigno['fr'] ?? null), 0), 'rpm'),
                'sparkline' => $buildSparkline($serieFr),
                'delta' => $formatDelta($deltaFromLastTwo($serieFr), 'rpm', 0),
            ],
            [
                'icon' => 'ri-contrast-drop-2-line',
                'label' => 'Saturación O2',
                'value' => $formatWithUnit($formatDecimal($toNumber($ultimoSigno['spo2'] ?? null), 0), '%'),
                'sparkline' => $buildSparkline($serieSpo2),
                'delta' => $formatDelta($deltaFromLastTwo($serieSpo2), '%', 0),
            ],
            [
                'icon' => 'ri-temp-cold-line',
                'label' => 'Temperatura',
                'value' => $formatWithUnit($formatDecimal($toNumber($ultimoSigno['temp'] ?? null), 1), 'C'),
                'sparkline' => $buildSparkline($serieTemp),
                'delta' => $formatDelta($deltaFromLastTwo($serieTemp), 'C', 1),
            ],
        ];

        $notasFirmadas = $consultas->map->notaSoap->filter(fn ($nota) => $nota && $nota->estado === NotaSoap::ESTADO_FIRMADA)->values();

        $lineasClinicas = $notasFirmadas
            ->flatMap(function ($nota) {
                return collect([$nota->subjetivo_motivo, $nota->subjetivo_hpi, $nota->subjetivo_notas, $nota->assessment, $nota->plan_general, $nota->plan_seguimiento, $nota->plan_notas])->filter();
            })
            ->flatMap(fn ($texto) => collect(preg_split('/\r\n|\r|\n/', (string) $texto)))
            ->map(fn ($linea) => trim((string) $linea, " \t\n\r\0\x0B-:;."))
            ->filter()
            ->values();

        $diagnosticosClinicos = $notasFirmadas
            ->flatMap(fn ($nota) => $nota->diagnosticos ? $nota->diagnosticos->pluck('texto') : collect())
            ->map(fn ($texto) => trim((string) $texto))
            ->filter()
            ->values();

        $notaEstructurada = $notasFirmadas->first(function ($nota) {
            $ros = is_array($nota->subjetivo_ros ?? null) ? $nota->subjetivo_ros : [];
            return is_array($ros['alergias'] ?? null) || is_array($ros['antecedentes'] ?? null);
        });

        $rosEstructurado = is_array($notaEstructurada?->subjetivo_ros) ? $notaEstructurada->subjetivo_ros : [];
        $alergiasEstructuradas = is_array($rosEstructurado['alergias'] ?? null) ? $rosEstructurado['alergias'] : [];
        $antecedentesEstructurados = is_array($rosEstructurado['antecedentes'] ?? null) ? $rosEstructurado['antecedentes'] : [];

        $alergias = collect();
        if (! empty($alergiasEstructuradas['no_conocidas'])) {
            $alergias->push('No conocidas');
        }

        $detalleAlergias = trim((string) ($alergiasEstructuradas['detalle'] ?? ''));
        if ($detalleAlergias !== '') {
            $alergias = $alergias->merge(
                collect(preg_split('/\r\n|\r|\n|,|;/', $detalleAlergias))
                    ->map(fn ($linea) => trim((string) $linea, " \t\n\r\0\x0B-:;."))
                    ->filter()
            );
        }
        $alergias = $alergias->unique()->values();

        $alergiasFallback = $lineasClinicas
            ->filter(fn ($linea) => str_contains(mb_strtolower($linea), 'alerg'))
            ->unique()
            ->take(8)
            ->values();

        if ($alergias->isEmpty()) {
            $alergias = $alergiasFallback;
        }

        $findLineByKeywords = static function (Collection $lineas, array $keywords): ?string {
            foreach ($lineas as $linea) {
                $normalized = mb_strtolower((string) $linea);
                foreach ($keywords as $keyword) {
                    if (str_contains($normalized, $keyword)) return (string) $linea;
                }
            }
            return null;
        };

        $lineasBusqueda = $lineasClinicas->merge($diagnosticosClinicos)->values();
        $patientFlag = $paciente->patientFlag;

        $enfermedadesCronicasFallback = $diagnosticosClinicos
            ->filter(function ($texto) {
                $value = mb_strtolower($texto);
                return str_contains($value, 'cron') || str_contains($value, 'asma') || str_contains($value, 'epoc') || str_contains($value, 'renal') || str_contains($value, 'cardio') || str_contains($value, 'hipertension') || str_contains($value, 'diabet');
            })
            ->unique()
            ->take(3)
            ->values();

        if ($patientFlag?->cronico && $enfermedadesCronicasFallback->isEmpty()) {
            $enfermedadesCronicasFallback = collect(['Paciente marcado con condicion cronica.']);
        }

        $cirugiasPreviasFallback = $findLineByKeywords($lineasBusqueda, ['cirug', 'quirurg', 'cesarea', 'apendic', 'colecist']);
        $hospitalizacionesFallback = $findLineByKeywords($lineasBusqueda, ['hospital', 'interna', 'emergenc', 'uci']);
        $diabetesFallback = $findLineByKeywords($lineasBusqueda, ['diabet']);
        $hipertensionFallback = $findLineByKeywords($lineasBusqueda, ['hipertension', 'hta', 'presion alta']);

        $otrosAntecedentesFallback = collect();
        if ($patientFlag?->adulto_mayor) $otrosAntecedentesFallback->push('Adulto mayor');
        if ($patientFlag?->embarazo) $otrosAntecedentesFallback->push('Embarazo');
        if ($patientFlag?->discapacidad) $otrosAntecedentesFallback->push('Discapacidad');

        $otrosTexto = $lineasBusqueda
            ->filter(function ($texto) {
                $value = mb_strtolower($texto);
                return str_contains($value, 'trauma') || str_contains($value, 'cancer') || str_contains($value, 'glaucoma') || str_contains($value, 'tromb') || str_contains($value, 'neurolog');
            })
            ->take(4)
            ->values();

        $otrosAntecedentesFallback = $otrosAntecedentesFallback->merge($otrosTexto)->unique()->values();

        $enfermedadesCronicasTexto = trim((string) ($antecedentesEstructurados['cronicas'] ?? ''));
        $enfermedadesCronicas = $enfermedadesCronicasTexto !== ''
            ? collect([$enfermedadesCronicasTexto])
            : $enfermedadesCronicasFallback;

        $cirugiasPrevias = trim((string) ($antecedentesEstructurados['cirugias'] ?? ''));
        if ($cirugiasPrevias === '') {
            $cirugiasPrevias = $cirugiasPreviasFallback;
        }

        $hospitalizaciones = trim((string) ($antecedentesEstructurados['hospitalizaciones'] ?? ''));
        if ($hospitalizaciones === '') {
            $hospitalizaciones = $hospitalizacionesFallback;
        }

        $diabetes = trim((string) ($antecedentesEstructurados['diabetes'] ?? ''));
        if ($diabetes === '') {
            $diabetes = $diabetesFallback;
        }

        $hipertension = trim((string) ($antecedentesEstructurados['hipertension'] ?? ''));
        if ($hipertension === '') {
            $hipertension = $hipertensionFallback;
        }

        $otrosAntecedentesTexto = trim((string) ($antecedentesEstructurados['otros'] ?? ''));
        $otrosAntecedentes = $otrosAntecedentesTexto !== ''
            ? collect([$otrosAntecedentesTexto])
            : $otrosAntecedentesFallback;

        $estadoConsulta = [
            Cita::ESTADO_PENDIENTE => 'Pendiente',
            Cita::ESTADO_CONFIRMADA => 'Confirmada',
            Cita::ESTADO_CANCELADA => 'Cancelada',
            Cita::ESTADO_REALIZADA => 'Realizada',
            Cita::ESTADO_NO_SE_PRESENTO => 'No se presento',
        ];

        $lineaTiempoClinica = collect();
        foreach ($consultas as $consultaTimeline) {
            $momentoConsulta = $toCitaDateTime($consultaTimeline);
            $notaTimeline = $consultaTimeline->notaSoap;
            $descripcionConsulta = Str::limit(
                $notaTimeline?->assessment ?: ($notaTimeline?->subjetivo_motivo ?: 'Atención clínica registrada.'),
                180
            );

            $lineaTiempoClinica->push([
                'fecha' => $momentoConsulta,
                'tipo' => 'consulta',
                'icono' => 'ri-stethoscope-line',
                'titulo' => 'Consulta médica',
                'detalle' => $descripcionConsulta,
                'cita_id' => $consultaTimeline->id,
            ]);

            if ($consultaTimeline->laboratorioOrden) {
                $ordenTimeline = $consultaTimeline->laboratorioOrden;
                $fechaLaboratorio = $ordenTimeline->resultado_publicado_at ?: $ordenTimeline->created_at ?: $momentoConsulta;
                $lineaTiempoClinica->push([
                    'fecha' => $fechaLaboratorio instanceof Carbon ? $fechaLaboratorio : Carbon::parse($fechaLaboratorio, $timezone),
                    'tipo' => 'laboratorio',
                    'icono' => 'ri-flask-line',
                    'titulo' => 'Laboratorio',
                    'detalle' => Str::limit(
                        trim((string) ($ordenTimeline->tipo_examen ?: $ordenTimeline->resultado_resumen ?: 'Orden de laboratorio registrada.')),
                        180
                    ),
                    'cita_id' => $consultaTimeline->id,
                ]);
            }

            if ($consultaTimeline->receta) {
                $recetaTimeline = $consultaTimeline->receta;
                $fechaReceta = $recetaTimeline->created_at ?: $momentoConsulta;
                $lineaTiempoClinica->push([
                    'fecha' => $fechaReceta instanceof Carbon ? $fechaReceta : Carbon::parse($fechaReceta, $timezone),
                    'tipo' => 'receta',
                    'icono' => 'ri-medicine-bottle-line',
                    'titulo' => 'Receta médica',
                    'detalle' => Str::limit(trim((string) ($recetaTimeline->diagnostico ?: 'Receta emitida.')), 180),
                    'cita_id' => $consultaTimeline->id,
                ]);
            }
        }

        $lineaTiempoClinica = $lineaTiempoClinica
            ->sortByDesc(fn ($evento) => ($evento['fecha'] instanceof Carbon ? $evento['fecha']->timestamp : 0))
            ->values();
    @endphp

    <div class="medical-record-shell">
        <section class="card p-5 medical-toolbar">
            <div class="medical-toolbar__content">
                <div>
                    <p class="medical-toolbar__eyebrow">Registro clínico</p>
                    <h2>Acciones rápidas por cita</h2>
                    <p>Usa el mismo flujo SOAP para registrar, editar o ver notas por cada consulta.</p>
                </div>
                <div class="medical-toolbar__actions">
                    @if($registroHoyUrl)
                        <a href="{{ $registroHoyUrl }}" class="btn btn-primary"><i class="ri-stethoscope-line"></i> {{ $registroHoyLabel }}</a>
                        <p>Cita objetivo: {{ $toCitaDateTime($citaConfirmadaProxima)->format('d/m/Y H:i') }} ({{ $estadoConsulta[$citaConfirmadaProxima->estado] ?? $citaConfirmadaProxima->estado }})</p>
                    @else
                        <button class="btn btn-outline" type="button" disabled><i class="ri-stethoscope-line"></i> Registrar consulta de hoy</button>
                        <p>No hay una cita confirmada disponible para hoy o próximas horas.</p>
                    @endif
                </div>
            </div>
        </section>

        <section class="medical-record">
            <aside class="medical-column medical-column--left">
                <x-medical.card title="Perfil del paciente" subtitle="Datos de identificación" icon="ri-user-3-line">
                    <div class="medical-profile">
                        <img
                            src="{{ $avatarPaciente['thumb'] }}"
                            @if($avatarPaciente['srcset']) srcset="{{ $avatarPaciente['srcset'] }}" sizes="112px" @endif
                            alt="Foto paciente"
                            class="medical-profile__avatar"
                            loading="lazy"
                            decoding="async"
                        >
                        <div class="medical-profile__content">
                            <h2>{{ $paciente->name }}</h2>
                            <dl>
                                <div><dt>Edad</dt><dd>{{ $edadPaciente !== null ? $edadPaciente . ' años' : 'No registrada' }}</dd></div>
                                <div><dt>Fecha nacimiento</dt><dd>{{ $paciente->fecha_nacimiento ? Carbon::parse($paciente->fecha_nacimiento)->format('d/m/Y') : 'No registrada' }}</dd></div>
                                <div>
                                    <dt>Ultima consulta</dt>
                                    <dd>
                                        @if($ultimaConsulta && $ultimaConsulta->fecha)
                                            {{ Carbon::parse($ultimaConsulta->fecha)->format('d/m/Y') }}
                                            @if($ultimaConsulta->hora) {{ Carbon::parse($ultimaConsulta->hora)->format('H:i') }} @endif
                                        @else
                                            Sin consultas
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </x-medical.card>

                <x-medical.card title="Signos vitales" subtitle="Últimos signos y evolución" icon="ri-pulse-line">
                    <div class="medical-vitals">
                        @foreach($metricasSignos as $metrica)
                            <article class="vital-metric">
                                <header>
                                    <span class="vital-metric__label"><i class="{{ $metrica['icon'] }}"></i>{{ $metrica['label'] }}</span>
                                    <strong class="vital-metric__value">{{ $metrica['value'] }}</strong>
                                </header>
                                @if($metrica['sparkline'])
                                    <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><polyline points="{{ $metrica['sparkline'] }}"></polyline></svg>
                                @else
                                    <p class="vital-metric__empty">Sin historico suficiente</p>
                                @endif
                                <p class="vital-metric__delta">Cambio vs consulta anterior: {{ $metrica['delta'] }}</p>
                            </article>
                        @endforeach
                    </div>

                    <div class="medical-vitals-summary">
                        <h4>Últimos signos vitales</h4>
                        <p>Peso: {{ $metricasSignos[1]['value'] }} | Altura: {{ $metricasSignos[0]['value'] }}</p>
                        <p>Presión: {{ $metricasSignos[2]['value'] }} | FC: {{ $metricasSignos[3]['value'] }} | FR: {{ $metricasSignos[4]['value'] }} | SpO2: {{ $metricasSignos[5]['value'] }} | Temp: {{ $metricasSignos[6]['value'] }}</p>
                    </div>

                    <div class="medical-vitals-evolution">
                        <h4>Evolución comparativa automática</h4>
                        @foreach($metricasSignos as $metrica)
                            <p>{{ $metrica['label'] }}: {{ $metrica['delta'] }}</p>
                        @endforeach
                    </div>
                </x-medical.card>
            </aside>

            <section class="medical-column medical-column--center">
                <x-medical.card title="Alergias" subtitle="Alertas relevantes del paciente" icon="ri-alarm-warning-line" :tone="$alergias->isNotEmpty() ? 'danger' : 'default'">
                    @if($alergias->isNotEmpty())
                        <ul class="medical-list medical-list--alerts">
                            @foreach($alergias as $alergia)
                                <li><i class="ri-alert-line"></i> {{ $alergia }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="medical-empty">No hay alergias registradas en el historial firmado.</p>
                    @endif
                </x-medical.card>

                <x-medical.card title="Antecedentes patológicos" subtitle="Resumen por categorías" icon="ri-file-history-line">
                    <div class="medical-grid-details">
                        <article>
                            <h4>Enfermedades cronicas</h4>
                            @if($enfermedadesCronicas->isNotEmpty())
                                <p>{{ $enfermedadesCronicas->implode(', ') }}</p>
                            @else
                                <p>Sin registro confirmado.</p>
                            @endif
                        </article>
                        <article><h4>Cirugias previas</h4><p>{{ $cirugiasPrevias ?: 'Sin registro confirmado.' }}</p></article>
                        <article><h4>Hospitalizaciones</h4><p>{{ $hospitalizaciones ?: 'Sin registro confirmado.' }}</p></article>
                        <article><h4>Diabetes</h4><p>{{ $diabetes ?: 'No documentada.' }}</p></article>
                        <article><h4>Hipertension</h4><p>{{ $hipertension ?: 'No documentada.' }}</p></article>
                        <article>
                            <h4>Otros</h4>
                            @if($otrosAntecedentes->isNotEmpty())
                                <p>{{ $otrosAntecedentes->implode(', ') }}</p>
                            @else
                                <p>Sin antecedentes adicionales.</p>
                            @endif
                        </article>
                    </div>
                </x-medical.card>

                <x-medical.card title="Consultas médicas" subtitle="Historial cronológico descendente y gestión de registro" icon="ri-stethoscope-line">
                    @if($consultas->isEmpty())
                        <p class="medical-empty">No hay consultas registradas para este paciente.</p>
                    @else
                        <div class="medical-stack">
                            @foreach($consultas as $cita)
                                @php
                                    $notaCita = $cita->notaSoap;
                                    $diagnostico = $notaCita?->diagnosticos?->pluck('texto')->filter()->implode(', ');
                                    $tratamiento = collect([$notaCita?->plan_general, $notaCita?->plan_notas])->filter()->implode(' | ');
                                    $fechaConsulta = $cita->fecha ? Carbon::parse($cita->fecha)->format('d/m/Y') : 'Sin fecha';
                                    $horaConsulta = $cita->hora ? Carbon::parse($cita->hora)->format('H:i') : '--:--';
                                    $estadoRegistro = $notaCita ? ($notaCita->estado === NotaSoap::ESTADO_FIRMADA ? 'Firmada' : 'Borrador') : 'Sin registro';
                                    $accionRegistro = $resolveRegistroAction($cita);
                                @endphp
                                <article class="medical-entry">
                                    <header class="medical-entry__header">
                                        <div>
                                            <strong>{{ $fechaConsulta }} {{ $horaConsulta }}</strong>
                                            <small>
                                                {{ $cita->especialidad?->nombre ?? 'Consulta general' }} |
                                                Estado cita: {{ $estadoConsulta[$cita->estado] ?? ucfirst($cita->estado) }} |
                                                Registro: {{ $estadoRegistro }}
                                            </small>
                                        </div>
                                        <div class="medical-entry__actions">
                                            @if($accionRegistro['label'])
                                                <a href="{{ route('doctor.citas.soap', $cita->id) }}" class="medical-chip medical-chip--strong">
                                                    <i class="ri-file-edit-line"></i> {{ $accionRegistro['label'] }}
                                                </a>
                                            @else
                                                <span class="medical-chip medical-chip--muted"><i class="ri-lock-line"></i> Sin acción</span>
                                            @endif

                                            @if($notaCita && $notaCita->estado === NotaSoap::ESTADO_FIRMADA)
                                                <a href="{{ route('doctor.citas.soap', $cita->id) }}" class="medical-chip"><i class="ri-eye-line"></i> Ver registro</a>
                                            @endif
                                        </div>
                                    </header>
                                    <dl>
                                        <div><dt>Motivo</dt><dd>{{ $notaCita?->subjetivo_motivo ?: 'Sin detalle' }}</dd></div>
                                        <div><dt>Examen clínico</dt><dd>{{ $notaCita?->examen_fisico ? Str::limit($notaCita->examen_fisico, 180) : 'Sin hallazgos registrados.' }}</dd></div>
                                        <div><dt>Observaciones médicas</dt><dd>{{ $notaCita?->notas_objetivas ? Str::limit($notaCita->notas_objetivas, 180) : 'Sin observaciones registradas.' }}</dd></div>
                                        <div><dt>Evaluación</dt><dd>{{ $notaCita?->assessment ? Str::limit($notaCita->assessment, 180) : 'Sin evaluación registrada.' }}</dd></div>
                                        <div><dt>Diagnóstico</dt><dd>{{ $diagnostico ?: 'Sin diagnóstico cargado.' }}</dd></div>
                                        <div><dt>Tratamiento</dt><dd>{{ $tratamiento ?: 'Sin tratamiento registrado.' }}</dd></div>
                                        <div><dt>Fecha de control</dt><dd>{{ $notaCita?->plan_seguimiento ?: 'Sin control registrado.' }}</dd></div>
                                        <div><dt>Doctor</dt><dd>{{ $cita->doctor?->name ?? 'No asignado' }}</dd></div>
                                        <div>
                                            <dt>Laboratorio</dt>
                                            <dd>
                                                @if($cita->laboratorioOrden)
                                                    {{ $cita->laboratorioOrden->tipo_examen ?? 'Orden de laboratorio registrada' }} |
                                                    Estado: {{ str_replace('_', ' ', (string) $cita->laboratorioOrden->estado) }}
                                                    @if($cita->laboratorioOrden->resultado_publicado_at)
                                                        | Publicado: {{ Carbon::parse($cita->laboratorioOrden->resultado_publicado_at)->format('d/m/Y') }}
                                                    @endif
                                                @else
                                                    Sin orden de laboratorio asociada.
                                                @endif
                                            </dd>
                                        </div>
                                    </dl>
                                    <p class="medical-entry__hint">{{ $accionRegistro['hint'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </x-medical.card>

                <x-medical.card title="Línea de tiempo clínica" subtitle="Eventos clínicos del paciente por orden cronológico" icon="ri-time-line">
                    @if($lineaTiempoClinica->isEmpty())
                        <p class="medical-empty">Sin eventos clínicos registrados.</p>
                    @else
                        <ol class="medical-timeline">
                            @foreach($lineaTiempoClinica as $evento)
                                <li>
                                    <span class="medical-timeline__date">
                                        {{ $evento['fecha'] instanceof Carbon ? $evento['fecha']->format('d/m/Y H:i') : '-' }}
                                    </span>
                                    <p class="medical-timeline__title">
                                        <i class="{{ $evento['icono'] }}"></i> {{ $evento['titulo'] }}
                                    </p>
                                    <p>{{ $evento['detalle'] ?: 'Sin descripcion registrada.' }}</p>
                                    @if(!empty($evento['cita_id']))
                                        <a href="{{ route('doctor.citas.soap', $evento['cita_id']) }}" class="medical-chip">
                                            <i class="ri-external-link-line"></i> Abrir consulta
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-medical.card>

                @if($notasPaginadas && method_exists($notasPaginadas, 'links'))
                    <div class="medical-pagination">{{ $notasPaginadas->links() }}</div>
                @endif
            </section>

            <aside class="medical-column medical-column--right">
                <x-medical.card title="Consultas agendadas" subtitle="Pendientes y confirmadas" icon="ri-calendar-check-line" tone="info">
                    @if($consultasAgendadas->isEmpty())
                        <p class="medical-empty">No hay consultas agendadas.</p>
                    @else
                        <div class="medical-stack medical-stack--compact">
                            @foreach($consultasAgendadas->take(6) as $cita)
                                @php($accionAgenda = $resolveRegistroAction($cita))
                                <article class="medical-activity">
                                    <strong>{{ Carbon::parse($cita->fecha)->format('d/m/Y') }} {{ Carbon::parse($cita->hora)->format('H:i') }}</strong>
                                    <p>{{ $cita->especialidad?->nombre ?? 'Consulta' }}</p>
                                    <span>{{ $estadoConsulta[$cita->estado] ?? ucfirst($cita->estado) }}</span>
                                    @if($accionAgenda['label'])
                                        <a href="{{ route('doctor.citas.soap', $cita->id) }}" class="medical-chip"><i class="ri-file-edit-line"></i> {{ $accionAgenda['label'] }}</a>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </x-medical.card>

                <x-medical.card title="Consultas realizadas" subtitle="Atenciones cerradas" icon="ri-checkbox-circle-line" tone="success">
                    @if($consultasRealizadas->isEmpty())
                        <p class="medical-empty">No hay consultas realizadas.</p>
                    @else
                        <div class="medical-stack medical-stack--compact">
                            @foreach($consultasRealizadas->take(6) as $cita)
                                @php($accionRealizada = $resolveRegistroAction($cita))
                                <article class="medical-activity">
                                    <strong>{{ Carbon::parse($cita->fecha)->format('d/m/Y') }} {{ Carbon::parse($cita->hora)->format('H:i') }}</strong>
                                    <p>{{ ($cita->notaSoap?->diagnosticos?->pluck('texto')->filter()->implode(', ')) ?: ($cita->especialidad?->nombre ?? 'Consulta realizada') }}</p>
                                    <span>Doctor: {{ $cita->doctor?->name ?? 'No asignado' }}</span>
                                    @if($accionRealizada['label'])
                                        <a href="{{ route('doctor.citas.soap', $cita->id) }}" class="medical-chip"><i class="ri-file-list-2-line"></i> {{ $accionRealizada['label'] }}</a>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </x-medical.card>
                <x-medical.card title="Resultados de laboratorio" subtitle="Registros de pruebas" icon="ri-flask-line">
                    @if($resultadosLaboratorio->isEmpty())
                        <p class="medical-empty">No hay resultados de laboratorio disponibles.</p>
                    @else
                        <div class="medical-stack medical-stack--compact">
                            @foreach($resultadosLaboratorio->take(6) as $orden)
                                <article class="medical-activity">
                                    <strong>{{ $orden->tipo_examen ?? 'Examen de laboratorio' }}</strong>
                                    <p>
                                        @if($orden->cita?->fecha)
                                            {{ Carbon::parse($orden->cita->fecha)->format('d/m/Y') }}
                                            @if($orden->cita?->hora) {{ Carbon::parse($orden->cita->hora)->format('H:i') }} @endif
                                        @else
                                            Fecha no registrada
                                        @endif
                                    </p>
                                    <span>
                                        Estado: {{ str_replace('_', ' ', $orden->estado) }}
                                        @if($orden->resultado_publicado_at)
                                            | Publicado: {{ Carbon::parse($orden->resultado_publicado_at)->format('d/m/Y') }}
                                        @endif
                                    </span>
                                    @if($orden->cita_id)
                                        <a href="{{ route('doctor.citas.soap', $orden->cita_id) }}" class="medical-chip">
                                            <i class="ri-external-link-line"></i> Abrir consulta
                                        </a>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </x-medical.card>

                <x-medical.card title="Recetas" subtitle="Indicaciones farmacéuticas" icon="ri-medicine-bottle-line">
                    @if($recetas->isEmpty())
                        <p class="medical-empty">No hay recetas emitidas.</p>
                    @else
                        <div class="medical-stack medical-stack--compact">
                            @foreach($recetas->take(6) as $receta)
                                <article class="medical-activity">
                                    <strong>
                                        @if($receta->cita?->fecha)
                                            {{ Carbon::parse($receta->cita->fecha)->format('d/m/Y') }}
                                            @if($receta->cita?->hora) {{ Carbon::parse($receta->cita->hora)->format('H:i') }} @endif
                                        @else
                                            Receta sin fecha
                                        @endif
                                    </strong>
                                    <p>{{ Str::limit($receta->diagnostico ?? 'Sin diagnóstico', 120) }}</p>
                                    <a href="{{ route('doctor.recetas.edit', $receta->cita_id) }}" class="medical-chip"><i class="ri-file-list-3-line"></i> Ver receta</a>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </x-medical.card>
            </aside>
        </section>

        <section class="card p-6">
            <x-ui.form-actions>
                <x-slot:left>
                    <a href="{{ route('doctor.citas') }}" class="btn btn-ghost">Volver a citas</a>
                </x-slot>
            </x-ui.form-actions>
        </section>
    </div>
@endsection
