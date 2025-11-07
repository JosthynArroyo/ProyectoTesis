document.addEventListener('DOMContentLoaded', () => {
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

    const resumenUrlMeta = document.querySelector('meta[name="dashboard-resumen-url"]');
    const resumenUrl = resumenUrlMeta?.content || '/admin/dashboard/resumen';

    async function refreshKPIs(){
        try{
            const res = await fetch(resumenUrl, { headers: { 'X-Requested-With':'XMLHttpRequest' }});
            if(!res.ok) return;
            const data = await res.json();
            const elAg = document.getElementById('kpi-agendadas');
            const elCo = document.getElementById('kpi-completadas');
            const elCa = document.getElementById('kpi-canceladas');
            if (elAg) elAg.textContent = data.agendadas ?? 0;
            if (elCo) elCo.textContent = data.completadas ?? 0;
            if (elCa) elCa.textContent = data.canceladas ?? 0;
        }catch(e){}
    }

    refreshKPIs();
    setInterval(refreshKPIs, 15000);
});
