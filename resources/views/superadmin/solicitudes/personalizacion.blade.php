@extends('layouts.superadmin')
@section('title','Solicitudes de personalización')
@section('header-title','Solicitudes')
@section('header-subtitle','Permisos de acceso a personalización')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Permisos</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Solicitudes de personalización</h1>
        <p class="text-slate-600">Aprueba o rechaza accesos solicitados por administradores.</p>
      </div>
    </div>
  </section>

  <form class="card p-5" method="GET" action="{{ route('superadmin.solicitudes.personalizacion.index') }}">
    <div class="flex flex-wrap items-center gap-4">
      <select class="form-select" name="status" onchange="this.form.submit()" required>
        <option value="all" @selected($status==='' || $status==='all')>Todos los estados</option>
        @foreach(['pending' => 'Pendiente', 'approved' => 'Aprobado', 'rejected' => 'Rechazado', 'revoked' => 'Revocado'] as $value => $label)
          <option value="{{ $value }}" @selected($status===$value)>{{ $label }}</option>
        @endforeach
      </select>
      @error('status')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
    </div>
  </form>

  @if ($errors->any())
    <x-ui.alert tone="error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</x-ui.alert>
  @endif
  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <div class="card p-0">
    <div class="table-shell">
      <table class="table" role="region" aria-label="Solicitudes de personalización">
        <thead>
          <tr>
            <th>Admin</th>
            <th>Estado</th>
            <th>Expira</th>
            <th class="text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @forelse($requests as $req)
          @php
            $estado = $req->status;
            $expira = $req->approved_until?->format('Y-m-d H:i');
            $rowError = (string)old('req_id') === (string)$req->id;
          @endphp
          <tr>
            <td>
              <div>
                <p class="font-semibold text-slate-900">{{ optional($req->user)->name ?? '-' }}</p>
                <p class="text-xs text-slate-500">{{ optional($req->user)->email ?? '' }}</p>
                <p class="text-xs text-slate-400">Solicitado: {{ $req->created_at->format('Y-m-d H:i') }}</p>
              </div>
            </td>
            <td>
              @if($estado === 'approved')
                <span class="badge success">Aprobado</span>
              @elseif($estado === 'rejected')
                <span class="badge danger">Rechazado</span>
              @elseif($estado === 'revoked')
                <span class="badge warning">Revocado</span>
              @else
                <span class="badge info">Pendiente</span>
              @endif
              @if($req->notes)
                <div class="mt-2 text-xs text-slate-500">Nota: {{ $req->notes }}</div>
              @endif
            </td>
            <td>
              <span class="text-sm text-slate-600">{{ $expira ?? 'Sin vencimiento' }}</span>
            </td>
            <td>
              <div class="table-actions flex-col items-stretch">
                @if($estado === 'pending')
                  <form method="POST" action="{{ route('superadmin.solicitudes.personalizacion.aprobar', $req) }}" class="action-group">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="req_id" value="{{ $req->id }}">
                    <input type="number" name="duration_hours" min="1" max="720" value="24" class="form-input w-full sm:w-auto sm:max-w-[110px]" title="Horas" required>
                    @if($rowError)
                      @error('duration_hours')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
                    @endif
                    <label class="text-xs text-slate-500 flex items-center gap-2">
                      <input type="hidden" name="no_expire" value="0">
                      <input type="checkbox" name="no_expire" value="1"> Sin vencimiento
                    </label>
                    @if($rowError)
                      @error('no_expire')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
                    @endif
                    <button class="btn btn-primary" type="submit"><i class="ri-check-line"></i> Aprobar</button>
                  </form>
                  <form method="POST" action="{{ route('superadmin.solicitudes.personalizacion.rechazar', $req) }}" class="action-group">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="req_id" value="{{ $req->id }}">
                    <input type="text" name="notes" class="form-input w-full sm:w-auto sm:max-w-[220px]" placeholder="Motivo" required>
                    @if($rowError)
                      @error('notes')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
                    @endif
                    <button class="btn btn-outline" type="submit"><i class="ri-close-line"></i> Rechazar</button>
                  </form>
                @elseif($estado === 'approved')
                  <form method="POST" action="{{ route('superadmin.solicitudes.personalizacion.revocar', $req) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-outline" type="submit" onclick="return confirm('Revocar acceso de {{ $req->user->name }}');">
                      <i class="ri-forbid-line"></i> Revocar
                    </button>
                  </form>
                @else
                  <span class="text-xs text-slate-400">Sin acciones disponibles</span>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="4">
              <div class="p-6 text-center text-sm text-slate-500">No hay solicitudes registradas.</div>
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 text-sm text-slate-500">
      <div>
        @if ($requests->hasPages())
          Página {{ $requests->currentPage() }} de {{ $requests->lastPage() }}
        @else
          Mostrando {{ $requests->count() }} registros
        @endif
      </div>
      {!! $requests->withQueryString()->links() !!}
    </div>
  </div>
</div>
@endsection
