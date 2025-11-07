const sideMenu = document.querySelector('aside');
const menuBtn = document.querySelector('#menu_bar');
const closeBtn = document.querySelector('aside .close'); 

const themeToggler = document.querySelector('.theme-toggler');

menuBtn.addEventListener('click', () => {
    sideMenu.style.display = "block";
});

closeBtn.addEventListener('click', () => {
    sideMenu.style.display = "none";
});

themeToggler.addEventListener('click', () => {
    document.body.classList.toggle('dark-theme-variables');
    themeToggler.querySelector('span:nth-child(1)').classList.toggle('active');
    themeToggler.querySelector('span:nth-child(2)').classList.toggle('active');
});


document.getElementById('exportForm').addEventListener('submit', function(e) {
    alert('Descargando archivo Excel...');
});
  


document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('citasBody');
    const btnMore = document.getElementById('btnShowMore');
    const btnLess = document.getElementById('btnShowLess');

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

    const resumenUrl = "{{ route('admin.dashboard.resumen') }}";
    async function refreshKPIs(){
        try{
            const res = await fetch(resumenUrl, { headers: { 'X-Requested-With':'XMLHttpRequest' }});
            if(!res.ok) return;
            const data = await res.json();
            document.getElementById('kpi-agendadas').textContent = data.agendadas;
            document.getElementById('kpi-completadas').textContent = data.completadas;
            document.getElementById('kpi-canceladas').textContent = data.canceladas;
        }catch(e){}
    }
    refreshKPIs();
    setInterval(refreshKPIs, 15000);
});