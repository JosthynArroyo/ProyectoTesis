document.addEventListener('DOMContentLoaded', () => {
  const zones = Array.from(document.querySelectorAll('[data-dropzone]'));
  if (!zones.length) {
    return;
  }

  const updateText = (zone, file) => {
    const textNode = zone.querySelector('[data-dropzone-text]');
    if (!textNode) {
      return;
    }
    if (!file) {
      textNode.textContent = 'Arrastra el PDF aquí o haz clic para seleccionar.';
      return;
    }
    textNode.textContent = `Archivo: ${file.name}`;
  };

  zones.forEach((zone) => {
    const input = zone.querySelector('[data-dropzone-input]');
    if (!input) {
      return;
    }

    zone.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
      updateText(zone, input.files && input.files[0] ? input.files[0] : null);
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
      zone.addEventListener(eventName, (event) => {
        event.preventDefault();
        event.stopPropagation();
        zone.classList.add('border-teal-400', 'bg-gray-100/70');
      });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
      zone.addEventListener(eventName, (event) => {
        event.preventDefault();
        event.stopPropagation();
        zone.classList.remove('border-teal-400', 'bg-gray-100/70');
      });
    });

    zone.addEventListener('drop', (event) => {
      const files = event.dataTransfer ? event.dataTransfer.files : null;
      if (!files || !files.length) {
        return;
      }
      const file = files[0];
      if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
        updateText(zone, null);
        return;
      }

      const transfer = new DataTransfer();
      transfer.items.add(file);
      input.files = transfer.files;
      updateText(zone, file);
    });
  });
});
