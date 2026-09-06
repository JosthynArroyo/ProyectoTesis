@extends('layouts.superadmin')
@section('title', 'Respaldos de base de datos')
@section('header-title', 'Respaldos de base de datos')
@section('header-subtitle', 'Control de respaldos cifrados en Cloudflare R2')

@section('main')
@php
  $isDemo = app(\App\Services\ApplicationModeService::class)->isDemo();
@endphp
<div class="space-y-6">

  {{-- Alerts --}}
  @if (session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  @if (session('error'))
    <x-ui.alert tone="danger">{{ session('error') }}</x-ui.alert>
  @endif

  @if($isOverdue)
    <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 shadow-sm flex items-start gap-3">
      <i class="ri-alert-line text-2xl text-amber-600 shrink-0 mt-0.5"></i>
      <div>
        <h4 class="font-bold text-amber-900">Advertencia: No existe un respaldo reciente</h4>
        <p class="text-sm mt-0.5 text-amber-800">
          El último respaldo exitoso tiene más de 26 horas o aún no se ha completado ningún respaldo automático.
        </p>
      </div>
    </div>
  @endif

  @if($hasPendingOrProcessing)
    <div class="rounded-xl border border-blue-300 bg-blue-50 p-4 text-blue-900 shadow-sm flex items-start gap-3">
      <i class="ri-loader-4-line text-2xl text-blue-600 shrink-0 mt-0.5 animate-spin"></i>
      <div>
        <p class="text-sm mt-0.5 text-blue-800">
          Actualmente existe un trabajo de respaldo enviado a la cola que se encuentra pendiente o en procesamiento.
          Asegúrese de ejecutar el worker exclusivo: <code class="bg-blue-100 px-1 py-0.5 rounded text-xs font-mono">php artisan queue:work database_backups --queue=backups --sleep=1 --tries=3 --timeout=1850</code>
        </p>
      </div>
    </div>
  @endif

  {{-- Overview Cards --}}
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="card p-5 space-y-1">
      <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Último respaldo exitoso</span>
      <div class="text-lg font-bold text-gray-900">
        {{ $lastSuccessfulDate ? $lastSuccessfulDate->format('d/m/Y H:i:s') : 'Ninguno' }}
      </div>
      <span class="text-xs text-gray-500">Zona horaria: America/Guayaquil</span>
    </div>

    <div class="card p-5 space-y-1">
      <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Estado del sistema</span>
      <div class="flex items-center gap-2">
        @if(!$isOverdue)
          <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300">
            <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Al día
          </span>
        @else
          <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800 dark:bg-amber-950/80 dark:text-amber-300">
            <span class="h-2 w-2 rounded-full bg-amber-500"></span> Pendiente / Vencido
          </span>
        @endif
      </div>
      <span class="text-xs text-gray-500">Almacenamiento: Cloudflare R2</span>
    </div>

    <div class="card p-5 space-y-1">
      <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Cifrado de respaldos</span>
      <div class="text-lg font-bold text-emerald-700 flex items-center gap-2">
        <i class="ri-shield-keyhole-line text-emerald-600"></i> AES-256 Activo
      </div>
      <span class="text-xs text-gray-500">Protección antes del envío</span>
    </div>

    <div class="card p-5 space-y-1">
      <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Acciones</span>
      <div class="pt-1">
        <form
          method="POST"
          action="{{ route('superadmin.respaldos.store') }}"
          id="form-manual-backup"
          data-confirm-title="¿Crear respaldo manual?"
          data-confirm-message="Se generará una copia cifrada de la base de datos y se almacenará de forma privada en Cloudflare R2."
          data-confirm-consequence="El proceso se ejecutará en segundo plano y podrá consultarse desde esta misma sección."
          data-confirm-btn="Sí, crear respaldo"
          data-confirm-action="crear"
          data-confirm-variant="primary"
          data-action-lock-title="Programando respaldo..."
          data-action-lock-description="Espera mientras enviamos el respaldo a la cola de procesamiento."
        >
          @csrf
          <button type="submit" id="btn-create-backup" class="btn btn-primary w-full justify-center" @disabled($hasPendingOrProcessing)>
            <i class="ri-database-2-line"></i> Crear respaldo manual
          </button>
        </form>
      </div>
    </div>
  </div>

  {{-- Filters Card --}}
  <div class="card p-5">
    <form method="GET" action="{{ route('superadmin.respaldos.index') }}" class="grid gap-4 sm:grid-cols-4 items-end">
      <div>
        <label class="form-label text-xs uppercase" for="type">Tipo</label>
        <select class="form-select text-sm" name="type" id="type">
          <option value="">Todos</option>
          <option value="manual" @selected(($filters['type'] ?? '') === 'manual')>Manual</option>
          <option value="daily" @selected(($filters['type'] ?? '') === 'daily')>Diario</option>
          <option value="weekly" @selected(($filters['type'] ?? '') === 'weekly')>Semanal</option>
          <option value="monthly" @selected(($filters['type'] ?? '') === 'monthly')>Mensual</option>
        </select>
      </div>

      <div>
        <label class="form-label text-xs uppercase" for="status">Estado</label>
        <select class="form-select text-sm" name="status" id="status">
          <option value="">Todos</option>
          <option value="verified" @selected(($filters['status'] ?? '') === 'verified')>Verificado</option>
          <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Completado</option>
          <option value="processing" @selected(($filters['status'] ?? '') === 'processing')>Procesando</option>
          <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pendiente</option>
          <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>Fallido</option>
        </select>
      </div>

      <div>
        <label class="form-label text-xs uppercase" for="date">Fecha</label>
        <input class="form-input text-sm" type="date" name="date" id="date" value="{{ $filters['date'] ?? '' }}">
      </div>

      <div class="flex items-center gap-2">
        <button type="submit" class="btn btn-secondary w-full justify-center">
          <i class="ri-filter-3-line"></i> Filtrar
        </button>
        @if(!empty(array_filter($filters)))
          <a href="{{ route('superadmin.respaldos.index') }}" class="btn btn-ghost" title="Limpiar filtros">
            <i class="ri-refresh-line"></i>
          </a>
        @endif
      </div>
    </form>
  </div>

  {{-- Backups Table (Desktop) & Card Stack (Mobile) --}}
  <div class="card overflow-hidden">
    {{-- Desktop Table --}}
    <div class="hidden md:block overflow-x-auto">
      <table class="w-full text-left text-sm text-gray-600">
        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500 border-b border-gray-100">
          <tr>
            <th class="px-4 py-3">Tipo</th>
            <th class="px-4 py-3">Estado</th>
            <th class="px-4 py-3">Fecha (Guayaquil)</th>
            <th class="px-4 py-3">Tamaño</th>
            <th class="px-4 py-3">Duración</th>
            <th class="px-4 py-3">Solicitado por</th>
            <th class="px-4 py-3">SHA-256</th>
            <th class="px-4 py-3">Última Verificación</th>
            <th class="px-4 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse($backups as $backup)
            <tr class="hover:bg-gray-50/60 transition-colors">
              <td class="px-4 py-3 font-semibold text-gray-900 capitalize">
                @if($backup->type === 'manual')
                  <span class="inline-flex items-center gap-1 rounded bg-purple-50 px-2 py-0.5 text-xs text-purple-700 font-medium dark:bg-purple-950/80 dark:text-purple-300">
                    <i class="ri-user-setting-line"></i> Manual
                  </span>
                @else
                  <span class="inline-flex items-center gap-1 rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-700 font-medium dark:bg-blue-950/80 dark:text-blue-300">
                    <i class="ri-time-line"></i> {{ ucfirst($backup->type) }}
                  </span>
                @endif
              </td>

              <td class="px-4 py-3">
                @if($backup->status === 'verified')
                  <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300">
                    <i class="ri-checkbox-circle-line"></i> Verificado
                  </span>
                @elseif($backup->status === 'completed')
                  <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-950/80 dark:text-green-300">
                    <i class="ri-check-line"></i> Completado
                  </span>
                @elseif($backup->status === 'processing')
                  <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 animate-pulse dark:bg-amber-950/80 dark:text-amber-300">
                    <i class="ri-loader-4-line animate-spin"></i> Procesando...
                  </span>
                @elseif($backup->status === 'pending')
                  <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    <i class="ri-time-line"></i> Pendiente
                  </span>
                @else
                  <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-950/80 dark:text-rose-300" title="{{ $backup->error_message }}">
                    <i class="ri-error-warning-line"></i> Fallido
                  </span>
                @endif
              </td>

              <td class="px-4 py-3 whitespace-nowrap">
                {{ $backup->created_at ? $backup->created_at->timezone('America/Guayaquil')->format('d/m/Y H:i:s') : '-' }}
              </td>

              <td class="px-4 py-3 whitespace-nowrap font-mono text-xs">
                {{ $backup->formattedSize() }}
              </td>

              <td class="px-4 py-3 whitespace-nowrap text-xs">
                {{ $backup->duration_seconds ? $backup->duration_seconds . 's' : '-' }}
              </td>

              <td class="px-4 py-3 whitespace-nowrap">
                {{ $backup->user ? $backup->user->name : 'Sistema (Automático)' }}
              </td>

              <td class="px-4 py-3 whitespace-nowrap font-mono text-xs text-gray-500" title="{{ $backup->sha256 }}">
                {{ $backup->sha256 ? substr($backup->sha256, 0, 10) . '...' : '-' }}
              </td>

              <td class="px-4 py-3 whitespace-nowrap text-xs">
                {{ $backup->last_verified_at ? $backup->last_verified_at->timezone('America/Guayaquil')->format('d/m/Y H:i') : 'Nunca' }}
              </td>

              <td class="px-4 py-3 whitespace-nowrap text-right space-x-1">
                @if($backup->isCompleted())
                  @if($isDemo)
                    <button
                      type="button"
                      class="btn btn-xs btn-ghost text-blue-600 hover:text-blue-800"
                      title="Descargar respaldo cifrado"
                    >
                      <i class="ri-download-2-line text-sm"></i> Descargar
                    </button>

                    <button
                      type="button"
                      class="btn btn-xs btn-ghost text-emerald-600 hover:text-emerald-800"
                      title="Verificar integridad de archivo y descifrado"
                    >
                      <i class="ri-shield-check-line text-sm"></i> Verificar
                    </button>
                  @else
                    <a
                      href="{{ route('superadmin.respaldos.download', $backup->id) }}"
                      class="btn btn-xs btn-ghost text-blue-600 hover:text-blue-800"
                      title="Descargar respaldo cifrado"
                      download
                      data-action-lock-ignore
                      data-skip-page-loader
                    >
                      <i class="ri-download-2-line text-sm"></i> Descargar
                    </a>

                    <form method="POST" action="{{ route('superadmin.respaldos.verify', $backup->id) }}" class="inline">
                      @csrf
                      <button type="submit" class="btn btn-xs btn-ghost text-emerald-600 hover:text-emerald-800" title="Verificar integridad de archivo y descifrado">
                        <i class="ri-shield-check-line text-sm"></i> Verificar
                      </button>
                    </form>
                  @endif
                @else
                  <span class="text-xs text-gray-400 font-italic">No disponible</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                No se encontraron respaldos registrados.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Mobile Card Stack --}}
    <div class="md:hidden divide-y divide-gray-100">
      @forelse($backups as $backup)
        <article class="p-4 space-y-3" data-backup-card>
          <div class="flex items-center justify-between gap-2">
            <div>
              @if($backup->type === 'manual')
                <span class="inline-flex items-center gap-1 rounded bg-purple-50 px-2 py-0.5 text-xs text-purple-700 font-medium dark:bg-purple-950/80 dark:text-purple-300">
                  <i class="ri-user-setting-line"></i> Manual
                </span>
              @else
                <span class="inline-flex items-center gap-1 rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-700 font-medium dark:bg-blue-950/80 dark:text-blue-300">
                  <i class="ri-time-line"></i> {{ ucfirst($backup->type) }}
                </span>
              @endif
            </div>
            <div>
              @if($backup->status === 'verified')
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300">
                  <i class="ri-checkbox-circle-line"></i> Verificado
                </span>
              @elseif($backup->status === 'completed')
                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-950/80 dark:text-green-300">
                  <i class="ri-check-line"></i> Completado
                </span>
              @elseif($backup->status === 'processing')
                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 animate-pulse dark:bg-amber-950/80 dark:text-amber-300">
                  <i class="ri-loader-4-line animate-spin"></i> Procesando...
                </span>
              @elseif($backup->status === 'pending')
                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                  <i class="ri-time-line"></i> Pendiente
                </span>
              @else
                <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-950/80 dark:text-rose-300" title="{{ $backup->error_message }}">
                  <i class="ri-error-warning-line"></i> Fallido
                </span>
              @endif
            </div>
          </div>

          <div class="text-xs text-gray-500 flex items-center gap-1.5">
            <i class="ri-calendar-line"></i>
            <span>{{ $backup->created_at ? $backup->created_at->timezone('America/Guayaquil')->format('d/m/Y H:i:s') : '-' }}</span>
          </div>

          <dl class="grid grid-cols-2 gap-2 text-xs rounded-xl bg-gray-50 p-3 border border-gray-100">
            <div>
              <dt class="text-gray-500 uppercase font-medium">Tamaño</dt>
              <dd class="font-mono font-semibold text-gray-900 mt-0.5">{{ $backup->formattedSize() }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 uppercase font-medium">Duración</dt>
              <dd class="text-gray-900 mt-0.5">{{ $backup->duration_seconds ? $backup->duration_seconds . 's' : '-' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 uppercase font-medium">Solicitado por</dt>
              <dd class="text-gray-900 mt-0.5 truncate">{{ $backup->user ? $backup->user->name : 'Sistema (Automático)' }}</dd>
            </div>
            <div>
              <dt class="text-gray-500 uppercase font-medium">SHA-256</dt>
              <dd class="font-mono text-gray-500 truncate mt-0.5" title="{{ $backup->sha256 }}">{{ $backup->sha256 ? substr($backup->sha256, 0, 10) . '...' : '-' }}</dd>
            </div>
          </dl>

          <div class="flex items-center justify-end gap-2 pt-1 border-t border-gray-100">
            @if($backup->isCompleted())
              @if($isDemo)
                <button
                  type="button"
                  class="btn btn-sm btn-ghost text-blue-600 hover:text-blue-800"
                  title="Descargar respaldo cifrado"
                >
                  <i class="ri-download-2-line"></i> Descargar
                </button>

                <button
                  type="button"
                  class="btn btn-sm btn-ghost text-emerald-600 hover:text-emerald-800"
                  title="Verificar integridad de archivo y descifrado"
                >
                  <i class="ri-shield-check-line"></i> Verificar
                </button>
              @else
                <a
                  href="{{ route('superadmin.respaldos.download', $backup->id) }}"
                  class="btn btn-sm btn-ghost text-blue-600 hover:text-blue-800"
                  title="Descargar respaldo cifrado"
                  download
                  data-action-lock-ignore
                  data-skip-page-loader
                >
                  <i class="ri-download-2-line"></i> Descargar
                </a>

                <form method="POST" action="{{ route('superadmin.respaldos.verify', $backup->id) }}" class="inline">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-ghost text-emerald-600 hover:text-emerald-800" title="Verificar integridad de archivo y descifrado">
                    <i class="ri-shield-check-line"></i> Verificar
                  </button>
                </form>
              @endif
            @else
              <span class="text-xs text-gray-400 font-italic">No disponible</span>
            @endif
          </div>
        </article>
      @empty
        <div class="p-8 text-center text-gray-500">
          No se encontraron respaldos registrados.
        </div>
      @endforelse
    </div>

    @if($backups->hasPages())
      <div class="px-4 py-3 border-t border-gray-100 bg-gray-50">
        {{ $backups->links() }}
      </div>
    @endif
  </div>

</div>

@endsection
