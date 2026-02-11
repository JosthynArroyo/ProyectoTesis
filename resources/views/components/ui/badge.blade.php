@props(['tone' => 'neutral'])

@php
    $tones = [
        'success' => 'badge success',
        'warning' => 'badge warning',
        'danger' => 'badge danger',
        'info' => 'badge info',
        'neutral' => 'badge neutral',
    ];
    $classes = $tones[$tone] ?? $tones['neutral'];
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</span>
