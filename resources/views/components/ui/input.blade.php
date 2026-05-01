@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'hint' => null,
])

@php
    $fieldId = $id ?? $name;
    $error = $name ? $errors->first($name) : null;
    $inputClass = 'form-input' . ($error ? ' border-rose-300 focus:border-rose-400 focus:ring-rose-200' : '');
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    @if($label)
        <label for="{{ $fieldId }}" class="form-label">{{ $label }}</label>
    @endif
    <input
        id="{{ $fieldId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($required) required @endif
        {{ $attributes->except('class') }}
        class="{{ $inputClass }}"
    >
    @if($hint)
        <p class="text-xs text-gray-500">{{ $hint }}</p>
    @endif
    @if($error)
        <p class="text-xs text-rose-600">{{ $error }}</p>
    @endif
</div>
