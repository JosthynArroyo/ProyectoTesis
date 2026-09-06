document.addEventListener('DOMContentLoaded', function() {
    const dobInput = document.getElementById('fecha_nacimiento');
    const submitBtn = document.getElementById('submitBtn');
    const ageAlert = document.getElementById('ageAlert');
    const ageAlertText = document.getElementById('ageAlertText');
    const form = document.getElementById('dependienteForm');

    function calculateAge(birthday) {
        const today = new Date();
        const birthDate = new Date(birthday);
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        return age;
    }

    function validateAge() {
        if (!dobInput.value) {
            ageAlert.classList.add('hidden');
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-60', 'cursor-not-allowed');
            return true;
        }

        const age = calculateAge(dobInput.value);

        if (age >= 18 && age <= 65) {
            ageAlertText.textContent = 'Aviso: El paciente ingresado es mayor de edad (' + age + ' años). Por políticas del sistema, las personas entre 18 y 65 años deben registrar y gestionar su propia cuenta principal.';
            ageAlert.classList.remove('hidden');
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-60', 'cursor-not-allowed');
            return false;
        } else {
            ageAlert.classList.add('hidden');
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-60', 'cursor-not-allowed');
            return true;
        }
    }

    dobInput.addEventListener('change', validateAge);
    dobInput.addEventListener('input', validateAge);

    form.addEventListener('submit', function(e) {
        if (!validateAge()) {
            e.preventDefault();
        }
    });

    // Instant Avatar Preview
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreview = document.getElementById('avatarPreview');
    const avatarFallback = document.getElementById('avatarFallback');

    if (avatarInput) {
        avatarInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const url = URL.createObjectURL(file);
                if (avatarPreview) {
                    avatarPreview.src = url;
                    avatarPreview.classList.remove('hidden');
                }
                if (avatarFallback) {
                    avatarFallback.classList.add('hidden');
                }
            }
        });
    }

    // Run initially in case of validation back with old input
    validateAge();
});
