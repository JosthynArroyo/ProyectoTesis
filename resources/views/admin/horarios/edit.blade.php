{{-- resources/views/admin/horarios/edit.blade.php --}}
@extends('layouts.admin')
@section('title','Editar horario')

@push('head')
  @vite('resources/css/admin/horarios/edit.css')
@endpush

@section('main')
<div class="page">
  <h1 class="page-title">Editar horario #{{ $horario->id }}</h1>

  @if ($errors->any())
    <div class="card errors" style="margin-bottom:14px;">
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('admin.horarios.update', $horario) }}" method="POST" class="card form-grid" id="form-horario-edit">
    @csrf
    @method('PUT')

    <div class="full">
      <label class="small" for="doctor_id">Doctor</label>
      <select class="select" id="doctor_id" name="doctor_id" required>
        @foreach($doctores as $d)
          <option value="{{ $d->id }}" {{ (old('doctor_id',$horario->doctor_id)==$d->id)?'selected':'' }}>{{ $d->name }}</option>
        @endforeach
      </select>
    </div>

    <div>
      <label class="small" for="fecha">Fecha</label>
      <input class="input" type="date" id="fecha" name="fecha" value="{{ old('fecha', \Carbon\Carbon::parse($horario->fecha)->format('Y-m-d')) }}" required>
    </div>

    <div>
      <label class="small" for="hora_inicio">Hora inicio</label>
      <input class="input" type="time" id="hora_inicio" name="hora_inicio" step="1800" value="{{ old('hora_inicio', \Carbon\Carbon::parse($horario->hora_inicio)->format('H:i')) }}" required>
    </div>

    <div>
      <label class="small" for="hora_fin">Hora fin</label>
      <input class="input" type="time" id="hora_fin" name="hora_fin" step="1800" value="{{ old('hora_fin', \Carbon\Carbon::parse($horario->hora_fin)->format('H:i')) }}" required>
    </div>

    <div class="full actions">
      <a class="btn btn-outline" href="{{ route('admin.horarios.index') }}">Cancelar</a>
      <button class="btn btn-primary" type="submit">Actualizar</button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/horarios/edit.js')
@endpush
