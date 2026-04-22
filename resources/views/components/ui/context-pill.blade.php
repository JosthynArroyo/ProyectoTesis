@props([
    'label' => null,
])

<div {{ $attributes->merge(['class' => 'context-pill']) }}>
    @if(isset($icon))
        <span class="context-pill__icon">{{ $icon }}</span>
    @endif

    <div class="context-pill__body">
        @if($label)
            <span class="context-pill__label">{{ $label }}</span>
        @endif

        @if(trim((string) $slot) !== '')
            <span class="context-pill__text">{{ $slot }}</span>
        @endif
    </div>
</div>
