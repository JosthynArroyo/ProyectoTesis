document.addEventListener('DOMContentLoaded', () => {
  const target = document.querySelector('.record-highlight');
  if (target) {
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
});
