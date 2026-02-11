(function () {
  const originInputs = Array.from(document.querySelectorAll('input[name="source"]'));
  const originBadge = document.querySelector('[data-origin-badge]');
  const medicalSection = document.querySelector('[data-origin-section="MEDICAL_ORDER"]');
  const routineSection = document.querySelector('[data-origin-section="ROUTINE"]');
  const orderSelect = document.getElementById('medical_order_id');
  const examSelect = document.getElementById('lab_test_id');
  const prepText = document.querySelector('[data-prep-text]');
  const examIndications = document.querySelector('[data-exam-indications]');
  const doctorNotes = document.querySelector('[data-doctor-notes]');
  const examWrapper = document.querySelector('[data-exam-wrapper]');

  function currentOrigin() {
    return originInputs.find((input) => input.checked).value || 'ROUTINE';
  }

  function setBadge(origin) {
    if (!originBadge) return;
    if (origin === 'MEDICAL_ORDER') {
      originBadge.textContent = 'Con orden medica';
      originBadge.classList.remove('is-routine');
    } else {
      originBadge.textContent = 'Rutina';
      originBadge.classList.add('is-routine');
    }
  }

  function setOptionAvailability(option, allowed) {
    option.hidden = !allowed;
    option.disabled = !allowed;
  }

  function resetExamOptions() {
    if (!examSelect) return;
    const options = Array.from(examSelect.options);
    options.forEach((opt) => {
      if (!opt.value) return;
      setOptionAvailability(opt, true);
    });
  }

  function filterRoutineOptions(isRoutine) {
    if (!examSelect) return;
    const options = Array.from(examSelect.options);
    options.forEach((opt) => {
      if (!opt.value) return;
      const rutina = opt.dataset.rutina === '1';
      const requiereOrden = opt.dataset.requiereOrden === '1';
      const allowed = !isRoutine || (rutina && !requiereOrden);
      setOptionAvailability(opt, allowed);
    });
    const selected = examSelect.selectedOptions && examSelect.selectedOptions[0];
    if (selected && selected.disabled) {
      examSelect.value = '';
    }
  }

  function updatePrep() {
    if (!examSelect || !prepText) return;
    const option = examSelect.selectedOptions && examSelect.selectedOptions[0];
    const fallback = examSelect.dataset.prepEmpty || 'Selecciona un examen para ver la preparacion.';
    const missing = examSelect.dataset.prepMissing || 'Este examen no tiene preparacion registrada. Contacte a la clinica.';
    const prep = option.dataset.prep || '';
    if (!option || !option.value) {
      prepText.textContent = fallback;
      return;
    }
    prepText.textContent = prep.trim().length  prep : missing;
  }

  function updateExamIndications() {
    if (!examSelect || !examIndications) return;
    const option = examSelect.selectedOptions && examSelect.selectedOptions[0];
    const fallback = examSelect.dataset.indicacionesEmpty || 'Selecciona un examen para ver las indicaciones del examen.';
    const missing = examSelect.dataset.indicacionesMissing || 'Sin indicaciones adicionales para este examen.';
    const indicaciones = option.dataset.indicaciones || '';
    if (!option || !option.value) {
      examIndications.textContent = fallback;
      return;
    }
    examIndications.textContent = indicaciones.trim().length  indicaciones : missing;
  }

  function updateExamDetails() {
    updatePrep();
    updateExamIndications();
  }

  function restrictExamToTest(testId) {
    if (!examSelect) return;
    const options = Array.from(examSelect.options);
    options.forEach((opt) => {
      if (!opt.value) return;
      const allowed = String(opt.value) === String(testId);
      setOptionAvailability(opt, allowed);
    });
  }

  function updateFromOrder() {
    if (!orderSelect || !examSelect) return;
    const option = orderSelect.selectedOptions && orderSelect.selectedOptions[0];
    const testId = option.dataset.testId;
    if (testId) {
      restrictExamToTest(testId);
      examSelect.value = testId;
      examSelect.removeAttribute('disabled');
    } else {
      resetExamOptions();
      examSelect.value = '';
      examSelect.setAttribute('disabled', 'disabled');
    }
    if (doctorNotes) {
      const notes = option.dataset.notes;
      doctorNotes.textContent = notes && notes.trim().length
         notes
        : 'Sin indicaciones adicionales.';
    }
    updateExamDetails();
  }

  function toggleExamReadonly(isReadonly) {
    if (!examWrapper) return;
    if (isReadonly) {
      examWrapper.classList.add('is-readonly');
    } else {
      examWrapper.classList.remove('is-readonly');
    }
  }

  function syncOrigin() {
    const origin = currentOrigin();
    setBadge(origin);
    if (medicalSection) medicalSection.hidden = origin !== 'MEDICAL_ORDER';
    if (routineSection) routineSection.hidden = origin !== 'ROUTINE';

    if (origin === 'MEDICAL_ORDER') {
      if (orderSelect.dataset.hasOrders === '1') {
        orderSelect.removeAttribute('disabled');
      }
      orderSelect.setAttribute('required', 'required');
      resetExamOptions();
      updateFromOrder();
      toggleExamReadonly(true);
    } else {
      orderSelect.setAttribute('disabled', 'disabled');
      orderSelect.removeAttribute('required');
      examSelect.removeAttribute('disabled');
      toggleExamReadonly(false);
      filterRoutineOptions(true);
      if (doctorNotes) {
        doctorNotes.textContent = 'Sin indicaciones adicionales.';
      }
      updateExamDetails();
    }
  }

  originInputs.forEach((input) => {
    input.addEventListener('change', syncOrigin);
  });
  orderSelect.addEventListener('change', updateFromOrder);
  examSelect.addEventListener('change', updateExamDetails);

  syncOrigin();
})();
