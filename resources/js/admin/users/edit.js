document.addEventListener('DOMContentLoaded', () => {
  const dateInput = document.querySelector('input[name="fecha_nacimiento"]');
  const badges = document.querySelectorAll('.js-age-badge');

  const setAgeText = (text) => {
    badges.forEach((badge) => {
      badge.textContent = text;
    });
  };

  const calculateAge = () => {
    if (!dateInput || !dateInput.value) {
      setAgeText('--');
      return;
    }

    const parts = dateInput.value.split('-').map(Number);
    if (parts.length !== 3 || parts.some((part) => Number.isNaN(part))) {
      setAgeText('--');
      return;
    }

    const [year, month, day] = parts;
    const birthDate = new Date(year, month - 1, day);
    if (Number.isNaN(birthDate.getTime())) {
      setAgeText('--');
      return;
    }

    const today = new Date();
    if (birthDate > today) {
      setAgeText('--');
      return;
    }

    let age = today.getFullYear() - year;
    const monthDiff = today.getMonth() - (month - 1);
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < day)) {
      age -= 1;
    }

    if (age < 0) {
      setAgeText('--');
      return;
    }

    setAgeText(`${age} años`);
  };

  if (!dateInput || badges.length === 0) {
    return;
  }

  calculateAge();
  dateInput.addEventListener('change', calculateAge);
  dateInput.addEventListener('input', calculateAge);
});
