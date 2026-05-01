document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('form.form');
  if (!form) {
    return;
  }

  form.addEventListener('submit', () => {
    form.querySelectorAll('input, select, textarea').forEach((el) => {
      if (!el.checkValidity()) {
        el.setAttribute('aria-invalid', 'true');
      } else {
        el.removeAttribute('aria-invalid');
      }
    });
  });

  form.addEventListener('input', (event) => {
    const element = event.target;
    if (element && 'checkValidity' in element && element.checkValidity()) {
      element.removeAttribute('aria-invalid');
    }
  });

  const emailInput = form.querySelector('#email');
  const emailStatus = form.querySelector('[data-email-availability]');
  const checkUrl = form.dataset.emailCheckUrl || '';
  if (!emailInput || !emailStatus || !checkUrl) {
    return;
  }

  let timer = null;
  let requestId = 0;

  const renderStatus = (message, tone = 'neutral') => {
    emailStatus.textContent = message || '';
    emailStatus.classList.remove('text-gray-500', 'text-emerald-700', 'text-rose-600');
    if (tone === 'success') {
      emailStatus.classList.add('text-emerald-700');
      return;
    }
    if (tone === 'error') {
      emailStatus.classList.add('text-rose-600');
      return;
    }
    emailStatus.classList.add('text-gray-500');
  };

  const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

  const checkEmail = async (value, localRequestId) => {
    try {
      const url = new URL(checkUrl, window.location.origin);
      url.searchParams.set('email', value);
      const response = await fetch(url.toString(), {
        headers: {
          Accept: 'application/json',
        },
      });
      const data = await response.json().catch(() => ({}));
      if (localRequestId !== requestId) {
        return;
      }

      if (!response.ok) {
        renderStatus(data.message || 'No se pudo verificar el correo.', 'error');
        emailInput.setAttribute('aria-invalid', 'true');
        return;
      }

      if (data.available) {
        renderStatus(data.message || 'Correo disponible.', 'success');
        emailInput.removeAttribute('aria-invalid');
      } else {
        renderStatus(data.message || 'Este correo ya existe.', 'error');
        emailInput.setAttribute('aria-invalid', 'true');
      }
    } catch (error) {
      if (localRequestId !== requestId) {
        return;
      }
      renderStatus('No se pudo verificar el correo.', 'error');
    }
  };

  emailInput.addEventListener('input', () => {
    const value = (emailInput.value || '').trim().toLowerCase();
    if (timer) {
      clearTimeout(timer);
    }

    if (!value) {
      renderStatus('');
      emailInput.removeAttribute('aria-invalid');
      return;
    }

    if (!isValidEmail(value)) {
      renderStatus('Ingresa un correo valido para verificar disponibilidad.', 'neutral');
      return;
    }

    renderStatus('Verificando correo...');
    requestId += 1;
    const localRequestId = requestId;
    timer = setTimeout(() => {
      checkEmail(value, localRequestId);
    }, 350);
  });
});
