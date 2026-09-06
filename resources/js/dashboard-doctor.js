const ROOT_SELECTOR = '[data-dashboard-page]';
const FORM_SELECTOR = '[data-dashboard-filters-form]';

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#039;',
})[char]);

const buildUrl = (endpoint, form) => {
  const url = new URL(endpoint, window.location.origin);
  const formData = new FormData(form);

  ['period', 'from', 'to'].forEach((key) => {
    const value = formData.get(key);
    if (value !== null && String(value).trim() !== '') {
      url.searchParams.set(key, value);
    }
  });

  return url;
};

const buildRow = (item) => {
  const estado = String(item.estado || '').toLowerCase();
  const estadoLabel = estado === 'no_se_presento'
    ? 'No se presento'
    : (estado ? estado.charAt(0).toUpperCase() + estado.slice(1) : 'Pendiente');
  const estadoTone = {
    pendiente: 'warning',
    confirmada: 'info',
    realizada: 'success',
    cancelada: 'danger',
    no_se_presento: 'danger',
  }[estado] || 'neutral';
  const prioridad = String(item.prioridad || 'BAJA').toUpperCase();
  const prioridadTone = {
    ALTA: 'danger',
    MEDIA: 'warning',
  }[prioridad] || 'neutral';

  return `
    <tr>
      <td data-label="Paciente">${escapeHtml(item.paciente || 'Paciente')}</td>
      <td data-label="Estado"><span class="badge ${estadoTone}">${escapeHtml(estadoLabel)}</span></td>
      <td data-label="Prioridad"><span class="badge ${prioridadTone}">${escapeHtml(prioridad)}</span>${item.red_flag ? ' <span class="badge danger">Red flag</span>' : ''}</td>
      <td data-label="Fecha">${escapeHtml(item.fecha_corta || '')}</td>
      <td data-label="Hora">${escapeHtml(item.hora_corta || '')}</td>
    </tr>
  `;
};

const refreshDashboard = async (root) => {
  if (document.hidden) {
    return;
  }
  const endpoint = root.dataset.dashboardEndpoint;
  const form = root.querySelector(FORM_SELECTOR);
  const tbody = document.getElementById('tbody-citas');

  if (!endpoint || !form || !tbody) {
    return;
  }

  try {
    const response = await fetch(buildUrl(endpoint, form), {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      cache: 'no-store',
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const payload = await response.json();
    const rows = Array.isArray(payload.citas) ? payload.citas : [];

    if (rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5">Sin citas para hoy.</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map(buildRow).join('');
  } catch (error) {
    console.error('Error refrescando el dashboard del doctor:', error);
  }
};

const boot = () => {
  document.querySelectorAll(ROOT_SELECTOR).forEach((root) => {
    refreshDashboard(root);

    const form = root.querySelector(FORM_SELECTOR);
    if (form) {
      form.addEventListener('submit', () => {
        window.setTimeout(() => refreshDashboard(root), 50);
      });
    }

    window.setInterval(() => refreshDashboard(root), 15000);
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) {
        refreshDashboard(root);
      }
    });
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
  boot();
}
