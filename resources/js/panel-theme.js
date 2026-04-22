const VALID_THEMES = new Set(['light', 'dark']);
const CLASS_LIGHT = 'panel-theme-light';
const CLASS_DARK = 'panel-theme-dark';
const TOAST_AUTO_HIDE_MS = 8000;
const TOAST_EXIT_MS = 180;
const THEME_COPY = {
    dark: {
        title: 'Tema oscuro activo',
        description: 'Puedes seguir navegando mientras decides si quieres usarlo solo por ahora o guardarlo.',
        note: 'Si no lo guardas, el tema oscuro se mantendra solo durante esta sesion.',
        temporaryLabel: 'Solo esta sesion',
        persistLabel: 'Mantener oscuro como predeterminado',
        iconClass: 'ri-moon-clear-line',
    },
    light: {
        title: 'Tema claro activo',
        description: 'Puedes seguir navegando mientras decides si quieres usarlo solo por ahora o guardarlo.',
        note: 'Si no lo guardas, el tema claro se mantendra solo durante esta sesion.',
        temporaryLabel: 'Solo esta sesion',
        persistLabel: 'Mantener claro como predeterminado',
        iconClass: 'ri-sun-line',
    },
};

function readMeta(name) {
    return document.querySelector(`meta[name="${name}"]`)?.content ?? '';
}

function normalizeTheme(value) {
    const lower = String(value || '').toLowerCase();
    return VALID_THEMES.has(lower) ? lower : 'light';
}

const root = document.documentElement;
const csrfToken = readMeta('csrf-token');
const updateUrl = readMeta('panel-theme-update-url');
const themeUserId = readMeta('panel-theme-user-id');
const themeSessionId = readMeta('panel-theme-session-id');
const preferenceToast = document.querySelector('[data-theme-preference-toast]');
const preferenceIcon = preferenceToast?.querySelector('[data-theme-preference-icon]');
const preferenceTitle = preferenceToast?.querySelector('[data-theme-preference-title]');
const preferenceDescription = preferenceToast?.querySelector('[data-theme-preference-description]');
const preferenceNote = preferenceToast?.querySelector('[data-theme-preference-note]');
const preferenceTemporary = preferenceToast?.querySelector('[data-theme-preference-temporary]');
const preferencePersist = preferenceToast?.querySelector('[data-theme-preference-persist]');
const preferenceDismissButtons = preferenceToast?.querySelectorAll('[data-theme-preference-dismiss]') ?? [];

let persistedTheme = normalizeTheme(readMeta('panel-theme-initial'));
let activeSaveRequest = null;
let pendingToastState = null;
let toastHideTimer = null;
let toastExitTimer = null;

function getCurrentTheme() {
    return normalizeTheme(root.getAttribute('data-panel-theme'));
}

function getTemporaryThemeStorageKey() {
    if (!themeUserId || !themeSessionId) {
        return '';
    }

    return `panel-theme-temp:${themeUserId}:${themeSessionId}`;
}

function readTemporaryTheme() {
    const storageKey = getTemporaryThemeStorageKey();

    if (!storageKey) {
        return '';
    }

    try {
        const value = localStorage.getItem(storageKey);
        return VALID_THEMES.has(value) ? value : '';
    } catch (error) {
        return '';
    }
}

function writeTemporaryTheme(theme) {
    const storageKey = getTemporaryThemeStorageKey();

    if (!storageKey) {
        return;
    }

    try {
        localStorage.setItem(storageKey, normalizeTheme(theme));
    } catch (error) {
        // Ignore storage failures; the current page can still keep the in-memory theme.
    }
}

function clearTemporaryTheme() {
    const storageKey = getTemporaryThemeStorageKey();

    if (!storageKey) {
        return;
    }

    try {
        localStorage.removeItem(storageKey);
    } catch (error) {
        // Ignore storage failures; future loads will use the persisted preference.
    }
}

function applyTheme(theme) {
    const normalized = normalizeTheme(theme);
    root.classList.remove(CLASS_LIGHT, CLASS_DARK);
    root.classList.add(normalized === 'dark' ? CLASS_DARK : CLASS_LIGHT);
    root.setAttribute('data-panel-theme', normalized);
    root.style.colorScheme = normalized;
    return normalized;
}

async function persistThemeOnServer(theme) {
    if (!updateUrl || !csrfToken) {
        return false;
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
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ theme: normalizeTheme(theme) }),
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`Theme save failed with status ${response.status}`);
        }

        return true;
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.warn('No se pudo guardar la preferencia de tema.', error);
        }

        return false;
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
        toggle.setAttribute('title', isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro');

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

function setTheme(theme) {
    const normalized = applyTheme(theme);
    syncToggleState(normalized);
    return normalized;
}

function syncPreferenceToastContent(theme) {
    const copy = THEME_COPY[normalizeTheme(theme)];

    if (preferenceTitle) {
        preferenceTitle.textContent = copy.title;
    }

    if (preferenceDescription) {
        preferenceDescription.textContent = copy.description;
    }

    if (preferenceNote) {
        preferenceNote.textContent = copy.note;
    }

    if (preferenceTemporary) {
        preferenceTemporary.textContent = copy.temporaryLabel;
    }

    if (preferencePersist) {
        preferencePersist.textContent = copy.persistLabel;
    }

    if (preferenceIcon) {
        preferenceIcon.className = copy.iconClass;
    }
}

function clearToastTimers() {
    if (toastHideTimer) {
        window.clearTimeout(toastHideTimer);
        toastHideTimer = null;
    }

    if (toastExitTimer) {
        window.clearTimeout(toastExitTimer);
        toastExitTimer = null;
    }
}

function scheduleToastHide() {
    if (!preferenceToast || preferenceToast.hidden) {
        return;
    }

    if (toastHideTimer) {
        window.clearTimeout(toastHideTimer);
    }

    toastHideTimer = window.setTimeout(() => {
        closePreferenceToast();
    }, TOAST_AUTO_HIDE_MS);
}

function openPreferenceToast(targetTheme) {
    if (!preferenceToast) {
        return;
    }

    pendingToastState = {
        targetTheme: normalizeTheme(targetTheme),
    };

    syncPreferenceToastContent(pendingToastState.targetTheme);
    clearToastTimers();
    preferenceToast.hidden = false;

    window.requestAnimationFrame(() => {
        preferenceToast.classList.add('is-open');
    });

    scheduleToastHide();
}

function closePreferenceToast() {
    clearToastTimers();
    pendingToastState = null;

    if (!preferenceToast || preferenceToast.hidden) {
        return;
    }

    preferenceToast.classList.remove('is-open');
    toastExitTimer = window.setTimeout(() => {
        if (preferenceToast) {
            preferenceToast.hidden = true;
        }
    }, TOAST_EXIT_MS);
}

function initThemeToggle() {
    const temporaryTheme = readTemporaryTheme();

    if (temporaryTheme && temporaryTheme !== persistedTheme) {
        setTheme(temporaryTheme);
    } else {
        clearTemporaryTheme();
        setTheme(persistedTheme);
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const currentTheme = getCurrentTheme();
            const targetTheme = currentTheme === 'dark' ? 'light' : 'dark';

            if (targetTheme === persistedTheme) {
                clearTemporaryTheme();
                setTheme(persistedTheme);
                closePreferenceToast();
                return;
            }

            writeTemporaryTheme(targetTheme);
            setTheme(targetTheme);
            openPreferenceToast(targetTheme);
        });
    });

    preferenceTemporary?.addEventListener('click', () => {
        if (!pendingToastState) {
            return;
        }

        writeTemporaryTheme(pendingToastState.targetTheme);
        setTheme(pendingToastState.targetTheme);
        closePreferenceToast();
    });

    preferencePersist?.addEventListener('click', async () => {
        if (!pendingToastState) {
            return;
        }

        const { targetTheme } = pendingToastState;
        setTheme(targetTheme);

        const saved = await persistThemeOnServer(targetTheme);

        if (saved) {
            persistedTheme = targetTheme;
            clearTemporaryTheme();
        } else {
            writeTemporaryTheme(targetTheme);
        }

        closePreferenceToast();
    });

    preferenceDismissButtons.forEach((button) => {
        button.addEventListener('click', () => {
            closePreferenceToast();
        });
    });

    preferenceToast?.addEventListener('mouseenter', () => {
        if (toastHideTimer) {
            window.clearTimeout(toastHideTimer);
            toastHideTimer = null;
        }
    });

    preferenceToast?.addEventListener('mouseleave', () => {
        if (preferenceToast?.classList.contains('is-open')) {
            scheduleToastHide();
        }
    });

    preferenceToast?.addEventListener('focusin', () => {
        if (toastHideTimer) {
            window.clearTimeout(toastHideTimer);
            toastHideTimer = null;
        }
    });

    preferenceToast?.addEventListener('focusout', () => {
        window.setTimeout(() => {
            if (preferenceToast?.classList.contains('is-open') && !preferenceToast.contains(document.activeElement)) {
                scheduleToastHide();
            }
        }, 0);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && preferenceToast?.classList.contains('is-open')) {
            closePreferenceToast();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initThemeToggle, { once: true });
} else {
    initThemeToggle();
}
