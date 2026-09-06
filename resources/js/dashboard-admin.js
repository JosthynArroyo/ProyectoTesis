document.addEventListener('DOMContentLoaded', () => {
    // 1. Export form handler
    const exportForm = document.getElementById('exportForm');
    if (exportForm) {
        exportForm.addEventListener('submit', function() {
            // Optional user alert, but let it proceed
        });
    }

    // 2. Pagination for recent appointments list
    const tbody = document.getElementById('citasBody');
    const btnMore = document.getElementById('btnShowMore');
    const btnLess = document.getElementById('btnShowLess');

    if (tbody && btnMore && btnLess) {
        const STEP = 8;
        let visible = STEP;

        const rows = () => Array.from(tbody.querySelectorAll('tr'));

        function renderRows() {
            const rs = rows();
            rs.forEach((tr, i) => tr.style.display = (i < visible) ? '' : 'none');
            btnMore.style.display = (visible < rs.length) ? '' : 'none';
            btnLess.style.display = (rs.length > STEP && visible > STEP) ? '' : 'none';
        }

        btnMore.addEventListener('click', () => { visible += STEP; renderRows(); });
        btnLess.addEventListener('click', () => { visible = STEP; renderRows(); });

        renderRows();
    }

    // 3. Auto-refresh global KPIs from data-dashboard-resumen
    const root = document.querySelector('[data-dashboard-page]');
    const resumenUrl = root?.dataset.dashboardResumen;
    if (resumenUrl) {
        async function refreshKPIs() {
            if (document.hidden) {
                return;
            }
            try {
                const res = await fetch(resumenUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) return;
                const data = await res.json();

                const elAg = document.getElementById('kpi-agendadas');
                const elCo = document.getElementById('kpi-completadas');
                const elCa = document.getElementById('kpi-canceladas');

                if (elAg) elAg.textContent = data.agendadas ?? 0;
                if (elCo) elCo.textContent = data.completadas ?? 0;
                if (elCa) elCa.textContent = data.canceladas ?? 0;
            } catch (e) {
                console.error('Error refreshing KPIs:', e);
            }
        }

        refreshKPIs();
        setInterval(refreshKPIs, 15000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                refreshKPIs();
            }
        });
    }
});
