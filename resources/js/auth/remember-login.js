const STORAGE_KEY = 'clinica.login.rememberedEmail';

const readSavedEmail = () => {
  try {
    return window.localStorage.getItem(STORAGE_KEY) || '';
  } catch {
    return '';
  }
};

const saveEmail = (email) => {
  try {
    window.localStorage.setItem(STORAGE_KEY, email);
  } catch {
    // The backend remember cookie still handles persistent login.
  }
};

const forgetEmail = () => {
  try {
    window.localStorage.removeItem(STORAGE_KEY);
  } catch {
    // Ignore browsers that block storage.
  }
};

function initRememberLogin() {
  document.querySelectorAll('[data-remember-login-form]').forEach((form) => {
    if (form.dataset.rememberLoginReady === '1') {
      return;
    }
    form.dataset.rememberLoginReady = '1';

    const emailInput = form.querySelector('[data-remember-login-email]');
    const checkbox = form.querySelector('[data-remember-login-checkbox]');

    if (!(emailInput instanceof HTMLInputElement) || !(checkbox instanceof HTMLInputElement)) {
      return;
    }

    const savedEmail = readSavedEmail();

    if (savedEmail && !emailInput.value) {
      emailInput.value = savedEmail;
      checkbox.checked = true;
    }

    checkbox.addEventListener('change', () => {
      if (!checkbox.checked) {
        forgetEmail();
      }
    });

    form.addEventListener('submit', () => {
      const email = emailInput.value.trim();

      if (checkbox.checked && email) {
        saveEmail(email);
        return;
      }

      forgetEmail();
    });
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initRememberLogin, { once: true });
} else {
  initRememberLogin();
}
