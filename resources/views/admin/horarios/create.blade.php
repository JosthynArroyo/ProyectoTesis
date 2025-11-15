{{-- resources/views/admin/horarios/create.blade.php --}}
@extends('layouts.admin')
@section('title','Crear horario')

@push('head')
  @vite('resources/css/admin/horarios/create.css')
@endpush

@section('main')
<div class="page">
  <h1 class="page-title">Crear horario</h1>

  @if ($errors->any())
    <div class="card" style="margin-bottom:14px;">
      <ul style="color:#dc2626;margin:0;padding-left:18px">
        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('admin.horarios.store') }}" method="POST" class="card form-grid" id="form-horario">
    @csrf

    <div class="full">
      <label class="small" for="doctor_id">Doctor</label>
      <select class="select" id="doctor_id" name="doctor_id" required>
        <option value="">Seleccione</option>
        @foreach($doctores as $d)
          <option value="{{ $d->id }}" {{ old('doctor_id')==$d->id?'selected':'' }}>{{ $d->name }}</option>
        @endforeach
      </select>
    </div>

    <div>
      <label class="small" for="fecha_inicio">Desde</label>
      <input class="input" type="date" id="fecha_inicio" name="fecha_inicio" value="{{ old('fecha_inicio') }}" required>
    </div>

    <div>
      <label class="small" for="fecha_fin">Hasta</label>
      <input class="input" type="date" id="fecha_fin" name="fecha_fin" value="{{ old('fecha_fin') }}" required>
    </div>

    <div class="full section">
      <div class="section-head">
        <div class="badge">
          <span class="material-symbols-outlined" style="font-size:16px">event_repeat</span> Repetir por días
        </div>
        <div class="muted">Elige días y define una o varias franjas por día.</div>
      </div>

      @php $dias = [1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom']; @endphp
      <div id="dias-wrap">
        @foreach($dias as $num=>$lbl)
          @php $checked = in_array($num, (array)old('dias', [1,2,3,4,5])); @endphp
          <label class="day-chip {{ $checked?'active':'' }}">
            <input type="checkbox" name="dias[]" value="{{ $num }}" {{ $checked?'checked':'' }}>
            <span>{{ $lbl }}</span>
          </label>
        @endforeach
      </div>

      <div class="toolbar">
        <button type="button" class="btn-mini" data-preset="lv">L-V</button>
        <button type="button" class="btn-mini" data-preset="ld">L-D</button>
        <button type="button" class="btn-mini" data-preset="sd">S-D</button>
        <button type="button" class="btn-mini" data-preset="none">Ninguno</button>
      </div>

      <label class="small same-wrap" for="misma_franja">
        <input type="checkbox" id="misma_franja" name="misma_franja" value="1" {{ old('misma_franja',1) ? 'checked' : '' }}>
        Usar la misma franja para todos los días marcados
      </label>

      <div id="franja-global" class="grid-2">
        <div>
          <label class="small" for="hora_inicio">Hora inicio</label>
          <input class="input" type="time" id="hora_inicio" name="hora_inicio" step="1800" value="{{ old('hora_inicio') }}">
        </div>
        <div>
          <label class="small" for="hora_fin">Hora fin</label>
          <input class="input" type="time" id="hora_fin" name="hora_fin" step="1800" value="{{ old('hora_fin') }}">
        </div>
      </div>

      <div id="franjas-por-dia" class="section inner" style="display:none">
        <div class="muted inner-help">Define horas por cada día marcado.</div>
        @foreach($dias as $num=>$lbl)
          @php $row = old("horas.$num", ['inicio'=>null,'fin'=>null]); @endphp
          <div class="row-dia" data-dia="{{ $num }}">
            <div class="row-dia__label">{{ $lbl }}</div>
            <input class="input" type="time" name="horas[{{ $num }}][inicio]" step="1800" value="{{ $row['inicio'] }}" placeholder="hh:mm">
            <span class="muted row-dia__sep">a</span>
            <input class="input" type="time" name="horas[{{ $num }}][fin]" step="1800" value="{{ $row['fin'] }}" placeholder="hh:mm">
          </div>
        @endforeach
      </div>

      <p class="kpi" id="kpi"></p>
    </div>

    <div class="full actions">
      <a class="btn btn-outline" href="{{ route('admin.horarios.index') }}">Cancelar</a>
      <button class="btn btn-primary" type="submit">Guardar</button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/horarios/create.js')
@endpush
