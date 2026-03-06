@extends('layouts.doctor')
@section('title','Mi horario | Doctor')
@section('activeSidebar','horario')
@section('header-title','Mi horario')
@section('header-subtitle','Configura tus bloques de atenciÃ³n')

@section('main')
  <div class="space-y-6">
    <section class="card p-6">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Horario</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Mi Horario</h1>
        <p class="text-slate-600">Configura y revisa tus bloques de atenciÃ³n.</p>
      </div>
    </section>

    @if(session('success')) <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert> @endif

    <div class="card p-6">
      <h3 class="text-lg font-semibold text-slate-900">Filtrar</h3>
      <form method="GET" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div>
          <label class="form-label">Desde</label>
          <input class="form-input" type="date" name="desde" value="{{ $desde }}" required>
          @error('desde')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Hasta</label>
          <input class="form-input" type="date" name="hasta" value="{{ $hasta }}" required>
          @error('hasta')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div class="sm:col-span-3">
          <button class="btn btn-primary">Aplicar</button>
        </div>
      </form>
    </div>

    <div class="card p-6">
      <h3 class="text-lg font-semibold text-slate-900">Crear horario (un dÃ­a)</h3>
      <form method="POST" action="{{ route('doctor.horario.store') }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @csrf
        <div>
          <label class="form-label">Fecha</label>
          <input class="form-input" type="date" name="fecha" required>
          @error('fecha')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Inicio</label>
          <input class="form-input" type="time" name="hora_inicio" required>
          @error('hora_inicio')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Fin</label>
          <input class="form-input" type="time" name="hora_fin" required>
          @error('hora_fin')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <input type="hidden" name="intervalo_minutos" value="30">
        <div class="flex items-end">
          <span class="badge neutral">Intervalo 30 min</span>
        </div>

        <div class="sm:col-span-4">
          <button class="btn btn-primary">Crear</button>
        </div>
      </form>
    </div>

    <div class="card p-6">
      <h3 class="text-lg font-semibold text-slate-900">Generar por rango</h3>
      <form method="POST" action="{{ route('doctor.horario.generar') }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @csrf
        <div>
          <label class="form-label">Desde</label>
          <input class="form-input" type="date" name="desde" required>
          @error('desde')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Hasta</label>
          <input class="form-input" type="date" name="hasta" required>
          @error('hasta')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div class="sm:col-span-4">
          <label class="form-label">DÃ­as</label>
          @php($dias=[1=>'Lun',2=>'Mar',3=>'Mie',4=>'Jue',5=>'Vie',6=>'Sab',7=>'Dom'])
          <div class="mt-2 flex flex-wrap gap-2">
            @foreach($dias as $k=>$v)
              <label class="flex items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-xs font-semibold">
                <input type="checkbox" name="dias[]" value="{{ $k }}" {{ $k <= 5 ? 'checked' : '' }}>
                <span>{{ $v }}</span>
              </label>
            @endforeach
          </div>
          @error('dias')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label class="form-label">Inicio</label>
          <input class="form-input" type="time" name="hora_inicio" required>
          @error('hora_inicio')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Fin</label>
          <input class="form-input" type="time" name="hora_fin" required>
          @error('hora_fin')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <input type="hidden" name="intervalo_minutos" value="30">
        <div class="flex items-end">
          <span class="badge neutral">Intervalo 30 min</span>
        </div>

        <div class="sm:col-span-4">
          <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="hidden" name="sobrescribir" value="0">
            <input type="checkbox" name="sobrescribir" value="1">
            Sobrescribir dÃ­as existentes
          </label>
          @error('sobrescribir')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div class="sm:col-span-4">
          <button class="btn btn-primary">Generar</button>
        </div>
      </form>
    </div>

    <div class="card p-6">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-slate-900">Mis horarios</h3>
      </div>
      <div class="mt-4 table-shell">
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
                <td><span class="badge neutral">30 min</span></td>
                <td>
                  <div class="table-actions table-actions--start">
                    <a class="btn btn-outline" href="{{ route('doctor.horario.edit',$h) }}">Editar</a>
                    <form action="{{ route('doctor.horario.destroy',$h) }}" method="POST" onsubmit="return confirm('Eliminar horario')">
                      @csrf @method('DELETE')
                      <button class="btn btn-danger" type="submit">Eliminar</button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="5">Sin horarios en el rango.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection

