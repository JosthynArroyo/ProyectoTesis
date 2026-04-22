@php
    $rowNumber = is_numeric($index) ? ((int) $index + 1) : '__NUMBER__';
@endphp

<article class="medical-editor__item" data-repeatable-item>
    <header>
        <h5>Alergia {{ $rowNumber }}</h5>
        <p>Registra el alergeno, el tipo de reaccion y su severidad.</p>
    </header>
    <div class="medical-editor__row">
        <div class="medical-field">
            <label class="form-label" for="allergies_{{ $index }}_allergen">Alergeno</label>
            <input id="allergies_{{ $index }}_allergen" class="form-input" name="allergies[{{ $index }}][allergen]" value="{{ $row['allergen'] ?? '' }}">
        </div>
        <div class="medical-field">
            <label class="form-label" for="allergies_{{ $index }}_reaction">Reaccion</label>
            <input id="allergies_{{ $index }}_reaction" class="form-input" name="allergies[{{ $index }}][reaction]" value="{{ $row['reaction'] ?? '' }}">
        </div>
        <div class="medical-field">
            <label class="form-label" for="allergies_{{ $index }}_severity">Severidad</label>
            <select id="allergies_{{ $index }}_severity" class="form-select" name="allergies[{{ $index }}][severity]">
                <option value="unknown" @selected(($row['severity'] ?? '') === 'unknown')>Sin severidad definida</option>
                <option value="mild" @selected(($row['severity'] ?? '') === 'mild')>Leve</option>
                <option value="moderate" @selected(($row['severity'] ?? '') === 'moderate')>Moderada</option>
                <option value="severe" @selected(($row['severity'] ?? '') === 'severe')>Severa</option>
            </select>
        </div>
        <div class="medical-field">
            <label class="form-label" for="allergies_{{ $index }}_status">Estado</label>
            <select id="allergies_{{ $index }}_status" class="form-select" name="allergies[{{ $index }}][status]">
                <option value="active" @selected(($row['status'] ?? '') === 'active')>Activa</option>
                <option value="resolved" @selected(($row['status'] ?? '') === 'resolved')>Resuelta</option>
            </select>
        </div>
        <div class="medical-field">
            <label class="form-label" for="allergies_{{ $index }}_noted_at">Fecha de registro</label>
            <input id="allergies_{{ $index }}_noted_at" class="form-input" type="date" name="allergies[{{ $index }}][noted_at]" value="{{ $row['noted_at'] ?? '' }}">
        </div>
        <div class="medical-field medical-field--full">
            <label class="form-label" for="allergies_{{ $index }}_notes">Observaciones clinicas</label>
            <textarea id="allergies_{{ $index }}_notes" class="form-textarea" rows="3" name="allergies[{{ $index }}][notes]">{{ $row['notes'] ?? '' }}</textarea>
        </div>
    </div>
</article>
