@props([
    'field' => 'fecha_nacimiento',
    'label' => 'Fecha de nacimiento',
    'value' => null,
    'required' => false,
    'help' => null,
])

@php
    $parts = \App\Support\DateField::parts(old($field, $value));

    $dayValue = old($field.'_day', $parts['day']);
    $monthValue = old($field.'_month', $parts['month']);
    $yearValue = old($field.'_year', $parts['year']);

    $errorMessage = $errors->first($field)
        ?: $errors->first($field.'_day')
        ?: $errors->first($field.'_month')
        ?: $errors->first($field.'_year');

    $helpId = $help ? $field.'_help' : null;
    $errorId = $errorMessage ? $field.'_error' : null;
    $describedBy = collect([$helpId, $errorId])->filter()->implode(' ');
@endphp

<div {{ $attributes->merge(['class' => 'date-parts']) }}>
    <div class="date-parts__legend">
        <label class="form-label">{{ $label }}</label>
        @if($help)
            <p class="date-parts__help" id="{{ $helpId }}">{{ $help }}</p>
        @endif
    </div>

    <div class="form-grid form-grid--3 date-parts__grid">
        <div class="date-parts__field">
            <label class="form-label" for="{{ $field }}_day">D&iacute;a</label>
            <input
                class="form-input"
                id="{{ $field }}_day"
                name="{{ $field }}_day"
                type="text"
                inputmode="numeric"
                pattern="\d{1,2}"
                maxlength="2"
                autocomplete="bday-day"
                placeholder="DD"
                value="{{ $dayValue }}"
                @if($required) required @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            >
        </div>
        <div class="date-parts__field">
            <label class="form-label" for="{{ $field }}_month">Mes</label>
            <input
                class="form-input"
                id="{{ $field }}_month"
                name="{{ $field }}_month"
                type="text"
                inputmode="numeric"
                pattern="\d{1,2}"
                maxlength="2"
                autocomplete="bday-month"
                placeholder="MM"
                value="{{ $monthValue }}"
                @if($required) required @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            >
        </div>
        <div class="date-parts__field">
            <label class="form-label" for="{{ $field }}_year">A&ntilde;o</label>
            <input
                class="form-input"
                id="{{ $field }}_year"
                name="{{ $field }}_year"
                type="text"
                inputmode="numeric"
                pattern="\d{4}"
                maxlength="4"
                autocomplete="bday-year"
                placeholder="AAAA"
                value="{{ $yearValue }}"
                @if($required) required @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            >
        </div>
    </div>

    @if($errorMessage)
        <div class="text-xs text-rose-600" id="{{ $errorId }}">{{ $errorMessage }}</div>
    @endif
</div>
