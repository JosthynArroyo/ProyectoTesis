import Datepicker from 'flowbite-datepicker/Datepicker';
import '../../../node_modules/flowbite-datepicker/dist/css/datepicker-bs5.min.css';

const spanishLocale = {
  days: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
  daysShort: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
  daysMin: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'],
  months: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
  monthsShort: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
  today: 'Hoy',
  clear: 'Limpiar',
  titleFormat: 'MM y',
};

Datepicker.locales = Datepicker.locales || {};
Datepicker.locales.es = Datepicker.locales.es || spanishLocale;

const pickers = new WeakMap();

function buildOptions(input) {
  const options = {
    autohide: true,
    format: 'yyyy-mm-dd',
    language: 'es',
    orientation: 'bottom auto',
    todayBtn: true,
    todayBtnMode: 1,
    weekStart: 1,
  };

  if (input.dataset.minDate) {
    options.minDate = input.dataset.minDate;
  }

  if (input.dataset.maxDate) {
    options.maxDate = input.dataset.maxDate;
  }

  if (input.dataset.defaultViewDate) {
    options.defaultViewDate = input.dataset.defaultViewDate;
  }

  return options;
}

function notifyDateChange(input) {
  input.dispatchEvent(new CustomEvent('enhanced-date:change', { bubbles: true }));
}

function bindTrigger(input, picker) {
  const triggerSelector = input.dataset.dateTrigger;
  if (!triggerSelector) {
    return;
  }

  const trigger = document.querySelector(triggerSelector);
  if (!trigger) {
    return;
  }

  trigger.addEventListener('click', (event) => {
    event.preventDefault();
    picker.show();
    input.focus();
  });
}

function initDatepicker(input) {
  if (!(input instanceof HTMLInputElement) || pickers.has(input)) {
    return;
  }

  const picker = new Datepicker(input, buildOptions(input));
  pickers.set(input, picker);

  input.addEventListener('changeDate', () => notifyDateChange(input));
  input.addEventListener('clearDate', () => notifyDateChange(input));
  input.addEventListener('keydown', (event) => {
    if (event.key !== 'ArrowDown') {
      return;
    }

    event.preventDefault();
    picker.show();
  });

  bindTrigger(input, picker);
}

export function initEnhancedDatepickers(root = document) {
  if (!root || typeof root.querySelectorAll !== 'function') {
    return;
  }

  root.querySelectorAll('[data-enhanced-date]').forEach((input) => initDatepicker(input));
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => initEnhancedDatepickers(), { once: true });
} else {
  initEnhancedDatepickers();
}
