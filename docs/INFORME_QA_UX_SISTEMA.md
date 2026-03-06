# Informe QA + UX del sistema

Fecha de auditoria: 2026-02-28
Base evaluada: `http://127.0.0.1:8000`

## 1. Resumen ejecutivo

Se validaron los flujos principales del sistema por rol: paciente, administrador, superadmin, doctor, laboratorio y canal publico. El sistema cubre bien el flujo medico principal, pagos, SOAP, recetas, exportes, laboratorio doctor->lab->paciente, chatbot y enlaces firmados por correo.

El estado general es funcional, pero hay 4 problemas importantes que deberian corregirse antes de considerar la UX estable:

1. El modo mantenimiento bloqueo tambien al superadmin, aunque la interfaz y el middleware indican que no deberia ocurrir.
2. La auto-solicitud de laboratorio del paciente se guarda en `LabOrder`, pero la vista principal del paciente lista `LaboratorioOrden`, por lo que la orden no aparece en "Resultados y ordenes".
3. El chatbot muestra horarios libres con una regla distinta a la que usa para reagendar, asi que puede ofrecer una hora y luego rechazarla.
4. En doctor, una cita cancelada futura puede mostrarse como "Proxima cita", lo que genera una lectura clinica incorrecta del seguimiento.

## 2. Cobertura ejecutada

### Flujos probados con exito

- Publico:
  - `/`
  - `/servicios`
  - `/contacto`
  - envio real del formulario de contacto con persistencia en BD
- Autenticacion:
  - login por modal publico
  - login por rol
  - biometria facial: enrolamiento y login con descriptor sintetico
- Paciente:
  - crear cita
  - reagendar
  - cancelar
  - ver pagos
  - enviar comprobante
  - descargar orden de cobro
  - historial
  - resultados de laboratorio
  - solicitar examen desde paciente
- Doctor:
  - crear horarios
  - aceptar/rechazar cita
  - SOAP: guardar, firmar, enmendar
  - marcar cita realizada
  - ver historial de paciente
  - crear/editar/reenviar/descargar receta
  - exportes Excel/PDF de citas
  - crear orden de laboratorio
- Laboratorio:
  - ver ordenes
  - marcar muestra tomada
  - subir resultado PDF
  - descargar resultado
- Admin:
  - dashboard
  - usuarios: index, detalle, edicion, exportes
  - pagos: index, detalle, aprobar, rechazar, anular, cambiar monto, cambiar metodo
  - horarios: crear, editar, eliminar
  - cambios de citas: index, exportes Excel/PDF
  - historial clinico: index, por paciente, detalle
  - override de citas
- Superadmin:
  - dashboard
  - crear/editar/suspender/inactivar/bloquear/reactivar/eliminar admin
  - solicitudes de personalizacion: aprobar, revocar, rechazar
  - admins index
  - usuarios index
  - personalizacion (pantallas)
  - mantenimiento (con hallazgo critico)
- Chatbot:
  - especialidades
  - doctores por especialidad
  - fechas disponibles
  - agendar
  - buscar citas
  - verificar paciente
  - enviar codigo
  - verificar codigo
  - ver perfil
  - actualizar perfil
  - reagendar
  - cancelar
- Enlaces firmados:
  - `email.cita.action` para aceptar como doctor
  - `email.cita.action` para cancelar como paciente

### Validacion parcial

- Biometria facial:
  - validada tecnicamente sin camara fisica; no se probo captura real desde webcam.
- Correo:
  - no se verifico recepcion real del email en bandeja externa; si se verifico la ruta firmada resultante y su efecto en la cita.

## 3. Hallazgos funcionales confirmados

### Critico 1. Mantenimiento bloquea al superadmin

Evidencia:

- Se activo mantenimiento desde superadmin.
- El sistema mostro la vista publica/mantenimiento incluso intentando volver a `/superadmin/mantenimiento`.
- Hubo que desactivar el flag por datos para no dejar el entorno bloqueado.

Referencias:

- `app/Http/Middleware/PreventRequestsDuringMaintenance.php:16-25`
- `bootstrap/app.php:20-21`

Impacto:

- El usuario con mas privilegios puede quedar fuera del sistema durante mantenimiento.
- Operativamente es un bloqueo severo.

Inferencia tecnica:

- El middleware intenta usar `$request->user()` para dejar pasar al superadmin, pero al reemplazar el middleware de framework en el pipeline global esa autenticacion no estaba disponible en el momento efectivo de la comprobacion.

### Critico 2. La auto-solicitud de laboratorio del paciente no aparece en su vista principal

Evidencia:

- Se creo una solicitud real desde `/paciente/laboratorio/solicitar`.
- Se guardo en BD como `LabOrder` con estado `pendiente_toma`.
- La pantalla `/paciente/laboratorio` siguio mostrando solo `LaboratorioOrden`.

Referencias:

- `app/Http/Controllers/Paciente/LabOrderController.php:164-177`
- `app/Http/Controllers/Paciente/LaboratorioController.php:14-19`
- `resources/views/paciente/laboratorio.blade.php:39-40`

Impacto:

- El paciente recibe mensaje de exito, pero no ve su orden en la vista donde espera verla.
- Es una ruptura directa entre flujo y feedback.

### Alto 3. Error tipografico en dashboard del paciente para estados de auto-ordenes

Evidencia:

- El dashboard usa `$order->statis` en lugar de `$order->status`.

Referencia:

- `resources/views/paciente/dashboard.blade.php:205`

Impacto:

- La tarjeta de laboratorio del paciente puede mostrar estado incorrecto o fallback constante.

### Alto 4. Inconsistencia entre slots publicos y validacion interna del chatbot

Evidencia:

- El endpoint publico de slots ofrecio `09:30` como libre.
- El endpoint de reagendar del chatbot rechazo esa misma hora con `Ese horario ya no esta disponible`.
- Luego reagendar a `10:00` si funciono.

Referencias:

- `app/Http/Controllers/Api/DoctorSlotController.php:64-65`
- `app/Http/Controllers/ChatBotController.php:1127`

Impacto:

- El usuario puede elegir una hora visible en la UI publica y luego recibir un rechazo tardio.
- Reduce confianza en el chatbot.

Causa observable:

- `DoctorSlotController` bloquea solo estados `pendiente` y `confirmada`.
- `ChatBotController` bloquea cualquier cita `activa`, incluyendo estados que la API publica no considera.

### Medio 5. Doctor muestra una cita cancelada como "Proxima cita"

Evidencia:

- En citas realizadas del doctor se mostro `Proxima cita: 02/03/2026 10:00 (Cancelada)`.

Referencia:

- `resources/views/doctor/citas.blade.php:186-206`

Impacto:

- En seguimiento clinico, una cita cancelada no deberia presentarse como proxima accion del paciente.

## 4. Hallazgos UX y responsividad

## 4.1 Problemas visibles en movil

### Admin cambios de citas no es realmente responsive

Evidencia:

- En movil la vista conserva tabla ancha de escritorio.
- En la captura la tabla queda comprimida y la lectura es pobre.

Referencia:

- `resources/views/admin/cambios-citas/index.blade.php:147-148`

Mejora recomendada:

- En `<768px`, convertir la tabla a cards por evento.
- Mover filtros a bottom sheet o panel colapsable.
- Exportes en menu `...`.

### Superadmin admins pierde acciones en movil

Evidencia:

- La tabla de admins no baja a cards.
- En la captura solo se alcanza a ver administrador/contacto/estado; acciones quedan fuera de foco.

Referencia:

- `resources/views/superadmin/admins/index.blade.php:46-47`

Mejora recomendada:

- Card por administrador en movil.
- Acciones secundarias dentro de menu kebab `...`.

### Paciente laboratorio recorta informacion importante

Evidencia:

- La tabla movil muestra examen y laboratorio, pero el resumen/acciones quedan truncados visualmente.

Referencia:

- `resources/views/paciente/laboratorio.blade.php:39-40`

Mejora recomendada:

- Reemplazar tabla por cards:
  - examen
  - estado
  - fecha
  - CTA principal `Descargar`
  - CTA secundaria en `...`

### SOAP del doctor es demasiado largo en movil

Dato observado:

- Altura aproximada de pagina: `5410px`.

Impacto:

- Fatiga visual.
- Riesgo de perder contexto clinico.
- Acciones principales demasiado lejos del contenido actual.

Mejora recomendada:

- Dividir en pasos o tabs:
  - motivo / anamnesis
  - examen fisico
  - diagnostico
  - plan
  - firma
- Footer sticky con `Guardar`, `Firmar`, `Agregar enmienda`.

### Admin usuarios es funcional pero demasiado denso

Datos observados en movil:

- Altura aproximada: `3573px`
- `43` botones/CTAs visibles
- `28` formularios en la misma vista

Impacto:

- Mucha carga cognitiva.
- Acciones destructivas demasiado cerca de acciones frecuentes.

Mejora recomendada:

- Dejar solo CTA principal por card.
- Mover bloquear/suspender/inactivar/eliminar a menu `...`.
- Separar filtros avanzados en panel colapsable.

## 4.2 Problemas de UX transversal

### Login demasiado dependiente del modal

Evidencia:

- En movil el acceso depende de abrir hamburguesa y luego el modal.
- Para automatizar y para usuarios menos expertos, el punto de entrada queda poco directo.

Referencia:

- `resources/views/layouts/navbar.blade.php:125`
- `resources/views/layouts/navbar.blade.php:143`

Mejora recomendada:

- Mantener el modal, pero ofrecer tambien una ruta `/login` clara y estable para movil y accesibilidad.

### Feedback inconsistente despues de acciones

Observaciones:

- En paciente, el reagendamiento si cambia datos, pero el feedback visible posterior no es tan claro como en crear o cancelar.
- En algunas pantallas el usuario vuelve al listado sin un cambio de foco claro hacia el registro actualizado.

Mejora recomendada:

- Toast uniforme.
- Resaltar el item afectado.
- Scroll automatico al registro cambiado.

### Exceso de acciones inline

Se nota sobre todo en:

- `admin/usuarios`
- `superadmin/admins`
- `admin/cambios-citas`
- algunos listados del doctor

Mejora recomendada:

- En movil, maximo 1 accion principal visible por item.
- El resto en menu de tres puntos `...`.
- Confirmaciones destructivas en bottom sheet, no en prompts del navegador.

## 5. Mejoras UX prioritarias que yo haria

## Prioridad 1

1. Arreglar mantenimiento para que el superadmin nunca quede fuera.
2. Unificar `LabOrder` y `LaboratorioOrden` en el flujo del paciente o construir una vista combinada.
3. Unificar la logica de disponibilidad entre API publica y chatbot.
4. Excluir citas canceladas/no validas del bloque "Proxima cita" del doctor.

## Prioridad 2

1. Convertir tablas sensibles a cards moviles:
   - `admin/cambios-citas`
   - `superadmin/admins`
   - `paciente/laboratorio`
2. Aplicar menu kebab `...` a acciones secundarias.
3. Volver SOAP un flujo por secciones con progreso visible.
4. Reducir longitud de `admin/usuarios` con filtros colapsables y acciones agrupadas.

## Prioridad 3

1. Estandarizar feedback despues de crear/editar/reagendar/cancelar.
2. Mantener CTA primario sticky en vistas largas.
3. Añadir estados vacios mas pedagogicos en laboratorio, pagos y auditoria.
4. Crear una version `/login` estable para movil y accesibilidad.

## 6. Estado del entorno al cerrar la auditoria

Se dejo el entorno utilizable:

- mantenimiento desactivado
- solicitudes temporales de personalizacion eliminadas
- prueba de contacto limpiada
- prueba facial del paciente limpiada
- usuario admin temporal eliminado

Datos QA que si quedaron porque forman parte de los flujos probados:

- Doctor QA: `doctor.qa@clinic.test`
- Laboratorio QA: `laboratorio.qa@clinic.test`
- Paciente chatbot QA: `chatbot.qa@clinic.test`
- citas/ordenes/pagos derivados de esas pruebas

Si se quiere dejar la base exactamente como estaba antes de la auditoria, hay que hacer una limpieza adicional de esos registros QA.

## 7. Evidencia visual

Capturas moviles generadas en:

- `tmp/qa_screens/home_mobile.png`
- `tmp/qa_screens/paciente_citas_mobile.png`
- `tmp/qa_screens/paciente_laboratorio_mobile.png`
- `tmp/qa_screens/paciente_pagos_mobile.png`
- `tmp/qa_screens/admin_usuarios_mobile.png`
- `tmp/qa_screens/admin_cambios_mobile.png`
- `tmp/qa_screens/admin_pago_mobile.png`
- `tmp/qa_screens/doctor_citas_mobile.png`
- `tmp/qa_screens/doctor_soap_mobile.png`
- `tmp/qa_screens/lab_ordenes_mobile.png`
- `tmp/qa_screens/superadmin_admins_mobile.png`
