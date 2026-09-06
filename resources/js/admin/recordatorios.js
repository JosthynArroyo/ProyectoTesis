document.addEventListener('click', async function (event) {
  const trigger = event.target.closest('[data-copy-target]');
  if (!trigger) {
    return;
  }

  const source = document.getElementById(trigger.dataset.copyTarget);
  if (!source) {
    return;
  }

  const text = source.value || source.textContent || '';
  const original = trigger.dataset.originalLabel || trigger.innerHTML;
  trigger.dataset.originalLabel = original;

  try {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(text);
    } else {
      const helper = document.createElement('textarea');
      helper.value = text;
      document.body.appendChild(helper);
      helper.select();
      document.execCommand('copy');
      document.body.removeChild(helper);
    }

    trigger.innerHTML = '<i class="ri-check-line"></i> Mensaje copiado';
    window.setTimeout(() => {
      trigger.innerHTML = original;
    }, 1800);
  } catch (error) {
    trigger.innerHTML = '<i class="ri-close-line"></i> No se pudo copiar';
    window.setTimeout(() => {
      trigger.innerHTML = original;
    }, 1800);
  }
});
