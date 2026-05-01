document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.dashboard-sidebar');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    const modal = document.getElementById('demo-action-blocked-modal');
    const modalMessage = document.getElementById('demo-action-blocked-message');
    const closeButtons = document.querySelectorAll('[data-demo-close-modal], #close-demo-modal');
    const defaultMessage = 'Disponible solo para usuarios registrados. Esta es una demostracion con datos simulados.';

    const openModal = (message = defaultMessage) => {
        if (!modal) {
            window.alert(message);
            return;
        }

        modalMessage.textContent = message;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };

    const closeModal = () => {
        if (!modal) {
            return;
        }

        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    };

    const openSidebar = () => {
        if (!sidebar || !overlay) {
            return;
        }

        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };

    const closeSidebar = () => {
        if (!sidebar || !overlay) {
            return;
        }

        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        if (modal?.classList.contains('hidden') !== false) {
            document.body.classList.remove('overflow-hidden');
        }
    };

    closeButtons.forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    overlay?.addEventListener('click', closeSidebar);
    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            openSidebar();
        });
    });
    document.querySelectorAll('[data-sidebar-close]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            closeSidebar();
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
            closeSidebar();
        }
    });

    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('click', (event) => {
        const target = event.target.closest('a, button, [data-demo-blocked]');
        if (!target) {
            return;
        }

        if (
            target.hasAttribute('data-demo-allow') ||
            target.hasAttribute('data-theme-toggle') ||
            target.hasAttribute('data-sidebar-toggle') ||
            target.hasAttribute('data-sidebar-close') ||
            target.hasAttribute('data-role-switcher-link')
        ) {
            return;
        }

        const href = target.getAttribute('href');
        const isDemoNavigation = target.tagName === 'A'
            && typeof href === 'string'
            && (href.includes('/demo') || href.startsWith('#'));

        if (target.classList.contains('demo-action-blocked') || target.hasAttribute('data-demo-blocked')) {
            event.preventDefault();
            openModal(target.getAttribute('data-demo-message') || defaultMessage);
            return;
        }

        if (target.tagName === 'A' && !isDemoNavigation) {
            event.preventDefault();
            openModal(target.getAttribute('data-demo-message') || defaultMessage);
        }
    });
});
