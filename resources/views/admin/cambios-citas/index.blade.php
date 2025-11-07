{{-- resources/views/admin/cambios-citas/index.blade.php --}}
@extends('layouts.admin')
@section('title','Cambios de citas | Admin')

@push('head')
  @vite('resources/css/admin/cambios-citas.css')
@endpush

@section('main')
<div class="page">
  <div class="hero" style="margin-bottom:1rem">
    <div>
      <h2>Auditoría de cambios de citas</h2>
      <p>Agendadas, confirmadas, canceladas, realizadas y reprogramadas. Filtra por doctor, estado y fecha.</p>
    </div>
    <div class="hero-stat"><span class="material-symbols-outlined">admin_panel_settings</span>Administrador</div>
  </div>

  <form method="GET" action="{{ url()->current() }}" class="card" style="margin-bottom:1rem;">
    <div class="card-header">
      <h3>Filtros</h3>
      <div style="display:flex;gap:.6rem;flex-wrap:wrap">
        <a class="btn btn-secondary" href="{{ route('admin.cambios-citas.export.excel', request()->query()) }}">Excel</a>
        <a class="btn btn-primary"   href="{{ route('admin.cambios-citas.export.pdf',   request()->query()) }}">PDF</a>
      </div>
    </div>

    <div class="card-body" style="gap:1rem">
      <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.8rem">
        <select class="select" name="tipo">
          <option value="">Todos los eventos</option>
          @foreach(['agendada','confirmada','cancelada','realizada','reprogramada'] as $t)
            <option value="{{ $t }}" @selected(($tipo??'')===$t)>{{ ucfirst($t) }}</option>
          @endforeach
        </select>

        <select class="select" name="estado">
          <option value="">Todos los estados</option>
          @foreach(['pendiente','confirmada','cancelada','realizada'] as $e)
            <option value="{{ $e }}" @selected(($estado??'')===$e)>{{ ucfirst($e) }}</option>
          @endforeach
        </select>

        <select class="select" name="doctor_id">
          <option value="">Todos los doctores</option>
          @foreach($doctores as $d)
            <option value="{{ $d->id }}" @selected(($doctorId??null)===$d->id)>{{ $d->name }}</option>
          @endforeach
        </select>

        <input class="input" type="text"  name="paciente" value="{{ $paciente }}" placeholder="Paciente">
        <input class="input" type="date"  name="desde"    value="{{ $desde }}">
        <input class="input" type="date"  name="hasta"    value="{{ $hasta }}">
        <input class="input" type="search" name="q"       value="{{ $q }}" placeholder="#cita, email, dni, doctor">
      </div>
    </div>

    <div class="card-footer">
      <button class="btn btn-primary" type="submit">Aplicar filtros</button>
      <a class="btn btn-secondary" href="{{ route('admin.cambios-citas.index') }}">Limpiar</a>
    </div>
  </form>

  <div class="card">
    <div class="card-header">
      <h3>Resultados</h3>
      <span>{{ $ev->total() }} eventos</span>
    </div>

    <div class="card-body" style="padding:0">
      <table class="users" style="width:100%">
        <thead>
          <tr>
            <th>Fecha/Hora</th>
            <th>Evento</th>
            <th>Cita</th>
            <th>Paciente</th>
            <th>Doctor</th>
            <th>De</th>
            <th>A</th>
          </tr>
        </thead>
        <tbody>
        @forelse($ev as $r)
          <tr>
            <td>{{ $r->created_at?->format('Y-m-d H:i') }}</td>
            <td>{{ ucfirst($r->tipo) }}</td>
            <td>#{{ $r->cita_id }}</td>
            <td>{{ $r->cita?->paciente?->name ?? '—' }}</td>
            <td>{{ $r->cita?->doctor?->name ?? '—' }}</td>
            <td>
              @if($r->tipo==='reprogramada')
                {{ $r->de_fecha?->format('Y-m-d') }} {{ $r->de_hora }}
              @else
                {{ $r->de_estado ?? '—' }}
              @endif
            </td>
            <td>
              @if($r->tipo==='reprogramada')
                {{ $r->a_fecha?->format('Y-m-d') }} {{ $r->a_hora }}
              @else
                {{ $r->a_estado ?? '—' }}
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" style="text-align:center;padding:1rem">Sin registros</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer" style="justify-content:space-between">
      <div>Página {{ $ev->currentPage() }} de {{ $ev->lastPage() }}</div>
      {!! $ev->withQueryString()->links() !!}
    </div>
  </div>
</div>
@endsection
