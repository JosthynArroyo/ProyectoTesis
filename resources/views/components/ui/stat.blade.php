@props([
    'label' => null,
    'value' => null,
    'tone' => 'teal',
])

@php
    $toneMap = [
        'teal' => 'bg-teal-100 text-teal-700',
        'sky' => 'bg-sky-100 text-sky-700',
        'amber' => 'bg-amber-100 text-amber-700',
        'rose' => 'bg-rose-100 text-rose-700',
        'slate' => 'bg-slate-100 text-slate-600',
    ];
    $toneClass = $toneMap[$tone] ?? $toneMap['slate'];
@endphp

<div {{ $attributes->merge(['class' => 'card p-5']) }}>
    <div class="flex items-start gap-4">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $toneClass }}">
            {{ $icon ?? '' }}
        </div>
        <div class="flex-1">
            @if($label)
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
            @endif
            @if($value)
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $value }}</p>
            @endif
            {{ $slot }}
        </div>
    </div>
</div>
