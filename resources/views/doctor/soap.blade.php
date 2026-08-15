@extends('layouts.doctor')
@section('title', 'Nota médica de consulta')
@section('activeSidebar', 'citas')
@section('header-title','Nota médica de consulta')
@section('header-subtitle','Registro clínico individual de la atención')

@php
  $nota = $nota ?? null;
  $isSigned = $nota && $nota->isSigned();
  $persistenciaClinica = is_array($persistenciaClinica ?? null) ? $persistenciaClinica : [];
  $signosPrevios = is_array($signosPrevios ?? null) ? $signosPrevios : [];
  $rosData = is_array($nota?->subjetivo_ros) ? $nota->subjetivo_ros : [];
  $rosText = old('subjetivo_ros', is_array($nota?->subjetivo_ros) ? ($nota->subjetivo_ros['texto'] ?? '') : ($nota?->subjetivo_ros ?? ''));
  $alergiasPersistidas = is_array($persistenciaClinica['alergias'] ?? null) ? $persistenciaClinica['alergias'] : [];
  $antecedentesPersistidos = is_array($persistenciaClinica['antecedentes'] ?? null) ? $persistenciaClinica['antecedentes'] : [];
  $alergiasData = is_array($rosData['alergias'] ?? null) ? $rosData['alergias'] : $alergiasPersistidas;
  $antecedentesData = is_array($rosData['antecedentes'] ?? null) ? $rosData['antecedentes'] : $antecedentesPersistidos;
  $alergiasNoConocidasInput = old('alergias_no_conocidas', !empty($alergiasData['no_conocidas']) ? '1' : null);
  $alergiasNoConocidasChecked = in_array((string) $alergiasNoConocidasInput, ['1', 'true', 'on'], true);
  $alergiasDetalle = old('alergias_detalle', $alergiasData['detalle'] ?? '');
  $antecedentesCronicas = old('antecedentes_cronicas', $antecedentesData['cronicas'] ?? '');
  $antecedentesCirugias = old('antecedentes_cirugias', $antecedentesData['cirugias'] ?? '');
  $antecedentesHospitalizaciones = old('antecedentes_hospitalizaciones', $antecedentesData['hospitalizaciones'] ?? '');
  $antecedentesDiabetes = old('antecedentes_diabetes', $antecedentesData['diabetes'] ?? '');
  $antecedentesHipertension = old('antecedentes_hipertension', $antecedentesData['hipertension'] ?? '');
  $antecedentesOtros = old('antecedentes_otros', $antecedentesData['otros'] ?? '');
  $antecedentesFamiliares = old('antecedentes_familiares', $antecedentesData['familiares'] ?? '');
  $antecedentesInmunizaciones = old('antecedentes_inmunizaciones', $antecedentesData['inmunizaciones'] ?? '');
  $internalHpi = old('subjetivo_hpi', $nota?->subjetivo_hpi ?: 'Registro estructurado de antecedentes del paciente.');
  $internalRos = $rosText !== '' ? $rosText : 'Registro estructurado de alergias y antecedentes.';
  $internalNotas = old('subjetivo_notas', $nota?->subjetivo_notas ?: 'Registro estructurado de alergias del paciente.');
  $sv = is_array($nota?->signos_vitales) ? $nota->signos_vitales : [];
  $diagnosticos = old(
    'diagnosticos',
    ($nota?->diagnosticos?->map(fn($d) => ['tipo'=>$d->tipo, 'texto'=>$d->texto, 'cie10'=>$d->cie10])->toArray()) ?: [['tipo'=>'principal','texto'=>'','cie10'=>'']]
  );
  if (empty($diagnosticos)) {
    $diagnosticos = [['tipo'=>'principal','texto'=>'','cie10'=>'']];
  }
  $followUpDate = old('follow_up_date', optional($nota?->follow_up_date)->format('Y-m-d'));
  $followUpNotes = old('follow_up_notes', $nota?->follow_up_notes ?: ($nota?->follow_up_date ? '' : ($nota?->plan_seguimiento ?? '')));
  $controlMinFecha = now(config('app.timezone', 'America/Guayaquil'))->toDateString();
  $motivoConsulta = old('subjetivo_motivo', $nota?->subjetivo_motivo ?: ($cita->motivo_consulta ?? ''));
  $controlCita = $controlCita ?? null;
  $controlEstado = $controlCita?->estado === \App\Models\Cita::ESTADO_PENDIENTE ? 'En revision' : ucfirst((string) $controlCita?->estado);
@endphp

@section('main')
@php
  $expedienteUrl = route('doctor.pacientes.historial', ['paciente' => $cita->paciente_id] + ($cita->dependiente_id ? ['dependiente_id' => $cita->dependiente_id] : []));
@endphp
<div
  class="space-y-6"
  id="soap-page"
  data-csrf="{{ csrf_token() }}"
  data-autosave-url="{{ route('doctor.citas.soap.store', $cita->id) }}"
  data-plan-url="{{ route('doctor.citas.proxima.planificada', $cita->id) }}"
  data-slots-url-template="{{ route('api.doctor.slots', ['doctor' => '__DOCTOR__', 'fecha' => '__FECHA__']) }}"
  data-check-url="{{ route('doctor.disponibilidad.check') }}"
  data-doctor-id="{{ $cita->doctor_id }}"
  data-signos-previos='@json($signosPrevios)'
>
  <div class="panel-action-bar panel-action-bar--between">
    <div class="panel-action-bar__meta">
      Cita #{{ $cita->id }} | {{ $cita->nombrePacienteReal() }} | {{ optional($cita->especialidad)->nombre ?? '-' }} |
      {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }} {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}
    </div>
    <div class="panel-action-bar__actions">
      <x-ui.badge :tone="$isSigned ? 'success' : 'warning'">{{ $isSigned ? 'Firmada' : 'Borrador' }}</x-ui.badge>
      @if($cita->paciente_id)
        <a href="{{ $expedienteUrl }}" class="btn btn-ghost btn-sm">Abrir expediente del paciente</a>
      @endif
    </div>
  </div>

  @if(session('success') || session('error'))
    <div aria-live="polite" class="space-y-3">
      @if(session('success'))
        <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
      @endif
      @if(session('error'))
        <x-ui.alert tone="error">{{ session('error') }}</x-ui.alert>
      @endif
    </div>
  @endif



  <section class="card p-6">
    <form method="POST" action="{{ route('doctor.citas.soap.store', $cita->id) }}" class="space-y-6">
      @csrf
      <input type="hidden" name="subjetivo_hpi" value="{{ $internalHpi }}">
      <input type="hidden" name="subjetivo_ros" value="{{ $internalRos }}">
      <input type="hidden" name="subjetivo_notas" value="{{ $internalNotas }}">
      <input type="hidden" name="plan_seguimiento" value="{{ old('plan_seguimiento', $nota?->plan_seguimiento) }}">

      <div class="grid gap-6 xl:grid-cols-2">
        <section class="space-y-4 rounded-2xl border border-gray-200 p-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-gray-500">Subjetivo</p>
            <h3 class="text-lg font-semibold text-gray-900">Motivo, alergias y antecedentes</h3>
          </div>

          <div>
            <label class="form-label" for="subjetivo_motivo">Motivo de consulta</label>
            <textarea id="subjetivo_motivo" name="subjetivo_motivo" class="form-textarea" rows="3" @if($isSigned) readonly @endif>{{ $motivoConsulta }}</textarea>
            @error('subjetivo_motivo')
              <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
            <p class="mt-2 text-xs text-gray-500">Tomado del motivo registrado por el paciente al agendar la cita.</p>
          </div>

          <div>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" name="alergias_no_conocidas" value="1" class="soap-allergy-checkbox" @checked($alergiasNoConocidasChecked) @if($isSigned) disabled @endif>
              Sin alergias conocidas
            </label>
            @error('alergias_no_conocidas')
              <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
          </div>
          <div>
            <textarea name="alergias_detalle" class="form-textarea" rows="3" placeholder="Detalle de alergias" @if($isSigned) readonly @endif>{{ $alergiasDetalle }}</textarea>
            @error('alergias_detalle')
              <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
          </div>

          <div class="grid gap-3 md:grid-cols-2">
            <div>
              <textarea name="antecedentes_cronicas" class="form-textarea" rows="3" placeholder="Enfermedades crónicas" @if($isSigned) readonly @endif>{{ $antecedentesCronicas }}</textarea>
              @error('antecedentes_cronicas')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
              @enderror
            </div>
            <div>
              <textarea name="antecedentes_cirugias" class="form-textarea" rows="3" placeholder="Cirugías previas" @if($isSigned) readonly @endif>{{ $antecedentesCirugias }}</textarea>
              @error('antecedentes_cirugias')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
              @enderror
            </div>
            <div>
              <textarea name="antecedentes_hospitalizaciones" class="form-textarea" rows="3" placeholder="Hospitalizaciones" @if($isSigned) readonly @endif>{{ $antecedentesHospitalizaciones }}</textarea>
              @error('antecedentes_hospitalizaciones')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
              @enderror
            </div>
            <div>
              <textarea name="antecedentes_otros" class="form-textarea" rows="3" placeholder="Otros antecedentes" @if($isSigned) readonly @endif>{{ $antecedentesOtros }}</textarea>
              @error('antecedentes_otros')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
              @enderror
            </div>
            <div>
              <textarea name="antecedentes_familiares" class="form-textarea" rows="3" placeholder="Antecedentes familiares" @if($isSigned) readonly @endif>{{ $antecedentesFamiliares }}</textarea>
              @error('antecedentes_familiares')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
              @enderror
            </div>
            <div>
              <textarea name="antecedentes_inmunizaciones" class="form-textarea" rows="3" placeholder="Inmunizaciones" @if($isSigned) readonly @endif>{{ $antecedentesInmunizaciones }}</textarea>
              @error('antecedentes_inmunizaciones')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
              @enderror
            </div>
            <div>
              <select name="antecedentes_diabetes" class="form-select" @if($isSigned) disabled @endif>
                <option value="">Diabetes</option>
                <option value="No" @selected($antecedentesDiabetes === 'No')>No</option>
                <option value="Tipo 1" @selected($antecedentesDiabetes === 'Tipo 1')>Tipo 1</option>
                <option value="Tipo 2" @selected($antecedentesDiabetes === 'Tipo 2')>Tipo 2</option>
                <option value="Gestacional" @selected($antecedentesDiabetes === 'Gestacional')>Gestacional</option>
                <option value="No aplica" @selected($antecedentesDiabetes === 'No aplica')>No aplica</option>
              </select>
              @error('antecedentes_diabetes')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
              @enderror
            </div>
            <div>
              <select name="antecedentes_hipertension" class="form-select" @if($isSigned) disabled @endif>
                <option value="">Hipertensión</option>
                <option value="No" @selected($antecedentesHipertension === 'No')>No</option>
                <option value="Si" @selected($antecedentesHipertension === 'Si')>Sí</option>
                <option value="No documentado" @selected($antecedentesHipertension === 'No documentado')>No documentado</option>
              </select>
              @error('antecedentes_hipertension')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
              @enderror
            </div>
          </div>
        </section>

        <section class="space-y-4 rounded-2xl border border-gray-200 p-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-gray-500">Objetivo</p>
            <h3 class="text-lg font-semibold text-gray-900">Signos vitales y exploración</h3>
          </div>

            <div class="relative">
              <label class="sr-only" for="sv_ta">Presión arterial en mmHg</label>
              <input id="sv_ta" name="sv_ta" class="form-input pr-20" placeholder="Presión arterial" value="{{ old('sv_ta', $sv['ta'] ?? '') }}" aria-describedby="sv_ta_unit" required @if($isSigned) readonly @endif>
              <span id="sv_ta_unit" class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm font-semibold text-gray-500">mmHg</span>
              @error('sv_ta')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
              @enderror
            </div>
            <div class="relative">
              <label class="sr-only" for="sv_fc">Pulso o frecuencia cardiaca en lpm</label>
              <input id="sv_fc" name="sv_fc" type="number" class="form-input pr-16" placeholder="Pulso" value="{{ old('sv_fc', $sv['fc'] ?? '') }}" aria-describedby="sv_fc_unit" required @if($isSigned) readonly @endif>
              <span id="sv_fc_unit" class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm font-semibold text-gray-500">lpm</span>
              @error('sv_fc')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
              @enderror
            </div>
            <div class="relative">
              <label class="sr-only" for="sv_fr">Frecuencia respiratoria en rpm</label>
              <input id="sv_fr" name="sv_fr" type="number" class="form-input pr-16" placeholder="Respiración" value="{{ old('sv_fr', $sv['fr'] ?? '') }}" aria-describedby="sv_fr_unit" required @if($isSigned) readonly @endif>
              <span id="sv_fr_unit" class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm font-semibold text-gray-500">rpm</span>
              @error('sv_fr')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
              @enderror
            </div>
            <div class="relative">
              <label class="sr-only" for="sv_temp">Temperatura en grados Celsius</label>
              <input id="sv_temp" name="sv_temp" type="number" step="0.1" class="form-input pr-16" placeholder="Temperatura" value="{{ old('sv_temp', $sv['temp'] ?? '') }}" aria-describedby="sv_temp_unit" required @if($isSigned) readonly @endif>
              <span id="sv_temp_unit" class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm font-semibold text-gray-500">°C</span>
              @error('sv_temp')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
              @enderror
            </div>
            <div class="relative">
              <label class="sr-only" for="sv_spo2">Saturación de oxígeno en porcentaje</label>
              <input id="sv_spo2" name="sv_spo2" type="number" class="form-input pr-16" placeholder="SpO2" value="{{ old('sv_spo2', $sv['spo2'] ?? '') }}" aria-describedby="sv_spo2_unit" required @if($isSigned) readonly @endif>
              <span id="sv_spo2_unit" class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm font-semibold text-gray-500">%</span>
              @error('sv_spo2')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
              @enderror
            </div>
            <div class="relative">
              <label class="sr-only" for="sv_peso">Peso en kilogramos</label>
              <input id="sv_peso" name="sv_peso" type="number" step="0.1" class="form-input pr-16" placeholder="Peso" value="{{ old('sv_peso', $sv['peso'] ?? '') }}" aria-describedby="sv_peso_unit" required @if($isSigned) readonly @endif>
              <span id="sv_peso_unit" class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm font-semibold text-gray-500">kg</span>
              @error('sv_peso')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
              @enderror
            </div>
            <div class="relative md:col-span-2">
              <label class="sr-only" for="sv_talla">Estatura en centímetros</label>
              <input id="sv_talla" name="sv_talla" type="number" step="0.01" min="0" max="300" class="form-input pr-20" placeholder="Estatura" value="{{ old('sv_talla', $sv['talla'] ?? '') }}" aria-describedby="sv_talla_unit sv_talla_help" required @if($isSigned) readonly @endif>
              <span id="sv_talla_unit" class="pointer-events-none absolute inset-y-0 right-10 flex items-center text-sm font-semibold text-gray-500">cm</span>
              @error('sv_talla')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
              @enderror
            </div>
            <p id="sv_talla_help" class="text-xs text-gray-500 md:col-span-2">Registra la estatura en centímetros. Puedes escribir 163 o 1,63; se guardará como 163 cm.</p>
          </div>

          @if(!empty($signosPrevios))
            <div class="soap-soft-note rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
              Últimos signos previos: TA {{ $signosPrevios['ta'] ?? '---' }} mmHg, FC {{ $signosPrevios['fc'] ?? '---' }} lpm, FR {{ $signosPrevios['fr'] ?? '---' }} rpm, Temp {{ $signosPrevios['temp'] ?? '---' }} °C.
            </div>
          @endif

          <div>
            <textarea id="examen_fisico" name="examen_fisico" class="form-textarea" rows="4" placeholder="Hallazgos del examen físico" required @if($isSigned) readonly @endif>{{ old('examen_fisico', $nota?->examen_fisico) }}</textarea>
            @error('examen_fisico')
              <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
          </div>
          <div>
            <textarea id="notas_objetivas" name="notas_objetivas" class="form-textarea" rows="4" placeholder="Observaciones médicas" required @if($isSigned) readonly @endif>{{ old('notas_objetivas', $nota?->notas_objetivas) }}</textarea>
            @error('notas_objetivas')
              <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
          </div>
        </section>
      </div>

      <section class="space-y-4 rounded-2xl border border-gray-200 p-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-gray-500">Evaluación</p>
          <h3 class="text-lg font-semibold text-gray-900">Evaluación y diagnósticos</h3>
        </div>

        <div>
          <textarea id="assessment" name="assessment" class="form-textarea" rows="4" placeholder="Evaluación médica" required @if($isSigned) readonly @endif>{{ old('assessment', $nota?->assessment) }}</textarea>
          @error('assessment')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
          @enderror
        </div>

        <div>
          <div class="mb-3 flex items-center justify-between">
            <label class="form-label">Diagnósticos *</label>
            @if(!$isSigned)
              <button class="btn btn-ghost btn-sm" type="button" id="add-diagnostico">Agregar diagnóstico</button>
            @endif
          </div>
          @error('diagnosticos')
            <p class="text-red-500 text-sm mb-3">{{ $message }}</p>
          @enderror
          <div class="space-y-3" id="diagnosticos-wrap">
            @foreach($diagnosticos as $index => $diag)
              <div class="grid gap-2 md:grid-cols-[140px_1fr_160px]" data-diag-row="1">
                <div>
                  <select name="diagnosticos[{{ $index }}][tipo]" class="form-select" @if($isSigned) disabled @endif>
                    <option value="principal" @selected(($diag['tipo'] ?? '') === 'principal')>Principal</option>
                    <option value="secundario" @selected(($diag['tipo'] ?? '') === 'secundario')>Secundario</option>
                    <option value="diferencial" @selected(($diag['tipo'] ?? '') === 'diferencial')>Diferencial</option>
                  </select>
                  @error("diagnosticos.$index.tipo")
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                  @enderror
                </div>
                <div>
                  <input name="diagnosticos[{{ $index }}][texto]" class="form-input" value="{{ $diag['texto'] ?? '' }}" placeholder="Diagnóstico" @if($isSigned) readonly @endif>
                  @error("diagnosticos.$index.texto")
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                  @enderror
                </div>
                <div>
                  <input name="diagnosticos[{{ $index }}][cie10]" class="form-input" value="{{ $diag['cie10'] ?? '' }}" placeholder="CIE-10" @if($isSigned) readonly @endif>
                  @error("diagnosticos.$index.cie10")
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                  @enderror
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </section>

      <section class="space-y-4 rounded-2xl border border-gray-200 p-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-gray-500">Plan</p>
          <h3 class="text-lg font-semibold text-gray-900">Tratamiento y seguimiento</h3>
        </div>

        <div>
          <textarea id="plan_general" name="plan_general" class="form-textarea" rows="4" placeholder="Tratamiento indicado" required @if($isSigned) readonly @endif>{{ old('plan_general', $nota?->plan_general) }}</textarea>
          @error('plan_general')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
          @enderror
        </div>
        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <label class="form-label" for="follow_up_date">Próximo control</label>
            <input id="follow_up_date" type="date" name="follow_up_date" class="form-input" value="{{ $followUpDate }}" min="{{ $controlMinFecha }}" @if($isSigned) readonly @endif>
            @error('follow_up_date')
              <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
          </div>
          <div>
            <label class="form-label" for="follow_up_notes">Observaciones de seguimiento</label>
            <textarea id="follow_up_notes" name="follow_up_notes" class="form-textarea" rows="3" @if($isSigned) readonly @endif>{{ $followUpNotes }}</textarea>
            @error('follow_up_notes')
              <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
          </div>
        </div>
        <div>
          <textarea id="plan_notas" name="plan_notas" class="form-textarea" rows="4" placeholder="Indicaciones al paciente" required @if($isSigned) readonly @endif>{{ old('plan_notas', $nota?->plan_notas) }}</textarea>
          @error('plan_notas')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
          @enderror
        </div>

        <div class="soap-plan-control-box rounded-2xl border border-gray-200 bg-white/80 p-4" id="plan-control-box">
          @if($isSigned && $controlCita)
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h4 class="text-sm font-semibold text-gray-900">Control agendado</h4>
                <p class="mt-1 text-sm font-semibold text-gray-900">
                  {{ \Carbon\Carbon::parse($controlCita->fecha)->format('d/m/Y') }}
                  {{ \Carbon\Carbon::parse($controlCita->hora)->format('H:i') }}
                </p>
                <p class="mt-1 text-xs text-gray-500">
                  Estado: {{ $controlEstado }}.
                  {{ $controlCita->especialidad?->nombre ? 'Especialidad: '.$controlCita->especialidad->nombre.'.' : '' }}
                  {{ $controlCita->doctor?->name ? 'Médico: '.$controlCita->doctor->name.'.' : '' }}
                </p>
              </div>
              <form method="POST" action="{{ route('doctor.citas.proxima.cancelar', ['cita' => $cita->id, 'control' => $controlCita->id]) }}">
                @csrf
                <button class="btn btn-danger btn-sm" type="submit" data-confirm-title="Cancelar control agendado" data-confirm-message="¿Estás seguro de que deseas cancelar el control agendado para este paciente?" data-confirm-consequence="Cancelar el control agendado no elimina la nota clínica, pero deja la cita sin vigencia." data-confirm-action="anular" data-confirm-btn="Sí, anular" data-action-lock-title="Cancelando control..." data-action-lock-description="Por favor, espera. No cierres esta página.">Cancelar control</button>
              </form>
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-[1fr_1fr_auto]">
              <div>
                <label class="form-label" for="control-fecha">Nueva fecha del control</label>
                <input id="control-fecha" type="date" class="form-input" min="{{ $controlMinFecha }}">
              </div>
              <div>
                <label class="form-label" for="control-hora">Nuevo horario disponible</label>
                <select id="control-hora" class="form-select" disabled>
                  <option value="">Selecciona fecha primero</option>
                </select>
              </div>
              <div class="flex items-end">
                <button class="btn btn-outline w-full lg:w-auto" type="button" id="btn-agendar-control" disabled data-action-lock-title="Reagendando control..." data-action-lock-description="Por favor, espera. No cierres esta página.">Reagendar control</button>
              </div>
            </div>
            <p id="control-help" class="mt-2 text-xs text-gray-500">Selecciona una nueva fecha para consultar horarios disponibles.</p>
          @else
            <h4 class="text-sm font-semibold text-gray-900">Agendar control</h4>
            <p class="mt-1 text-xs text-gray-500">
              Programa la próxima cita de seguimiento sin salir de esta consulta.
              @if($isSigned && $followUpDate)
                <span class="block mt-1">Fecha sugerida registrada en la nota, aun sin cita agendada: {{ \Carbon\Carbon::parse($followUpDate)->format('d/m/Y') }}.</span>
              @endif
            </p>
            @if($isSigned)
              <div class="mt-3 grid gap-3 lg:grid-cols-[1fr_1fr_auto]">
                <div>
                  <label class="form-label" for="control-fecha">Fecha del control</label>
                  <input id="control-fecha" type="date" class="form-input" min="{{ $controlMinFecha }}">
                </div>
                <div>
                  <label class="form-label" for="control-hora">Horario disponible</label>
                  <select id="control-hora" class="form-select" disabled>
                    <option value="">Selecciona fecha primero</option>
                  </select>
                </div>
                <div class="flex items-end">
                  <button class="btn btn-outline w-full lg:w-auto" type="button" id="btn-agendar-control" disabled data-action-lock-title="Agendando control..." data-action-lock-description="Por favor, espera. No cierres esta página.">Agendar control</button>
                </div>
              </div>
              <p id="control-help" class="mt-2 text-xs text-gray-500">Selecciona una fecha para consultar horarios disponibles.</p>
            @else
              <p class="mt-2 text-xs text-gray-500">Firma la nota para habilitar el agendamiento del siguiente control.</p>
            @endif
          @endif
        </div>
      </section>

      <x-ui.form-actions class="soap-action-bar">
        <x-slot:left>
          <a href="{{ route('doctor.citas') }}" class="btn btn-ghost">Volver</a>
        </x-slot>
        @if(!$isSigned)
          <button class="btn btn-outline" type="submit">Guardar borrador</button>
          <button class="btn btn-primary" type="submit" formaction="{{ route('doctor.citas.soap.firmar', $cita->id) }}">Firmar y cerrar</button>
        @endif
      </x-ui.form-actions>
    </form>
  </section>

  @if($isSigned)
    <section class="card p-6 space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h3 class="text-lg font-semibold text-gray-900">Consulta cerrada</h3>
          <p class="text-sm text-gray-600">La nota ya fue firmada. La corrección posterior se registra mediante enmiendas.</p>
        </div>
        @if($cita->estado === \App\Models\Cita::ESTADO_CONFIRMADA)
          <form action="{{ route('doctor.citas.realizar', $cita->id) }}" method="POST">
            @csrf
            <button class="btn btn-primary" type="submit">Marcar cita como realizada</button>
          </form>
        @endif
      </div>
    </section>

    <section class="card p-6 space-y-4">
      <h3 class="text-lg font-semibold text-gray-900">Enmiendas y aclaraciones</h3>
          @if(collect($nota?->enmiendas ?? [])->isNotEmpty())
        <div class="space-y-3">
          @foreach(($nota?->enmiendas ?? []) as $enmienda)
            <div class="soap-enmienda-card rounded-xl border border-gray-200 bg-white/90 px-3 py-3 text-sm text-gray-700">
              <div class="font-semibold text-gray-900">{{ $enmienda->motivo }}</div>
              <p class="mt-2">{{ $enmienda->contenido }}</p>
              <div class="mt-2 text-xs text-gray-500">{{ $enmienda->autor?->name ?? 'Usuario' }} | {{ $enmienda->created_at?->format('Y-m-d H:i') }}</div>
            </div>
          @endforeach
        </div>
      @else
        <p class="text-sm text-gray-600">No hay enmiendas registradas.</p>
      @endif

      <form method="POST" action="{{ route('doctor.citas.soap.enmienda', $cita->id) }}" class="space-y-3">
        @csrf
        <input id="motivo" name="motivo" class="form-input" value="{{ old('motivo') }}" placeholder="Motivo de la corrección" required>
        <textarea id="contenido" name="contenido" class="form-textarea" rows="3" placeholder="Detalle de la corrección" required>{{ old('contenido') }}</textarea>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <a href="{{ route('doctor.citas') }}" class="btn btn-ghost">Volver</a>
          <button class="btn btn-outline" type="submit">Registrar enmienda</button>
        </div>
      </form>
    </section>
  @endif
</div>

@push('styles')
  <style>
    #soap-page .soap-action-bar {
      --soap-action-bar-bg: rgba(255, 255, 255, 0.94);
      --soap-action-bar-border: #e5e7eb;
      --soap-action-bar-shadow: 0 -12px 30px rgba(17, 24, 39, 0.08);
      --soap-action-bar-backdrop: blur(10px);
    }

    html.dashboard-root.panel-theme-dark #soap-page .soap-action-bar {
      --soap-action-bar-bg: linear-gradient(180deg, rgba(15, 23, 42, 0.98) 0%, rgba(17, 24, 39, 0.98) 100%);
      --soap-action-bar-border: #334155;
      --soap-action-bar-shadow: 0 -12px 30px rgba(2, 6, 23, 0.36);
    }

    html.dashboard-root.panel-theme-dark #soap-page .soap-plan-control-box,
    html.dashboard-root.panel-theme-dark #soap-page .soap-enmienda-card,
    html.dashboard-root.panel-theme-dark #soap-page .soap-soft-note {
      border-color: #334155;
      background: rgba(15, 23, 42, 0.92);
      color: #e2e8f0;
    }

    html.dashboard-root.panel-theme-dark #soap-page .text-gray-900 {
      color: #f8fafc;
    }

    html.dashboard-root.panel-theme-dark #soap-page .text-gray-800 {
      color: #e2e8f0;
    }

    html.dashboard-root.panel-theme-dark #soap-page .text-gray-700 {
      color: #d1d5db;
    }

    html.dashboard-root.panel-theme-dark #soap-page .text-gray-600 {
      color: #94a3b8;
    }

    html.dashboard-root.panel-theme-dark #soap-page .text-gray-500 {
      color: #64748b;
    }

    html.dashboard-root.panel-theme-dark #soap-page .border-gray-200 {
      border-color: #334155;
    }

    html.dashboard-root.panel-theme-dark #soap-page .soap-allergy-checkbox {
      appearance: none;
      -webkit-appearance: none;
      display: inline-grid;
      place-content: center;
      flex: none;
      width: 1rem;
      height: 1rem;
      margin: 0;
      border: 1px solid #475569;
      border-radius: 0.25rem;
      background: #0f172a;
      box-shadow: inset 0 1px 2px rgba(2, 6, 23, 0.32);
      color: var(--accent);
      cursor: pointer;
    }

    html.dashboard-root.panel-theme-dark #soap-page .soap-allergy-checkbox:checked {
      border-color: var(--accent);
      background-color: var(--accent);
      background-repeat: no-repeat;
      background-position: center;
      background-size: 0.75rem 0.75rem;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M3.5 8.5l3 3 6-6' stroke='%23fff' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    }

    html.dashboard-root.panel-theme-dark #soap-page .soap-allergy-checkbox:focus-visible {
      outline: 2px solid color-mix(in srgb, var(--accent) 56%, white);
      outline-offset: 2px;
    }

    html.dashboard-root.panel-theme-dark #soap-page .soap-allergy-checkbox:disabled {
      cursor: not-allowed;
      opacity: 0.75;
    }

    @media (max-width: 768px) {
      html.dashboard-root.panel-theme-dark #soap-page .soap-action-bar {
        --soap-action-bar-shadow: 0 -10px 24px rgba(2, 6, 23, 0.42);
      }
    }
  </style>
@endpush

@push('scripts')
  @vite('resources/js/doctor/soap.js')
@endpush
@endsection
