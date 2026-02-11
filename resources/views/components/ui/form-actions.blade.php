@props([
    'align' => 'between',
])

@php
  $justify = $align === 'start' ? 'justify-start' : 'justify-between';
@endphp

<div {{ $attributes->merge(['class' => "flex flex-wrap items-center {$justify} gap-3"]) }}>
  <div class="flex flex-wrap items-center gap-3">
    {{ $left ?? '' }}
  </div>
  <div class="flex flex-wrap items-center gap-3">
    {{ $slot }}
  </div>
</div>
