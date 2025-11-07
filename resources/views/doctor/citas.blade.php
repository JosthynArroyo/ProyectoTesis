@extends('layouts.doctor')
@section('title', 'Mis Citas (Doctor)')
@section('activeSidebar', 'citas')

@push('styles')
<style>
  :root{ --gap:.5rem; }

  .appointments-page{
    width:min(1100px,100%);
    margin:84px auto 36px;
    padding:0 clamp(16px,2.4vw,28px) 36px;
  }

  .appointments-card{
    background:#fff; border:1px solid var(--clr-border);
    border-radius:var(--card-border-radius); box-shadow:var(--box-shadow); overflow:hidden;
  }

  .appointments-header{
    display:flex; justify-content:space-between; align-items:center;
    padding:16px 18px; border-bottom:1px solid var(--clr-border);
  }
  .appointments-title{ display:flex; align-items:center; gap:.6rem; font-weight:800; }

  .btn{
    display:inline-flex; align-items:center; gap:.35rem;
    padding:.5rem .9rem; border-radius:10px; font-weight:700;
    border:1px solid var(--clr-border); text-decoration:none; cursor:pointer;
    font-size:.9rem; white-space:nowrap;
  }
  .btn[disabled]{ opacity:.65; cursor:not-allowed; }
  .btn-success{ background:var(--clr-success); color:#fff; border-color:var(--clr-success); }
  .btn-outline-success{ background:rgba(26,127,92,.08); border:1px solid rgba(26,127,92,.35); color:var(--clr-success); }
  .btn-outline-danger{ background:rgba(178,58,72,.08); border:1px solid rgba(178,58,72,.32); color:#8b2a33; }
  .btn-mark-done{ background:rgba(37,99,235,.08); border:1px solid rgba(37,99,235,.3); color:#2563eb; }
  .btn-mark-done:hover{ background:#2563eb; color:#fff; }
  .btn-prescription{ background:rgba(16,185,129,.10); border:1px solid rgba(16,185,129,.35); color:#10b981; }
  .btn-prescription:hover{ background:#10b981; color:#fff; }

  .table-wrap{ width:100%; overflow-x:auto; }
  .appointments-table{ width:100%; border-collapse:separate; border-spacing:0; table-layout:auto; }
  .appointments-table thead th{
    background:var(--clr-primary); color:#fff; font-weight:700; padding:12px 10px; text-align:left;
  }
  .appointments-table td{ padding:12px 10px; border-bottom:1px solid var(--clr-border); vertical-align:middle; }

  /* ====== ACCIONES con SCROLL horizontal solo en la celda ====== */
  .th-actions{ min-width:560px; }
  .cell-actions{ padding:0; } /* quitamos padding al td */
  .actions-scroll{
    display:flex; align-items:center; gap:var(--gap);
    overflow-x:auto; overflow-y:hidden; max-width:100%;
    padding:12px 10px; flex-wrap:nowrap;
    -webkit-overflow-scrolling:touch; scroll-behavior:smooth;
  }
  .actions-scroll > *{ flex:0 0 auto; min-width:max-content; }

  /* Bloques internos */
  .stack{ display:grid; gap:8px; }
  .row-actions{ display:flex; gap:var(--gap); align-items:center; flex-wrap:nowrap; }

  .badge{ display:inline-flex; align-items:center; gap:.3rem; padding:.3rem .55rem; border-radius:999px; font-size:.8rem; font-weight:700; }
  .bg-warning{ background:rgba(182,130,42,.12); color:#8b5a12; }
  .bg-info{ background:rgba(37,99,235,.12); color:#2563eb; }
  .bg-danger{ background:rgba(178,58,72,.12); color:#8b2a33; }
  .bg-success{ background:rgba(26,127,92,.12); color:#125c40; }

  .muted{ color:#6b7280; font-weight:600; display:inline-flex; align-items:center; gap:.35rem; }

  .next-info{
    display:flex; align-items:center; gap:.45rem;
    padding:.45rem .7rem; border:1px solid #e5e7eb; border-radius:10px; background:#fafafa;
    min-width:max-content;
  }

  /* Toast */
  .toast{ position:fixed; right:16px; bottom:16px; width:min(560px,94vw); background:#fff; border:1px solid #e5e7eb; border-radius:14px; box-shadow:0 10px 22px rgba(0,0,0,.12); display:none; z-index:2000; }
  .toast[open]{ display:block; }
  .toast-h{ display:flex; align-items:center; justify-content:space-between; padding:12px 14px; border-bottom:1px solid #eef2f7; }
  .toast-h strong{ font-weight:800; }
  .toast-b{ padding:12px 14px; display:grid; gap:10px; }
  .toast-f{ display:flex; justify-content:flex-end; gap:8px; padding:10px 14px; border-top:1px solid #eef2f7; }
  .field{ display:grid; gap:6px; }
  .input, .select{ width:100%; padding:10px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; }
  .slots{ display:flex; flex-wrap:wrap; gap:8px; }
  .chip{ padding:.42rem .7rem; border:1px solid #e5e7eb; border-radius:999px; cursor:pointer; user-select:none; }
  .chip[aria-checked="true"]{ background:rgba(37,99,235,.08); border-color:rgba(37,99,235,.3); color:#2563eb; }
  .toast-warn{ border-left:6px solid #f59e0b; background:#fff7ed; padding:10px 12px; border-radius:10px; }

  /* ===== Breakpoints ===== */
  @media (max-width:1400px){ .th-actions{ min-width:520px; } }
  @media (max-width:1200px){
    .appointments-page{ margin:60px auto 28px; }
    .th-actions{ min-width:480px; }
    .btn{ font-size:.88rem; }
  }
  @media (max-width:768px){
    .appointments-page{ margin:44px auto 24px; padding:0 16px 28px; }
    .appointments-table thead th:nth-child(4),
    .appointments-table tbody td:nth-child(4){ display:none; } /* Hora */
    .th-actions{ min-width:420px; }
    .next-info{ padding:.4rem .6rem; }
  }
  @media (max-width:640px){
    .appointments-table thead th:nth-child(2),
    .appointments-table tbody td:nth-child(2){ display:none; } /* Especialidad */
    .th-actions{ min-width:360px; }
    .btn{ font-size:.86rem; }
    /* en móviles dejamos el scroll horizontal en acciones sin forzar botones full width */
  }
</style>
@endpush

@section('content')
<section class="appointments-page">
  <div class="appointments-card">
    <div class="appointments-header">
      <div class="appointments-title">
        <span class="material-symbols-outlined">calendar_month</span>
        <h1>Mis Citas (Doctor)</h1>
      </div>
      <button id="btn-refresh" class="btn btn-success" type="button">
        <span class="material-symbols-outlined">refresh</span> Actualizar
      </button>
    </div>

    <div class="table-wrap">
      <table class="appointments-table" id="tabla-citas">
        <thead>
          <tr>
            <th>Paciente</th>
            <th>Especialidad</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Estado</th>
            <th class="th-actions">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @foreach($citas as $cita)
            @php
              $estado = $cita->estado;
              $badge = match($estado){
                'pendiente' => 'bg-warning',
                'confirmada'=> 'bg-info',
                'cancelada' => 'bg-danger',
                'realizada' => 'bg-success',
                default     => 'bg-secondary'
              };
            @endphp
            <tr>
              <td>{{ $cita->paciente->name ?? '—' }}</td>
              <td>{{ $cita->especialidad->nombre ?? '—' }}</td>
              <td>{{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }}</td>
              <td>{{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}</td>
              <td><span class="badge {{ $badge }}">{{ ucfirst($estado) }}</span></td>

              <td class="cell-actions">
                <div class="actions-scroll">

                  {{-- PENDIENTE --}}
                  @if($estado === 'pendiente')
                    <div class="row-actions">
                      <form action="{{ route('doctor.citas.aceptar',$cita->id) }}" method="POST">@csrf
                        <button class="btn btn-outline-success" type="submit">
                          <span class="material-symbols-outlined">done</span> Aceptar
                        </button>
                      </form>
                      <form action="{{ route('doctor.citas.rechazar',$cita->id) }}" method="POST" onsubmit="return confirm('¿Rechazar cita?');">@csrf
                        <button class="btn btn-outline-danger" type="submit">
                          <span class="material-symbols-outlined">close</span> Rechazar
                        </button>
                      </form>
                      <a href="{{ route('paciente.editar-cita',$cita->id) }}" class="btn btn-success">
                        <span class="material-symbols-outlined">event</span> Reagendar
                      </a>
                    </div>
                  @endif

                  {{-- CONFIRMADA --}}
                  @if($estado === 'confirmada')
                    <div class="row-actions">
                      <form action="{{ route('doctor.citas.realizar',$cita->id) }}" method="POST">@csrf
                        <button class="btn btn-mark-done" type="submit">
                          <span class="material-symbols-outlined">check_circle</span> Marcar como realizada
                        </button>
                      </form>
                    </div>
                  @endif

                  {{-- REALIZADA --}}
                  @if($estado === 'realizada')
                    @php
                      $px = $cita->proxima_cita ?? null;
                      $hayProxima = $px && \Carbon\Carbon::parse($px->fecha)
                                    ->gte(\Carbon\Carbon::now('America/Guayaquil')->startOfDay());
                    @endphp

                    <div class="stack">
                      {{-- Próxima cita: chip si existe; si no, botón --}}
                      @if($hayProxima)
                        <div class="next-info">
                          <span class="material-symbols-outlined">event_upcoming</span>
                          <span>
                            Próxima cita: {{ \Carbon\Carbon::parse($px->fecha)->format('d/m/Y') }}
                            {{ \Carbon\Carbon::parse($px->hora)->format('H:i') }}
                            ({{ ucfirst($px->estado) }})
                          </span>
                        </div>
                      @else
                        <div class="row-actions">
                          <button type="button"
                                  class="btn btn-outline-success"
                                  data-accion="planificador"
                                  data-cita="{{ $cita->id }}"
                                  data-doctor="{{ $cita->doctor_id }}">
                            <span class="material-symbols-outlined">event_available</span> Agendar próxima cita
                          </button>
                        </div>
                      @endif

                      {{-- Receta: generar o editar --}}
                      <div class="row-actions">
                        @if($cita->receta)
                          @if($cita->receta->can_edit)
                            <a href="{{ route('doctor.recetas.edit',$cita->id) }}" class="btn btn-prescription">
                              <span class="material-symbols-outlined">edit_square</span> Editar receta
                            </a>
                          @else
                            <span class="muted">
                              <span class="material-symbols-outlined">lock_clock</span> Edición expirada
                            </span>
                          @endif
                        @else
                          <a href="{{ route('doctor.recetas.create',$cita->id) }}" class="btn btn-prescription">
                            <span class="material-symbols-outlined">local_pharmacy</span> Generar receta
                          </a>
                        @endif
                      </div>
                    </div>
                  @endif

                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</section>

{{-- Toast planificador --}}
<div class="toast" id="toast-planificador" aria-live="polite">
  <div class="toast-h">
    <strong>Planificar próxima cita</strong>
    <button type="button" class="btn" id="toast-close"><span class="material-symbols-outlined">close</span></button>
  </div>
  <div class="toast-b">
    <div id="toast-warn" class="toast-warn" style="display:none">
      No tienes horarios configurados esta semana.
      <div style="margin-top:6px; display:flex; gap:8px;">
        <a href="{{ route('doctor.horario.index') }}" class="btn">Ir a “Mi horario”</a>
      </div>
    </div>

    <div class="field">
      <label for="tp-fecha">Fecha</label>
      <input id="tp-fecha" class="input" type="date" min="{{ \Carbon\Carbon::now('America/Guayaquil')->toDateString() }}">
    </div>

    <div class="field">
      <label>Horas disponibles</label>
      <div id="tp-slots" class="slots"></div>
      <small id="tp-help" class="muted"></small>
    </div>
  </div>
  <div class="toast-f">
    <button type="button" class="btn" id="tp-cancelar">Cancelar</button>
    <button type="button" class="btn btn-success" id="tp-crear" disabled>
      <span class="material-symbols-outlined">save</span> Crear cita
    </button>
  </div>
</div>

@push('scripts')
<script>
(function(){
  const $  = (q,ctx=document)=>ctx.querySelector(q);
  const $$ = (q,ctx=document)=>[...ctx.querySelectorAll(q)];
  const CSRF = @json(csrf_token());
  const RUTA_CHECK = @json(route('doctor.disponibilidad.check'));
  const RUTA_PROX_PLAN = (id)=> @json(route('doctor.citas.proxima.planificada',['cita'=>'__ID__'])).replace('__ID__', id);
  const RUTA_SLOTS = (doctor, fecha)=> @json(route('api.doctor.slots',['doctor'=>'__D__','fecha'=>'__F__']))
                                      .replace('__D__', String(doctor)).replace('__F__', String(fecha));

  let ctx = { citaId:null, doctorId:null, slot:null };

  $('#btn-refresh')?.addEventListener('click', ()=>location.reload());

  // Abrir toast
  $$('#tabla-citas [data-accion="planificador"]').forEach(b=>{
    b.addEventListener('click', async (e)=>{
      e.preventDefault();
      ctx.citaId = b.dataset.cita;
      ctx.doctorId = b.dataset.doctor;
      ctx.slot = null;
      $('#tp-fecha').value = '';
      $('#tp-slots').innerHTML = '';
      $('#tp-help').textContent = '';
      $('#tp-crear').disabled = true;
      $('#toast-warn').style.display = 'none';

      const chk = await getJson(RUTA_CHECK);
      if(!chk.ok || (chk.json.ok===false && chk.json.reason==='sin_horario')){
        $('#toast-warn').style.display = 'block';
      }
      openToast();
    });
  });

  // Cerrar toast
  $('#toast-close').addEventListener('click', closeToast);
  $('#tp-cancelar').addEventListener('click', closeToast);

  function normalizeSlots(raw){
    if(!Array.isArray(raw)) return [];
    return raw.map(s=>{
      if(typeof s === 'string') return {label:s, value:s};
      if(typeof s === 'number'){
        const z = String(s).padStart(4,'0');
        const hhmm = `${z.slice(0,2)}:${z.slice(2)}`;
        return {label:hhmm, value:hhmm};
      }
      if(s == null || typeof s !== 'object') return null;
      const cand = s.hhmm || s.hora || s.time || s.label || s.value;
      if(cand) return {label:String(cand), value:String(cand)};
      const first = Object.values(s)[0];
      return first ? {label:String(first), value:String(first)} : null;
    }).filter(Boolean);
  }

  $('#tp-fecha').addEventListener('change', async ()=>{
    ctx.slot=null;
    $('#tp-crear').disabled = true;
    $('#tp-slots').innerHTML='';
    $('#tp-help').textContent='Cargando…';

    const fecha = $('#tp-fecha').value;
    if(!fecha){ $('#tp-help').textContent=''; return; }

    const r = await getJson(RUTA_SLOTS(ctx.doctorId, fecha));
    if(!r.ok){ $('#tp-help').textContent='Error al cargar horarios.'; return; }

    const slotsRaw = (r.json && (r.json.slots ?? r.json.data ?? r.json)) || [];
    const slots = normalizeSlots(slotsRaw);

    if(slots.length === 0){
      $('#tp-help').textContent = 'Sin disponibilidad en esta fecha.';
      return;
    }

    $('#tp-help').textContent = 'Seleccione una hora.';
    const wrap = $('#tp-slots'); wrap.innerHTML='';

    slots.forEach(({label,value})=>{
      const chip = document.createElement('button');
      chip.type='button'; chip.className='chip'; chip.textContent=label;
      chip.addEventListener('click', ()=>{
        $$('#tp-slots .chip').forEach(c=>c.removeAttribute('aria-checked'));
        chip.setAttribute('aria-checked','true');
        ctx.slot = value;
        $('#tp-crear').disabled = false;
      });
      wrap.appendChild(chip);
    });
  });

  $('#tp-crear').addEventListener('click', async ()=>{
    if(!ctx.citaId || !$('#tp-fecha').value || !ctx.slot) return;
    setLoading($('#tp-crear'), true);

    const res = await postJson(RUTA_PROX_PLAN(ctx.citaId), { fecha: $('#tp-fecha').value, hora: ctx.slot });
    setLoading($('#tp-crear'), false);

    if(res.ok && res.json.ok){
      toastSmall('Cita creada y notificada por email.');
      closeToast();
      location.reload();
      return;
    }
    if(res.status===422){
      toastWarn(res.json?.msg || 'Validación rechazada.'); return;
    }
    if(res.status===419){
      toastWarn('Sesión expirada.'); location.href = @json(route('login')); return;
    }
    toastWarn('No se pudo crear la cita.');
  });

  function openToast(){ $('#toast-planificador').setAttribute('open',''); }
  function closeToast(){ $('#toast-planificador').removeAttribute('open'); }
  function setLoading(btn, loading){
    if(loading){
      btn.dataset._txt = btn.innerHTML;
      btn.innerHTML = '<span class="material-symbols-outlined">hourglass_top</span> Procesando…';
      btn.setAttribute('disabled','disabled');
    }else{
      if(btn.dataset._txt) btn.innerHTML = btn.dataset._txt;
      btn.removeAttribute('disabled');
    }
  }
  async function getJson(u){ const r = await fetch(u, {headers:{Accept:'application/json'}}); return {ok:r.ok,status:r.status,json:await safeJson(r)}; }
  async function postJson(u, data){
    const r = await fetch(u, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json',Accept:'application/json'}, body: JSON.stringify(data||{}) });
    return {ok:r.ok,status:r.status,json:await safeJson(r)};
  }
  async function safeJson(resp){ const t = await resp.text(); try{return t?JSON.parse(t):{};}catch{return {}}; }

  function toastSmall(msg){
    const el = document.createElement('div');
    el.style.position='fixed'; el.style.right='18px'; el.style.bottom='18px';
    el.style.background='#10b981'; el.style.color='#fff'; el.style.padding='10px 14px';
    el.style.borderRadius='10px'; el.style.boxShadow='0 10px 22px rgba(0,0,0,.12)'; el.style.zIndex='3000';
    el.textContent = msg; document.body.appendChild(el); setTimeout(()=>el.remove(), 2200);
  }
  function toastWarn(msg){
    const box = document.createElement('div');
    box.style.position='fixed'; box.style.right='18px'; box.style.bottom='18px';
    box.style.background='#fff7ed'; box.style.color='#92400e'; box.style.padding='12px 14px';
    box.style.border='1px solid #fed7aa'; box.style.borderLeft='6px solid #f59e0b';
    box.style.borderRadius='10px'; box.style.boxShadow='0 10px 22px rgba(0,0,0,.12)'; box.style.zIndex='3000';
    box.innerHTML = '<div style="display:flex;align-items:center;gap:8px;"><span class="material-symbols-outlined">warning</span><div>'+msg+'</div></div>';
    document.body.appendChild(box); setTimeout(()=>box.remove(), 4000);
  }
})();
</script>
@endpush

@endsection
