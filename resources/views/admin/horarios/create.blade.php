{{-- resources/views/admin/horarios/create.blade.php --}}
@extends('layouts.admin')
@section('title','Crear horario')

@push('head')
<style>
  :root{
    --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --bg:#f8fafc; --card:#fff;
    --brand:#4f46e5; --brand-2:#6366f1; --danger:#ef4444;
    --r-lg:16px; --r-sm:10px; --shadow:0 10px 24px rgba(15,23,42,.06);
  }
  .page{display:grid; gap:14px}
  .page-title{margin:0; color:var(--ink); font-weight:800}

  /* base inputs/botones para consistencia con el índice */
  .card{background:var(--card); border:1px solid var(--line); border-radius:var(--r-lg); box-shadow:var(--shadow); padding:16px}
  .small{font-size:12px; color:var(--muted); margin-bottom:6px; display:block}
  .input,.select{width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:var(--r-sm); background:#fff; color:var(--ink)}
  .input:focus,.select:focus{outline:none; border-color:#c7d2fe; box-shadow:0 0 0 3px rgba(99,102,241,.15)}
  .btn{padding:10px 14px; border-radius:var(--r-sm); border:1px solid var(--line); background:#fff; font-weight:600; cursor:pointer}
  .btn:hover{border-color:#cbd5e1}
  .btn-primary{background:var(--brand); color:#fff; border-color:transparent}
  .btn-primary:hover{filter:brightness(1.05)}
  .btn-outline{background:#fff}

  /* layout del formulario */
  .form-grid{display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px}
  .full{grid-column:1/-1}

  /* sección “repetir por días” */
  .section{padding:14px 16px; border:1px solid var(--line); border-radius:12px; background:#fff}
  .badge{display:inline-flex; align-items:center; gap:6px; padding:4px 8px; border-radius:999px; background:#eef2ff; color:#3730a3; font-weight:700; font-size:12px}

  /* chips de días */
  #dias-wrap{display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px}
  .day-chip{
    display:inline-flex; align-items:center; gap:8px; padding:10px 14px;
    border:1px solid var(--line); border-radius:999px; cursor:pointer; user-select:none;
    font-weight:700; color:var(--ink); background:#f8fafc; transition:.15s;
    box-shadow:0 2px 0 rgba(0,0,0,.02);
  }
  .day-chip input{appearance:none; position:absolute; opacity:0; width:0; height:0}
  .day-chip:hover{transform:translateY(-1px)}
  .day-chip.active{background:#eef2ff; color:#1e1b4b; border-color:#c7d2fe; box-shadow:0 1px 0 rgba(0,0,0,.03),0 0 0 4px rgba(99,102,241,.10)}

  /* barra de presets */
  .toolbar{display:flex; gap:8px; flex-wrap:wrap}
  .btn-mini{font-size:12px; padding:6px 10px; border:1px solid var(--line); border-radius:8px; background:#f1f5f9; cursor:pointer}
  .btn-mini:hover{background:#e2e8f0}
  .btn-mini:active{transform:translateY(1px)}

  /* modos de franja */
  .grid-2{display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px}
  .row-dia{display:grid; grid-template-columns:42px 1fr auto 1fr; gap:10px; align-items:center}
  .muted{font-size:12px; color:var(--muted)}
  .kpi{font-size:12px; color:var(--ink); margin-top:6px}

  /* acciones */
  .actions{display:flex; gap:10px; justify-content:flex-end}
</style>
@endpush

@section('main')
<div class="page">
  <h1 class="page-title">Crear horario</h1>

  @if ($errors->any())
    <div class="card" style="margin-bottom:14px;">
      <ul style="color:#dc2626;margin:0;padding-left:18px">
        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('admin.horarios.store') }}" method="POST" class="card form-grid" id="form-horario">
    @csrf

    <div class="full">
      <label class="small" for="doctor_id">Doctor</label>
      <select class="select" id="doctor_id" name="doctor_id" required>
        <option value="">Seleccione</option>
        @foreach($doctores as $d)
          <option value="{{ $d->id }}" {{ old('doctor_id')==$d->id?'selected':'' }}>{{ $d->name }}</option>
        @endforeach
      </select>
    </div>

    <div>
      <label class="small" for="fecha_inicio">Desde</label>
      <input class="input" type="date" id="fecha_inicio" name="fecha_inicio" value="{{ old('fecha_inicio') }}" required>
    </div>

    <div>
      <label class="small" for="fecha_fin">Hasta</label>
      <input class="input" type="date" id="fecha_fin" name="fecha_fin" value="{{ old('fecha_fin') }}" required>
    </div>

    <div class="full section">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div class="badge">
          <span class="material-symbols-outlined" style="font-size:16px">event_repeat</span> Repetir por días
        </div>
        <div class="muted">Elige días y define una o varias franjas por día.</div>
      </div>

      @php $dias = [1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom']; @endphp
      <div id="dias-wrap">
        @foreach($dias as $num=>$lbl)
          @php $checked = in_array($num, (array)old('dias', [1,2,3,4,5])); @endphp
          <label class="day-chip {{ $checked?'active':'' }}">
            <input type="checkbox" name="dias[]" value="{{ $num }}" {{ $checked?'checked':'' }}>
            <span>{{ $lbl }}</span>
          </label>
        @endforeach
      </div>

      <div class="toolbar" style="margin:10px 0 8px">
        <button type="button" class="btn-mini" data-preset="lv">L-V</button>
        <button type="button" class="btn-mini" data-preset="ld">L-D</button>
        <button type="button" class="btn-mini" data-preset="sd">S-D</button>
        <button type="button" class="btn-mini" data-preset="none">Ninguno</button>
      </div>

      <label class="small" for="misma_franja" style="display:flex;align-items:center;gap:8px;margin:6px 0 10px">
        <input type="checkbox" id="misma_franja" name="misma_franja" value="1" {{ old('misma_franja',1) ? 'checked' : '' }}>
        Usar la misma franja para todos los días marcados
      </label>

      <div id="franja-global" class="grid-2">
        <div>
          <label class="small" for="hora_inicio">Hora inicio</label>
          <input class="input" type="time" id="hora_inicio" name="hora_inicio" step="1800" value="{{ old('hora_inicio') }}">
        </div>
        <div>
          <label class="small" for="hora_fin">Hora fin</label>
          <input class="input" type="time" id="hora_fin" name="hora_fin" step="1800" value="{{ old('hora_fin') }}">
        </div>
      </div>

      <div id="franjas-por-dia" class="section" style="display:none;margin-top:12px">
        <div class="muted" style="margin-bottom:8px">Define horas por cada día marcado.</div>
        @foreach($dias as $num=>$lbl)
          @php $row = old("horas.$num", ['inicio'=>null,'fin'=>null]); @endphp
          <div class="row-dia" data-dia="{{ $num }}" style="margin-bottom:8px">
            <div style="font-weight:800">{{ $lbl }}</div>
            <input class="input" type="time" name="horas[{{ $num }}][inicio]" step="1800" value="{{ $row['inicio'] }}" placeholder="hh:mm">
            <span class="muted" style="text-align:center">a</span>
            <input class="input" type="time" name="horas[{{ $num }}][fin]" step="1800" value="{{ $row['fin'] }}" placeholder="hh:mm">
          </div>
        @endforeach
      </div>

      <p class="kpi" id="kpi"></p>
    </div>

    <div class="full actions">
      <a class="btn btn-outline" href="{{ route('admin.horarios.index') }}">Cancelar</a>
      <button class="btn btn-primary" type="submit">Guardar</button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
(function(){
  const wrap = document.getElementById('dias-wrap');
  const same = document.getElementById('misma_franja');
  const boxGlobal = document.getElementById('franja-global');
  const boxPerDay = document.getElementById('franjas-por-dia');
  const kpi = document.getElementById('kpi');
  const f1 = document.getElementById('fecha_inicio');
  const f2 = document.getElementById('fecha_fin');

  // util: parseo local sin zona horaria
  function parseLocalDate(yyyy_mm_dd){
    if(!yyyy_mm_dd) return null;
    const [y,m,d] = yyyy_mm_dd.split('-').map(Number);
    return new Date(y, m-1, d); // local
  }

  // chips visual
  wrap.addEventListener('change', (e)=>{
    if(e.target && e.target.type==='checkbox'){
      e.target.closest('.day-chip').classList.toggle('active', e.target.checked);
      toggleRows(); updateKPI();
    }
  });

  // presets
  document.querySelectorAll('.btn-mini').forEach(b=>{
    b.addEventListener('click', ()=>{
      const set = {lv:[1,2,3,4,5], ld:[1,2,3,4,5,6,7], sd:[6,7], none:[]}[b.dataset.preset]||[];
      [...wrap.querySelectorAll('input[type="checkbox"]')].forEach(i=>{
        const on = set.includes(parseInt(i.value,10));
        i.checked = on; i.closest('.day-chip').classList.toggle('active', on);
      });
      toggleRows(); updateKPI();
    });
  });

  same.addEventListener('change', syncMode);
  [f1,f2].forEach(i=>i.addEventListener('change', updateKPI));

  function syncMode(){
    const on = same.checked;
    boxGlobal.style.display = on ? '' : 'none';
    boxPerDay.style.display = on ? 'none' : '';
    toggleRows(); updateKPI();
  }

  function toggleRows(){
    const checked = new Set([...wrap.querySelectorAll('input[type="checkbox"]:checked')].map(b=>parseInt(b.value,10)));
    boxPerDay.querySelectorAll('.row-dia').forEach(row=>{
      const d = parseInt(row.dataset.dia,10);
      const show = checked.has(d);
      row.style.opacity = show ? '1' : '.35';
      row.querySelectorAll('input').forEach(i=> i.disabled = !show);
    });
    if (same.checked) boxPerDay.querySelectorAll('input').forEach(i=> i.disabled = true);
  }

  function updateKPI(){
    const start = parseLocalDate(f1.value);
    const end   = parseLocalDate(f2.value);
    const selDays = [...wrap.querySelectorAll('input[type="checkbox"]:checked')].map(b=>parseInt(b.value,10));
    let total=0;
    if(start && end && start<=end && selDays.length){
      const d = new Date(start.getFullYear(), start.getMonth(), start.getDate()); // clonado
      while(d <= end){
        // mapear: 1=Lun ... 7=Dom
        const iso = ((d.getDay()+6)%7)+1; // getDay: 0=Dom ... 6=Sáb
        if(selDays.includes(iso)) total++;
        d.setDate(d.getDate()+1);
      }
    }
    kpi.textContent = selDays.length
      ? `Días marcados: ${selDays.length}. Fechas afectadas en el rango: ${total}.`
      : `Selecciona al menos un día.`;
  }

  // init
  syncMode();
})();
</script>
@endpush
