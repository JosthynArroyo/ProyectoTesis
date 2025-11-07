@extends('layouts.app')

@section('content')
<div class="container" style="max-width:640px;">
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h4 mb-3">Reagendar Cita</h2>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-3">
                <strong>Doctor:</strong> {{ $cita->doctor->name ?? 'Sin asignar' }}<br>
                <strong>Especialidad:</strong> {{ $cita->especialidad->nombre ?? '—' }}
            </div>

            <form method="POST" action="
                @if(auth()->user()->hasRole('doctor'))
                    {{ route('doctor.citas.actualizar', $cita->id) }}
                @else
                    {{ route('paciente.citas.actualizar', $cita->id) }}
                @endif
            ">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Nueva fecha</label>
                    <input type="date" name="fecha" value="{{ old('fecha', $cita->fecha) }}" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nueva hora</label>
                    <input type="time" name="hora" value="{{ old('hora', $cita->hora) }}" class="form-control" required>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ auth()->user()->hasRole('doctor') ? route('doctor.citas') : route('paciente.citas') }}" class="btn btn-secondary">
                        Volver
                    </a>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
