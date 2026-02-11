@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'required' => false,
    'hint' => null,
])

@php
    $fieldId = $id ?? $name;
    $error = $name ? $errors->first($name) : null;
    $selectClass = 'form-select' . ($error ? ' border-rose-300 focus:border-rose-400 focus:ring-rose-200' : '');
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    @if($label)
        <label for="{{ $fieldId }}" class="form-label">{{ $label }}</label>
    @endif
    <select
        id="{{ $fieldId }}"
        name="{{ $name }}"
        @if($required) required @endif
        {{ $attributes->except('class') }}
        class="{{ $selectClass }}"
    >
        {{ $slot }}
    </select>
    @if($hint)
        <p class="text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @if($error)
        <p class="text-xs text-rose-600">{{ $error }}</p>
    @endif
</div>
