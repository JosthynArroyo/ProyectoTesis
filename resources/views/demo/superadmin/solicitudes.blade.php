@extends('layouts.demo')
@section('title', 'Solicitudes | Demo')
@section('header-title', 'Solicitudes')
@section('header-subtitle', 'Permisos de acceso a personalización')

@section('sidebar')
    @include('demo.partials.sidebar-superadmin-demo')
@endsection

@section('main')
<div class="space-y-6">
  <form class="card p-5" method="GET" action="{{ route('demo.superadmin.solicitudes') }}">
    <div class="flex flex-wrap items-center gap-4">
      <select class="form-select" name="status" onchange="this.form.submit()" required>
        <option value="all" @selected($status==='' || $status==='all')>Todos los estados</option>
        @foreach(['pending' => 'Pendiente', 'approved' => 'Aprobado', 'expired' => 'Expirado', 'rejected' => 'Rechazado', 'revoked' => 'Revocado'] as $value => $label)
          <option value="{{ $value }}" @selected($status===$value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
  </form>

  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <div class="card p-0">
    <div class="table-shell table-responsive-cards">
      <table class="table" role="region" aria-label="Solicitudes de personalización">
        <thead>
          <tr>
            <th class="text-center">Admin</th>
            <th class="text-center">Estado</th>
            <th class="text-center">Expira</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @forelse($requests as $req)
          @php
            $estado = $req['status'];
            $isExpired = ($estado === 'expired');
            $canRevoke = ($estado === 'approved');
            $expira = $req['expiry'];
          @endphp
          <tr>
            <td class="text-center align-middle" data-label="Admin">
              <div class="mx-auto text-center">
                <p class="font-semibold text-gray-900">{{ $req['admin'] }}</p>
                <p class="text-xs text-gray-500">{{ $req['email'] }}</p>
                <p class="text-xs text-gray-400">Solicitado: {{ $req['created_at'] instanceof \Carbon\Carbon ? $req['created_at']->format('Y-m-d H:i') : $req['created_at'] }}</p>
              </div>
            </td>
            <td class="text-center align-middle" data-label="Estado">
              @if($isExpired)
                <span class="badge warning">Expirado</span>
              @elseif($estado === 'approved')
                <span class="badge success">Aprobado</span>
              @elseif($estado === 'rejected')
                <span class="badge danger">Rechazado</span>
              @elseif($estado === 'revoked')
                <span class="badge warning">Revocado</span>
              @else
                <span class="badge info">Pendiente</span>
              @endif
              @if($req['notes'])
                <div class="mt-2 text-center text-xs text-gray-500">Nota: {{ $req['notes'] }}</div>
              @endif
            </td>
            <td class="text-center align-middle" data-label="Expira">
              <span class="text-sm text-gray-600">{{ $expira ?? 'Sin vencimiento' }}</span>
            </td>
            <td class="text-center align-middle" data-label="Acciones">
              <div class="table-actions flex-col items-center justify-center min-w-0">
                @if($estado === 'pending')
                  <form method="GET" action="{{ route('demo.superadmin.solicitudes') }}" class="action-group justify-center">
                    <input type="hidden" name="simulated_action" value="approved">
                    <input type="hidden" name="req_id" value="{{ $req['id'] }}">
                    <input type="number" name="duration_hours" min="1" max="720" value="24" class="form-input w-full sm:w-auto sm:max-w-[110px]" title="Horas" required>
                    <label class="flex items-center justify-center gap-2 text-xs text-gray-500">
                      <input type="hidden" name="no_expire" value="0">
                      <input type="checkbox" name="no_expire" value="1" onchange="this.form.querySelector('[name=duration_hours]').disabled = this.checked"> Sin vencimiento
                    </label>
                    <button class="btn btn-primary" type="submit"><i class="ri-check-line"></i> Aprobar</button>
                  </form>
                  <form method="GET" action="{{ route('demo.superadmin.solicitudes') }}" class="action-group justify-center">
                    <input type="hidden" name="simulated_action" value="rejected">
                    <input type="hidden" name="req_id" value="{{ $req['id'] }}">
                    <input type="text" name="notes" class="form-input w-full sm:w-auto sm:max-w-[220px]" placeholder="Motivo" required>
                    <button class="btn btn-outline" type="submit"><i class="ri-close-line"></i> Rechazar</button>
                  </form>
                @elseif($canRevoke)
                  <form method="GET" action="{{ route('demo.superadmin.solicitudes') }}">
                    <input type="hidden" name="simulated_action" value="revoked">
                    <input type="hidden" name="req_id" value="{{ $req['id'] }}">
                    <button class="btn btn-outline" type="submit" data-confirm-title="Revocar permiso" data-confirm-message="¿Estás seguro de que deseas revocar el acceso de {{ $req['admin'] }}?" data-confirm-action="revocar" data-confirm-btn="Sí, revocar">
                      <i class="ri-forbid-line"></i> Revocar
                    </button>
                  </form>
                @elseif($isExpired)
                  <span class="text-xs font-medium text-amber-600">Acceso expirado</span>
                @else
                  <span class="text-xs text-gray-400">Sin acciones disponibles</span>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="4">
              <div class="p-6 text-center text-sm text-gray-500">No hay solicitudes registradas.</div>
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 text-sm text-gray-500">
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
