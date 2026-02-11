<!-- resources/views/layouts/admin.blade.php -->
<!DOCTYPE html>
<html lang="es" class="dashboard-root">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title','Panel Administrativo - Clínica Don Bosco')</title>
  @include('layouts.partials.favicon')
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2family=Sora:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css">
  <meta name="dashboard-resumen-url" content="{{ route('admin.dashboard.resumen') }}">
  @vite(['resources/css/app.css','resources/js/app.js'])
  @stack('head')
</head>
@php
  $hasRight = View::hasSection('right');
  $headerTitle = trim($__env->yieldContent('header-title')) ?: 'Administración';
  $headerSubtitle = trim($__env->yieldContent('header-subtitle')) ?: 'Gestión integral del sistema';
@endphp
<body class="min-h-screen text-slate-900 dashboard-shell">
<div class="min-h-screen lg:flex dashboard-layout">
  @include('admin.partials.sidebar')
  <div class="fixed inset-0 z-30 hidden bg-slate-900/50 backdrop-blur-sm lg:hidden" data-sidebar-overlay></div>

  <div class="flex min-h-screen flex-1 flex-col">
    <x-layout.dashboard-header :title="$headerTitle" :subtitle="$headerSubtitle" role="Administración" :profile-route="route('admin.perfil.edit')" />

    <div class="dashboard-content flex-1 px-4 pb-10 lg:px-8">
      @if($hasRight)
        <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
          <main class="space-y-6">@yield('main')</main>
          <aside class="space-y-4">@yield('right')</aside>
        </div>
      @else
        <main class="space-y-6">@yield('main')</main>
      @endif
    </div>
  </div>
</div>

@php
  $personalizacionStatus = app(\App\Services\FeatureAccessService::class)->status(auth()->user(), 'personalizacion');
  $personalizacionPending = $personalizacionStatus['pending'] ?? false;
  $personalizacionAutoOpen = session('open_personalizacion_modal');
@endphp

<div id="personalizacionModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="personalizacionTitle" aria-hidden="true" data-open-onload="{{ $personalizacionAutoOpen ? '1' : '0' }}">
  <div class="modal-backdrop" data-close-personalizacion></div>
  <div class="modal-dialog" role="document" tabindex="-1">
    <div class="card mx-auto w-full max-w-md">
      <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
        <h3 id="personalizacionTitle" class="text-lg font-semibold">Solicitar permiso para acceder a Personalización</h3>
        <button class="btn btn-ghost px-2" aria-label="Cerrar modal" data-close-personalizacion>
          <i class="ri-close-line text-lg"></i>
        </button>
      </div>
      <div class="p-6 space-y-4">
        <p class="text-sm text-slate-600">
          Para acceder a la sección de Personalización necesitas aprobación del superadmin.
        </p>
        @if($personalizacionPending)
          <x-ui.alert tone="warning">Ya enviaste una solicitud. Recibirás respuesta pronto.</x-ui.alert>
        @else
          <form method="POST" action="{{ route('admin.personalizacion.request') }}">
            @csrf
            <button class="btn btn-primary w-full" type="submit">
              <i class="ri-send-plane-2-line"></i> Solicitar
            </button>
          </form>
        @endif
      </div>
    </div>
  </div>
</div>

@stack('scripts')
@vite('resources/js/admin/personalizacion-modal.js')
</body>
</html>
