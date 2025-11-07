@extends('layouts.doctor')
@section('title', 'Generar Receta')
@section('activeSidebar', 'citas')

@push('styles')
<style>
  .rx-wrap{ width:min(900px,100%); margin:84px auto 36px; background:#fff; border:1px solid var(--clr-border); border-radius:var(--card-border-radius); box-shadow:var(--box-shadow); padding:22px; }
  .rx-hdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
  .rx-grid{display:grid;gap:14px}
  .rx-grid label{font-weight:700;margin-bottom:6px;display:block}
  .rx-grid textarea{width:100%;min-height:120px;padding:12px;border:1px solid var(--clr-border);border-radius:12px;resize:vertical}
  .rx-actions{display:flex;gap:.6rem;justify-content:flex-end;margin-top:12px}
  .btn{display:inline-flex;align-items:center;gap:.35rem;padding:.6rem 1rem;border-radius:10px;font-weight:700;border:1px solid var(--clr-border);cursor:pointer;text-decoration:none}
  .btn-primary{background:var(--clr-primary);color:#fff;border-color:var(--clr-primary)}
  .btn-muted{background:#f3f4f6}
</style>
@endpush

@section('content')
<div class="rx-wrap">
  <div class="rx-hdr">
    <h1>Generar Receta</h1>
    <div>
      <strong>Paciente:</strong> {{ $cita->paciente->name ?? '—' }} |
      <strong>Fecha cita:</strong> {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }} {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}
    </div>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger" style="margin-bottom:12px">
      <ul style="margin:0;padding-left:18px">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('doctor.recetas.store') }}">
    @csrf
    <input type="hidden" name="cita_id" value="{{ $cita->id }}">

    <div class="rx-grid">
      <div>
        <label for="diagnostico">Diagnóstico / Motivo</label>
        <textarea id="diagnostico" name="diagnostico" required>{{ old('diagnostico') }}</textarea>
      </div>

      <div>
        <label for="medicamentos">Medicamentos (dosis y frecuencia)</label>
        <textarea id="medicamentos" name="medicamentos" required>{{ old('medicamentos') }}</textarea>
      </div>

      <div>
        <label for="indicaciones">Indicaciones adicionales (opcional)</label>
        <textarea id="indicaciones" name="indicaciones">{{ old('indicaciones') }}</textarea>
      </div>
    </div>

    <div class="rx-actions">
      <a href="{{ route('doctor.citas') }}" class="btn btn-muted">Cancelar</a>
      <button type="submit" class="btn btn-primary">
        <span class="material-symbols-outlined">local_pharmacy</span> Generar y enviar
      </button>
    </div>
  </form>
</div>
@endsection
