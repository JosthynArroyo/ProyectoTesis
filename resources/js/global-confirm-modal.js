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

  const getConfirmText = (actionType, customText) => {
    const raw = (customText || '').trim();
    if (raw && Object.values(validActionTexts).includes(raw)) {
      return raw;
    }
    
    const combined = `${actionType || ''} ${raw}`.toLowerCase();
    if (combined.includes('quitar')) return 'Sí, quitar';
    if (combined.includes('desactivar') || combined.includes('inactivar') || combined.includes('suspend')) return 'Sí, desactivar';
    if (combined.includes('bloquear') || combined.includes('block')) return 'Sí, bloquear';
    if (combined.includes('revocar')) return 'Sí, revocar';
    if (combined.includes('anular') || combined.includes('cancelar')) return 'Sí, anular';
    if (combined.includes('rechazar')) return 'Sí, rechazar';
    return 'Sí, eliminar';
  };

  const openModal = ({ title, target, message, consequence, confirmText, actionType, form, trigger, callback }) => {
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
      activeForm.submit();
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

    const submitBtn = e.submitter || form.querySelector('[type="submit"]') || document.activeElement;
    const hasConfirmAttr = form.hasAttribute('data-confirm') || form.hasAttribute('data-confirm-title') || (submitBtn && (submitBtn.hasAttribute('data-confirm') || submitBtn.hasAttribute('data-confirm-title')));
    const isDeleteMethod = form.querySelector('input[name="_method"][value="DELETE"]') !== null || (form.getAttribute('method') || '').toUpperCase() === 'DELETE';
    const formOnsubmit = form.getAttribute('onsubmit') || '';
    const btnOnclick = submitBtn ? submitBtn.getAttribute('onclick') || '' : '';
    const hasInlineConfirm = formOnsubmit.includes('confirm') || btnOnclick.includes('confirm');

    if (hasConfirmAttr || isDeleteMethod || hasInlineConfirm) {
      e.preventDefault();
      e.stopPropagation();

      const title = form.dataset.confirmTitle || submitBtn?.dataset?.confirmTitle || '¿Confirmar acción crítica?';
      const target = form.dataset.confirmTarget || submitBtn?.dataset?.confirmTarget || '';
      const message = form.dataset.confirmMessage || submitBtn?.dataset?.confirmMessage || '¿Estás seguro de que deseas ejecutar esta acción?';
      const consequence = form.dataset.confirmConsequence || submitBtn?.dataset?.confirmConsequence || 'Esta modificación alterará el estado del registro.';
      const confirmText = form.dataset.confirmButton || form.dataset.confirmBtn || submitBtn?.dataset?.confirmButton || submitBtn?.dataset?.confirmBtn || '';
      const actionType = form.dataset.confirmAction || submitBtn?.dataset?.confirmAction || (isDeleteMethod ? 'eliminar' : 'eliminar');

      openModal({
        title,
        target,
        message,
        consequence,
        confirmText,
        actionType,
        form,
        trigger: submitBtn || form
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

        openModal({
          title,
          target,
          message,
          consequence,
          confirmText,
          actionType,
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
      const href = trigger.getAttribute('href');

      openModal({
        title,
        target,
        message,
        consequence,
        confirmText,
        actionType,
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
