const themeToggler = document.querySelector('.theme-toggler');

themeToggler.addEventListener('click', () => {
  document.body.classList.toggle('dark-theme-variables');
  themeToggler.querySelector('span:nth-child(1)').classList.toggle('active');
  themeToggler.querySelector('span:nth-child(2)').classList.toggle('active');
});

document.addEventListener('DOMContentLoaded', () => {
  const dateInput = document.querySelector('.date input[type="date"]');
  if (dateInput) {
    const d = new Date();
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const todayLocal = `${yyyy}-${mm}-${dd}`;
    dateInput.value = todayLocal;
  }
});
