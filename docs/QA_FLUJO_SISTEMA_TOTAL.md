# Flujo funcional total de pruebas (E2E)

Este documento define un flujo completo para validar el sistema de forma ordenada, desde la creacion del `superadmin` hasta los modulos finales (pagos, laboratorio, chatbot y biometria facial).

Base verificada en rutas reales (`php artisan route:list --except-vendor`): 169 rutas.

## 1) Preparacion del entorno

1. Instalar dependencias:
   - `composer install`
   - `npm install`
2. Configurar entorno:
   - Copiar `.env.example` a `.env`
   - `php artisan key:generate`
3. Ejecutar BD + seed:
   - `php artisan migrate --seed`
4. Crear symlink para archivos publicos:
   - `php artisan storage:link`
5. Levantar app:
   - `php artisan serve`
   - `npm run dev`
6. Si usas cola distinta a `sync`, levantar worker:
   - `php artisan queue:work`

## 2) Usuario inicial y credenciales base

El `superadmin` se crea en `database/seeders/DatabaseSeeder.php`.

- Email: `superadmin@clinic.test`
- Password: `superadmin1234`
- Rol: `superadmin`

Roles disponibles (seed): `superadmin`, `administrador`, `doctor`, `paciente`, `laboratorio`.

## 3) Datos QA recomendados

Usa estos datos para evitar errores de validacion:

- Admin QA:
  - Nombre: `Admin QA`
  - Email: `admin.qa@clinic.test`
  - Password: `Adminqa123!`
  - Confirmacion: `Adminqa123!`
  - Telefono: `0999999991`
  - Cedula: `0999999991`
  - Direccion: `Quito Norte`
  - Fecha nacimiento: `1990-01-01`
  - Sexo: `Masculino`
- Doctor QA:
  - Nombre: `Dr QA Uno`
  - Email: `doctor.qa@clinic.test`
  - Password: `Doctorqa123!`
  - Telefono: `0999999992`
  - Cedula: `0999999992`
  - Especialidad: `Medicina General`
  - Precio: `25`
- Laboratorio QA:
  - Nombre: `Lab QA Uno`
  - Email: `lab.qa@clinic.test`
  - Password: `Labqa123!`
  - Telefono: `0999999993`
  - Cedula: `0999999993`
  - Rol: `laboratorio`
  - Precio: `15`
- Paciente QA:
  - Nombre: `Paciente QA Uno`
  - Email: `paciente.qa@clinic.test`
  - Password: `Paciente123!`
  - Telefono: `0999999994`
  - Cedula: `0999999994`
  - Flags: dejar en `No` (0) para todos inicialmente

## 4) Flujo maestro secuencial (de inicio a fin)

### Fase A. Superadmin y gobernanza

1. Login superadmin (`/login` -> `superadmin.dashboard`).
2. Crear administrador (`superadmin.admins.create` -> `superadmin.admins.store`).
3. Verificar CRUD de admin:
   - Editar (`superadmin.admins.edit/update`)
   - Bloquear, suspender, activar, desactivar
   - Eliminar (no debe permitir eliminarse a si mismo)
4. Probar mantenimiento:
   - Activar en `superadmin.maintenance.edit/update`
   - Verificar que un usuario no superadmin vea pantalla de mantenimiento
   - Verificar que superadmin si puede entrar

### Fase B. Administrador operativo

1. Login admin (`admin.dashboard`).
2. Solicitar acceso a personalizacion (`admin.personalizacion.request`).
3. Volver a superadmin y aprobar solicitud (`superadmin.solicitudes.personalizacion.aprobar`).
4. Volver a admin y validar acceso:
   - `admin.personalizacion.bienvenida.edit/update`
   - `admin.personalizacion.servicios.edit/update`
5. Crear usuarios de prueba en `admin.usuarios.create/store`:
   - Doctor
   - Paciente
   - Laboratorio
6. Validar listado y detalle:
   - `admin.usuarios.index/show/edit/update`
7. Validar acciones de estado:
   - `admin.usuarios.block/suspend/activate/deactivate`
8. Validar exportes:
   - `admin.usuarios.export.excel`
   - `admin.usuarios.export.pdf`

### Fase C. Horarios y citas medicas

1. Login doctor QA.
2. Crear horario (`doctor.horario.store`) para fecha futura:
   - Ejemplo: manana, 09:00 a 12:00
3. Validar agenda (`doctor.agenda`) y listado (`doctor.citas`).
4. Login paciente QA.
5. Agendar cita (`paciente.crear-cita.store`) con el doctor QA.
6. Validar:
   - Cita creada en estado `pendiente`
   - En doctor.citas aparece pendiente
   - Se creo pago en estado `pendiente` (modulo pagos)
7. Doctor acepta cita (`doctor.citas.aceptar`) -> estado `confirmada`.

### Fase D. SOAP, cierre de cita y orden de cobro

1. Doctor abre SOAP (`doctor.citas.soap`).
2. Guardar borrador (`doctor.citas.soap.store`).
3. Firmar (`doctor.citas.soap.firmar`) con campos clinicos requeridos.
4. Registrar enmienda (`doctor.citas.soap.enmienda`).
5. Marcar cita como realizada (`doctor.citas.realizar`):
   - Debe bloquear si SOAP no esta firmada
   - Con SOAP firmada debe pasar
6. Validar consecuencia:
   - Se genera/asegura orden de cobro para el pago de esa cita

### Fase E. Pagos (paciente -> admin)

1. Login paciente y abrir `paciente.pagos.index`.
2. Descargar orden de cobro (`paciente.pagos.orden.pdf`).
3. Enviar pago:
   - Transferencia + comprobante (`paciente.pagos.submit`)
   - O efectivo (sin comprobante)
4. Login admin y revisar `admin.pagos.index/show`.
5. Validar acciones admin:
   - Actualizar monto (`admin.pagos.monto.update`)
   - Actualizar metodo (`admin.pagos.metodo.update`)
   - Aprobar (`admin.pagos.aprobar`) o rechazar (`admin.pagos.rechazar`)
   - Anular (`admin.pagos.anular`)
6. Si pago aprobado:
   - Validar recibo PDF en admin (`admin.pagos.recibo.pdf`)
   - Validar recibo PDF en paciente (`paciente.pagos.recibo.pdf`)
7. Validar comprobante:
   - Paciente (`paciente.pagos.comprobante`)
   - Admin (`admin.pagos.comprobante`)

### Fase F. Regla de bloqueo por pagos y override

1. Con pago en `pendiente` o `en_verificacion`, intentar crear cita como paciente:
   - Debe redirigir a `paciente.pagos.index` con bloqueo
2. Como admin, usar override:
   - `admin.citas.override.create/store`
   - Si paciente bloqueado, debe exigir `forzar_bloqueo` + `override_reason`
3. Validar que la cita override se crea y queda auditada.

### Fase G. Laboratorio (dos subflujos)

1. Subflujo doctor -> laboratorio:
   - Doctor crea orden/cita de laboratorio (`doctor.laboratorio.create/store`)
   - Usuario laboratorio ve ordenes (`laboratorio.ordenes.index`)
   - Laboratorio marca muestra (`laboratorio.ordenes.muestra`)
   - Laboratorio sube resultado PDF (`laboratorio.ordenes.resultado`)
   - Paciente descarga resultado (`paciente.laboratorio.download`)
2. Subflujo paciente solicita examen:
   - Paciente crea solicitud (`paciente.laboratorio.solicitar/store`)
   - Validar que se registre en dashboard/historial segun vistas del paciente

### Fase H. Recetas, historial y exportes doctor

1. Doctor:
   - Lista recetas (`doctor.recetas.index`)
   - Crear receta (`doctor.recetas.create/store`)
   - Editar receta (`doctor.recetas.edit/update`)
   - Reenviar (`doctor.recetas.resend`)
   - Descargar (`doctor.recetas.download`)
2. Historial:
   - Paciente (`paciente.historial`, `paciente.historial.show`)
   - Doctor (`doctor.pacientes.historial`)
   - Admin (`admin.historial.index/show/paciente`)
3. Exportes doctor:
   - Excel (`doctor.citas.export.excel`)
   - PDF (`doctor.citas.export.pdf`)

### Fase I. Publico, chatbot, contacto y biometria

1. Publico:
   - Home `/`
   - Servicios `servicios.index`
   - Contacto `contacto.form/enviar`
2. Chatbot:
   - `chatbot.especialidades`
   - `chatbot.especialidad.doctores`
   - `chatbot.doctor.fechas`
   - `chatbot.verificarPaciente`
   - `chatbot.enviarCodigo`
   - `chatbot.verificarCodigo`
   - `chatbot.agendar`
   - `chatbot.buscarCitas`
   - `chatbot.cancelar`
   - `chatbot.reagendar`
   - `chatbot.perfil`
   - `chatbot.perfil.actualizar`
3. Face auth:
   - Enrollment autenticado (`face.enroll` GET/POST)
   - Login facial invitado (`face.login` POST)
4. Accion por email firmado:
   - `email.cita.action` (aceptar/cancelar desde link firmado)

## 5) Checklist de cobertura por modulo (marcar PASS/FAIL)

### Superadmin
- [ ] Dashboard `superadmin.dashboard`
- [ ] Admins list/create/store/edit/update/destroy
- [ ] Admins block/suspend/activate/deactivate
- [ ] Solicitudes personalizacion list/aprobar/rechazar/revocar
- [ ] Personalizacion bienvenida edit/update
- [ ] Personalizacion servicios edit/update
- [ ] Mantenimiento edit/update

### Admin
- [ ] Dashboard `admin.dashboard`
- [ ] Resumen ajax `admin.dashboard.resumen`
- [ ] Perfil edit/update
- [ ] Usuarios index/create/store/show/edit/update/destroy
- [ ] Usuarios block/suspend/activate/deactivate
- [ ] Usuarios export excel/pdf
- [ ] Horarios index/create/store/edit/update/destroy
- [ ] Cambios de citas index/export excel/export pdf
- [ ] Historial index/paciente/show
- [ ] Pagos index/show/monto/metodo/aprobar/rechazar/anular
- [ ] Pagos comprobante/orden.pdf/recibo.pdf
- [ ] Cita override create/store
- [ ] Personalizacion request + bienvenida + servicios

### Doctor
- [ ] Dashboard + data
- [ ] Perfil edit/update
- [ ] Citas list + aceptar + rechazar + realizar
- [ ] Agenda semanal
- [ ] Disponibilidad check
- [ ] Planificacion de proxima cita
- [ ] SOAP show/store/firmar/enmienda
- [ ] Recetas index/create/store/edit/update/resend/download
- [ ] Laboratorio create/store
- [ ] Horario index/store/edit/update/destroy/generar
- [ ] Historial de pacientes
- [ ] Exportes citas excel/pdf

### Paciente
- [ ] Dashboard
- [ ] Perfil edit/update
- [ ] Citas list/create/store/cancelar/edit/update
- [ ] Pagos index/submit/comprobante/orden.pdf/recibo.pdf
- [ ] Laboratorio index/solicitar/store/download
- [ ] Historial index/show
- [ ] Mensajes (vista)

### Laboratorio
- [ ] Dashboard
- [ ] Ordenes index
- [ ] Marcar muestra
- [ ] Subir resultado
- [ ] Descargar resultado

### Publico/API
- [ ] Home `/`
- [ ] Servicios
- [ ] Contacto
- [ ] API tarifa doctor
- [ ] API slots doctor
- [ ] Especialidades -> doctores
- [ ] Chatbot flujo completo
- [ ] Face enrollment/login
- [ ] Cobro por token `pagos.token.show`

## 6) Validacion de imagenes optimizadas (obligatorio)

Probar en: perfil admin, perfil doctor, perfil paciente, personalizacion de bienvenida (slides/doctores), comprobante de pago en imagen.

1. Subir imagen JPG/PNG.
2. Validar que en `storage/app/public/images/...` existan:
   - `thumb/*.webp`
   - `medium/*.webp`
   - `large/*.webp`
3. Validar que la BD guarda path normalizado y las vistas usan variantes (`ImageUrl`).
4. Ejecutar migracion de imagenes existentes:
   - Dry run: `php artisan images:optimize-existing --dry-run`
   - Ejecucion: `php artisan images:optimize-existing --limit=100`
   - Por carpeta: `php artisan images:optimize-existing --folder=users`

## 7) Criterios de aceptacion final

1. Ninguna ruta funcional critica devuelve 500.
2. Estados de cita y pago cambian segun reglas.
3. Bloqueo por pagos funciona y override admin funciona.
4. SOAP firmada es requisito para marcar cita realizada.
5. Exportes y PDFs se descargan sin error.
6. Chatbot permite agendar/buscar/cancelar/reagendar.
7. Imagenes quedan optimizadas y con fallback si falta archivo.

## 8) Nota rapida para ejecucion diaria (smoke corto)

Si quieres una prueba diaria de 15-20 min:

1. Login superadmin -> crear admin.
2. Login admin -> crear doctor + paciente.
3. Login doctor -> crear horario.
4. Login paciente -> crear cita.
5. Login doctor -> aceptar + SOAP firmada + realizar.
6. Login paciente -> enviar pago.
7. Login admin -> aprobar pago.
8. Login paciente -> verificar recibo y nuevo agendamiento habilitado.

