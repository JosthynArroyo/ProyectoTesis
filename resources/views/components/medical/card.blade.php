@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'tone' => 'default',
])

@php
    $toneClass = [
        'default' => 'medical-card--default',
        'danger' => 'medical-card--danger',
        'info' => 'medical-card--info',
        'success' => 'medical-card--success',
        'warning' => 'medical-card--warning',
    ][$tone] ?? 'medical-card--default';
@endphp

<article {{ $attributes->class(['medical-card', $toneClass]) }}>
    @if($title || $subtitle || isset($actions))
        <header class="medical-card__header">
            <div class="medical-card__heading">
                @if($icon)
                    <span class="medical-card__icon-wrap" aria-hidden="true">
                        <i class="{{ $icon }}"></i>
                    </span>
                @endif
                <div>
                    @if($title)
                        <h3 class="medical-card__title">{{ $title }}</h3>
                    @endif
                    @if($subtitle)
                        <p class="medical-card__subtitle">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>
            @isset($actions)
                <div class="medical-card__actions">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="medical-card__content">
        {{ $slot }}
    </div>
</article>
