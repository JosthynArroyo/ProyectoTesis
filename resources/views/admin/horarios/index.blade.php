@extends('layouts.admin')
@section('title','Horarios de doctores')

@push('head')
<style>
  :root{
    --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --bg:#f8fafc; --card:#fff;
    --brand:#4f46e5; --brand-2:#6366f1; --danger:#ef4444;
    --r-lg:16px; --r-sm:10px; --shadow:0 10px 24px rgba(15,23,42,.06);
  }
  .page{display:grid; gap:14px}
  .page-title{margin:0; color:var(--ink); font-weight:800}

  /* ===== toolbar sticky ===== */
  .toolbar-wrap{position:sticky; top:8px; z-index:5}
  .toolbar{
    display:grid; grid-template-columns:1fr 1fr auto auto auto auto auto; gap:12px; align-items:end;
    padding:12px; background:var(--card); border:1px solid var(--line); border-radius:var(--r-lg); box-shadow:var(--shadow);
  }
  .small{font-size:12px; color:var(--muted); margin-bottom:6px; display:block}
  .input,.select{width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:var(--r-sm); background:#fff; color:var(--ink)}
  .btn{padding:10px 14px; border-radius:var(--r-sm); border:1px solid var(--line); background:#fff; font-weight:600; cursor:pointer}
  .btn:hover{border-color:#cbd5e1}
  .btn-primary{background:var(--brand); color:#fff; border-color:transparent}
  .btn-primary:hover{filter:brightness(1.05)}
  .btn-outline{background:#fff}

  /* ===== rango ===== */
  .range-card{padding:16px; background:var(--card); border:1px solid var(--line); border-radius:var(--r-lg); box-shadow:var(--shadow)}
  .range{font-weight:800; color:#1e293b}
  .muted{font-size:13px; color:var(--muted)}

  /* ===== tabs doctores ===== */
  .tabs{
    position:sticky; top:74px; z-index:4;    /* debajo de toolbar */
    background:linear-gradient(#ffffff, #ffffffcc 60%, #ffffff00); padding:8px 0 2px;
    backdrop-filter:saturate(120%) blur(2px);
  }
  .tab-scroller{display:flex; gap:10px; overflow:auto; scrollbar-width:thin; padding-bottom:6px}
  .chip{
    flex:0 0 auto; display:flex; align-items:center; gap:8px; padding:9px 12px;
    border:1px solid var(--line); border-radius:999px; background:#fff; color:#111827; font-weight:700; cursor:pointer;
    transition:transform .1s, border-color .2s, background .2s;
  }
  .chip:hover{transform:translateY(-1px)}
  .chip.active{border-color:#c7d2fe; background:#eef2ff; color:#1e1b4b}
  .chip .dot{inline-size:8px; block-size:8px; border-radius:999px; background:var(--brand-2)}

  /* ===== tablero semanal ===== */
  .board{display:grid; gap:12px}
  .days{display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:12px}
  .day{background:#fff; border:1px solid var(--line); border-radius:12px; padding:12px; min-height:150px;
       box-shadow:var(--shadow); transition:transform .12s}
  .day:hover{transform:translateY(-1px)}
  .day-head{display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; padding-bottom:8px; border-bottom:2px solid #eef2ff}
  .day-name{font-weight:800; color:#1e293b; font-size:13px; text-transform:uppercase; letter-spacing:.4px}
  .day-date{font-size:11px; color:var(--muted); font-weight:700}

  .slot{display:grid; gap:6px; background:linear-gradient(135deg,#f8fafc 0%,#eef2ff 100%);
        border:1px solid var(--line); border-left:3px solid var(--brand-2); border-radius:10px;
        padding:10px; margin-bottom:8px; animation:rise .25s ease}
  .slot:hover{border-left-color:var(--brand)}
  .slot-time{font-weight:800; color:#111827; font-size:13px}
  .slot-actions{display:flex; gap:10px; font-size:12px}
  .slot-actions a{color:var(--brand-2); font-weight:700; text-decoration:none}
  .slot-actions a:hover{text-decoration:underline}
  .slot-actions button{color:var(--danger); background:none; border:0; padding:0; font-weight:700; cursor:pointer}
  .slot-actions button:hover{text-decoration:underline}
  .empty{font-size:13px; color:#94a3b8; text-align:center; padding:18px 8px; font-style:italic}

  /* ===== animaciones ===== */
  @keyframes rise{from{opacity:0; transform:translateY(-6px)} to{opacity:1; transform:none}}

  /* ===== responsive ===== */
  @media (max-width:1400px){ .days{grid-template-columns:repeat(5,1fr)} }
  @media (max-width:1200px){ .days{grid-template-columns:repeat(4,1fr)} }
  @media (max-width:900px){
    .toolbar{grid-template-columns:1fr 1fr auto}
    .tabs{top:68px}
    .days{grid-template-columns:repeat(2,1fr)}
  }
  @media (max-width:600px){
    .toolbar{grid-template-columns:1fr}
    .tabs{top:64px}
    .days{grid-template-columns:1fr}
  }
</style>
@endpush

@section('main')
@php
  use Carbon\Carbon;

  // Espera: $horarios, $doctores, $doctorId, $weekStart, $weekEnd
  $days=[]; $c=$weekStart->copy();
  for($i=0;$i<7;$i++){ $days[]=$c->copy(); $c->addDay(); }
  $fmt = fn($t)=>Carbon::parse($t)->format('H:i');

  // doctor activo: el seleccionado o el primero disponible
  $activeDoctorId = $doctorId ?? ($doctores->first()->id ?? null);

  $prev=$weekStart->copy()->subWeek()->toDateString();
  $next=$weekStart->copy()->addWeek()->toDateString();

  // indexar horarios por doctor y fecha
  $byDoctor = collect($horarios)->groupBy('doctor_id');
@endphp

<div class="page">
  <h1 class="page-title">Horarios de doctores</h1>

  {{-- Toolbar --}}
  <div class="toolbar-wrap">
    <form method="GET" action="{{ route('admin.horarios.index') }}" class="toolbar">
      <div>
        <label class="small" for="doctor_id">Doctor</label>
        <select id="doctor_id" name="doctor_id" class="select">
          @foreach($doctores as $d)
            <option value="{{ $d->id }}" {{ (string)$activeDoctorId===(string)$d->id?'selected':'' }}>{{ $d->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="small">Semana</label>
        <input class="input" type="date" name="week" value="{{ request('week',$weekStart->toDateString()) }}">
      </div>
      <button class="btn btn-primary" type="submit">Aplicar</button>
      <a class="btn btn-outline" href="{{ route('admin.horarios.index',['doctor_id'=>$activeDoctorId,'week'=>$prev]) }}">⟵ Anterior</a>
      <a class="btn btn-outline" href="{{ route('admin.horarios.index',['doctor_id'=>$activeDoctorId,'week'=>Carbon::now()->toDateString()]) }}">Hoy</a>
      <a class="btn btn-outline" href="{{ route('admin.horarios.index',['doctor_id'=>$activeDoctorId,'week'=>$next]) }}">Siguiente ⟶</a>
      <a class="btn btn-primary" href="{{ route('admin.horarios.create') }}" style="margin-left:auto">+ Nuevo horario</a>
    </form>
  </div>

  {{-- Rango --}}
  <div class="range-card">
    <div class="range">📅 {{ $weekStart->format('d M Y') }} — {{ $weekEnd->format('d M Y') }}</div>
    <div class="muted">Selecciona un doctor en las pestañas para ver su semana.</div>
  </div>

  {{-- Tabs doctores --}}
  <div class="tabs">
    <div class="tab-scroller" id="doctor-tabs">
      @foreach($doctores as $d)
        <button class="chip {{ (string)$activeDoctorId===(string)$d->id?'active':'' }}"
                data-doctor="{{ $d->id }}" type="button" title="{{ $d->name }}">
          <span class="dot"></span><span>{{ $d->name }}</span>
        </button>
      @endforeach
    </div>
  </div>

  {{-- Tablero semanal del activo --}}
  @php
    $activeItems = $byDoctor->get($activeDoctorId, collect());
    $byDate = $activeItems->groupBy(fn($h)=>Carbon::parse($h->fecha)->toDateString());
  @endphp

  <div class="board">
    <div class="days">
      @foreach($days as $d)
        @php
          $key = $d->toDateString();
          $slots = $byDate->get($key, collect())->sortBy(['hora_inicio','hora_fin']);
        @endphp
        <div class="day">
          <div class="day-head">
            <div class="day-name">{{ $d->isoFormat('ddd') }}</div>
            <div class="day-date">{{ $d->format('d/m') }}</div>
          </div>
          @forelse($slots as $h)
            <div class="slot">
              <div class="slot-time">🕐 {{ $fmt($h->hora_inicio) }} – {{ $fmt($h->hora_fin) }}</div>
              <div class="slot-actions">
                <a href="{{ route('admin.horarios.edit',$h) }}">Editar</a>
                <form action="{{ route('admin.horarios.destroy',$h) }}" method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar este horario?');">
                  @csrf @method('DELETE')
                  <button type="submit">Eliminar</button>
                </form>
              </div>
            </div>
          @empty
            <div class="empty">Sin horarios</div>
          @endforelse
        </div>
      @endforeach
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  // Tabs → cambian doctor en la URL manteniendo semana
  const tabs = document.getElementById('doctor-tabs');
  if(!tabs) return;

  tabs.addEventListener('click', (e)=>{
    const btn = e.target.closest('.chip'); if(!btn) return;
    const doctorId = btn.dataset.doctor;
    const url = new URL(window.location.href);
    url.searchParams.set('doctor_id', doctorId);
    // conservar la semana actual
    if(!url.searchParams.get('week')){
      const inp = document.querySelector('input[name="week"]');
      if(inp && inp.value) url.searchParams.set('week', inp.value);
    }
    window.location.href = url.toString();
  });
})();
</script>
@endpush
