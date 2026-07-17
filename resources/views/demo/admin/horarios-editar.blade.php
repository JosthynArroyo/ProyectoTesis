@extends('layouts.demo')
@section('title','Editar horario - Demo')
@section('header-title','Editar horario')
@section('header-subtitle','Define rangos y franjas (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@section('main')
<div class="space-y-6">
  <form action="{{ route('demo.admin.horarios.update', $horario->id) }}" method="POST" class="card p-6">
    @csrf
    @method('PUT')

    <div class="space-y-5">
      <section class="panel-form-section">
        <div class="panel-form-section__header">
          <div class="panel-form-section__heading">
            <h2 class="panel-form-section__title">
              <span class="panel-form-section__icon"><i class="ri-user-heart-line"></i></span>
              Horario del profesional
            </h2>
            <p class="panel-form-section__hint">Edita la franja horaria para el bloque seleccionado.</p>
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="form-label" for="doctor_name">Doctor</label>
            <input class="form-input" id="doctor_name" type="text" value="{{ $horario->doctor }}" readonly>
          </div>
          <div>
            <label class="form-label" for="fecha">Fecha</label>
            <input class="form-input" id="fecha" type="date" value="{{ $horario->fecha }}" readonly>
          </div>
          <div>
            <label class="form-label" for="range">Franja horaria (Ej. 08:00 - 12:00)</label>
            <input class="form-input" id="range" name="range" value="{{ $horario->hora_inicio }} - {{ $horario->hora_fin }}" required>
          </div>
          <div>
            <label class="form-label" for="note">Nota</label>
            <input class="form-input" id="note" name="note" value="Simulación de guardia" required>
          </div>
        </div>
      </section>
    </div>

    <div class="mt-6 flex justify-between">
      <a class="btn btn-ghost" href="{{ route('demo.admin.horarios.index') }}">
        <i class="ri-arrow-left-line"></i> Volver
      </a>
      <button class="btn btn-primary" type="submit">
        <i class="ri-save-line"></i> Guardar cambios
      </button>
    </div>
  </form>
</div>
@endsection
