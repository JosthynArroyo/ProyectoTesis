document.addEventListener('DOMContentLoaded', () => {
  const forms = Array.from(document.querySelectorAll('form[data-draft-key]'));
  if (!forms.length) {
    return;
  }

  const toSerializableValue = (element) => {
    if (element.type === 'checkbox') {
      return element.checked ? '1' : '0';
    }
    if (element.type === 'radio') {
      return element.checked ? element.value : null;
    }
    return element.value;
  };

  const applyValue = (element, value) => {
    if (element.type === 'checkbox') {
      element.checked = value === '1' || value === true;
      return;
    }
    if (element.type === 'radio') {
      element.checked = String(element.value) === String(value);
      return;
    }
    element.value = value ?? '';
  };

  forms.forEach((form) => {
    const key = form.dataset.draftKey;
    if (!key) {
      return;
    }

    const statusNode = form.querySelector('[data-draft-status]');
    const saveBtn = form.querySelector('[data-save-draft]');
    const restoreBtn = form.querySelector('[data-restore-draft]');
    const clearBtn = form.querySelector('[data-clear-draft]');

    const fields = Array.from(form.querySelectorAll('input, textarea, select')).filter((field) => {
      const name = field.getAttribute('name') || '';
      if (!name || field.disabled) {
        return false;
      }
      if (field.type === 'file' || field.type === 'password') {
        return false;
      }
      if (name === '_token' || name === '_method') {
        return false;
      }
      return true;
    });

    const renderStatus = (message) => {
      if (statusNode) {
        statusNode.textContent = message;
      }
    };

    const saveDraft = () => {
      const payload = {
        savedAt: new Date().toISOString(),
        values: {},
      };

      fields.forEach((field) => {
        if (field.type === 'radio' && !field.checked) {
          return;
        }
        payload.values[field.name] = toSerializableValue(field);
      });

      localStorage.setItem(key, JSON.stringify(payload));
      const stamp = new Date().toLocaleTimeString();
      renderStatus(`Borrador guardado (${stamp}).`);
    };

    const restoreDraft = () => {
      const raw = localStorage.getItem(key);
      if (!raw) {
        renderStatus('No hay borrador guardado.');
        return;
      }

      let payload = null;
      try {
        payload = JSON.parse(raw);
      } catch (error) {
        renderStatus('No se pudo leer el borrador.');
        return;
      }

      const values = payload?.values || {};
      fields.forEach((field) => {
        if (!Object.prototype.hasOwnProperty.call(values, field.name)) {
          return;
        }
        applyValue(field, values[field.name]);
      });
      renderStatus('Borrador restaurado.');
      form.dispatchEvent(new Event('input', { bubbles: true }));
      form.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const clearDraft = () => {
      localStorage.removeItem(key);
      renderStatus('Borrador eliminado.');
    };

    if (saveBtn) {
      saveBtn.addEventListener('click', saveDraft);
    }
    if (restoreBtn) {
      restoreBtn.addEventListener('click', restoreDraft);
    }
    if (clearBtn) {
      clearBtn.addEventListener('click', clearDraft);
    }

    setInterval(saveDraft, 30000);
  });
});
