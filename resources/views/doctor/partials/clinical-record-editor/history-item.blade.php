@php
    $rowNumber = is_numeric($index) ? ((int) $index + 1) : '__NUMBER__';
@endphp

<article class="medical-editor__item" data-repeatable-item>
    <header>
        <h5>{{ $itemTitle }} {{ $rowNumber }}</h5>
        <p>Completa el titulo, la descripcion y la fecha si aplica.</p>
    </header>
    <div class="medical-editor__row">
        <div class="medical-field">
            <label class="form-label" for="{{ $field }}_{{ $index }}_title">Titulo</label>
            <input id="{{ $field }}_{{ $index }}_title" class="form-input" name="{{ $field }}[{{ $index }}][title]" value="{{ $row['title'] ?? '' }}">
        </div>
        @if($showRelation)
            <div class="medical-field">
                <label class="form-label" for="{{ $field }}_{{ $index }}_relation">Parentesco</label>
                <input id="{{ $field }}_{{ $index }}_relation" class="form-input" name="{{ $field }}[{{ $index }}][relation_label]" value="{{ $row['relation_label'] ?? '' }}">
            </div>
        @endif
        <div class="medical-field">
            <label class="form-label" for="{{ $field }}_{{ $index }}_occurred_on">Fecha</label>
            <input id="{{ $field }}_{{ $index }}_occurred_on" class="form-input" type="date" name="{{ $field }}[{{ $index }}][occurred_on]" value="{{ $row['occurred_on'] ?? '' }}">
        </div>
        <div class="medical-field medical-field--full">
            <label class="form-label" for="{{ $field }}_{{ $index }}_description">Descripcion</label>
            <textarea id="{{ $field }}_{{ $index }}_description" class="form-textarea" rows="3" name="{{ $field }}[{{ $index }}][description]">{{ $row['description'] ?? '' }}</textarea>
        </div>
        <div class="medical-field medical-field--full">
            <label class="form-label" for="{{ $field }}_{{ $index }}_notes">Observaciones</label>
            <textarea id="{{ $field }}_{{ $index }}_notes" class="form-textarea" rows="3" name="{{ $field }}[{{ $index }}][notes]">{{ $row['notes'] ?? '' }}</textarea>
        </div>
    </div>
</article>
