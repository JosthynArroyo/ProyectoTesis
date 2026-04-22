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
    $hasValue = !is_null($value) && $value !== '';
    $displayValue = $value;

    if ($hasValue && is_numeric($value)) {
        $displayValue = number_format(
            (float) $value,
            floor((float) $value) === (float) $value ? 0 : 2,
            ',',
            '.',
        );
    }
@endphp

<div {{ $attributes->merge(['class' => 'card stat-card p-5']) }}>
    <div class="flex items-start gap-4">
        <div class="stat-card__icon flex h-12 w-12 items-center justify-center rounded-2xl {{ $toneClass }}">
            {{ $icon ?? '' }}
        </div>
        <div class="flex-1">
            @if($label)
                <p class="stat-card__label text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
            @endif
            @if($hasValue)
                <p class="stat-card__value mt-1 text-2xl font-semibold text-slate-900">{{ $displayValue }}</p>
            @endif
            {{ $slot }}
        </div>
    </div>
</div>
