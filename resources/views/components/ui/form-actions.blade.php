@props([
    'align' => 'between',
])

@php
  $justify = $align === 'start' ? 'justify-start' : 'justify-between';
@endphp

<div {{ $attributes->merge(['class' => "form-actions flex flex-wrap items-center {$justify} gap-3", 'data-form-actions' => '1']) }}>
  <div class="flex flex-wrap items-center gap-3">
    {{ $left ?? '' }}
  </div>
  <div class="flex flex-wrap items-center gap-3">
    {{ $slot }}
  </div>
</div>
