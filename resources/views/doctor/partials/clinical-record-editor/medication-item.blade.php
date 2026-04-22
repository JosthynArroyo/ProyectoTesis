@php
    $rowNumber = is_numeric($index) ? ((int) $index + 1) : '__NUMBER__';
@endphp

<article class="medical-editor__item" data-repeatable-item>
    <header>
        <h5>Medicacion {{ $rowNumber }}</h5>
        <p>Indica nombre, dosis, frecuencia, via y vigencia.</p>
    </header>
    <div class="medical-editor__row">
        <div class="medical-field">
            <label class="form-label" for="medications_{{ $index }}_name">Medicamento</label>
            <input id="medications_{{ $index }}_name" class="form-input" name="medications[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}">
        </div>
        <div class="medical-field">
            <label class="form-label" for="medications_{{ $index }}_presentation">Presentacion</label>
            <input id="medications_{{ $index }}_presentation" class="form-input" name="medications[{{ $index }}][presentation]" value="{{ $row['presentation'] ?? '' }}">
        </div>
        <div class="medical-field">
            <label class="form-label" for="medications_{{ $index }}_dosage">Dosis</label>
            <input id="medications_{{ $index }}_dosage" class="form-input" name="medications[{{ $index }}][dosage]" value="{{ $row['dosage'] ?? '' }}">
        </div>
        <div class="medical-field">
            <label class="form-label" for="medications_{{ $index }}_frequency">Frecuencia</label>
            <input id="medications_{{ $index }}_frequency" class="form-input" name="medications[{{ $index }}][frequency]" value="{{ $row['frequency'] ?? '' }}">
        </div>
        <div class="medical-field">
            <label class="form-label" for="medications_{{ $index }}_route">Via de administracion</label>
            <input id="medications_{{ $index }}_route" class="form-input" name="medications[{{ $index }}][route]" value="{{ $row['route'] ?? '' }}">
        </div>
        <div class="medical-field">
            <label class="form-label" for="medications_{{ $index }}_status">Estado</label>
            <select id="medications_{{ $index }}_status" class="form-select" name="medications[{{ $index }}][status]">
                <option value="active" @selected(($row['status'] ?? '') === 'active')>Activa</option>
                <option value="suspended" @selected(($row['status'] ?? '') === 'suspended')>Suspendida</option>
                <option value="completed" @selected(($row['status'] ?? '') === 'completed')>Finalizada</option>
            </select>
        </div>
        <div class="medical-field">
            <label class="form-label" for="medications_{{ $index }}_started_at">Fecha de inicio</label>
            <input id="medications_{{ $index }}_started_at" class="form-input" type="date" name="medications[{{ $index }}][started_at]" value="{{ $row['started_at'] ?? '' }}">
        </div>
        <div class="medical-field">
            <label class="form-label" for="medications_{{ $index }}_ended_at">Fecha de cierre</label>
            <input id="medications_{{ $index }}_ended_at" class="form-input" type="date" name="medications[{{ $index }}][ended_at]" value="{{ $row['ended_at'] ?? '' }}">
        </div>
        <div class="medical-field medical-field--full">
            <label class="form-label" for="medications_{{ $index }}_instructions">Indicaciones clinicas</label>
            <textarea id="medications_{{ $index }}_instructions" class="form-textarea" rows="3" name="medications[{{ $index }}][instructions]">{{ $row['instructions'] ?? '' }}</textarea>
        </div>
    </div>
</article>
