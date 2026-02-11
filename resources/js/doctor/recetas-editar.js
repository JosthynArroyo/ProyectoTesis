document.addEventListener('DOMContentLoaded', () => {
  const wrap = document.querySelector('.rx-wrap');
  const resendCheckbox = document.getElementById('reenviar');
  const note = document.getElementById('reenviar-note');
  const pill = document.getElementById('reenviar-pill');
  const submitBtn = document.getElementById('btn-guardar');
  const btnText = submitBtn ? submitBtn.querySelector('.btn-text') : null;
  const resendForm = document.getElementById('form-resend-postsave');

  function syncUI() {
    if (!resendCheckbox || !submitBtn || !btnText || !note || !pill) return;

    const isChecked = resendCheckbox.checked;
    btnText.textContent = isChecked ? 'Guardar y reenviar' : 'Guardar cambios';
    note.classList.toggle('is-visible', isChecked);
    pill.classList.toggle('is-visible', isChecked);
  }

  syncUI();
  resendCheckbox.addEventListener('change', syncUI);

  if (wrap.dataset.askResend === '1' && resendForm) {
    setTimeout(() => {
      const ok = window.confirm('La receta se actualizó correctamente.\n¿Deseas reenviar la receta actualizada al paciente ahora');
      if (ok) {
        resendForm.submit();
      }
    }, 80);
  }
});
