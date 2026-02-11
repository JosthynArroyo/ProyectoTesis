@props([
    'tone' => 'neutral',
    'title' => null,
])

@php
    $tones = [
        'success' => 'alert success',
        'error' => 'alert error',
        'warning' => 'alert warning',
        'neutral' => 'alert',
    ];
    $classes = $tones[$tone] ?? $tones['neutral'];
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    @isset($icon)
        <div class="mt-0.5 text-lg">{{ $icon }}</div>
    @endisset
    <div>
        @if($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div class="text-sm">{{ $slot }}</div>
    </div>
</div>
