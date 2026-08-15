document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('global-confirm-modal');
  if (!modal) return;

  const titleEl = document.getElementById('global-confirm-title');
  const targetEl = document.getElementById('global-confirm-target');
  const messageEl = document.getElementById('global-confirm-message');
  const consequenceEl = document.getElementById('global-confirm-consequence');
  const consequenceTextEl = document.getElementById('global-confirm-consequence-text');
  const cancelBtn = document.getElementById('global-confirm-cancel-btn');
  const submitBtn = document.getElementById('global-confirm-submit-btn');
  const submitTextEl = document.getElementById('global-confirm-submit-text');
  const iconBgEl = document.getElementById('global-confirm-icon-bg');
  const iconEl = document.getElementById('global-confirm-icon');

  let activeTrigger = null;
  let activeForm = null;
  let activeCallback = null;
  let isProcessing = false;
  let currentActionType = 'eliminar';
  let currentCustomText = '';
  const actionLock = window.ActionLock || null;

  const validActionTexts = {
    eliminar: 'Sí, eliminar',
    quitar: 'Sí, quitar',
    desactivar: 'Sí, desactivar',
    bloquear: 'Sí, bloquear',
    revocar: 'Sí, revocar',
    anular: 'Sí, anular',
    rechazar: 'Sí, rechazar',
  };

  /**
   * Resolve the confirmation button label.
   * If an explicit customText is provided via data-confirm-btn, it is always honoured
   * (trimmed, capped at 80 chars, inserted via textContent — never innerHTML).
   * Inference and the "Sí, eliminar" fallback only apply when customText is absent.
   */
  const getConfirmText = (actionType, customText) => {
    const raw = (customText || '').trim().slice(0, 80);
    if (raw !== '') {
      return raw;
    }

    const combined = `${actionType || ''}`.toLowerCase();
    if (combined.includes('quitar')) return 'Sí, quitar';
    if (combined.includes('desactivar') || combined.includes('inactivar') || combined.includes('suspend')) return 'Sí, desactivar';
    if (combined.includes('bloquear') || combined.includes('block')) return 'Sí, bloquear';
    if (combined.includes('revocar')) return 'Sí, revocar';
    if (combined.includes('anular') || combined.includes('cancelar')) return 'Sí, anular';
    if (combined.includes('rechazar')) return 'Sí, rechazar';
    return 'Sí, eliminar';
  };

  /**
   * Apply visual variant to the submit button and icon.
   * Allowed values: 'primary', 'warning', 'danger'.
   * When absent or unrecognised the original 'danger' styling is preserved.
   */
  const applyVariant = (variant) => {
    // Reset to a neutral state first
    submitBtn.classList.remove('btn-danger', 'btn-primary', 'btn-warning');
    if (iconBgEl) {
      iconBgEl.classList.remove(
        'bg-rose-100', 'dark:bg-rose-950/40', 'text-rose-600', 'dark:text-rose-400',
        'bg-amber-50', 'dark:bg-amber-950/40', 'text-amber-600', 'dark:text-amber-400'
      );
      iconBgEl.style.background = '';
      iconBgEl.style.color = '';
    }
    if (iconEl) {
      iconEl.classList.remove('ri-error-warning-line', 'ri-information-line', 'ri-alert-line', 'ri-database-2-line');
    }

    if (variant === 'primary') {
      submitBtn.classList.add('btn-primary');
      if (iconBgEl) {
        iconBgEl.style.background = 'var(--accent-soft)';
        iconBgEl.style.color = 'var(--accent)';
      }
      if (iconEl) iconEl.classList.add('ri-information-line');
    } else if (variant === 'warning') {
      submitBtn.classList.add('btn-warning');
      if (iconBgEl) iconBgEl.classList.add('bg-amber-50', 'dark:bg-amber-950/40', 'text-amber-600', 'dark:text-amber-400');
      if (iconEl) iconEl.classList.add('ri-alert-line');
    } else {
      // 'danger' or unrecognised — original destructive styling
      submitBtn.classList.add('btn-danger');
      if (iconBgEl) iconBgEl.classList.add('bg-rose-100', 'dark:bg-rose-950/40', 'text-rose-600', 'dark:text-rose-400');
      if (iconEl) iconEl.classList.add('ri-error-warning-line');
    }
  };

  const openModal = ({ title, target, message, consequence, confirmText, actionType, variant, form, trigger, callback }) => {
    activeTrigger = trigger || document.activeElement;
    activeForm = form || null;
    activeCallback = callback || null;
    isProcessing = false;
    currentActionType = actionType || 'eliminar';
    currentCustomText = confirmText || '';

    titleEl.textContent = title || '¿Confirmar acción crítica?';
    
    if (target && target.trim() !== '') {
      targetEl.textContent = target.trim();
      targetEl.classList.remove('hidden');
    } else {
      targetEl.textContent = '';
      targetEl.classList.add('hidden');
    }

    messageEl.textContent = message || 'Esta acción afectará los registros seleccionados en el sistema.';
    
    if (consequence && consequence.trim() !== '') {
      consequenceTextEl.textContent = consequence.trim();
      consequenceEl.classList.remove('hidden');
    } else {
      consequenceTextEl.textContent = '';
      consequenceEl.classList.add('hidden');
    }

    const btnLabel = getConfirmText(currentActionType, currentCustomText);
    submitTextEl.textContent = btnLabel;

    applyVariant(variant || 'danger');

    submitBtn.disabled = false;
    cancelBtn.disabled = false;
    submitBtn.classList.remove('opacity-50', 'pointer-events-none', 'cursor-not-allowed');
    cancelBtn.classList.remove('opacity-50', 'pointer-events-none', 'cursor-not-allowed');

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    cancelBtn.focus();
  };

  const closeModal = () => {
    if (isProcessing) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    if (activeTrigger && typeof activeTrigger.focus === 'function') {
      activeTrigger.focus();
    }
    activeForm = null;
    activeTrigger = null;
    activeCallback = null;
  };

  cancelBtn.addEventListener('click', (e) => {
    e.preventDefault();
    closeModal();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden') && !isProcessing) {
      closeModal();
    }
  });

  submitBtn.addEventListener('click', async (e) => {
    e.preventDefault();
    if (isProcessing) return;

    isProcessing = true;
    submitBtn.disabled = true;
    cancelBtn.disabled = true;
    submitBtn.classList.add('opacity-50', 'pointer-events-none', 'cursor-not-allowed');
    cancelBtn.classList.add('opacity-50', 'pointer-events-none', 'cursor-not-allowed');
    submitTextEl.textContent = 'Procesando...';

    if (activeForm) {
      activeForm.dataset.confirmed = 'true';
      // Use requestSubmit so that all form event listeners (including action-lock) fire.
      // Fall back to submit() only if requestSubmit is not available.
      if (typeof activeForm.requestSubmit === 'function') {
        activeForm.requestSubmit();
      } else {
        activeForm.submit();
      }
      return;
    }

    if (activeCallback) {
      try {
        await activeCallback();
        isProcessing = false;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      } catch (err) {
        isProcessing = false;
        submitBtn.disabled = false;
        cancelBtn.disabled = false;
        submitBtn.classList.remove('opacity-50', 'pointer-events-none', 'cursor-not-allowed');
        cancelBtn.classList.remove('opacity-50', 'pointer-events-none', 'cursor-not-allowed');
        submitTextEl.textContent = getConfirmText(currentActionType, currentCustomText);
      }
    }
  });

  // Global submit event handler
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.dataset.confirmed === 'true' || form.id === 'form-registrar-usuario' || form.classList.contains('form-login') || form.classList.contains('form-search')) {
      return;
    }

    const submitter = e.submitter || form.querySelector('[type="submit"]') || document.activeElement;
    const hasConfirmAttr = form.hasAttribute('data-confirm') || form.hasAttribute('data-confirm-title') || (submitter && (submitter.hasAttribute('data-confirm') || submitter.hasAttribute('data-confirm-title')));
    const isDeleteMethod = form.querySelector('input[name="_method"][value="DELETE"]') !== null || (form.getAttribute('method') || '').toUpperCase() === 'DELETE';
    const formOnsubmit = form.getAttribute('onsubmit') || '';
    const btnOnclick = submitter ? submitter.getAttribute('onclick') || '' : '';
    const hasInlineConfirm = formOnsubmit.includes('confirm') || btnOnclick.includes('confirm');

    if (hasConfirmAttr || isDeleteMethod || hasInlineConfirm) {
      e.preventDefault();
      e.stopPropagation();

      const title = form.dataset.confirmTitle || submitter?.dataset?.confirmTitle || '¿Confirmar acción crítica?';
      const target = form.dataset.confirmTarget || submitter?.dataset?.confirmTarget || '';
      const message = form.dataset.confirmMessage || submitter?.dataset?.confirmMessage || '¿Estás seguro de que deseas ejecutar esta acción?';
      const consequence = form.dataset.confirmConsequence || submitter?.dataset?.confirmConsequence || 'Esta modificación alterará el estado del registro.';
      const confirmText = form.dataset.confirmButton || form.dataset.confirmBtn || submitter?.dataset?.confirmButton || submitter?.dataset?.confirmBtn || '';
      const actionType = form.dataset.confirmAction || submitter?.dataset?.confirmAction || (isDeleteMethod ? 'eliminar' : 'eliminar');
      const variant = form.dataset.confirmVariant || submitter?.dataset?.confirmVariant || '';

      openModal({
        title,
        target,
        message,
        consequence,
        confirmText,
        actionType,
        variant,
        form,
        trigger: submitter || form
      });
    }
  }, true);

  // Global click event handler for elements with data-confirm-form or data-confirm
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-confirm-form], [data-confirm-trigger], [data-confirm-title], [data-confirm-message]');
    if (!trigger || trigger.tagName === 'FORM') return;

    if (trigger.type === 'submit' && trigger.closest('form') && !trigger.dataset.confirmForm) return;

    if (trigger.dataset.confirmForm) {
      const form = document.getElementById(trigger.dataset.confirmForm);
      if (form) {
        e.preventDefault();
        e.stopPropagation();

        const title = trigger.dataset.confirmTitle || '¿Confirmar acción crítica?';
        const target = trigger.dataset.confirmTarget || '';
        const message = trigger.dataset.confirmMessage || '¿Estás seguro de que deseas ejecutar esta acción?';
        const consequence = trigger.dataset.confirmConsequence || 'Esta modificación alterará el estado del registro.';
        const confirmText = trigger.dataset.confirmButton || trigger.dataset.confirmBtn || '';
        const actionType = trigger.dataset.confirmAction || (confirmText ? confirmText : 'eliminar');
        const variant = trigger.dataset.confirmVariant || '';

        openModal({
          title,
          target,
          message,
          consequence,
          confirmText,
          actionType,
          variant,
          form,
          trigger
        });
        return;
      }
    }

    if (trigger.tagName === 'A' || trigger.tagName === 'BUTTON') {
      if (trigger.closest('form')) return;

      e.preventDefault();
      const title = trigger.dataset.confirmTitle || '¿Confirmar acción crítica?';
      const target = trigger.dataset.confirmTarget || '';
      const message = trigger.dataset.confirmMessage || '¿Estás seguro de que deseas ejecutar esta acción?';
      const consequence = trigger.dataset.confirmConsequence || 'Esta modificación alterará el estado del registro.';
      const confirmText = trigger.dataset.confirmButton || trigger.dataset.confirmBtn || '';
      const actionType = trigger.dataset.confirmAction || 'eliminar';
      const variant = trigger.dataset.confirmVariant || '';
      const href = trigger.getAttribute('href');

      openModal({
        title,
        target,
        message,
        consequence,
        confirmText,
        actionType,
        variant,
        trigger,
        callback: async () => {
          if (href && href !== '#') {
            const copy = actionLock?.resolveNavigationCopy ? actionLock.resolveNavigationCopy(trigger) : null;
            if (copy && actionLock?.startNavigation) {
              actionLock.startNavigation(copy);
            }
            window.location.href = href;
          }
        }
      });
    }
  }, true);

  window.AntigravityConfirm = { openModal, closeModal };
});
