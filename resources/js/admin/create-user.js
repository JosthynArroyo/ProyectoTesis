document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('form.form') || document.querySelector('form[action*="usuarios"]');
  if (!form) {
    return;
  }

  let isSubmitting = false;
  const submitBtn = form.querySelector('button[type="submit"]') || form.querySelector('#btn-registrar-usuario');
  const resetBtn = form.querySelector('button[type="reset"]') || form.querySelector('#btn-limpiar-usuario');
  const originalSubmitHtml = submitBtn ? submitBtn.innerHTML : '<i class="ri-save-line"></i> Registrar';

  const restoreButtons = () => {
    isSubmitting = false;
    form.dataset.isSubmitting = 'false';
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.removeAttribute('disabled');
      submitBtn.removeAttribute('aria-disabled');
      submitBtn.classList.remove('opacity-50', 'pointer-events-none', 'cursor-not-allowed');
      submitBtn.innerHTML = originalSubmitHtml;
    }
    if (resetBtn) {
      resetBtn.disabled = false;
      resetBtn.removeAttribute('disabled');
      resetBtn.removeAttribute('aria-disabled');
      resetBtn.removeAttribute('tabindex');
      resetBtn.classList.remove('opacity-50', 'pointer-events-none', 'cursor-not-allowed');
    }
  };

  const preventIfSubmitting = (e) => {
    if (isSubmitting || form.dataset.isSubmitting === 'true') {
      e.preventDefault();
      e.stopPropagation();
      if (e.stopImmediatePropagation) {
        e.stopImmediatePropagation();
      }
      return false;
    }
  };

  if (resetBtn) {
    resetBtn.addEventListener('click', preventIfSubmitting, true);
  }

  form.addEventListener('reset', preventIfSubmitting, true);

  form.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      if (isSubmitting || form.dataset.isSubmitting === 'true') {
        e.preventDefault();
        e.stopPropagation();
        return false;
      }
    }
  }, true);

  form.addEventListener('submit', (e) => {
    form.querySelectorAll('input, select, textarea').forEach((el) => {
      if (!el.checkValidity()) {
        el.setAttribute('aria-invalid', 'true');
      } else {
        el.removeAttribute('aria-invalid');
      }
    });

    if (!form.checkValidity()) {
      restoreButtons();
      return;
    }

    if (isSubmitting || form.dataset.isSubmitting === 'true') {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }

    isSubmitting = true;
    form.dataset.isSubmitting = 'true';

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.setAttribute('disabled', 'disabled');
      submitBtn.setAttribute('aria-disabled', 'true');
      submitBtn.classList.add('opacity-50', 'pointer-events-none', 'cursor-not-allowed');
      submitBtn.innerHTML = '<i class="ri-loader-4-line animate-spin"></i> Registrando...';
    }

    if (resetBtn) {
      resetBtn.disabled = true;
      resetBtn.setAttribute('disabled', 'disabled');
      resetBtn.setAttribute('aria-disabled', 'true');
      resetBtn.setAttribute('tabindex', '-1');
      resetBtn.classList.add('opacity-50', 'pointer-events-none', 'cursor-not-allowed');
    }
  });

  window.addEventListener('pageshow', () => {
    restoreButtons();
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
