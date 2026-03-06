import '../css/app.css';
import './bootstrap';
import 'flowbite';

// Nucleo de UI compartido
import './navbar';
import './welcome-login-modal';
import './face-login-modal';
import './sidebar-toggle';
import './welcome-carousel';
import './servicios';
import './auth/password-toggle';
import './panel-back-button';
import './forms/numeric-inputs';
import './forms/motivo-chips';
import './table-responsive-x';

document.addEventListener('DOMContentLoaded', () => {
  const closeKebabs = () => {
    document.querySelectorAll('.kebab-menu[data-open="1"]').forEach((menu) => {
      menu.setAttribute('data-open', '0');
    });
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-kebab]');
    if (!trigger) {
      closeKebabs();
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const menu = document.getElementById(trigger.dataset.kebab || '');
    if (!menu) {
      return;
    }

    const isOpen = menu.getAttribute('data-open') === '1';
    closeKebabs();
    menu.setAttribute('data-open', isOpen ? '0' : '1');
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeKebabs();
    }
  });
});
