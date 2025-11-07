@extends('layouts.doctor')
@section('title', 'Editar Receta')
@section('activeSidebar', 'citas')

@push('styles')
<style>
  .rx-wrap{ width:min(900px,100%); margin:84px auto 36px; background:#fff; border:1px solid var(--clr-border); border-radius:var(--card-border-radius); box-shadow:var(--box-shadow); padding:22px; }
  .rx-hdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:10px;flex-wrap:wrap}
  .rx-grid{display:grid;gap:14px}
  .rx-grid label{font-weight:700;margin-bottom:6px;display:block}
  .rx-grid textarea{width:100%;min-height:120px;padding:12px;border:1px solid var(--clr-border);border-radius:12px;resize:vertical}
  .rx-actions{display:flex;gap:.6rem;justify-content:flex-end;margin-top:12px;flex-wrap:wrap}
  .btn{display:inline-flex;align-items:center;gap:.35rem;padding:.6rem 1rem;border-radius:10px;font-weight:700;border:1px solid var(--clr-border);cursor:pointer;text-decoration:none}
  .btn-primary{background:var(--clr-primary);color:#fff;border-color:var(--clr-primary)}
  .btn-muted{background:#f3f4f6}
  .btn-outline{background:#fff}
  .pill{padding:.35rem .7rem;border:1px solid var(--clr-border);border-radius:999px;background:#f9fafb;font-size:.85rem}
  .pill-ok{background:#e8fff4;border-color:#b7f3d7;color:#0a7a55;display:none}
  .help-note{display:none;color:#0a7a55;font-weight:600}
</style>
@endpush

@section('content')
<div class="rx-wrap" @if(session('ask_resend')) data-ask-resend="1" @endif>
  <div class="rx-hdr">
    <div>
      <h1 style="margin:0 0 6px">Editar Receta</h1>
      <div class="pill">
        <strong>Paciente:</strong> {{ $cita->paciente->name ?? '—' }}
        &nbsp;|&nbsp;
        <strong>Fecha cita:</strong> {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }} {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}
        @if($receta->enviado_en)
          &nbsp;|&nbsp;
          <strong>Último envío:</strong> {{ $receta->enviado_en->format('d/m/Y H:i') }}
        @endif
      </div>
    </div>

    <div class="rx-actions" style="margin:0">
      @if($receta->pdf_path)
        <a class="btn btn-outline" href="{{ route('doctor.recetas.download', $cita->id) }}">
          <span class="material-symbols-outlined">download</span> Descargar PDF
        </a>
      @endif
      {{-- Botón "Reenviar ahora" eliminado --}}
      <span id="reenviar-pill" class="pill pill-ok">Reenviar activado</span>
    </div>
  </div>

  @if (session('success'))
    <div class="alert alert-success" style="margin-bottom:12px">{{ session('success') }}</div>
  @endif
  @if (session('info'))
    <div class="alert alert-info" style="margin-bottom:12px">{{ session('info') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger" style="margin-bottom:12px">
      <ul style="margin:0;padding-left:18px">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form id="form-receta" method="POST" action="{{ route('doctor.recetas.update') }}">
    @csrf
    <input type="hidden" name="cita_id" value="{{ $cita->id }}">

    <div class="rx-grid">
      <div>
        <label for="diagnostico">Diagnóstico / Motivo</label>
        <textarea id="diagnostico" name="diagnostico" required>{{ old('diagnostico', $receta->diagnostico) }}</textarea>
      </div>

      <div>
        <label for="medicamentos">Medicamentos (dosis y frecuencia)</label>
        <textarea id="medicamentos" name="medicamentos" required>{{ old('medicamentos', $receta->medicamentos) }}</textarea>
      </div>

      <div>
        <label for="indicaciones">Indicaciones adicionales (opcional)</label>
        <textarea id="indicaciones" name="indicaciones">{{ old('indicaciones', $receta->indicaciones) }}</textarea>
      </div>
    </div>

    <div class="rx-actions" style="justify-content:flex-start">
      <label><input type="checkbox" name="regenerar_pdf" value="1" {{ old('regenerar_pdf') ? 'checked' : '' }}> Regenerar PDF</label>
      <label>
        <input type="checkbox" id="reenviar" name="reenviar" value="1" {{ old('reenviar') ? 'checked' : '' }}>
        Reenviar correo al paciente
      </label>
      @if($receta->pdf_path)
        <small style="color:#6b7280">Archivo actual: {{ $receta->pdf_path }}</small>
      @endif
    </div>
    <div id="reenviar-note" class="help-note">Al guardar, se enviará la <strong>receta actualizada</strong> al paciente.</div>

    <div class="rx-actions">
      <a href="{{ route('doctor.citas') }}" class="btn btn-muted">Cancelar</a>
      <button type="submit" id="btn-guardar" class="btn btn-primary">
        <span class="material-symbols-outlined">save</span>
        <span class="btn-text">Guardar cambios</span>
      </button>
    </div>
  </form>

  {{-- Formulario oculto para reenviar DESPUÉS de guardar (cuando no se marcó la casilla) --}}
  <form id="form-resend-postsave" method="POST" action="{{ route('doctor.recetas.resend', $cita->id) }}" style="display:none">
    @csrf
  </form>
</div>

{{-- JS inline para indicaciones visuales y prompt post-guardado --}}
<script>
(function () {
  const wrap = document.querySelector('.rx-wrap');
  const chk  = document.getElementById('reenviar');
  const note = document.getElementById('reenviar-note');
  const pill = document.getElementById('reenviar-pill');
  const btn  = document.getElementById('btn-guardar');
  const btnText = btn ? btn.querySelector('.btn-text') : null;

  function syncUI() {
    if (!chk || !btn || !btnText || !note || !pill) return;
    if (chk.checked) {
      btnText.textContent = 'Guardar y reenviar';
      note.style.display = 'block';
      pill.style.display = 'inline-flex';
    } else {
      btnText.textContent = 'Guardar cambios';
      note.style.display = 'none';
      pill.style.display = 'none';
    }
  }

  // Estado inicial (incluye old('reenviar'))
  syncUI();

  // Cambios en tiempo real
  if (chk) chk.addEventListener('change', syncUI);

  // Prompt post-guardado solo si NO estaba marcado reenviar
  if (wrap && wrap.getAttribute('data-ask-resend') === '1') {
    setTimeout(function() {
      const ok = confirm('La receta se actualizó correctamente.\n¿Deseas reenviar la receta actualizada al paciente ahora?');
      if (ok) {
        document.getElementById('form-resend-postsave').submit();
      }
    }, 80);
  }
})();
</script>
@endsection
