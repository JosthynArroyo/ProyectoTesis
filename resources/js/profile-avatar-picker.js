export function setupProfileAvatarPicker({
  inputId = 'avatarInput',
  previewId = 'avatarPreview',
  triggerSelectors = [],
  keyboardTriggerSelectors = [],
} = {}) {
  const input = document.getElementById(inputId);
  const preview = document.getElementById(previewId);

  if (!input || !preview) return;

  const triggers = uniqueElements(triggerSelectors.flatMap((selector) => [...document.querySelectorAll(selector)]));
  const keyboardTriggers = uniqueElements(
    keyboardTriggerSelectors.flatMap((selector) => [...document.querySelectorAll(selector)])
  );

  if (!triggers.length) return;

  let pickerLocked = false;
  let activePreviewUrl = null;

  const releasePicker = () => {
    window.setTimeout(() => {
      pickerLocked = false;
    }, 250);
  };

  const openPicker = (event) => {
    event?.preventDefault();
    event?.stopPropagation();

    if (pickerLocked) return;

    pickerLocked = true;
    input.value = '';
    input.click();
  };

  triggers.forEach((trigger) => {
    trigger.addEventListener('click', openPicker);
  });

  keyboardTriggers.forEach((trigger) => {
    trigger.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      openPicker(event);
    });
  });

  input.addEventListener('change', (event) => {
    releasePicker();

    const [file] = event.target.files || [];
    if (!file || !isPreviewableImage(file)) return;

    if (activePreviewUrl) {
      URL.revokeObjectURL(activePreviewUrl);
    }

    activePreviewUrl = URL.createObjectURL(file);
    preview.removeAttribute('srcset');
    preview.removeAttribute('sizes');
    preview.src = activePreviewUrl;
  });

  input.addEventListener('cancel', releasePicker);
  window.addEventListener('focus', () => {
    if (pickerLocked) releasePicker();
  });
}

function uniqueElements(elements) {
  return [...new Set(elements.filter(Boolean))];
}

function isPreviewableImage(file) {
  return file.type.startsWith('image/') || /\.(jpe?g|png|webp|svg)$/i.test(file.name);
}
