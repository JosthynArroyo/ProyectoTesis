@extends('layouts.doctor')
@section('title','Mi horario | Doctor')
@section('activeSidebar','horario')

@push('head')
  @vite('resources/css/doctor/horario.css')
@endpush

@section('content')
  @if(session('success')) <div class="alert success">{{ session('success') }}</div> @endif
  @if($errors->any())     <div class="alert danger">{{ $errors->first() }}</div> @endif

  <div class="card">
    <h3 class="card-title">Filtrar</h3>
    <form method="GET" class="grid">
      <div>
        <label>Desde</label>
        <input type="date" name="desde" value="{{ $desde }}">
      </div>
      <div>
        <label>Hasta</label>
        <input type="date" name="hasta" value="{{ $hasta }}">
      </div>
      <div class="full">
        <button class="btn btn-primary">Aplicar</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h3 class="card-title">Crear horario (un día)</h3>
    <form method="POST" action="{{ route('doctor.horario.store') }}" class="grid">
      @csrf
      <div><label>Fecha</label><input type="date" name="fecha" required></div>
      <div><label>Inicio</label><input type="time" name="hora_inicio" required></div>
      <div><label>Fin</label><input type="time" name="hora_fin" required></div>

      {{-- Fijo a 30 minutos --}}
      <input type="hidden" name="intervalo_minutos" value="30">
      <div class="readonly">
        <label>Intervalo</label>
        <span class="pill">30 min</span>
      </div>

      <div class="full">
        <button class="btn btn-primary">Crear</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h3 class="card-title">Generar por rango</h3>
    <form method="POST" action="{{ route('doctor.horario.generar') }}" class="grid">
      @csrf
      <div><label>Desde</label><input type="date" name="desde" required></div>
      <div><label>Hasta</label><input type="date" name="hasta" required></div>

      <div class="full">
        <label>Días</label>
        @php($dias=[1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom'])
        <div class="week-pills">
          @foreach($dias as $k=>$v)
            <label class="chk"><input type="checkbox" name="dias[]" value="{{ $k }}" {{ $k<=5?'checked':'' }}><span>{{ $v }}</span></label>
          @endforeach
        </div>
      </div>

      <div><label>Inicio</label><input type="time" name="hora_inicio" required></div>
      <div><label>Fin</label><input type="time" name="hora_fin" required></div>

      {{-- Fijo a 30 minutos --}}
      <input type="hidden" name="intervalo_minutos" value="30">
      <div class="readonly">
        <label>Intervalo</label>
        <span class="pill">30 min</span>
      </div>

      <div class="full">
        <label class="chk"><input type="checkbox" name="sobrescribir" value="1"><span>Sobrescribir días existentes</span></label>
      </div>

      <div class="full">
        <button class="btn btn-primary">Generar</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head">
      <h3 class="card-title">Mis horarios</h3>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Fecha</th><th>Inicio</th><th>Fin</th><th>Intervalo</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($items as $h)
            <tr>
              <td>{{ $h->fecha }}</td>
              <td>{{ substr($h->hora_inicio,0,5) }}</td>
              <td>{{ substr($h->hora_fin,0,5) }}</td>
              <td><span class="pill pill-soft">30 min</span></td>
              <td class="actions">
                <a class="btn btn-ghost" href="{{ route('doctor.horario.edit',$h) }}">Editar</a>
                <form action="{{ route('doctor.horario.destroy',$h) }}" method="POST" onsubmit="return confirm('Eliminar horario?')">
                  @csrf @method('DELETE')
                  <button class="btn btn-danger" type="submit">Eliminar</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="5">Sin horarios en el rango.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
