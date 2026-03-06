document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('personalizacionModal');
  if (!modal) return;

  const openers = document.querySelectorAll('[data-open-personalizacion]');
  const closers = modal.querySelectorAll('[data-close-personalizacion]');
  const dialog = modal.querySelector('.modal-dialog');
  const form = modal.querySelector('[data-personalizacion-request-form]');
  const submitButton = modal.querySelector('[data-personalizacion-request-submit]');
  const pendingState = modal.querySelector('[data-personalizacion-pending-state]');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

  const open = () => {
    modal.classList.add('is-open');
    document.body.classList.add('modal-open');
    modal.setAttribute('aria-hidden', 'false');
    if (dialog) {
      try {
        dialog.focus({ preventScroll: true });
      } catch {}
    }
  };

  const close = () => {
    modal.classList.remove('is-open');
    document.body.classList.remove('modal-open');
    modal.setAttribute('aria-hidden', 'true');
  };

  const showToast = (message, tone = 'success') => {
    const toast = document.createElement('div');
    toast.className = `toast-float${tone === 'warn' ? ' toast-float--warn' : ' toast-float--success'}`;
    toast.setAttribute('role', 'status');

    const content = document.createElement('div');
    content.className = 'toast-float__content';

    const icon = document.createElement('i');
    icon.className = tone === 'warn' ? 'ri-error-warning-line' : 'ri-checkbox-circle-line';

    const label = document.createElement('span');
    label.textContent = message;

    content.append(icon, label);
    toast.appendChild(content);

    document.body.appendChild(toast);
    window.setTimeout(() => {
      toast.remove();
    }, 3200);
  };

  const markAsPending = () => {
    form?.classList.add('hidden');
    pendingState?.classList.remove('hidden');
  };

  openers.forEach((btn) => btn.addEventListener('click', open));
  closers.forEach((btn) => btn.addEventListener('click', close));

  modal.addEventListener('click', (event) => {
    if (event.target === modal || event.target.classList.contains('modal-backdrop')) {
      close();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal.classList.contains('is-open')) {
      close();
    }
  });

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const originalHtml = submitButton?.innerHTML ?? '';
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.innerHTML = '<i class="ri-loader-4-line animate-spin"></i> Enviando...';
    }

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: new FormData(form),
      });

      const data = await response.json().catch(() => null);
      if (!response.ok || !data?.ok) {
        throw new Error(data?.message || 'No se pudo enviar la solicitud.');
      }

      if (data.status === 'pending_created' || data.status === 'already_pending') {
        markAsPending();
      }

      close();
      showToast(data.message || 'Solicitud enviada al superadmin.');
    } catch (error) {
      showToast(error.message || 'No se pudo enviar la solicitud.', 'warn');
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.innerHTML = originalHtml;
      }
    }
  });

  if (modal.dataset.openOnload === '1') {
    open();
  }
});
