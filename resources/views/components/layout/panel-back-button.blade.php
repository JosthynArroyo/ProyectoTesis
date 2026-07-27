@props([
    'fallbackUrl' => '/',
    'sidebarRoutes' => [],
    'label' => 'Volver',
    'force' => false,
])

@php
    $currentRouteName = request()->route()?->getName();
    $shouldRender = $force || ($currentRouteName && !request()->routeIs(...$sidebarRoutes));
@endphp

@if($shouldRender)
    <div
        class="panel-back-anchor"
        data-panel-back-anchor
        data-panel-back-fallback="{{ $fallbackUrl }}"
        data-action-lock-nav-title="Cargando sección..."
        data-action-lock-nav-description="Por favor, espera mientras cargamos esta sección."
    >
        <button type="button" class="panel-back-button" data-panel-back-button aria-label="Volver a la vista anterior">
            <span class="panel-back-button__icon">
                <i class="ri-arrow-left-line"></i>
            </span>
            <span class="panel-back-button__label">{{ $label }}</span>
        </button>
        <div class="panel-back-anchor__extras" data-panel-back-extras></div>
    </div>
@endif
