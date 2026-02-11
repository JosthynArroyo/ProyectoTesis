# Centralización de Validaciones

Backend
- Reglas reutilizables: `app/Support/ValidationRules.php` (correo único, cédula única, teléfono de 10 dígitos, contraseña con complejidad).
- Mensajes en español: `resources/lang/es/validation.php` (mensajes globales y personalizados por campo).
- FormRequests y controladores usan estas reglas (por ejemplo `app/Http/Requests/Admin/UserRequest.php`, `app/Http/Requests/Admin/UpdateUserRequest.php`, `app/Http/Controllers/*`).

Frontend
- Restricción de solo números y longitud fija: `resources/js/forms/numeric-inputs.js` usando `data-digits="10"`.
- Mostrar/ocultar contraseña: `resources/js/auth/password-toggle.js` (clases `.toggle-eye` y `.btn-eye`).
- Mensajes bajo cada campo: `@error(...)` en las vistas de `resources/views/**`.
