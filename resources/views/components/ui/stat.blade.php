@props([
    'label' => null,
    'value' => null,
    'tone' => 'teal',
])

@php
    $toneMap = [
        'teal' => [
            'card' => 'stat-card--teal',
            'icon' => 'stat-card__icon--teal',
        ],
        'sky' => [
            'card' => 'stat-card--sky',
            'icon' => 'stat-card__icon--sky',
        ],
        'amber' => [
            'card' => 'stat-card--amber',
            'icon' => 'stat-card__icon--amber',
        ],
        'rose' => [
            'card' => 'stat-card--rose',
            'icon' => 'stat-card__icon--rose',
        ],
        'slate' => [
            'card' => 'stat-card--slate',
            'icon' => 'stat-card__icon--slate',
        ],
    ];
    $toneClasses = $toneMap[$tone] ?? $toneMap['slate'];
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

<div {{ $attributes->merge(['class' => 'card stat-card '.$toneClasses['card'].' p-5']) }}>
    <div class="flex items-start gap-4">
        <div class="stat-card__icon {{ $toneClasses['icon'] }} flex h-12 w-12 items-center justify-center rounded-2xl">
            {{ $icon ?? '' }}
        </div>
        <div class="flex-1">
            @if($label)
                <p class="stat-card__label text-xs uppercase tracking-wide text-gray-500">{{ $label }}</p>
            @endif
            @if($hasValue)
                <p class="stat-card__value mt-1 text-2xl font-semibold text-gray-900">{{ $displayValue }}</p>
            @endif
            {{ $slot }}
        </div>
    </div>
</div>
