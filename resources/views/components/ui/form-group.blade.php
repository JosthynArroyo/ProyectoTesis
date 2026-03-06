@props(['cols' => 2])

@php
    $colClass = match((int) $cols) {
        1 => '',
        3 => 'form-grid--3',
        4 => 'form-grid--4',
        default => 'form-grid--2',
    };
@endphp

<div {{ $attributes->merge(['class' => "form-grid {$colClass}"]) }}>
    {{ $slot }}
</div>
