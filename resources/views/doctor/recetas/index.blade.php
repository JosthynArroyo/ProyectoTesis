@extends('layouts.doctor')
@section('title', 'Historial de Recetas')
@section('activeSidebar', 'recetas')

@push('styles')
<style>
  .rx-page{width:min(1100px,100%);margin:84px auto 36px;padding:0 clamp(16px,2.4vw,28px) 36px;}
  .rx-card{background:#fff;border:1px solid var(--clr-border);border-radius:var(--card-border-radius);box-shadow:var(--box-shadow);overflow:hidden}
  .rx-header{display:flex;justify-content:space-between;align-items:center;padding:16px 18px;border-bottom:1px solid var(--clr-border)}
  .rx-title{display:flex;align-items:center;gap:.6rem;font-weight:800}
  .rx-table{width:100%;border-collapse:separate;border-spacing:0}
  .rx-table thead th{background:var(--clr-primary);color:#fff;font-weight:700;padding:12px 10px}
  .rx-table td{padding:12px 10px;border-bottom:1px solid var(--clr-border);vertical-align:middle}
  .rx-actions{display:flex;gap:10px;align-items:center}
  .btn{display:inline-flex;align-items:center;gap:.35rem;padding:.5rem .9rem;border-radius:10px;font-weight:700;border:1px solid var(--clr-border);text-decoration:none;cursor:pointer;font-size:.9rem}
  .btn-download{background:rgba(37,99,235,.08);border:1px solid rgba(37,99,235,.3)}
  .muted{color:#6b7280;font-weight:600}
</style>
@endpush

@section('content')
<section class="rx-page">
  <div class="rx-card">
    <div class="rx-header">
      <div class="rx-title">
        <span class="material-symbols-outlined">prescriptions</span>
        <h2>Historial de Recetas</h2>
      </div>
      
    </div>

    <div class="table-wrap">
      <table class="rx-table">
        <thead>
          <tr>
            <th>Paciente</th>
            <th>Especialidad</th>
            <th>Fecha de cita</th>
            <th>Hora</th>
            <th>PDF</th>
          </tr>
        </thead>
        <tbody>
          @forelse($recetas as $receta)
            <tr>
              <td>{{ $receta->cita->paciente->name ?? '—' }}</td>
              <td>{{ $receta->cita->especialidad->nombre ?? '—' }}</td>
              <td>{{ optional($receta->cita->fecha)->format('d/m/Y') }}</td>
              <td>{{ \Carbon\Carbon::parse($receta->cita->hora)->format('H:i') }}</td>
              <td class="rx-actions">
                @if($receta->pdf_path)
                  <a class="btn btn-download" href="{{ route('doctor.recetas.download', $receta->cita->id) }}">
                    <span class="material-symbols-outlined">download</span> Descargar
                  </a>
                @else
                  <span class="muted">Sin PDF</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5">Aún no hay recetas.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div style="width:min(1100px,100%);margin:12px auto 0;">
    {{ $recetas->links() }}
  </div>
</section>
@endsection
