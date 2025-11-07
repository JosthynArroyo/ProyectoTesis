{{-- resources/views/paciente/citas/index.blade.php --}}
@extends('layouts.paciente')
@section('title', 'Mis Citas Médicas')
@section('body-class', 'paciente-body--citas')

@push('head')
  @vite(['resources/css/paciente/citas.css', 'resources/js/sidebar-toggle.js'])
@endpush

@section('main')
<div class="citas">
  <div class="mobile-topbar">
    <button id="menu_bar" aria-label="Abrir menú">
      <span class="material-symbols-sharp">menu</span>
    </button>
  </div>

  <section class="hero" aria-labelledby="citas-heading">
    <div class="hero-inner">
      <div class="hgroup">
        <h1 id="citas-heading" class="title">Mis Citas Médicas</h1>
        <p class="subtitle">Gestiona, busca y filtra tus citas. Todo en un solo lugar.</p>
      </div>
      <div class="hero-actions">
        <a href="{{ route('paciente.crear-cita') }}" class="btn btn-primary" aria-label="Agendar nueva cita">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Agendar Cita
        </a>
      </div>
    </div>
  </section>

  <section class="toolbar" aria-label="Barra de búsqueda y filtros">
    <form class="filters" method="GET" action="{{ url()->current() }}">
      <div class="field" role="search">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="margin-right:8px"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3" stroke-linecap="round"/></svg>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por doctor o especialidad…" aria-label="Buscar citas"/>
      </div>
      @php $est = request('estado'); @endphp
      <div class="field">
        <select name="estado" aria-label="Filtrar por estado">
          <option value="">Todos los estados</option>
          <option value="pendiente"  {{ $est==='pendiente' ? 'selected' : '' }}>Pendiente</option>
          <option value="confirmada" {{ $est==='confirmada' ? 'selected' : '' }}>Confirmada</option>
          <option value="cancelada"  {{ $est==='cancelada' ? 'selected' : '' }}>Cancelada</option>
          <option value="realizada"  {{ $est==='realizada' ? 'selected' : '' }}>Realizada</option>
        </select>
      </div>
      <button class="btn btn-outline" type="submit" aria-label="Aplicar filtros">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Filtrar
      </button>
    </form>
  </section>

  @if(session('success') || session('error'))
    <div aria-live="polite" aria-atomic="true">
      @if(session('success'))
        <div class="empty" role="status" style="border-color:#cfe9dc;background:#edf7f1;color:#0f5132">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="empty" role="alert" style="border-color:#f2c0c0;background:#fff1f1;color:#842029">{{ session('error') }}</div>
      @endif
    </div>
  @endif

  <section class="list" aria-label="Listado de citas">
    @php $collection = $citas ?? collect(); @endphp

    @if($collection->isEmpty())
      <div class="empty">
        <h3>{{ $emptyMessage ?? 'No tienes citas registradas.' }}</h3>
        @if(empty($emptyMessage) || Str::startsWith($emptyMessage, 'No tienes citas registradas'))
          <p>Agenda tu primera cita para verla aquí con su estado y acciones.</p>
          <a href="{{ route('paciente.crear-cita') }}" class="btn btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Agendar Cita
          </a>
        @endif
      </div>
    @else
      @foreach($collection as $cita)
        <article class="card">
          <div class="left">
            <div class="avatar" aria-hidden="true">
              @php $name = $cita->doctor->name ?? 'DR'; $ini = mb_substr(trim($name),0,2,'UTF-8'); @endphp
              {{ mb_strtoupper($ini,'UTF-8') }}
            </div>
            <div class="info">
              <h3 class="title-row">
                <span class="chip" aria-label="Fecha y hora">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 3" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                  {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }} •
                  {{ strlen($cita->hora ?? '')>=5 ? substr($cita->hora,0,5) : $cita->hora }}
                </span>
              </h3>
              <p class="meta"><strong>Doctor:</strong> {{ $cita->doctor->name ?? 'Sin asignar' }}</p>
              <p class="meta"><strong>Especialidad:</strong> {{ $cita->especialidad->nombre ?? 'Sin especialidad' }}</p>
            </div>
          </div>
          <div class="right">
            @switch($cita->estado)
              @case('pendiente')  <span class="pill pending">PENDIENTE</span>  @break
              @case('confirmada') <span class="pill success">CONFIRMADA</span> @break
              @case('cancelada')  <span class="pill danger">CANCELADA</span>   @break
              @case('realizada')  <span class="pill info">REALIZADA</span>     @break
              @default            <span class="pill info">{{ strtoupper($cita->estado) }}</span>
            @endswitch

            @if(!in_array($cita->estado, ['cancelada','realizada']))
              <div class="actions">
                <form action="{{ route('paciente.citas.cancelar', $cita->id) }}" method="POST" style="display:inline-block">
                  @csrf
                  <button type="submit" class="btn btn-danger" aria-label="Cancelar cita">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    Cancelar
                  </button>
                </form>
                <a class="btn btn-outline" href="{{ route('paciente.editar-cita', $cita->id) }}" aria-label="Reagendar cita">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9M3 3v6h6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                  Reagendar
                </a>
              </div>
            @endif
          </div>
        </article>
      @endforeach
    @endif
  </section>

  @if(method_exists($citas, 'links'))
    <div style="display:flex;justify-content:center;margin-top:10px">
      {{ $citas->appends(['q'=>request('q'),'estado'=>request('estado')])->links() }}
    </div>
  @endif
</div>
@endsection
