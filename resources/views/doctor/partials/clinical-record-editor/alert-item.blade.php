@php
    $rowNumber = is_numeric($index) ? ((int) $index + 1) : '__NUMBER__';
@endphp

<article class="medical-editor__item" data-repeatable-item>
    <header>
        <h5>Alerta manual {{ $rowNumber }}</h5>
        <p>Utiliza esta seccion para riesgos, advertencias o contexto clinico relevante.</p>
    </header>
    <div class="medical-editor__row">
        <div class="medical-field">
            <label class="form-label" for="alerts_{{ $index }}_title">Titulo de la alerta</label>
            <input id="alerts_{{ $index }}_title" class="form-input" name="alerts[{{ $index }}][title]" value="{{ $row['title'] ?? '' }}">
        </div>
        <div class="medical-field">
            <label class="form-label" for="alerts_{{ $index }}_severity">Prioridad</label>
            <select id="alerts_{{ $index }}_severity" class="form-select" name="alerts[{{ $index }}][severity]">
                <option value="info" @selected(($row['severity'] ?? '') === 'info')>Informativa</option>
                <option value="warning" @selected(($row['severity'] ?? '') === 'warning')>Advertencia</option>
                <option value="high" @selected(($row['severity'] ?? '') === 'high')>Alta prioridad</option>
            </select>
        </div>
        <div class="medical-field">
            <label class="form-label" for="alerts_{{ $index }}_type">Tipo</label>
            <select id="alerts_{{ $index }}_type" class="form-select" name="alerts[{{ $index }}][type]">
                <option value="clinical" @selected(($row['type'] ?? '') === 'clinical')>Clinica</option>
                <option value="context" @selected(($row['type'] ?? '') === 'context')>Contexto</option>
            </select>
        </div>
        <div class="medical-field medical-field--checkbox">
            <label class="form-label" for="alerts_{{ $index }}_is_active">Estado</label>
            <label class="medical-checkbox">
                <input type="hidden" name="alerts[{{ $index }}][is_active]" value="0">
                <input id="alerts_{{ $index }}_is_active" type="checkbox" name="alerts[{{ $index }}][is_active]" value="1" @checked(($row['is_active'] ?? '0') === '1')>
                <span>Mantener alerta activa</span>
            </label>
        </div>
        <div class="medical-field medical-field--full">
            <label class="form-label" for="alerts_{{ $index }}_description">Descripcion de la alerta</label>
            <textarea id="alerts_{{ $index }}_description" class="form-textarea" rows="3" name="alerts[{{ $index }}][description]">{{ $row['description'] ?? '' }}</textarea>
        </div>
    </div>
</article>
