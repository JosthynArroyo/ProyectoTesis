@extends('layouts.app')

@section('main')
  <main class="section-pad">
    <div class="page-shell max-w-2xl">
      <div class="card p-6">
        <h2 class="text-xl font-semibold text-slate-900">Reagendar cita</h2>


        <div class="mt-4 text-sm text-slate-600">
          <p><strong>Doctor:</strong> {{ optional($cita->doctor)->name ?? 'Sin asignar' }}</p>
          <p><strong>Especialidad:</strong> {{ optional($cita->especialidad)->nombre ?? 'â€”' }}</p>
        </div>

        <form method="POST" action="
            @if(auth()->user()->hasRole('doctor'))
                {{ route('doctor.citas.actualizar', $cita->id) }}
            @else
                {{ route('paciente.citas.actualizar', $cita->id) }}
            @endif
        " class="mt-6 space-y-4">
            @csrf

            <div>
                <label class="form-label">Nueva fecha</label>
                <input type="date" name="fecha" value="{{ old('fecha', $cita->fecha) }}" class="form-input" required>
                @error('fecha')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="form-label">Nueva hora</label>
                <input type="time" name="hora" value="{{ old('hora', $cita->hora) }}" class="form-input" required>
                @error('hora')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
            </div>

            <x-ui.form-actions>
                <x-slot:left>
                    <a href="{{ auth()->user()->hasRole('doctor') ? route('doctor.citas') : route('paciente.citas') }}" class="btn btn-ghost">Volver</a>
                </x-slot>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </x-ui.form-actions>
        </form>
      </div>
    </div>
  </main>
@endsection

