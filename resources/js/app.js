import '../css/app.css';
import './bootstrap';
import './action-lock';
import './global-confirm-modal';

const hasElement = (selector) => document.querySelector(selector) !== null;

const loadModule = (shouldLoad, importer, warning) => {
  if (!shouldLoad()) {
    return;
  }

  importer().catch((error) => {
    console.warn(warning, error);
  });
};

const loadFlowbite = () => {
  if (!hasElement('[data-dropdown-toggle], [data-modal-toggle], [data-collapse-toggle], [data-tooltip-target]')) {
    return;
  }

  import('flowbite')
    .then((module) => {
      if (typeof module.initFlowbite === 'function') {
        module.initFlowbite();
      }
    })
    .catch((error) => {
      console.warn('No se pudo inicializar Flowbite.', error);
    });
};



const loadDatepickers = () => {
  if (!document.querySelector('[data-enhanced-date]')) {
    return;
  }

  import('./forms/datepickers').catch((error) => {
    console.warn('No se pudo inicializar el datepicker mejorado.', error);
  });
};

const loadNativeDatePickers = () => {
  if (!hasElement('[data-native-date-open], input[type="date"], input[type="datetime-local"], input[type="month"]')) {
    return;
  }

  import('./forms/native-date-picker').catch((error) => {
    console.warn('No se pudo inicializar el selector nativo de fechas.', error);
  });
};

const syncDashboardHeaderHeight = () => {
  const root = document.documentElement;
  const header = document.querySelector('[data-dashboard-topbar]');

  if (!root.classList.contains('dashboard-root') || !header) {
    return;
  }

  const updateHeaderHeight = () => {
    const height = Math.ceil(header.getBoundingClientRect().height);
    if (height > 0) {
      root.style.setProperty('--dashboard-header-height', `${height}px`);
    }
  };

  updateHeaderHeight();

  if (header.dataset.heightSyncReady === '1') {
    return;
  }

  header.dataset.heightSyncReady = '1';

  if ('ResizeObserver' in window) {
    const observer = new ResizeObserver(() => updateHeaderHeight());
    observer.observe(header);
  }

  window.addEventListener('resize', updateHeaderHeight);
  window.addEventListener('load', updateHeaderHeight, { once: true });
  requestAnimationFrame(updateHeaderHeight);
};

const bootDeferredModules = () => {
  syncDashboardHeaderHeight();
  loadModule(
    () => hasElement('[data-legal-modal], [data-legal-open]'),
    () => import('./legal-modals'),
    'No se pudieron inicializar los modales legales.'
  );
  loadModule(
    () => hasElement('[data-kebab]'),
    () => import('./ui/kebab-menus'),
    'No se pudieron inicializar los menus de acciones.'
  );
  loadFlowbite();
  loadModule(
    () => hasElement('#cnav-header'),
    () => import('./navbar'),
    'No se pudo inicializar la navegacion publica.'
  );
  loadModule(
    () => hasElement('#loginModal'),
    () => import('./welcome-login-modal'),
    'No se pudo inicializar el modal de ingreso.'
  );
  loadModule(
    () => hasElement('aside.dashboard-sidebar, [data-open-sidebar], #menu_bar'),
    () => import('./sidebar-toggle'),
    'No se pudo inicializar el menu lateral.'
  );
  loadModule(
    () => hasElement('[data-carousel]'),
    () => import('./welcome-carousel'),
    'No se pudo inicializar el carrusel principal.'
  );
  loadModule(
    () => hasElement('.toggle-eye, .btn-eye'),
    () => import('./auth/password-toggle'),
    'No se pudo inicializar el control de contrasenas.'
  );
  loadModule(
    () => hasElement('[data-remember-login-form]'),
    () => import('./auth/remember-login'),
    'No se pudo inicializar recordar acceso.'
  );
  loadModule(
    () => hasElement('[data-logout-trigger]'),
    () => import('./auth/logout'),
    'No se pudo inicializar el cierre de sesion.'
  );
  loadModule(
    () => hasElement('[data-panel-back-anchor]'),
    () => import('./panel-back-button'),
    'No se pudo inicializar volver del panel.'
  );
  loadModule(
    () => hasElement('[data-document-fields-wrap]'),
    () => import('./forms/document-fields'),
    'No se pudieron inicializar los campos de documento.'
  );
  loadModule(
    () => hasElement('input[data-digits]'),
    () => import('./forms/numeric-inputs'),
    'No se pudieron inicializar los campos numericos.'
  );
  loadModule(
    () => hasElement('[data-motivo-chip]'),
    () => import('./forms/motivo-chips'),
    'No se pudieron inicializar las sugerencias de motivo.'
  );
  loadModule(
    () => hasElement('table'),
    () => import('./table-responsive-x'),
    'No se pudo inicializar el ajuste responsive de tablas.'
  );
  loadModule(
    () => hasElement('[data-dashboard-page]'),
    () => import('./dashboard-charts'),
    'No se pudieron inicializar las graficas del dashboard.'
  );
  loadModule(
    () => hasElement('[data-auto-submit]'),
    () => import('./forms/auto-submit'),
    'No se pudo inicializar el auto-submit de filtros.'
  );
  loadDatepickers();
  loadNativeDatePickers();

};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootDeferredModules, { once: true });
} else {
  bootDeferredModules();
}
