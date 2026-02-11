@extends('layouts.laboratorio')
@section('title','Mi horario | Laboratorio')
@section('activeSidebar','horario')
@section('header-title','Mi horario')
@section('header-subtitle','Configura y revisa tus bloques de atención')

@section('content')
  <div class="space-y-6">
    <section class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Horario</p>
          <h1 class="mt-2 text-2xl font-semibold text-slate-900">Mi horario</h1>
          <p class="text-slate-600">Configura y revisa tus bloques de atención.</p>
        </div>
        <span class="badge info"><i class="ri-time-line"></i> Laboratorio</span>
      </div>
    </section>

    @if(session('success'))
      <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
    @endif
    @if($errors->any())
      <x-ui.alert tone="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <section class="card p-6">
      <h3 class="text-lg font-semibold text-slate-900">Filtrar</h3>
      <form method="GET" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div>
          <label class="form-label">Desde</label>
          <input type="date" name="desde" value="{{ $desde }}" class="form-input" required>
          @error('desde')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Hasta</label>
          <input type="date" name="hasta" value="{{ $hasta }}" class="form-input" required>
          @error('hasta')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div class="flex items-end">
          <button class="btn btn-primary w-full">Aplicar</button>
        </div>
      </form>
    </section>

    <section class="card p-6">
      <h3 class="text-lg font-semibold text-slate-900">Crear horario (un dia)</h3>
      <form method="POST" action="{{ route('laboratorio.horario.store') }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @csrf
        <div>
          <label class="form-label">Fecha</label>
          <input type="date" name="fecha" required class="form-input">
          @error('fecha')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Inicio</label>
          <input type="time" name="hora_inicio" required class="form-input">
          @error('hora_inicio')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Fin</label>
          <input type="time" name="hora_fin" required class="form-input">
          @error('hora_fin')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <input type="hidden" name="intervalo_minutos" value="30">
        <div>
          <label class="form-label">Intervalo</label>
          <div class="mt-2 inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">30 min</div>
        </div>

        <div class="md:col-span-4">
          <button class="btn btn-primary">Crear</button>
        </div>
      </form>
    </section>

    <section class="card p-6">
      <h3 class="text-lg font-semibold text-slate-900">Generar por rango</h3>
      <form method="POST" action="{{ route('laboratorio.horario.generar') }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @csrf
        <div>
          <label class="form-label">Desde</label>
          <input type="date" name="desde" required class="form-input">
          @error('desde')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Hasta</label>
          <input type="date" name="hasta" required class="form-input">
          @error('hasta')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div class="md:col-span-4">
          <label class="form-label">Dias</label>
          @php($dias=[1=>'Lun',2=>'Mar',3=>'Mie',4=>'Jue',5=>'Vie',6=>'Sab',7=>'Dom'])
          <div class="mt-2 flex flex-wrap gap-2">
            @foreach($dias as $k=>$v)
              <label class="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600">
                <input type="checkbox" name="dias[]" value="{{ $k }}" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500" {{ $k <= 5 ? 'checked' : '' }}>
                <span>{{ $v }}</span>
              </label>
            @endforeach
          </div>
          @error('dias')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div>
          <label class="form-label">Inicio</label>
          <input type="time" name="hora_inicio" required class="form-input">
          @error('hora_inicio')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label">Fin</label>
          <input type="time" name="hora_fin" required class="form-input">
          @error('hora_fin')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <input type="hidden" name="intervalo_minutos" value="30">
        <div>
          <label class="form-label">Intervalo</label>
          <div class="mt-2 inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">30 min</div>
        </div>

        <div class="md:col-span-4">
          <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="hidden" name="sobrescribir" value="0">
            <input type="checkbox" name="sobrescribir" value="1" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
            <span>Sobrescribir dias existentes</span>
          </label>
          @error('sobrescribir')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>

        <div class="md:col-span-4">
          <button class="btn btn-primary">Generar</button>
        </div>
      </form>
    </section>

    <section class="card p-6">
      <div class="flex items-center justify-between gap-3">
        <h3 class="text-lg font-semibold text-slate-900">Mis horarios</h3>
      </div>
      <div class="mt-4 table-shell">
        <table class="table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Inicio</th>
              <th>Fin</th>
              <th>Intervalo</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($items as $h)
              <tr>
                <td>{{ $h->fecha }}</td>
                <td>{{ substr($h->hora_inicio,0,5) }}</td>
                <td>{{ substr($h->hora_fin,0,5) }}</td>
                <td><x-ui.badge tone="info">30 min</x-ui.badge></td>
                <td>
                  <div class="table-actions table-actions--start">
                    <a class="btn btn-ghost" href="{{ route('laboratorio.horario.edit',$h) }}">Editar</a>
                    <form action="{{ route('laboratorio.horario.destroy',$h) }}" method="POST" onsubmit="return confirm('Eliminar horario')">
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
    </section>
  </div>
@endsection
