const WRAPPER_CLASS = 'table-responsive-x';

const SKIP_ANCESTORS = [
    `.${WRAPPER_CLASS}`,
    '.table-shell',
    '.table-responsive-cards',
    '.dataTables_wrapper',
    '[data-no-table-responsive]',
    '.no-table-responsive',
].join(', ');

function hasParentTable(table) {
    const parentTable = table.parentElement?.closest('table');
    return Boolean(parentTable);
}

function shouldWrapTable(table) {
    if (!(table instanceof HTMLTableElement)) {
        return false;
    }

    if (table.dataset.noTableResponsive === '1') {
        return false;
    }

    if (table.closest(SKIP_ANCESTORS)) {
        return false;
    }

    if (hasParentTable(table)) {
        return false;
    }

    return true;
}

function wrapTable(table) {
    if (!shouldWrapTable(table)) {
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = WRAPPER_CLASS;
    wrapper.dataset.generated = 'true';
    table.parentNode?.insertBefore(wrapper, table);
    wrapper.appendChild(table);
}

function processTables(root = document) {
    if (!root?.querySelectorAll) {
        return;
    }

    root.querySelectorAll('table').forEach((table) => wrapTable(table));
}

function setupTableResponsiveX() {
    processTables(document);

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (!(node instanceof HTMLElement)) {
                    continue;
                }

                if (node.matches('table')) {
                    wrapTable(node);
                    continue;
                }

                processTables(node);
            }
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupTableResponsiveX, { once: true });
} else {
    setupTableResponsiveX();
}
