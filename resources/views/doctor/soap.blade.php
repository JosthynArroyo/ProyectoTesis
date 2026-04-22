@extends('layouts.doctor')
@section('title', 'Nota medica de consulta')
@section('activeSidebar', 'citas')
@section('header-title','Nota medica de consulta')
@section('header-subtitle','Registro clinico individual de la atencion')

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
      Cita #{{ $cita->id }} | {{ optional($cita->paciente)->name ?? 'Paciente' }} | {{ optional($cita->especialidad)->nombre ?? '-' }} |
      {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }} {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}
    </div>
    <div class="panel-action-bar__actions">
      <x-ui.badge :tone="$isSigned ? 'success' : 'warning'">{{ $isSigned ? 'Firmada' : 'Borrador' }}</x-ui.badge>
      @if($cita->paciente_id)
        <a href="{{ route('doctor.pacientes.historial', $cita->paciente_id) }}" class="btn btn-ghost btn-sm">Abrir expediente del paciente</a>
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
        <section class="space-y-4 rounded-2xl border border-slate-200 p-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">Subjetivo</p>
            <h3 class="text-lg font-semibold text-slate-900">Motivo, alergias y antecedentes</h3>
          </div>

          <div>
            <label class="form-label" for="subjetivo_motivo">Motivo de consulta</label>
            <textarea id="subjetivo_motivo" name="subjetivo_motivo" class="form-textarea" rows="3" @if($isSigned) readonly @endif>{{ $motivoConsulta }}</textarea>
            <p class="mt-2 text-xs text-slate-500">Tomado del motivo registrado por el paciente al agendar la cita.</p>
          </div>

          <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="alergias_no_conocidas" value="1" @checked($alergiasNoConocidasChecked) @if($isSigned) disabled @endif>
            Sin alergias conocidas
          </label>
          <textarea name="alergias_detalle" class="form-textarea" rows="3" placeholder="Detalle de alergias" @if($isSigned) readonly @endif>{{ $alergiasDetalle }}</textarea>

          <div class="grid gap-3 md:grid-cols-2">
            <textarea name="antecedentes_cronicas" class="form-textarea" rows="3" placeholder="Enfermedades cronicas" @if($isSigned) readonly @endif>{{ $antecedentesCronicas }}</textarea>
            <textarea name="antecedentes_cirugias" class="form-textarea" rows="3" placeholder="Cirugias previas" @if($isSigned) readonly @endif>{{ $antecedentesCirugias }}</textarea>
            <textarea name="antecedentes_hospitalizaciones" class="form-textarea" rows="3" placeholder="Hospitalizaciones" @if($isSigned) readonly @endif>{{ $antecedentesHospitalizaciones }}</textarea>
            <textarea name="antecedentes_otros" class="form-textarea" rows="3" placeholder="Otros antecedentes" @if($isSigned) readonly @endif>{{ $antecedentesOtros }}</textarea>
            <select name="antecedentes_diabetes" class="form-select" @if($isSigned) disabled @endif>
              <option value="">Diabetes</option>
              <option value="No" @selected($antecedentesDiabetes === 'No')>No</option>
              <option value="Tipo 1" @selected($antecedentesDiabetes === 'Tipo 1')>Tipo 1</option>
              <option value="Tipo 2" @selected($antecedentesDiabetes === 'Tipo 2')>Tipo 2</option>
              <option value="Gestacional" @selected($antecedentesDiabetes === 'Gestacional')>Gestacional</option>
              <option value="No aplica" @selected($antecedentesDiabetes === 'No aplica')>No aplica</option>
            </select>
            <select name="antecedentes_hipertension" class="form-select" @if($isSigned) disabled @endif>
              <option value="">Hipertension</option>
              <option value="No" @selected($antecedentesHipertension === 'No')>No</option>
              <option value="Si" @selected($antecedentesHipertension === 'Si')>Si</option>
              <option value="No documentado" @selected($antecedentesHipertension === 'No documentado')>No documentado</option>
            </select>
          </div>
        </section>

        <section class="space-y-4 rounded-2xl border border-slate-200 p-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-slate-500">Objetivo</p>
            <h3 class="text-lg font-semibold text-slate-900">Signos vitales y exploracion</h3>
          </div>

          <div class="grid gap-3 md:grid-cols-2">
            <input id="sv_ta" name="sv_ta" class="form-input" placeholder="Presion arterial" value="{{ old('sv_ta', $sv['ta'] ?? '') }}" required @if($isSigned) readonly @endif>
            <input id="sv_fc" name="sv_fc" type="number" class="form-input" placeholder="Pulso" value="{{ old('sv_fc', $sv['fc'] ?? '') }}" required @if($isSigned) readonly @endif>
            <input id="sv_fr" name="sv_fr" type="number" class="form-input" placeholder="Respiracion" value="{{ old('sv_fr', $sv['fr'] ?? '') }}" required @if($isSigned) readonly @endif>
            <input id="sv_temp" name="sv_temp" type="number" step="0.1" class="form-input" placeholder="Temperatura" value="{{ old('sv_temp', $sv['temp'] ?? '') }}" required @if($isSigned) readonly @endif>
            <input id="sv_spo2" name="sv_spo2" type="number" class="form-input" placeholder="SpO2" value="{{ old('sv_spo2', $sv['spo2'] ?? '') }}" required @if($isSigned) readonly @endif>
            <input id="sv_peso" name="sv_peso" type="number" step="0.1" class="form-input" placeholder="Peso" value="{{ old('sv_peso', $sv['peso'] ?? '') }}" required @if($isSigned) readonly @endif>
            <div class="relative md:col-span-2">
              <label class="sr-only" for="sv_talla">Estatura en centimetros</label>
              <input id="sv_talla" name="sv_talla" type="number" step="0.01" min="0" max="300" class="form-input pr-20" placeholder="Estatura" value="{{ old('sv_talla', $sv['talla'] ?? '') }}" aria-describedby="sv_talla_unit sv_talla_help" required @if($isSigned) readonly @endif>
              <span id="sv_talla_unit" class="pointer-events-none absolute inset-y-0 right-10 flex items-center text-sm font-semibold text-slate-500">cm</span>
            </div>
            <p id="sv_talla_help" class="text-xs text-slate-500 md:col-span-2">Registra la estatura en centimetros. Puedes escribir 163 o 1,63; se guardara como 163 cm.</p>
          </div>

          @if(!empty($signosPrevios))
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
              Ultimos signos previos: TA {{ $signosPrevios['ta'] ?? '---' }}, FC {{ $signosPrevios['fc'] ?? '---' }}, FR {{ $signosPrevios['fr'] ?? '---' }}, Temp {{ $signosPrevios['temp'] ?? '---' }}.
            </div>
          @endif

          <textarea id="examen_fisico" name="examen_fisico" class="form-textarea" rows="4" placeholder="Hallazgos del examen fisico" required @if($isSigned) readonly @endif>{{ old('examen_fisico', $nota?->examen_fisico) }}</textarea>
          <textarea id="notas_objetivas" name="notas_objetivas" class="form-textarea" rows="4" placeholder="Observaciones medicas" required @if($isSigned) readonly @endif>{{ old('notas_objetivas', $nota?->notas_objetivas) }}</textarea>
        </section>
      </div>

      <section class="space-y-4 rounded-2xl border border-slate-200 p-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Evaluacion</p>
          <h3 class="text-lg font-semibold text-slate-900">Assessment y diagnosticos</h3>
        </div>

        <textarea id="assessment" name="assessment" class="form-textarea" rows="4" placeholder="Evaluacion medica" required @if($isSigned) readonly @endif>{{ old('assessment', $nota?->assessment) }}</textarea>

        <div>
          <div class="mb-3 flex items-center justify-between">
            <label class="form-label">Diagnosticos *</label>
            @if(!$isSigned)
              <button class="btn btn-ghost btn-sm" type="button" id="add-diagnostico">Agregar diagnostico</button>
            @endif
          </div>
          <div class="space-y-3" id="diagnosticos-wrap">
            @foreach($diagnosticos as $index => $diag)
              <div class="grid gap-2 md:grid-cols-[140px_1fr_160px]" data-diag-row="1">
                <select name="diagnosticos[{{ $index }}][tipo]" class="form-select" @if($isSigned) disabled @endif>
                  <option value="principal" @selected(($diag['tipo'] ?? '') === 'principal')>Principal</option>
                  <option value="secundario" @selected(($diag['tipo'] ?? '') === 'secundario')>Secundario</option>
                  <option value="diferencial" @selected(($diag['tipo'] ?? '') === 'diferencial')>Diferencial</option>
                </select>
                <input name="diagnosticos[{{ $index }}][texto]" class="form-input" value="{{ $diag['texto'] ?? '' }}" placeholder="Diagnostico" @if($isSigned) readonly @endif>
                <input name="diagnosticos[{{ $index }}][cie10]" class="form-input" value="{{ $diag['cie10'] ?? '' }}" placeholder="CIE-10" @if($isSigned) readonly @endif>
              </div>
            @endforeach
          </div>
        </div>
      </section>

      <section class="space-y-4 rounded-2xl border border-slate-200 p-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Plan</p>
          <h3 class="text-lg font-semibold text-slate-900">Tratamiento y seguimiento</h3>
        </div>

        <textarea id="plan_general" name="plan_general" class="form-textarea" rows="4" placeholder="Tratamiento indicado" required @if($isSigned) readonly @endif>{{ old('plan_general', $nota?->plan_general) }}</textarea>
        <div class="grid gap-3 md:grid-cols-2">
          <div>
            <label class="form-label" for="follow_up_date">Proximo control</label>
            <input id="follow_up_date" type="date" name="follow_up_date" class="form-input" value="{{ $followUpDate }}" min="{{ $controlMinFecha }}" @if($isSigned) readonly @endif>
          </div>
          <div>
            <label class="form-label" for="follow_up_notes">Observaciones de seguimiento</label>
            <textarea id="follow_up_notes" name="follow_up_notes" class="form-textarea" rows="3" @if($isSigned) readonly @endif>{{ $followUpNotes }}</textarea>
          </div>
        </div>
        <textarea id="plan_notas" name="plan_notas" class="form-textarea" rows="4" placeholder="Indicaciones al paciente" required @if($isSigned) readonly @endif>{{ old('plan_notas', $nota?->plan_notas) }}</textarea>

        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4" id="plan-control-box">
          @if($isSigned && $controlCita)
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h4 class="text-sm font-semibold text-slate-900">Control agendado</h4>
                <p class="mt-1 text-sm font-semibold text-slate-900">
                  {{ \Carbon\Carbon::parse($controlCita->fecha)->format('d/m/Y') }}
                  {{ \Carbon\Carbon::parse($controlCita->hora)->format('H:i') }}
                </p>
                <p class="mt-1 text-xs text-slate-500">Estado: {{ $controlEstado }}. Puedes reagendar o cancelar este control.</p>
              </div>
              <form method="POST" action="{{ route('doctor.citas.proxima.cancelar', ['cita' => $cita->id, 'control' => $controlCita->id]) }}">
                @csrf
                <button class="btn btn-danger btn-sm" type="submit">Cancelar control</button>
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
                <button class="btn btn-outline w-full lg:w-auto" type="button" id="btn-agendar-control" disabled>Reagendar control</button>
              </div>
            </div>
            <p id="control-help" class="mt-2 text-xs text-slate-500">Selecciona una nueva fecha para consultar horarios disponibles.</p>
          @else
            <h4 class="text-sm font-semibold text-slate-900">Agendar control</h4>
            <p class="mt-1 text-xs text-slate-500">Programa la proxima cita de seguimiento sin salir de esta consulta.</p>
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
                  <button class="btn btn-outline w-full lg:w-auto" type="button" id="btn-agendar-control" disabled>Agendar control</button>
                </div>
              </div>
              <p id="control-help" class="mt-2 text-xs text-slate-500">Selecciona una fecha para consultar horarios disponibles.</p>
            @else
              <p class="mt-2 text-xs text-slate-500">Firma la nota para habilitar el agendamiento del siguiente control.</p>
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
          <h3 class="text-lg font-semibold text-slate-900">Consulta cerrada</h3>
          <p class="text-sm text-slate-600">La nota ya fue firmada. La correccion posterior se registra mediante enmiendas.</p>
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
      <h3 class="text-lg font-semibold text-slate-900">Enmiendas y aclaraciones</h3>
      @if(collect($nota?->enmiendas ?? [])->isNotEmpty())
        <div class="space-y-3">
          @foreach(($nota?->enmiendas ?? []) as $enmienda)
            <div class="rounded-xl border border-slate-200 bg-white/90 px-3 py-3 text-sm text-slate-700">
              <div class="font-semibold text-slate-900">{{ $enmienda->motivo }}</div>
              <p class="mt-2">{{ $enmienda->contenido }}</p>
              <div class="mt-2 text-xs text-slate-500">{{ $enmienda->autor?->name ?? 'Usuario' }} | {{ $enmienda->created_at?->format('Y-m-d H:i') }}</div>
            </div>
          @endforeach
        </div>
      @else
        <p class="text-sm text-slate-600">No hay enmiendas registradas.</p>
      @endif

      <form method="POST" action="{{ route('doctor.citas.soap.enmienda', $cita->id) }}" class="space-y-3">
        @csrf
        <input id="motivo" name="motivo" class="form-input" value="{{ old('motivo') }}" placeholder="Motivo de la correccion" required>
        <textarea id="contenido" name="contenido" class="form-textarea" rows="3" placeholder="Detalle de la correccion" required>{{ old('contenido') }}</textarea>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <a href="{{ route('doctor.citas') }}" class="btn btn-ghost">Volver</a>
          <button class="btn btn-outline" type="submit">Registrar enmienda</button>
        </div>
      </form>
    </section>
  @endif
</div>

@push('scripts')
  @vite('resources/js/doctor/soap.js')
@endpush
@endsection
