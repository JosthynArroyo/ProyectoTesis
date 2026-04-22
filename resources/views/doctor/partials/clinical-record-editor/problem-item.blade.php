@php
    $rowNumber = is_numeric($index) ? ((int) $index + 1) : '__NUMBER__';
@endphp

<article class="medical-editor__item" data-repeatable-item>
    <header>
        <h5>Problema clinico {{ $rowNumber }}</h5>
        <p>Estado, cronologia y observaciones del problema longitudinal.</p>
    </header>
    <div class="medical-editor__row">
        <div class="medical-field">
            <label class="form-label" for="problems_{{ $index }}_name">Problema o diagnostico</label>
            <input id="problems_{{ $index }}_name" class="form-input" name="problems[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}">
        </div>
        <div class="medical-field">
            <label class="form-label" for="problems_{{ $index }}_cie10">Codigo CIE-10</label>
            <input id="problems_{{ $index }}_cie10" class="form-input" name="problems[{{ $index }}][cie10]" value="{{ $row['cie10'] ?? '' }}">
        </div>
        <div class="medical-field">
            <label class="form-label" for="problems_{{ $index }}_status">Estado</label>
            <select id="problems_{{ $index }}_status" class="form-select" name="problems[{{ $index }}][status]">
                <option value="active" @selected(($row['status'] ?? '') === 'active')>Activo</option>
                <option value="monitoring" @selected(($row['status'] ?? '') === 'monitoring')>En seguimiento</option>
                <option value="resolved" @selected(($row['status'] ?? '') === 'resolved')>Resuelto</option>
            </select>
        </div>
        <div class="medical-field medical-field--checkbox">
            <label class="form-label" for="problems_{{ $index }}_is_chronic">Curso cronico</label>
            <label class="medical-checkbox">
                <input type="hidden" name="problems[{{ $index }}][is_chronic]" value="0">
                <input id="problems_{{ $index }}_is_chronic" type="checkbox" name="problems[{{ $index }}][is_chronic]" value="1" @checked(($row['is_chronic'] ?? '0') === '1')>
                <span>Marcar como problema cronico</span>
            </label>
        </div>
        <div class="medical-field">
            <label class="form-label" for="problems_{{ $index }}_started_at">Fecha de inicio</label>
            <input id="problems_{{ $index }}_started_at" class="form-input" type="date" name="problems[{{ $index }}][started_at]" value="{{ $row['started_at'] ?? '' }}">
        </div>
        <div class="medical-field">
            <label class="form-label" for="problems_{{ $index }}_resolved_at">Fecha de resolucion</label>
            <input id="problems_{{ $index }}_resolved_at" class="form-input" type="date" name="problems[{{ $index }}][resolved_at]" value="{{ $row['resolved_at'] ?? '' }}">
        </div>
        <div class="medical-field medical-field--full">
            <label class="form-label" for="problems_{{ $index }}_notes">Observaciones clinicas</label>
            <textarea id="problems_{{ $index }}_notes" class="form-textarea" rows="3" name="problems[{{ $index }}][notes]">{{ $row['notes'] ?? '' }}</textarea>
        </div>
    </div>
</article>
