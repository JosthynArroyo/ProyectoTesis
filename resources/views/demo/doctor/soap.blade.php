@extends('layouts.demo')
@section('title', 'Nota médica de consulta - Demo')
@section('activeSidebar', 'citas')
@section('header-title','Nota médica de consulta')
@section('header-subtitle','Registro clínico individual de la atención (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-doctor-demo')
@endsection

@php
  $citaId = request()->route('cita');
  
  $cita = new \App\Models\Cita();
  $cita->id = $citaId ?? 1;
  $cita->fecha = now()->toDateString();
  $cita->hora = '09:00';
  $cita->motivo_consulta = 'Dolor al tragar desde hace tres días, con congestión y malestar durante la noche.';
  $cita->paciente_id = 10;
  $cita->dependiente_id = 1;
  $p = new \App\Models\User(['name' => 'Lucia Vega', 'dni' => '1756789012']);
  $cita->setRelation('paciente', $p);
  $d = new \App\Models\User(['name' => 'Dra. Sofia Cardenas']);
  $cita->setRelation('doctor', $d);
  $esp = new \App\Models\Especialidad(['nombre' => 'Pediatría']);
  $cita->setRelation('especialidad', $esp);

  $isSigned = false;
  $motivoConsulta = $cita->motivo_consulta;
@endphp

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar panel-action-bar--between">
    <div class="panel-action-bar__meta">
      Cita #{{ $cita->id }} | {{ $cita->nombrePacienteReal() }} | {{ optional($cita->especialidad)->nombre ?? '-' }} |
      {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }} {{ $cita->hora }}
    </div>
    <div class="panel-action-bar__actions">
      <x-ui.badge tone="warning">Borrador</x-ui.badge>
      <a href="{{ route('demo.doctor.pacientes.historial', $cita->paciente_id) }}" class="btn btn-ghost btn-sm">Abrir expediente del paciente</a>
    </div>
  </div>

  @if(session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <form method="POST" action="{{ route('demo.doctor.citas.soap.store', $cita->id) }}" class="space-y-6">
    @csrf

    <div class="grid gap-6 xl:grid-cols-2">
      <!-- Subjetivo -->
      <section class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6">
        <div>
          <p class="text-xs uppercase tracking-widest text-gray-500">Subjetivo</p>
          <h3 class="text-lg font-semibold text-gray-900">Motivo, alergias y antecedentes</h3>
        </div>

        <div>
          <label class="form-label" for="subjetivo_motivo">Motivo de consulta</label>
          <textarea id="subjetivo_motivo" name="subjetivo_motivo" class="form-textarea" rows="3">{{ $motivoConsulta }}</textarea>
        </div>

        <div>
          <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="alergias_no_conocidas" value="1" checked>
            Sin alergias conocidas
          </label>
        </div>
        <div>
          <textarea name="alergias_detalle" class="form-textarea" rows="2" placeholder="Detalle de alergias (si aplica)"></textarea>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
          <textarea name="antecedentes_cronicas" class="form-textarea" rows="2" placeholder="Enfermedades crónicas"></textarea>
          <textarea name="antecedentes_cirugias" class="form-textarea" rows="2" placeholder="Cirugías previas"></textarea>
        </div>
      </section>

      <!-- Objetivo -->
      <section class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6">
        <div>
          <p class="text-xs uppercase tracking-widest text-gray-500">Objetivo</p>
          <h3 class="text-lg font-semibold text-gray-900">Signos vitales y exploración</h3>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="form-label" for="sv_ta">Presión arterial (mmHg)</label>
            <input class="form-input" id="sv_ta" name="signos_vitales[ta]" value="110/70">
          </div>
          <div>
            <label class="form-label" for="sv_fc">Frecuencia cardíaca (lpm)</label>
            <input class="form-input" id="sv_fc" name="signos_vitales[fc]" value="80">
          </div>
          <div>
            <label class="form-label" for="sv_fr">Frecuencia respiratoria (rpm)</label>
            <input class="form-input" id="sv_fr" name="signos_vitales[fr]" value="18">
          </div>
          <div>
            <label class="form-label" for="sv_temp">Temperatura (°C)</label>
            <input class="form-input" id="sv_temp" name="signos_vitales[temp]" value="36.5">
          </div>
          <div>
            <label class="form-label" for="sv_peso">Peso (kg)</label>
            <input class="form-input" id="sv_peso" name="signos_vitales[peso]" value="25">
          </div>
          <div>
            <label class="form-label" for="sv_talla">Talla (cm)</label>
            <input class="form-input" id="sv_talla" name="signos_vitales[talla]" value="120">
          </div>
        </div>
      </section>

      <!-- Análisis -->
      <section class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 xl:col-span-2">
        <div>
          <p class="text-xs uppercase tracking-widest text-gray-500">Análisis</p>
          <h3 class="text-lg font-semibold text-gray-900">Diagnósticos (CIE-10)</h3>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
          <div>
            <label class="form-label" for="diag_code">Código CIE-10</label>
            <input class="form-input" id="diag_code" name="diagnosticos[0][cie10]" value="J03.9">
          </div>
          <div class="md:col-span-2">
            <label class="form-label" for="diag_desc">Descripción del diagnóstico</label>
            <input class="form-input" id="diag_desc" name="diagnosticos[0][texto]" value="Faringoamigdalitis aguda, no especificada">
          </div>
        </div>
      </section>

      <!-- Plan -->
      <section class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 xl:col-span-2">
        <div>
          <p class="text-xs uppercase tracking-widest text-gray-500">Plan</p>
          <h3 class="text-lg font-semibold text-gray-900">Tratamiento y seguimiento</h3>
        </div>

        <div>
          <label class="form-label" for="plan_indicaciones">Indicaciones médicas</label>
          <textarea id="plan_indicaciones" name="plan_seguimiento" class="form-textarea" rows="3">Paracetamol 250mg cada 8 horas por 3 días si hay fiebre. Abundantes líquidos.</textarea>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="form-label" for="follow_up_date">Cita de control</label>
            <input class="form-input" type="date" id="follow_up_date" name="follow_up_date" value="{{ now()->addDays(7)->toDateString() }}">
          </div>
          <div>
            <label class="form-label" for="follow_up_notes">Notas de control</label>
            <input class="form-input" id="follow_up_notes" name="follow_up_notes" value="Control en 7 días para evaluar evolución de vías respiratorias.">
          </div>
        </div>
      </section>
    </div>

    <div class="mt-6 flex flex-wrap gap-3 justify-between">
      <a href="{{ route('demo.doctor.citas') }}" class="btn btn-ghost">Cancelar</a>
      <div class="flex gap-3">
        <button class="btn btn-outline" type="submit">
          <i class="ri-save-line"></i> Guardar Borrador
        </button>
        <button class="btn btn-primary" type="submit" name="firmar" value="1" onclick="this.form.action='{{ route('demo.doctor.citas.soap.firmar', $cita->id) }}';">
          <i class="ri-quill-pen-line"></i> Firmar Nota Clínica
        </button>
      </div>
    </div>
  </form>
</div>
@endsection
