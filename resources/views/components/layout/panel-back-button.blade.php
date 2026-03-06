@props([
    'fallbackUrl' => '/',
    'sidebarRoutes' => [],
    'label' => 'Volver',
])

@php
    $currentRouteName = request()->route()?->getName();
    $shouldRender = $currentRouteName && !request()->routeIs(...$sidebarRoutes);
@endphp

@if($shouldRender)
    <div class="panel-back-anchor" data-panel-back-anchor data-panel-back-fallback="{{ $fallbackUrl }}">
        <button type="button" class="panel-back-button" data-panel-back-button aria-label="Volver a la vista anterior">
            <span class="panel-back-button__icon">
                <i class="ri-arrow-left-line"></i>
            </span>
            <span class="panel-back-button__label">{{ $label }}</span>
        </button>
    </div>
@endif
