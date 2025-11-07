{{-- resources/views/admin/horarios/edit.blade.php --}}
@extends('layouts.admin')
@section('title','Editar horario')

@push('head')
<style>
  :root{
    --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --bg:#f8fafc; --card:#fff;
    --brand:#4f46e5; --danger:#ef4444; --r-lg:16px; --r-sm:10px; --shadow:0 10px 24px rgba(15,23,42,.06);
  }
  .page{display:grid; gap:14px}
  .page-title{margin:0; color:var(--ink); font-weight:800}

  .card{background:var(--card); border:1px solid var(--line); border-radius:var(--r-lg); box-shadow:var(--shadow); padding:16px}
  .form-grid{display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px}
  .full{grid-column:1/-1}

  .small{font-size:12px; color:var(--muted); margin-bottom:6px; display:block}
  .input,.select{width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:var(--r-sm); background:#fff; color:var(--ink)}
  .input:focus,.select:focus{outline:none; border-color:#c7d2fe; box-shadow:0 0 0 3px rgba(99,102,241,.15)}

  .btn{padding:10px 14px; border-radius:var(--r-sm); border:1px solid var(--line); background:#fff; font-weight:600; cursor:pointer}
  .btn:hover{border-color:#cbd5e1}
  .btn-primary{background:var(--brand); color:#fff; border-color:transparent}
  .btn-primary:hover{filter:brightness(1.05)}
  .btn-outline{background:#fff}

  .errors ul{margin:0; padding-left:18px; color:var(--danger)}
  @media (max-width:700px){ .form-grid{grid-template-columns:1fr} }
</style>
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
    @csrf @method('PUT')

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
      <input class="input" type="date" id="fecha" name="fecha"
             value="{{ old('fecha', \Carbon\Carbon::parse($horario->fecha)->format('Y-m-d')) }}" required>
    </div>

    <div>
      <label class="small" for="hora_inicio">Hora inicio</label>
      <input class="input" type="time" id="hora_inicio" name="hora_inicio" step="1800"
             value="{{ old('hora_inicio', \Carbon\Carbon::parse($horario->hora_inicio)->format('H:i')) }}" required>
    </div>

    <div>
      <label class="small" for="hora_fin">Hora fin</label>
      <input class="input" type="time" id="hora_fin" name="hora_fin" step="1800"
             value="{{ old('hora_fin', \Carbon\Carbon::parse($horario->hora_fin)->format('H:i')) }}" required>
    </div>

    <div class="full" style="display:flex;gap:10px;justify-content:flex-end;">
      <a class="btn btn-outline" href="{{ route('admin.horarios.index') }}">Cancelar</a>
      <button class="btn btn-primary" type="submit">Actualizar</button>
    </div>
  </form>
</div>
@endsection
