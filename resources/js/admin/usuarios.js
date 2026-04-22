import '../ui/kebab-menus';

document.addEventListener('DOMContentLoaded', () => {
  const chips = document.querySelectorAll('.filter-chip');
  const moreFiltersBtn = document.getElementById('btn-more-filters');
  const filtersWrap = document.getElementById('filters-wrap');
  const rows = Array.from(document.querySelectorAll('.users tbody tr[data-user-row]'));
  const mobileQuery = window.matchMedia('(max-width: 768px)');
  const confirmSheet = document.querySelector('[data-confirm-sheet]');
  const confirmTitle = confirmSheet ? confirmSheet.querySelector('[data-confirm-title]') : null;
  const confirmMessage = confirmSheet ? confirmSheet.querySelector('[data-confirm-message]') : null;
  const confirmSubmit = confirmSheet ? confirmSheet.querySelector('[data-confirm-submit]') : null;
  const suspendSheet = document.querySelector('[data-suspend-sheet]');
  const suspendForm = suspendSheet ? suspendSheet.querySelector('[data-suspend-sheet-form]') : null;
  const suspendTitle = suspendSheet ? suspendSheet.querySelector('[data-suspend-title]') : null;
  const suspendName = suspendSheet ? suspendSheet.querySelector('[data-suspend-name]') : null;
  let pendingFormId = null;

  const sync = (chip) => {
    const input = chip.querySelector('input[type="checkbox"]');
    const active = input.checked;
    chip.classList.toggle('is-active', active);
    chip.setAttribute('aria-pressed', String(active));
    const icon = chip.querySelector('.icon');
    if (icon) {
      icon.classList.toggle('ri-checkbox-circle-line', active);
      icon.classList.toggle('ri-checkbox-blank-circle-line', !active);
    }
  };

  chips.forEach((chip) => {
    const input = chip.querySelector('input[type="checkbox"]');
    sync(chip);
    input.addEventListener('change', () => sync(chip));
    chip.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        input.checked = !input.checked;
        sync(chip);
      }
    });
  });

  if (moreFiltersBtn && filtersWrap) {
    moreFiltersBtn.addEventListener('click', () => {
      const isHidden = filtersWrap.hasAttribute('hidden');
      if (isHidden) filtersWrap.removeAttribute('hidden');
      else filtersWrap.setAttribute('hidden', 'hidden');
      moreFiltersBtn.setAttribute('aria-expanded', String(isHidden));
    });
  }

  const setBodyScrollLock = () => {
    const hasOpenModal = document.querySelector('.modal.is-open');
    document.body.classList.toggle('modal-open', Boolean(hasOpenModal));
  };

  const openSheet = (sheet) => {
    if (!sheet) {
      return;
    }
    sheet.classList.add('is-open');
    sheet.setAttribute('aria-hidden', 'false');
    setBodyScrollLock();
  };

  const closeSheet = (sheet) => {
    if (!sheet) {
      return;
    }
    sheet.classList.remove('is-open');
    sheet.setAttribute('aria-hidden', 'true');
    setBodyScrollLock();
  };

  const setRowState = (row, open) => {
    row.classList.toggle('is-open', open);
    row.querySelectorAll('[data-row-toggle]').forEach((button) => {
      button.setAttribute('aria-expanded', String(open));
      const label = open
        ? (button.dataset.openLabel || 'Ocultar detalles')
        : (button.dataset.closedLabel || 'Ver detalles');
      button.innerHTML = `<i class="ri-arrow-${open ? 'up' : 'down'}-s-line"></i> ${label}`;
    });
  };

  const syncRows = () => {
    if (!mobileQuery.matches) {
      rows.forEach((row) => setRowState(row, true));
      return;
    }

    rows.forEach((row) => {
      if (!row.dataset.initialized) {
        setRowState(row, row.classList.contains('is-open'));
        row.dataset.initialized = '1';
      }
    });
  };

  rows.forEach((row) => {
    row.querySelectorAll('[data-row-toggle]').forEach((button) => {
      button.addEventListener('click', () => {
        const open = !row.classList.contains('is-open');
        if (mobileQuery.matches) {
          rows.forEach((item) => {
            if (item !== row) {
              setRowState(item, false);
            }
          });
        }
        setRowState(row, open);
      });
    });
  });

  syncRows();
  if (mobileQuery.addEventListener) {
    mobileQuery.addEventListener('change', syncRows);
  } else {
    window.addEventListener('resize', syncRows);
  }

  document.addEventListener('click', (event) => {
    const confirmTrigger = event.target.closest('[data-confirm-form]');
    if (confirmTrigger && confirmSheet) {
      event.preventDefault();
      pendingFormId = confirmTrigger.dataset.confirmForm || null;
      if (confirmTitle) {
        confirmTitle.textContent = confirmTrigger.dataset.confirmTitle || 'Confirmar accion';
      }
      if (confirmMessage) {
        confirmMessage.textContent = confirmTrigger.dataset.confirmMessage || 'Confirma para continuar.';
      }
      if (confirmSubmit) {
        confirmSubmit.textContent = confirmTrigger.dataset.confirmButton || 'Confirmar';
      }
      openSheet(confirmSheet);
      return;
    }

    const suspendTrigger = event.target.closest('[data-suspend-open]');
    if (suspendTrigger && suspendSheet && suspendForm) {
      event.preventDefault();
      suspendForm.setAttribute('action', suspendTrigger.dataset.suspendAction || suspendForm.getAttribute('action') || '');

      const idInput = suspendForm.querySelector('[name="admin_id"], [name="user_id"]');
      if (idInput) {
        idInput.value = suspendTrigger.dataset.suspendId || '';
      }

      if (suspendTitle) {
        suspendTitle.textContent = suspendTrigger.dataset.suspendTitle || 'Suspender acceso';
      }
      if (suspendName) {
        suspendName.textContent = suspendTrigger.dataset.suspendName || 'Usuario';
      }
      openSheet(suspendSheet);
    }
  });

  if (confirmSubmit && confirmSheet) {
    confirmSubmit.addEventListener('click', () => {
      if (!pendingFormId) {
        closeSheet(confirmSheet);
        return;
      }
      const form = document.getElementById(pendingFormId);
      if (form) {
        form.submit();
      }
    });
  }

  document.querySelectorAll('[data-sheet-close]').forEach((button) => {
    button.addEventListener('click', () => {
      closeSheet(button.closest('.modal'));
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }

    document.querySelectorAll('.modal.is-open').forEach((sheet) => {
      closeSheet(sheet);
    });
  });

  if (suspendSheet && suspendSheet.dataset.openOnLoad === '1') {
    openSheet(suspendSheet);
  }
});
