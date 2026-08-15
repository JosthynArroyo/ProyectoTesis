import '../ui/kebab-menus';

document.addEventListener('DOMContentLoaded', () => {
  const chips = document.querySelectorAll('.filter-chip');
  const moreFiltersBtn = document.getElementById('btn-more-filters');
  const filtersWrap = document.getElementById('filters-wrap');
  const rows = Array.from(document.querySelectorAll('.users tbody tr[data-user-row]'));
  const mobileQuery = window.matchMedia('(max-width: 768px)');
  const suspendSheet = document.querySelector('[data-suspend-sheet]');
  const suspendForm = suspendSheet ? suspendSheet.querySelector('[data-suspend-sheet-form]') : null;
  const suspendTitle = suspendSheet ? suspendSheet.querySelector('[data-suspend-title]') : null;
  const suspendName = suspendSheet ? suspendSheet.querySelector('[data-suspend-name]') : null;

  const sync = (chip) => {
    const input = chip.querySelector('input[type="checkbox"]');
    if (!input) return;
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
    if (!input) return;
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
    if (!sheet) return;
    sheet.classList.add('is-open');
    sheet.setAttribute('aria-hidden', 'false');
    setBodyScrollLock();
  };

  const closeSheet = (sheet) => {
    if (!sheet) return;
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

  const setDependentState = (button, open) => {
    const panelId = button.dataset.dependentTarget;
    if (!panelId) return;

    const panel = document.getElementById(panelId);
    button.setAttribute('aria-expanded', String(open));

    const label = button.querySelector('[data-dependent-label]');
    if (label) {
      label.textContent = open ? 'Ocultar dependientes' : 'Ver dependientes';
    }

    const chevron = button.querySelector('[data-dependent-chevron]');
    if (chevron) {
      chevron.classList.toggle('ri-arrow-up-s-line', open);
      chevron.classList.toggle('ri-arrow-down-s-line', !open);
    }

    if (panel) {
      panel.hidden = !open;
      panel.classList.toggle('is-open', open);
    }
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

  document.querySelectorAll('[data-dependent-toggle]').forEach((button) => {
    const panelId = button.dataset.dependentTarget;
    const panel = panelId ? document.getElementById(panelId) : null;
    if (panel) {
      panel.hidden = true;
    }

    button.addEventListener('click', () => {
      const open = panel ? panel.hidden : button.getAttribute('aria-expanded') !== 'true';
      setDependentState(button, open);
    });
  });

  syncRows();
  if (mobileQuery.addEventListener) {
    mobileQuery.addEventListener('change', syncRows);
  } else {
    window.addEventListener('resize', syncRows);
  }

  document.addEventListener('click', (event) => {
    const suspendTrigger = event.target.closest('[data-suspend-open]');
    if (suspendTrigger && suspendSheet && suspendForm) {
      event.preventDefault();
      suspendForm.setAttribute('action', suspendTrigger.dataset.suspendAction || suspendForm.getAttribute('action') || '');

      const idInput = suspendForm.querySelector('[name="admin_id"], [name="user_id"]');
      if (idInput) {
        idInput.value = suspendTrigger.dataset.suspendId || '';
      }

      if (suspendTitle) {
        suspendTitle.textContent = suspendTrigger.dataset.suspendTitle || 'Suspender usuario';
      }
      if (suspendName) {
        suspendName.textContent = suspendTrigger.dataset.suspendName || 'Usuario';
      }
      openSheet(suspendSheet);
    }
  });

  document.querySelectorAll('[data-sheet-close]').forEach((button) => {
    button.addEventListener('click', () => {
      closeSheet(button.closest('.modal'));
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.modal.is-open').forEach((sheet) => {
      closeSheet(sheet);
    });
  });

  if (suspendSheet && suspendSheet.dataset.openOnLoad === '1') {
    openSheet(suspendSheet);
  }
});
