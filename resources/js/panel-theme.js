const VALID_THEMES = new Set(['light', 'dark']);
const CLASS_LIGHT = 'panel-theme-light';
const CLASS_DARK = 'panel-theme-dark';

function readMeta(name) {
    return document.querySelector(`meta[name="${name}"]`)?.content ?? '';
}

const root = document.documentElement;
const csrfToken = readMeta('csrf-token');
const updateUrl = readMeta('panel-theme-update-url');
const preferenceModal = document.querySelector('[data-theme-preference-modal]');
const preferenceDialog = preferenceModal?.querySelector('.modal-dialog');
const preferenceSaveDark = preferenceModal?.querySelector('[data-theme-preference-save-dark]');
const preferenceLight = preferenceModal?.querySelector('[data-theme-preference-light]');
const preferenceDismissButtons = preferenceModal?.querySelectorAll('[data-theme-preference-dismiss]') ?? [];

function normalizeTheme(value) {
    const lower = String(value || '').toLowerCase();
    return VALID_THEMES.has(lower) ? lower : 'light';
}

function getCurrentTheme() {
    return normalizeTheme(root.getAttribute('data-panel-theme'));
}

function applyTheme(theme) {
    const normalized = normalizeTheme(theme);
    root.classList.remove(CLASS_LIGHT, CLASS_DARK);
    root.classList.add(normalized === 'dark' ? CLASS_DARK : CLASS_LIGHT);
    root.setAttribute('data-panel-theme', normalized);
    root.style.colorScheme = normalized;
    return normalized;
}

let activeSaveRequest = null;
async function persistThemeOnServer(theme) {
    if (!updateUrl || !csrfToken) {
        return;
    }

    if (activeSaveRequest) {
        activeSaveRequest.abort();
    }

    const controller = new AbortController();
    activeSaveRequest = controller;

    try {
        const response = await fetch(updateUrl, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ theme }),
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`Theme save failed with status ${response.status}`);
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.warn('No se pudo guardar la preferencia de tema.', error);
        }
    } finally {
        if (activeSaveRequest === controller) {
            activeSaveRequest = null;
        }
    }
}

function syncToggleState(theme) {
    const isDark = theme === 'dark';
    const toggles = document.querySelectorAll('[data-theme-toggle]');

    toggles.forEach((toggle) => {
        toggle.setAttribute('aria-pressed', String(isDark));
        toggle.setAttribute('data-theme-current', theme);
        toggle.setAttribute('title', isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');

        const lightIcon = toggle.querySelector('[data-theme-icon="light"]');
        const darkIcon = toggle.querySelector('[data-theme-icon="dark"]');

        if (lightIcon) {
            lightIcon.classList.toggle('is-active', !isDark);
            lightIcon.setAttribute('aria-hidden', String(isDark));
        }

        if (darkIcon) {
            darkIcon.classList.toggle('is-active', isDark);
            darkIcon.setAttribute('aria-hidden', String(!isDark));
        }
    });
}

function setTheme(theme, options = {}) {
    const { persistServer = false } = options;
    const normalized = applyTheme(theme);

    syncToggleState(normalized);

    if (persistServer) {
        persistThemeOnServer(normalized);
    }
}

function openPreferenceModal() {
    if (!preferenceModal) {
        return;
    }

    preferenceModal.classList.add('is-open');
    preferenceModal.setAttribute('aria-hidden', 'false');
    preferenceDialog?.focus();
    document.body.classList.add('modal-open');
}

function closePreferenceModal({ restoreLight = false, persistLight = false } = {}) {
    if (preferenceModal) {
        preferenceModal.classList.remove('is-open');
        preferenceModal.setAttribute('aria-hidden', 'true');
    }

    document.body.classList.remove('modal-open');

    if (restoreLight) {
        setTheme('light', { persistServer: persistLight });
    }
}

function initThemeToggle() {
    setTheme(getCurrentTheme());

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const current = getCurrentTheme();

            if (current === 'dark') {
                closePreferenceModal();
                setTheme('light', { persistServer: true });
                return;
            }

            setTheme('dark');
            openPreferenceModal();
        });
    });

    preferenceSaveDark?.addEventListener('click', async () => {
        await persistThemeOnServer('dark');
        closePreferenceModal();
    });

    preferenceLight?.addEventListener('click', () => {
        closePreferenceModal({ restoreLight: true, persistLight: true });
    });

    preferenceDismissButtons.forEach((button) => {
        button.addEventListener('click', () => {
            closePreferenceModal({ restoreLight: true });
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && preferenceModal?.classList.contains('is-open')) {
            closePreferenceModal({ restoreLight: true });
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initThemeToggle, { once: true });
} else {
    initThemeToggle();
}
