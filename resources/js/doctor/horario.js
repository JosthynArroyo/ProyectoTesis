import { populateHoursSelects, getClinicHoursConfig } from '../shared/horarios/schedule-common.js';

document.addEventListener('DOMContentLoaded', () => {
    const clinicConfig = getClinicHoursConfig();
    
    // 1. Single day edit/create form
    const dateInput = document.getElementById('fecha_single');
    const startSelect = document.getElementById('hora_inicio_single') || document.getElementById('hora_inicio');
    const endSelect = document.getElementById('hora_fin_single') || document.getElementById('hora_fin');
    const infoText = document.getElementById('clinic-hours-info-single') || document.getElementById('clinic-hours-info');
    
    const getInterval = () => {
        return parseInt(document.getElementById('intervalo_minutos')?.value || '30', 10);
    };

    if (dateInput && startSelect && endSelect) {
        const updateForDate = () => {
            const day = getIsoDayFromDateString(dateInput.value);
            if (day) {
                populateHoursSelects(day, startSelect, endSelect, infoText, clinicConfig, startSelect.dataset.old, endSelect.dataset.old, getInterval());
            }
        };
        dateInput.addEventListener('change', updateForDate);
        
        const intervalSelect = document.getElementById('intervalo_minutos');
        if (intervalSelect) {
            intervalSelect.addEventListener('change', updateForDate);
        }
        
        updateForDate();
    }

    // 2. Multi-day range generator
    const checkboxes = document.querySelectorAll('input[name="dias[]"]');
    const rangeStartSelect = document.getElementById('hora_inicio_global');
    const rangeEndSelect = document.getElementById('hora_fin_global');
    const rangeInfoText = document.getElementById('clinic-hours-info-global');

    if (checkboxes.length > 0 && rangeStartSelect && rangeEndSelect) {
        const updateForCheckboxes = () => {
            let maxOpening = null;
            let minClosing = null;
            let checkedCount = 0;
            let closedChecked = false;

            checkboxes.forEach(cb => {
                if (cb.checked) {
                    checkedCount++;
                    const dayNum = parseInt(cb.value, 10);
                    const dayConfig = clinicConfig[dayNum];
                    if (!dayConfig || String(dayConfig.status) !== '1') {
                        closedChecked = true;
                    } else {
                        if (maxOpening === null || dayConfig.opening > maxOpening) {
                            maxOpening = dayConfig.opening;
                        }
                        if (minClosing === null || dayConfig.closing < minClosing) {
                            minClosing = dayConfig.closing;
                        }
                    }
                }
            });

            if (checkedCount === 0) {
                rangeStartSelect.innerHTML = '<option value="">Selecciona días</option>';
                rangeEndSelect.innerHTML = '<option value="">Selecciona días</option>';
                rangeStartSelect.disabled = true;
                rangeEndSelect.disabled = true;
                if (rangeInfoText) rangeInfoText.textContent = 'Selecciona al menos un día.';
                return;
            }

            if (closedChecked) {
                rangeStartSelect.innerHTML = '<option value="">Día cerrado seleccionado</option>';
                rangeEndSelect.innerHTML = '<option value="">Día cerrado seleccionado</option>';
                rangeStartSelect.disabled = true;
                rangeEndSelect.disabled = true;
                if (rangeInfoText) rangeInfoText.textContent = 'Has seleccionado un día en que la clínica está cerrada.';
                return;
            }

            const mockDayNum = 99;
            clinicConfig[mockDayNum] = {
                status: '1',
                opening: maxOpening,
                closing: minClosing
            };
            populateHoursSelects(mockDayNum, rangeStartSelect, rangeEndSelect, rangeInfoText, clinicConfig, rangeStartSelect.dataset.old, rangeEndSelect.dataset.old, getInterval());
        };

        checkboxes.forEach(cb => cb.addEventListener('change', updateForCheckboxes));
        
        const intervalSelect = document.getElementById('intervalo_minutos');
        if (intervalSelect) {
            intervalSelect.addEventListener('change', updateForCheckboxes);
        }
        
        updateForCheckboxes();
    }
});

function getIsoDayFromDateString(dateStr) {
    if (!dateStr) return null;
    const parts = dateStr.split('-');
    if (parts.length !== 3) return null;
    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10);
    const d = parseInt(parts[2], 10);
    const date = new Date(y, m - 1, d);
    const jsDay = date.getDay();
    return jsDay === 0 ? 7 : jsDay;
}
