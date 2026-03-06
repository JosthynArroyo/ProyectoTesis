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
