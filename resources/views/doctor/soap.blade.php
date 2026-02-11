@extends('layouts.doctor')
@section('title', 'Consulta medica')
@section('activeSidebar', 'citas')
@section('header-title','Consulta medica')
@section('header-subtitle','Formulario de atencion medica')

@php
  $nota = $nota ?? null;
  $isSigned = $nota && $nota->isSigned();
  $persistenciaClinica = is_array($persistenciaClinica ?? null) ? $persistenciaClinica : [];
  $signosPrevios = is_array($signosPrevios ?? null) ? $signosPrevios : [];
  $rosData = is_array($nota?->subjetivo_ros) ? $nota->subjetivo_ros : [];
  $rosText = old(
    'subjetivo_ros',
    is_array($nota?->subjetivo_ros)
      ? ($nota->subjetivo_ros['texto'] ?? '')
      : ($nota?->subjetivo_ros ?? '')
  );
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
  $internalHpi = old('subjetivo_hpi', $nota?->subjetivo_hpi ?: 'Registro estructurado de antecedentes del paciente.');
  $internalRos = $rosText !== '' ? $rosText : 'Registro estructurado de alergias y antecedentes.';
  $internalNotas = old('subjetivo_notas', $nota?->subjetivo_notas ?: 'Registro estructurado de alergias del paciente.');
  $sv = is_array($nota?->signos_vitales) ? $nota->signos_vitales : [];
  $diagnosticos = old(
    'diagnosticos',
    ($nota?->diagnosticos?->map(function($d){
      return ['tipo'=>$d->tipo, 'texto'=>$d->texto, 'cie10'=>$d->cie10];
    })->toArray()) ?: [['tipo'=>'principal','texto'=>'','cie10'=>'']]
  );
  if (empty($diagnosticos)) {
    $diagnosticos = [['tipo'=>'principal','texto'=>'','cie10'=>'']];
  }
  $controlMinFecha = now(config('app.timezone', 'America/Guayaquil'))->toDateString();
@endphp

@section('content')
<div
  class="space-y-6"
  id="soap-page"
  data-csrf="{{ csrf_token() }}"
  data-plan-url="{{ route('doctor.citas.proxima.planificada', $cita->id) }}"
  data-slots-url-template="{{ route('api.doctor.slots', ['doctor' => '__DOCTOR__', 'fecha' => '__FECHA__']) }}"
  data-check-url="{{ route('doctor.disponibilidad.check') }}"
  data-doctor-id="{{ $cita->doctor_id }}"
  data-signos-previos='@json($signosPrevios)'
>
  <section class="card p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Cita #{{ $cita->id }}</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Consulta medica</h1>
        <p class="text-sm text-slate-600">Formulario de atencion medica.</p>
        <p class="text-slate-600">
          {{ optional($cita->paciente)->name ?? 'Paciente' }} &middot; {{ optional($cita->especialidad)->nombre ?? '-' }} &middot;
          {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }} {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}
        </p>
      </div>
      <div>
        <x-ui.badge :tone="$isSigned ? 'success' : 'warning'">{{ $isSigned ? 'Firmada' : 'Borrador' }}</x-ui.badge>
      </div>
    </div>
  </section>

  @if(session('success') || session('error'))
    <div aria-live="polite">
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

      <fieldset class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4">
        <legend class="px-2 text-sm font-semibold text-slate-900">1) Informacion del paciente</legend>
        <p class="mb-3 text-xs text-slate-500">Registra solo lo que se mostrara en el historial profesional.</p>
        <p class="mb-3 text-xs text-slate-500">Esto se vera en: Consultas realizadas.</p>

        <div>
          <label class="form-label" for="subjetivo_motivo">Por que consulta hoy? *</label>
          <textarea id="subjetivo_motivo" name="subjetivo_motivo" class="form-textarea" rows="3" required @if($isSigned) readonly @endif>{{ old('subjetivo_motivo', $nota?->subjetivo_motivo) }}</textarea>
          @error('subjetivo_motivo')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>

        <input type="hidden" name="subjetivo_hpi" value="{{ $internalHpi }}">
        <input type="hidden" name="subjetivo_ros" value="{{ $internalRos }}">
        <input type="hidden" name="subjetivo_notas" value="{{ $internalNotas }}">
      </fieldset>

      <fieldset class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4">
        <legend class="px-2 text-sm font-semibold text-slate-900">2) Alergias</legend>
        <p class="mb-3 text-xs text-slate-500">Registra alergias activas del paciente para alertas clinicas.</p>
        <p class="mb-3 text-xs text-slate-500">Esto se vera en: Alergias.</p>

        <div class="space-y-3">
          <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="alergias_no_conocidas" value="1" @if($alergiasNoConocidasChecked) checked @endif @if($isSigned) disabled @endif>
            No conocidas
          </label>
          <div>
            <label class="form-label" for="alergias_detalle">Detalle de alergias</label>
            <textarea id="alergias_detalle" name="alergias_detalle" class="form-textarea" rows="3" @if($isSigned) readonly @endif>{{ $alergiasDetalle }}</textarea>
          </div>
        </div>
      </fieldset>

      <fieldset class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4">
        <legend class="px-2 text-sm font-semibold text-slate-900">3) Antecedentes del paciente</legend>
        <p class="mb-3 text-xs text-slate-500">Completa antecedentes relevantes para seguimiento medico.</p>
        <p class="mb-3 text-xs text-slate-500">Esto se vera en: Antecedentes patologicos.</p>

        <div class="grid gap-4 lg:grid-cols-2">
          <div>
            <label class="form-label" for="antecedentes_cronicas">Enfermedades cronicas</label>
            <textarea id="antecedentes_cronicas" name="antecedentes_cronicas" class="form-textarea" rows="3" @if($isSigned) readonly @endif>{{ $antecedentesCronicas }}</textarea>
          </div>
          <div>
            <label class="form-label" for="antecedentes_cirugias">Cirugias previas</label>
            <textarea id="antecedentes_cirugias" name="antecedentes_cirugias" class="form-textarea" rows="3" @if($isSigned) readonly @endif>{{ $antecedentesCirugias }}</textarea>
          </div>
          <div>
            <label class="form-label" for="antecedentes_hospitalizaciones">Hospitalizaciones</label>
            <textarea id="antecedentes_hospitalizaciones" name="antecedentes_hospitalizaciones" class="form-textarea" rows="3" @if($isSigned) readonly @endif>{{ $antecedentesHospitalizaciones }}</textarea>
          </div>
          <div>
            <label class="form-label" for="antecedentes_diabetes">Diabetes</label>
            <select id="antecedentes_diabetes" name="antecedentes_diabetes" class="form-select" @if($isSigned) disabled @endif>
              <option value="">Seleccionar</option>
              <option value="No" {{ $antecedentesDiabetes === 'No' ? 'selected' : '' }}>No</option>
              <option value="Tipo 1" {{ $antecedentesDiabetes === 'Tipo 1' ? 'selected' : '' }}>Tipo 1</option>
              <option value="Tipo 2" {{ $antecedentesDiabetes === 'Tipo 2' ? 'selected' : '' }}>Tipo 2</option>
              <option value="Gestacional" {{ $antecedentesDiabetes === 'Gestacional' ? 'selected' : '' }}>Gestacional</option>
              <option value="No aplica" {{ $antecedentesDiabetes === 'No aplica' ? 'selected' : '' }}>No aplica</option>
            </select>
          </div>
          <div>
            <label class="form-label" for="antecedentes_hipertension">Hipertension</label>
            <select id="antecedentes_hipertension" name="antecedentes_hipertension" class="form-select" @if($isSigned) disabled @endif>
              <option value="">Seleccionar</option>
              <option value="No" {{ $antecedentesHipertension === 'No' ? 'selected' : '' }}>No</option>
              <option value="Si" {{ $antecedentesHipertension === 'Si' ? 'selected' : '' }}>Si</option>
              <option value="No documentado" {{ $antecedentesHipertension === 'No documentado' ? 'selected' : '' }}>No documentado</option>
            </select>
          </div>
          <div class="lg:col-span-2">
            <label class="form-label" for="antecedentes_otros">Otros antecedentes</label>
            <textarea id="antecedentes_otros" name="antecedentes_otros" class="form-textarea" rows="3" @if($isSigned) readonly @endif>{{ $antecedentesOtros }}</textarea>
          </div>
        </div>
      </fieldset>

      <fieldset class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4">
        <legend class="px-2 text-sm font-semibold text-slate-900">4) Examen clinico</legend>
        <p class="mb-3 text-xs text-slate-500">Signos vitales, hallazgos y examen fisico.</p>
        <p class="mb-3 text-xs text-slate-500">Lo registrado en signos vitales se vera en "Signos vitales" del Historial clinico profesional y se usara para la evolucion con consultas anteriores.</p>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <label class="form-label" for="sv_ta">Presion arterial *</label>
            <input id="sv_ta" name="sv_ta" class="form-input" value="{{ old('sv_ta', $sv['ta'] ?? '') }}" required @if($isSigned) readonly @endif>
            @error('sv_ta')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>
          <div>
            <label class="form-label" for="sv_fc">Pulso cardiaco *</label>
            <input id="sv_fc" name="sv_fc" type="number" class="form-input" value="{{ old('sv_fc', $sv['fc'] ?? '') }}" required @if($isSigned) readonly @endif>
            @error('sv_fc')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>
          <div>
            <label class="form-label" for="sv_fr">Respiracion por minuto *</label>
            <input id="sv_fr" name="sv_fr" type="number" class="form-input" value="{{ old('sv_fr', $sv['fr'] ?? '') }}" required @if($isSigned) readonly @endif>
            @error('sv_fr')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>
          <div>
            <label class="form-label" for="sv_temp">Temperatura corporal *</label>
            <input id="sv_temp" name="sv_temp" type="number" step="0.1" class="form-input" value="{{ old('sv_temp', $sv['temp'] ?? '') }}" required @if($isSigned) readonly @endif>
            @error('sv_temp')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>
          <div>
            <label class="form-label" for="sv_spo2">Saturacion de oxigeno *</label>
            <input id="sv_spo2" name="sv_spo2" type="number" class="form-input" value="{{ old('sv_spo2', $sv['spo2'] ?? '') }}" required @if($isSigned) readonly @endif>
            @error('sv_spo2')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>
          <div>
            <label class="form-label" for="sv_peso">Peso (kg) *</label>
            <input id="sv_peso" name="sv_peso" type="number" step="0.1" class="form-input" value="{{ old('sv_peso', $sv['peso'] ?? '') }}" required @if($isSigned) readonly @endif>
            @error('sv_peso')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>
          <div>
            <label class="form-label" for="sv_talla">Estatura (cm) *</label>
            <input id="sv_talla" name="sv_talla" type="number" step="0.1" class="form-input" value="{{ old('sv_talla', $sv['talla'] ?? '') }}" required @if($isSigned) readonly @endif>
            @error('sv_talla')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>
        </div>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-white/80 p-4" id="sv-evolution">
          <h4 class="text-sm font-semibold text-slate-900">Evolucion automatica de signos vitales</h4>
          @if(empty($signosPrevios))
            <p class="mt-2 text-xs text-slate-500">No hay una consulta firmada previa con signos vitales para comparar.</p>
          @else
            <p class="mt-2 text-xs text-slate-500">El sistema compara los valores actuales con la ultima consulta firmada del paciente.</p>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
              <article class="rounded-xl border border-slate-200 bg-slate-50/70 p-3" data-sv-card data-sv-input="sv_ta" data-sv-unit="mmHg" data-sv-previous="{{ e((string) ($signosPrevios['ta'] ?? '')) }}">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Presion arterial</p>
                <p class="text-sm text-slate-700">Ultimo valor: <strong>{{ $signosPrevios['ta'] ?? 'Sin registro' }}</strong></p>
                <p class="text-xs text-slate-500">Cambio actual: <span data-sv-delta>Ingresa un valor actual para comparar.</span></p>
              </article>
              <article class="rounded-xl border border-slate-200 bg-slate-50/70 p-3" data-sv-card data-sv-input="sv_fc" data-sv-unit="lpm" data-sv-previous="{{ e((string) ($signosPrevios['fc'] ?? '')) }}">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pulso cardiaco</p>
                <p class="text-sm text-slate-700">Ultimo valor: <strong>{{ $signosPrevios['fc'] ?? 'Sin registro' }}</strong></p>
                <p class="text-xs text-slate-500">Cambio actual: <span data-sv-delta>Ingresa un valor actual para comparar.</span></p>
              </article>
              <article class="rounded-xl border border-slate-200 bg-slate-50/70 p-3" data-sv-card data-sv-input="sv_fr" data-sv-unit="rpm" data-sv-previous="{{ e((string) ($signosPrevios['fr'] ?? '')) }}">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Respiracion</p>
                <p class="text-sm text-slate-700">Ultimo valor: <strong>{{ $signosPrevios['fr'] ?? 'Sin registro' }}</strong></p>
                <p class="text-xs text-slate-500">Cambio actual: <span data-sv-delta>Ingresa un valor actual para comparar.</span></p>
              </article>
              <article class="rounded-xl border border-slate-200 bg-slate-50/70 p-3" data-sv-card data-sv-input="sv_temp" data-sv-unit="C" data-sv-previous="{{ e((string) ($signosPrevios['temp'] ?? '')) }}">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Temperatura</p>
                <p class="text-sm text-slate-700">Ultimo valor: <strong>{{ $signosPrevios['temp'] ?? 'Sin registro' }}</strong></p>
                <p class="text-xs text-slate-500">Cambio actual: <span data-sv-delta>Ingresa un valor actual para comparar.</span></p>
              </article>
              <article class="rounded-xl border border-slate-200 bg-slate-50/70 p-3" data-sv-card data-sv-input="sv_spo2" data-sv-unit="%" data-sv-previous="{{ e((string) ($signosPrevios['spo2'] ?? '')) }}">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Saturacion de oxigeno</p>
                <p class="text-sm text-slate-700">Ultimo valor: <strong>{{ $signosPrevios['spo2'] ?? 'Sin registro' }}</strong></p>
                <p class="text-xs text-slate-500">Cambio actual: <span data-sv-delta>Ingresa un valor actual para comparar.</span></p>
              </article>
              <article class="rounded-xl border border-slate-200 bg-slate-50/70 p-3" data-sv-card data-sv-input="sv_peso" data-sv-unit="kg" data-sv-previous="{{ e((string) ($signosPrevios['peso'] ?? '')) }}">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Peso</p>
                <p class="text-sm text-slate-700">Ultimo valor: <strong>{{ $signosPrevios['peso'] ?? 'Sin registro' }}</strong></p>
                <p class="text-xs text-slate-500">Cambio actual: <span data-sv-delta>Ingresa un valor actual para comparar.</span></p>
              </article>
              <article class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 md:col-span-2" data-sv-card data-sv-input="sv_talla" data-sv-unit="cm" data-sv-previous="{{ e((string) ($signosPrevios['talla'] ?? '')) }}">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estatura</p>
                <p class="text-sm text-slate-700">Ultimo valor: <strong>{{ $signosPrevios['talla'] ?? 'Sin registro' }}</strong></p>
                <p class="text-xs text-slate-500">Cambio actual: <span data-sv-delta>Ingresa un valor actual para comparar.</span></p>
              </article>
            </div>
          @endif
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
          <div>
            <label class="form-label" for="examen_fisico">Hallazgos del examen fisico *</label>
            <textarea id="examen_fisico" name="examen_fisico" class="form-textarea" required rows="3" @if($isSigned) readonly @endif>{{ old('examen_fisico', $nota?->examen_fisico) }}</textarea>
            @error('examen_fisico')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>
          <div>
            <label class="form-label" for="notas_objetivas">Observaciones medicas *</label>
            <textarea id="notas_objetivas" name="notas_objetivas" class="form-textarea" required rows="3" @if($isSigned) readonly @endif>{{ old('notas_objetivas', $nota?->notas_objetivas) }}</textarea>
            @error('notas_objetivas')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>
        </div>
      </fieldset>
      <fieldset class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4">
        <legend class="px-2 text-sm font-semibold text-slate-900">5) Diagnostico</legend>
        <p class="mb-3 text-xs text-slate-500">Diagnostico presuntivo o confirmado.</p>
        <p class="mb-3 text-xs text-slate-500">Esto se listara en "Consultas realizadas" del Historial clinico profesional.</p>

        <div>
          <label class="form-label" for="assessment">Evaluacion medica *</label>
          <textarea id="assessment" name="assessment" class="form-textarea" required rows="4" @if($isSigned) readonly @endif>{{ old('assessment', $nota?->assessment) }}</textarea>
          @error('assessment')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>

        <div class="mt-4">
          <div class="flex items-center justify-between">
            <label class="form-label">Diagnostico *</label>
            @if(!$isSigned)
              <button class="btn btn-ghost" type="button" id="add-diagnostico"><i class="ri-add-line"></i> Agregar</button>
            @endif
          </div>
          <div class="space-y-3" id="diagnosticos-wrap">
            @foreach($diagnosticos as $index => $diag)
              <div class="grid gap-2 md:grid-cols-[140px_1fr_140px]" data-diag-row="1">
                <select name="diagnosticos[{{ $index }}][tipo]" class="form-select" required @if($isSigned) disabled @endif>
                  <option value="principal" {{ ($diag['tipo'] ?? '') === 'principal' ? 'selected' : '' }}>Principal</option>
                  <option value="secundario" {{ ($diag['tipo'] ?? '') === 'secundario' ? 'selected' : '' }}>Secundario</option>
                  <option value="diferencial" {{ ($diag['tipo'] ?? '') === 'diferencial' ? 'selected' : '' }}>Diferencial</option>
                </select>
                @error("diagnosticos.$index.tipo")<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                <input name="diagnosticos[{{ $index }}][texto]" class="form-input" placeholder="Diagnostico" value="{{ $diag['texto'] ?? '' }}" required @if($isSigned) readonly @endif>
                @error("diagnosticos.$index.texto")<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                <input name="diagnosticos[{{ $index }}][cie10]" class="form-input" placeholder="CIE-10 (opcional)" value="{{ $diag['cie10'] ?? '' }}" @if($isSigned) readonly @endif>
                @error("diagnosticos.$index.cie10")<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
              </div>
            @endforeach
          </div>
          @error('diagnosticos')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>
      </fieldset>

      <fieldset class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4">
        <legend class="px-2 text-sm font-semibold text-slate-900">6) Tratamiento e indicaciones</legend>
        <p class="mb-3 text-xs text-slate-500">Tratamiento, indicaciones, examenes y control.</p>
        <p class="mb-3 text-xs text-slate-500">Esto se listara en "Consultas realizadas" del Historial clinico profesional.</p>

        <div class="grid gap-4 lg:grid-cols-2">
          <div>
            <label class="form-label" for="plan_general">Tratamiento indicado *</label>
            <textarea id="plan_general" name="plan_general" class="form-textarea" required rows="4" @if($isSigned) readonly @endif>{{ old('plan_general', $nota?->plan_general) }}</textarea>
            @error('plan_general')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>
          <div>
            <label class="form-label" for="plan_seguimiento">Fecha de control *</label>
            <textarea id="plan_seguimiento" name="plan_seguimiento" class="form-textarea" required rows="3" @if($isSigned) readonly @endif>{{ old('plan_seguimiento', $nota?->plan_seguimiento) }}</textarea>
            @error('plan_seguimiento')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>
          <div class="lg:col-span-2">
            <label class="form-label" for="plan_notas">Indicaciones al paciente *</label>
            <textarea id="plan_notas" name="plan_notas" class="form-textarea" required rows="3" @if($isSigned) readonly @endif>{{ old('plan_notas', $nota?->plan_notas) }}</textarea>
            @error('plan_notas')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
          </div>

          <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white/80 p-4" id="plan-control-box">
            <h4 class="text-sm font-semibold text-slate-900">Agendar control</h4>
            <p class="mt-1 text-xs text-slate-500">Programa la proxima cita de seguimiento sin salir de esta consulta.</p>
            @if($isSigned)
              <div class="mt-3 grid gap-3 lg:grid-cols-[1fr_1fr_auto]">
                <div>
                  <label class="form-label" for="control-fecha">Fecha de control</label>
                  <input id="control-fecha" type="date" class="form-input" min="{{ $controlMinFecha }}">
                </div>
                <div>
                  <label class="form-label" for="control-hora">Horario disponible</label>
                  <select id="control-hora" class="form-select" disabled>
                    <option value="">Selecciona fecha primero</option>
                  </select>
                </div>
                <div class="flex items-end">
                  <button class="btn btn-outline w-full lg:w-auto" type="button" id="btn-agendar-control" disabled>
                    <i class="ri-calendar-check-line"></i> Agendar control
                  </button>
                </div>
              </div>
              <p id="control-help" class="mt-2 text-xs text-slate-500">Selecciona una fecha para consultar horarios disponibles.</p>
            @else
              <p class="mt-2 text-xs text-slate-500">Firma la nota clinica para habilitar este agendamiento.</p>
            @endif
          </div>
        </div>
      </fieldset>

      <x-ui.form-actions>
        <x-slot:left>
          <a href="{{ route('doctor.citas') }}" class="btn btn-ghost">Volver</a>
        </x-slot>
        @if(!$isSigned)
          <button class="btn btn-outline" type="submit"><i class="ri-save-line"></i> Guardar borrador</button>
          <button class="btn btn-primary" type="submit" formaction="{{ route('doctor.citas.soap.firmar', $cita->id) }}"><i class="ri-check-line"></i> Firmar y cerrar</button>
        @endif
      </x-ui.form-actions>
    </form>
  </section>
  @if($isSigned)
    <section class="card p-6 space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h3 class="text-lg font-semibold text-slate-900">Consulta cerrada</h3>
          <p class="text-sm text-slate-600">Esta consulta ya fue finalizada y no puede modificarse.</p>
        </div>
        @if($cita->estado === \App\Models\Cita::ESTADO_CONFIRMADA)
          <form action="{{ route('doctor.citas.realizar', $cita->id) }}" method="POST">
            @csrf
            <button class="btn btn-primary" type="submit"><i class="ri-checkbox-circle-line"></i> Marcar cita como realizada</button>
          </form>
        @endif
      </div>
    </section>

    <section class="card p-6 space-y-4">
      <h3 class="text-lg font-semibold text-slate-900">Correcciones</h3>
      @if(collect($nota?->enmiendas ?? [])->isNotEmpty())
        <div class="space-y-3">
          @foreach(($nota?->enmiendas ?? []) as $enmienda)
            <div class="rounded-xl border border-slate-200 bg-white/90 px-3 py-3 text-sm text-slate-700">
              <div class="font-semibold text-slate-900">{{ $enmienda->motivo }}</div>
              <p class="mt-2">{{ $enmienda->contenido }}</p>
              <div class="mt-2 text-xs text-slate-500">{{ $enmienda->autor?->name ?? 'Usuario' }} &middot; {{ $enmienda->created_at?->format('Y-m-d H:i') }}</div>
            </div>
          @endforeach
        </div>
      @else
        <p class="text-sm text-slate-600">No hay enmiendas registradas.</p>
      @endif

      <form method="POST" action="{{ route('doctor.citas.soap.enmienda', $cita->id) }}" class="space-y-3">
        @csrf
        <div>
          <label class="form-label" for="motivo">Motivo de correccion</label>
          <input id="motivo" name="motivo" class="form-input" value="{{ old('motivo') }}" required>
          @error('motivo')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>
        <div>
          <label class="form-label" for="contenido">Descripcion de la correccion</label>
          <textarea id="contenido" name="contenido" class="form-textarea" rows="3" required>{{ old('contenido') }}</textarea>
          @error('contenido')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>
        <button class="btn btn-outline" type="submit"><i class="ri-edit-2-line"></i> Registrar enmienda</button>
      </form>
    </section>
  @endif
</div>

@push('scripts')
  @vite('resources/js/doctor/soap.js')
@endpush
@endsection
