# Flujo de QA end-to-end (smoke test)

Este documento valida el sistema completo luego de los cambios: roles, personalización, citas y módulo SOAP.

> Zona horaria usada por el sistema: `America/Guayaquil` (importante para horarios y la regla de +1 hora).

---

## A) Preparación

### A.1 Levantar el proyecto en local (comandos reales)
1. Instalar dependencias PHP:
   - `composer install`
2. Instalar dependencias frontend:
   - `npm install`
3. Crear archivo de entorno (si no existe):
   - Copiar `.env.example` a `.env`
4. Generar APP_KEY:
   - `php artisan key:generate`
5. Ejecutar migraciones + seeders:
   - `php artisan migrate --seed`
6. Levantar servidor y Vite:
   - Backend: `php artisan serve`
   - Frontend: `npm run dev`

### A.2 Verificar que mantenimiento está OFF
- **UI real**: entrar como superadmin y abrir `Mantenimiento` (ruta `superadmin.maintenance.edit`).
- Validar que el toggle “Mantenimiento” esté desactivado.
- Nota: por defecto, `maintenance.enabled` es `0` si no hay configuración en `site_settings` (ver `app/Services/SiteSettingsService.php`).

### A.3 Usuarios de prueba tras seed
- Seeder real: `database/seeders/DatabaseSeeder.php`.
- Usuario superadmin creado automáticamente:
  - Email: `superadmin@clinic.test`
  - Password: `superadmin1234`
  - Rol: `superadmin`
- Roles cargados: `superadmin`, `administrador`, `doctor`, `paciente`, `laboratorio` (`database/seeders/RoleSeeder.php`).
- Especialidades cargadas: `Odontologia`, `Pediatria`, `Dermatologia`, `Medicina General`, `Ginecologia`, `Laboratorio Clinico` (`database/seeders/EspecialidadesSeeder.php`).

---

## B) Superadmin

### B.1 Login superadmin
1. Ir a `/login` (redirige al home con login).
2. Credenciales:
   - `superadmin@clinic.test` / `superadmin1234`
3. Validar acceso al dashboard superadmin (`superadmin.dashboard`).

### B.2 Crear admin
1. Ir a menú `Administradores` (ruta `superadmin.admins.index`).
2. Click `Crear` (ruta `superadmin.admins.create`).
3. Crear admin con:
   - Nombre: `Admin QA`
   - Email: `admin.qa@clinic.test`
   - Password: `Adminqa123!`
   - Cédula: `0999999999` (debe ser 10 dígitos, único)
4. Validar:
   - Flash “Administrador creado correctamente.”
   - Aparece en listado con rol `administrador`.

### B.3 Personalización (control de acceso)
1. Como superadmin, entrar a `Solicitudes` (ruta `superadmin.solicitudes.personalizacion.index`).
2. Este flujo se valida cuando el admin lo solicite (ver sección C). Mantener esta página abierta o volver luego.

---

## C) Administrador

### C.1 Login admin
1. Cerrar sesión superadmin.
2. Login con `admin.qa@clinic.test` / `Adminqa123!`.
3. Validar acceso al dashboard admin (`admin.dashboard`).

### C.2 Solicitar acceso a Personalización
1. En menú admin, abrir `Personalizacion`.
2. Debería abrir modal de solicitud (si no tiene permiso).
3. Click `Solicitar` (POST `admin.personalizacion.request`).
4. Validar mensaje/estado “Pendiente”.
5. Volver al superadmin y **aprobar** la solicitud:
   - Ruta: `superadmin.solicitudes.personalizacion.index`.
   - Acción: `Aprobar`.
6. Regresar como admin y validar acceso real a:
   - `admin.personalizacion.bienvenida.edit`.

### C.3 Crear doctor
1. Ir a `Usuarios` (ruta `admin.usuarios.index`).
2. Click `Registrar Usuario` (`admin.usuarios.create`).
3. Crear doctor con:
   - Nombre: `Dr. Juan Perez`
   - Email: `doctor.qa@clinic.test`
   - Password: `Doctorqa123!`
   - Cédula: `0999999998`
   - Rol: `doctor`
   - Especialidad: `Medicina General`
   - Precio consulta (opcional): `20`
4. Validar:
   - Flash “Usuario creado correctamente.”
   - Doctor aparece en listado.

### C.4 Crear paciente
1. En `Registrar Usuario` crear paciente:
   - Nombre: `Maria Gomez`
   - Email: `paciente.qa@clinic.test`
   - Password: `Paciente123!`
   - Cédula: `0999999997`
   - Rol: `paciente`
2. Validar:
   - Flash “Usuario creado correctamente.”

### C.5 Validar filtro por especialidad
- Esto se valida cuando el paciente agenda (ver sección E):
  - Al seleccionar `Medicina General`, debe aparecer `Dr. Juan Perez`.

---

## D) Doctor

### D.1 Login doctor
1. Login con `doctor.qa@clinic.test` / `Doctorqa123!`.
2. Validar acceso a dashboard doctor (`doctor.dashboard`).

### D.2 Crear horario
1. Ir a `Mi horario` (`doctor.horario.index`).
2. Crear horario manual:
   - Fecha: **mañana** (ej. `YYYY-MM-DD`)
   - Hora inicio: `09:00`
   - Hora fin: `12:00`
   - Intervalo: `30`
3. Validar:
   - Flash “Horario creado.”
   - Horario aparece en la lista.

### D.3 Ver agenda y listado de citas
1. Ir a `Mis Citas` (`doctor.citas`).
2. Debe estar vacío hasta que el paciente agende.
3. Ir a `Agenda Semanal` (`doctor.agenda`) y validar que muestra el rango y horarios.

---

## E) Paciente

### E.1 Login paciente
1. Login con `paciente.qa@clinic.test` / `Paciente123!`.
2. Validar acceso a dashboard paciente (`paciente.dashboard`).

### E.2 Agendar cita (UI real)
1. Ir a `Agendar Cita` (`paciente.crear-cita`).
2. Seleccionar:
   - Especialidad: `Medicina General`
   - Doctor: `Dr. Juan Perez` (debe aparecer al seleccionar especialidad)
   - Fecha: **mañana** (o fecha futura válida)
   - Hora: seleccionar un slot disponible
3. Validaciones esperadas:
   - Si intentas una hora dentro de 1 hora desde ahora, debe bloquearse.
   - Si seleccionas hora no disponible, no aparece en el combo.
4. Guardar:
   - Esperar flash: “Cita creada con éxito. Confirmación enviada y doctor notificado.”

### E.3 Validar cita creada
1. Ir a `Mis Citas` (`paciente.citas`).
2. La cita debe aparecer en estado `pendiente`.

---

## F) Doctor gestiona la cita

### F.1 Aceptar cita
1. Login doctor.
2. Ir a `Mis Citas` (`doctor.citas`).
3. Aceptar la cita (`doctor.citas.aceptar`).
4. Validaciones esperadas:
   - Flash: “Cita confirmada.”
   - Estado cambia a `confirmada`.
   - Debe reflejarse en la agenda.

### F.2 (Opcional) Rechazar/cancelar
- Solo si quieres probar reglas: usar `Rechazar` (estado `cancelada`).
- Esto deshabilita SOAP (no se puede crear). Si pruebas esto, vuelve a crear otra cita para el flujo SOAP.

---

## G) Nota clínica (SOAP)

### G.1 Abrir SOAP
1. Desde `Mis Citas` en estado `confirmada`, click `Nota clínica (SOAP)`.
2. Ruta real: `doctor.citas.soap`.

### G.2 Guardar borrador
1. Completar algunos campos (mínimo uno).
2. Click `Guardar borrador`.
3. Validaciones esperadas:
   - No debe exigir diagnóstico principal ni plan.
   - Flash: “Borrador guardado correctamente.”
   - Estado sigue “Borrador”.
   - Se registra `cita_eventos.tipo = soap_guardada`.

### G.3 Firmar/Cerrar
1. Completar obligatorios:
   - Motivo de consulta
   - Plan general
   - Al menos 1 diagnóstico principal (tipo `principal` con texto)
2. Click `Firmar y cerrar`.
3. Validaciones esperadas:
   - Flash: “Nota clínica firmada correctamente.”
   - Estado pasa a `signed`.
   - Campos quedan solo lectura.
   - Se registra `cita_eventos.tipo = soap_firmada`.

### G.4 Enmiendas
1. Con nota firmada, agregar enmienda:
   - Motivo
   - Detalle
2. Validar:
   - Enmienda queda listada con autor y fecha.
   - Se registra `cita_eventos.tipo = soap_enmienda`.

> Nota: en auditoría (`admin.cambios-citas`) estos tipos no aparecen en el filtro, pero sí en el listado si no filtras por tipo.

---

## H) Marcar cita como realizada

### H.1 Bloqueo sin SOAP firmada
1. Si intentas `Marcar como realizada` sin SOAP firmada:
   - Debe redirigir a SOAP con error:
     “Debes firmar la nota clínica (SOAP) antes de marcar la cita como realizada.”

### H.2 Realizar con SOAP firmada
1. Con nota firmada, click `Marcar cita como realizada`.
2. Validaciones esperadas:
   - Flash: “Cita marcada como realizada.”
   - Estado cambia a `realizada`.
   - Aparece como realizada en doctor y paciente.

---

## I) Historial clínico del paciente

### I.1 Vista del paciente
1. Login paciente.
2. Ir a `Historial clinico` (`paciente.historial`).
3. Validar:
   - Aparece la nota firmada ordenada por fecha.
4. Abrir nota:
   - Ruta `paciente.historial.show`.
   - Debe ser solo lectura (sin edición).

### I.2 Vista del doctor
1. Login doctor.
2. En `Mis Citas` o historial paciente, abrir `Historial paciente`.
3. Validar:
   - Solo ve notas firmadas de pacientes que atendió.

### I.3 Vista del admin
1. Login admin.
2. Ir a `Historial clinico` (`admin.historial.index`).
3. Validar:
   - Ve notas firmadas globales.
   - Puede abrir detalle (`admin.historial.show`).

---

# 2) Datos de prueba recomendados

- **Admin**
  - Nombre: `Admin QA`
  - Email: `admin.qa@clinic.test`
  - Password: `Adminqa123!`
  - Cédula: `0999999999`

- **Doctor**
  - Nombre: `Dr. Juan Perez`
  - Email: `doctor.qa@clinic.test`
  - Password: `Doctorqa123!`
  - Cédula: `0999999998`
  - Especialidad: `Medicina General`

- **Paciente**
  - Nombre: `Maria Gomez`
  - Email: `paciente.qa@clinic.test`
  - Password: `Paciente123!`
  - Cédula: `0999999997`

- **Horario ejemplo**
  - Fecha: mañana (timezone `America/Guayaquil`)
  - Hora inicio: `09:00`
  - Hora fin: `12:00`
  - Intervalo: `30`

- **Cita ejemplo**
  - Fecha: mañana
  - Hora: `10:00`

---

# 3) Validaciones esperadas (resumen por paso)

- Crear cita: estado inicial `pendiente`, flash de éxito en paciente.
- Aceptar cita: cambia a `confirmada`.
- SOAP borrador: permite guardar sin campos obligatorios estrictos.
- SOAP firmada: exige motivo, plan y diagnóstico principal.
- SOAP firmada bloquea edición.
- Marcar realizada: bloquea si SOAP no firmada.
- Historial paciente/admin: solo muestra SOAP firmadas.

---

# 4) Resumen de troubleshooting (si falla X, revisar Y)

- **No aparecen doctores por especialidad**: revisar asignación de especialidad al doctor (UI `admin.usuarios.create`) y estado `active` del usuario.
- **No aparecen slots**: revisar horario en `doctor.horario.index` y reglas de +1 hora (`DoctorSlotController`).
- **No se puede firmar SOAP**: revisar validación en `SignSoapRequest.php` (motivo, plan y diagnóstico principal).
- **No permite marcar realizada**: revisar si nota SOAP está `signed` (`CitaController@realizar`).
- **Historial vacío**: solo se muestran SOAP firmadas (`Paciente/HistorialController`).
- **Personalización bloqueada para admin**: verificar aprobación en `superadmin.solicitudes.personalizacion.index`.
