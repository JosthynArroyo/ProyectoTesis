# Diagramas PlantUML para tesis

## Escaneo del proyecto

El repositorio corresponde a una aplicacion Laravel 12. La evidencia primaria es `composer.json`, que exige `laravel/framework:^12.0`, y la verificacion local con `php artisan --version`, que devolvio `Laravel Framework 12.20.0`. En la configuracion de entorno inspeccionada (`.env` y `.env.example`) el motor de base de datos activo es `DB_CONNECTION=sqlite`, por lo que el despliegue existente se documento sobre SQLite y no sobre MySQL.

La estructura de roles real se apoya en `database/seeders/RoleSeeder.php`, donde se crean exactamente `superadmin`, `administrador`, `doctor`, `paciente` y `laboratorio`. El control de acceso se ejecuta por `app/Http/Middleware/EnsureUserRole.php`, `app/Http/Middleware/EnsureAccountActive.php`, `app/Http/Middleware/EnsureFeatureAccess.php` y `app/Http/Middleware/EnsureNoPendingPaymentsForBooking.php`, todos registrados en `app/Http/Kernel.php`. No se encontro un directorio `app/Policies` ni definiciones `Gate::define` o `Gate::policy`, asi que el modelado por permisos se baso en middleware, relaciones `users`-`roles` y validaciones por controlador, no en policies o gates.

Las rutas de negocio viven en `routes/web.php`. El archivo `routes/api.php` no existe en el repositorio, pero el proyecto si expone endpoints JSON con prefijo `/api/...` dentro del mismo `routes/web.php`, concretamente `/api/tarifa/doctor/{id}` y `/api/doctor/{doctor}/fecha/{fecha}/slots`. Esto se refleja en los diagramas de secuencia y arquitectura, porque el frontend Vite consume esos endpoints por `fetch()` desde `resources/js/paciente/crear-cita.js`, `resources/js/paciente/editar-cita.js`, `resources/js/doctor/soap.js`, `resources/js/doctor/laboratorio-create.js` y `resources/js/chatbot/widget.js`.

Los modelos principales del dominio clinico y operativo estan en `app/Models`: `User`, `Role`, `Especialidad`, `Horario`, `Cita`, `PatientFlag`, `NotaSoap`, `NotaSoapDiagnostico`, `NotaSoapEnmienda`, `Receta`, `LaboratorioOrden`, `LabTest`, `MedicalOrder`, `LabOrder`, `LabOrderItem`, `Factura`, `FacturaItem`, `Pago`, `PaymentStatusLog`, `PaymentReceipt`, `PaymentReceiptLog`, `BookingOverrideLog` y `FeatureAccessRequest`. Las tablas y cardinalidades se derivaron de migraciones en `database/migrations`, en especial las de usuarios, roles, citas, SOAP, recetas, laboratorio, facturacion y pagos.

Tambien se identificaron integraciones externas reales. `app/Services/WhatsAppService.php` usa `twilio/sdk` y `config/services.php` para mensajeria WhatsApp; `app/Services/VisionClient.php` consume por HTTP un servicio configurado en `config/captcha.php`; y el sistema envia correos por `Mail::to(...)` desde jobs y controladores, con `MAIL_MAILER=smtp` en el entorno inspeccionado. No se encontro evidencia de `routes/api.php`, de proveedores de hosting como Render o cPanel, ni de un manifiesto Docker o IaC que permita afirmar un despliegue productivo especifico.

## Mapa de evidencias

- `composer.json` -> version base de Laravel, dependencias de PDF, Excel, Twilio y stack general.
- `package.json` -> confirma Vite, Tailwind, Bootstrap, `axios` y `face-api.js`.
- `vite.config.js` -> evidencia del patron Blade + assets Vite + modulos JS por rol.
- `.env` y `.env.example` -> evidencia de SQLite, SMTP y variables de Twilio/WhatsApp.
- `routes/web.php` -> mapa real de endpoints por rol, endpoints JSON `/api/...`, ausencia de `routes/api.php`, flujos de citas, pagos, SOAP, recetas y laboratorio.
- `database/seeders/RoleSeeder.php` -> actores reales: `superadmin`, `administrador`, `doctor`, `paciente`, `laboratorio`.
- `database/seeders/DatabaseSeeder.php` -> existencia de superadmin inicial y uso real de la tabla pivote `role_user`.
- `app/Http/Kernel.php` -> alias y aplicacion de middlewares de autenticacion, rol, feature access y estado de cuenta.
- `app/Http/Middleware/EnsureUserRole.php` -> control por rol y bypass para `superadmin`.
- `app/Http/Middleware/EnsureAccountActive.php` -> bloqueo por estado de cuenta inactiva o suspendida.
- `app/Http/Middleware/EnsureNoPendingPaymentsForBooking.php` -> restriccion real para agendar cuando existen pagos bloqueantes.
- `app/Http/Controllers/Auth/LoginController.php` -> validacion de login y redireccion por rol.
- `app/Listeners/UpdateLastLoginAndGuardStatus.php` -> actualizacion de `last_login_at` al autenticarse.
- `app/Models/User.php` + migraciones de usuarios -> atributos de pacientes, doctores y laboratorios.
- `app/Models/Role.php` + migraciones de roles -> modelo real de roles y pivote `role_user`.
- `app/Models/Especialidad.php` + migraciones de especialidades -> catalogo y asignacion a doctores/laboratorio.
- `app/Models/Horario.php` + migraciones `horarios` -> agenda configurable por profesional.
- `app/Models/Cita.php` + migraciones de `citas_medicas` -> estados reales, prioridad, unicidad por slot y relacion central del sistema.
- `app/Services/PriorityEvaluator.php` -> regla real de prioridad por red flags y vulnerabilidad.
- `app/Services/CitaNoShowService.php` -> regla real de cierre automatico en `no_se_presento`.
- `app/Http/Controllers/CitaController.php` -> agendamiento, reprogramacion, cancelacion, aceptacion, rechazo, realizacion, exportes y planificacion de proximo control.
- `app/Events/CitaAgendada.php`, `app/Events/CitaAtendida.php`, `app/Providers/EventServiceProvider.php` -> eventos reales que disparan pagos, facturas y orden de cobro.
- `app/Listeners/CrearFacturaBorrador.php`, `app/Models/Factura.php`, `app/Models/FacturaItem.php` -> facturacion creada al agendar.
- `app/Listeners/CrearPagoPendiente.php`, `app/Listeners/GenerarOrdenCobroAlAtenderCita.php`, `app/Services/PagoService.php`, `app/Http/Controllers/Admin/PagoController.php` -> pagos, ordenes de cobro, recibos y bloqueo de agendamiento.
- `app/Models/NotaSoap.php`, `app/Models/NotaSoapDiagnostico.php`, `app/Models/NotaSoapEnmienda.php`, `app/Http/Controllers/Doctor/SoapController.php` -> historia clinica SOAP, firma y enmiendas.
- `app/Models/Receta.php`, `app/Http/Controllers/Doctor/RecetaController.php`, `resources/views/pdf/receta.blade.php` -> receta medica, PDF, descarga y reenvio por correo.
- `app/Models/LaboratorioOrden.php`, `app/Http/Controllers/Doctor/LaboratorioController.php`, `app/Http/Controllers/Laboratorio/OrdenController.php`, `app/Http/Controllers/Paciente/LaboratorioController.php` -> ordenes de laboratorio ligadas a una cita.
- `app/Models/LabTest.php`, `app/Models/MedicalOrder.php`, `app/Models/LabOrder.php`, `app/Models/LabOrderItem.php`, `app/Http/Controllers/Paciente/LabOrderController.php` -> modulo de solicitudes catalogadas de laboratorio.
- `app/Http/Controllers/Admin/HistorialController.php`, `app/Http/Controllers/Doctor/HistorialController.php`, `app/Http/Controllers/Paciente/HistorialController.php` -> consulta de historia clinica por administrador, doctor y paciente.
- `app/Http/Controllers/Superadmin/AdminsController.php`, `app/Http/Controllers/Superadmin/DashboardController.php`, `app/Http/Controllers/Superadmin/PersonalizacionRequestController.php`, `app/Models/FeatureAccessRequest.php` -> gestion operativa del superadmin.
- `resources/views/paciente/crear-cita.blade.php` + `resources/js/paciente/crear-cita.js` -> evidencia de formulario de agendamiento, tarifa y slots via AJAX.
- `resources/views/doctor/soap.blade.php` + `resources/js/doctor/soap.js` -> evidencia del flujo SOAP, autoguardado y programacion de control.
- `config/services.php` + `app/Services/WhatsAppService.php` -> integracion Twilio/WhatsApp.
- `config/captcha.php`, `app/Services/VisionClient.php`, `app/Http/Controllers/Captcha/CaptchaController.php` -> integracion HTTP con servicio de vision para CAPTCHA.

## Explicacion de diagramas

### 01_use_cases.puml

Este diagrama resume los actores reales del sistema y los casos de uso que si aparecen en las rutas, controladores y vistas del repositorio. Se mantuvieron solo los cinco actores sembrados por `RoleSeeder`: `superadmin`, `administrador`, `doctor`, `paciente` y `laboratorio`. Los casos de uso giran alrededor de autenticacion, administracion de usuarios, agenda clinica, historia clinica, recetas, laboratorio, pagos y personalizacion.

La evidencia principal esta en `routes/web.php`, donde cada grupo por prefijo y middleware delimita el alcance real de cada actor. Los paneles y vistas asociados se confirman por los layouts y por vistas concretas como `resources/views/paciente/crear-cita.blade.php`, `resources/views/doctor/soap.blade.php` y `resources/views/laboratorio/ordenes/index.blade.php`.

No se modelo un caso de uso independiente de "Gestion de roles" porque no se encontro un CRUD exclusivo para roles. En el codigo inspeccionado, la asignacion de rol ocurre dentro de la gestion de usuarios y de administradores. Esa exclusion no es un invento: responde a la estructura real del repo.

### 02_class_diagram.puml

El diagrama de clases toma como base `app/Models` y organiza el dominio en cuatro areas: usuarios/acceso, agenda y atencion, laboratorio, y facturacion/pagos. Se incluyeron los atributos solo cuando resultan claros desde `fillable`, `casts` o migraciones, y se priorizaron las relaciones declaradas por `belongsTo`, `hasMany`, `hasOne` y `belongsToMany`.

La evidencia directa esta en `app/Models/User.php`, `Role.php`, `Especialidad.php`, `Horario.php`, `Cita.php`, `NotaSoap.php`, `Receta.php`, `LaboratorioOrden.php`, `LabOrder.php`, `LabOrderItem.php`, `LabTest.php`, `MedicalOrder.php`, `Factura.php`, `FacturaItem.php`, `Pago.php`, `PaymentStatusLog.php`, `PaymentReceipt.php` y `BookingOverrideLog.php`. El comportamiento de negocio que justifica varias asociaciones se observa en `CitaController`, `Doctor/SoapController`, `Doctor/RecetaController`, `Doctor/LaboratorioController`, `Paciente/LabOrderController` y `Admin/PagoController`.

Se dejo una nota explicita sobre `CitaEvento` porque el modelo declara relaciones `paciente()` y `doctor()`, pero en las migraciones inspeccionadas de `cita_eventos` no aparecen columnas `paciente_id` ni `doctor_id`. Tambien se omitio la asociacion `Horario::citas()` porque no se encontro `horario_id` en `citas_medicas`. Por rigor, el diagrama solo modela las relaciones que pudieron sostenerse con codigo y esquema al mismo tiempo.

### 03_erd.puml

El ERD se construyo exclusivamente a partir de migraciones, no desde inferencias de Eloquent. Por eso aparecen las tablas pivote `role_user` y `doctor_especialidad`, las tablas clinicas (`citas_medicas`, `notas_soap`, `recetas`, `laboratorio_ordenes`), el catalogo de laboratorio (`lab_tests`, `medical_orders`, `lab_orders`, `lab_order_items`) y el bloque economico (`facturas`, `factura_items`, `pagos`, `payment_status_logs`, `payment_receipts`, `payment_receipt_logs`, `booking_override_logs`).

Las cardinalidades provienen de PK, FK, indices unicos y nulabilidad real. Ejemplos: `pagos.cita_id` es unico y por eso se documento como 1 a 0..1 entre `citas_medicas` y `pagos`; `recetas.cita_id`, `notas_soap.cita_id` y `laboratorio_ordenes.cita_id` tambien son unicos; en cambio `facturas.cita_id` es nullable sin `UNIQUE`, por lo que el ERD muestra una relacion potencialmente 1 a muchos aunque la aplicacion cree una sola factura borrador por cita mediante listener.

Tambien aqui se dejo constancia de una inconsistencia tecnica del repo: `cita_eventos` solo tiene FK segura hacia `citas_medicas`, mientras que `user_id` se almacena sin clave foranea y las relaciones `paciente()` y `doctor()` del modelo no quedaron respaldadas por las migraciones inspeccionadas. El ERD refleja la base real y no agrega FKs inexistentes.

### 04_sequence_login.puml

La secuencia de autenticacion se centro en el flujo `POST /login` generado por `Auth::routes(['register' => false])`, mas el comportamiento concreto de `LoginController`. El diagrama muestra validacion de credenciales, consulta de roles, actualizacion de `last_login_at`, validacion de estado de cuenta y redireccion al dashboard correspondiente para `superadmin`, `administrador`, `paciente`, `doctor` o `laboratorio`.

La evidencia proviene de `routes/web.php` (habilitacion de `Auth::routes` y ruta `/home` por rol), `app/Http/Controllers/Auth/LoginController.php` (metodos `validateLogin`, `authenticated`, `redirectTo`) y `app/Listeners/UpdateLastLoginAndGuardStatus.php` (actualizacion del ultimo acceso). El control adicional por estado se completa con `app/Http/Middleware/EnsureAccountActive.php`.

### 05_sequence_appointment.puml

Esta secuencia representa el agendamiento real desde el panel de paciente. Se incluyo la preparacion del formulario desde la vista/JS porque `resources/views/paciente/crear-cita.blade.php` y `resources/js/paciente/crear-cita.js` consultan profesionales, tarifa y disponibilidad antes de enviar el `POST /paciente/crear-cita`. Luego se documentan los pasos reales del backend: middleware de pagos pendientes, validaciones de fecha y horario, control de choques, calculo de prioridad, persistencia de la cita y creacion opcional de una `LaboratorioOrden` cuando la especialidad es "Laboratorio Clinico".

La evidencia principal esta en `app/Http/Controllers/CitaController.php`, `app/Http/Middleware/EnsureNoPendingPaymentsForBooking.php`, `app/Services/PagoService.php`, `app/Services/PriorityEvaluator.php`, `app/Http/Controllers/Api/TarifaController.php` y `app/Http/Controllers/Api/DoctorSlotController.php`. El diagrama tambien deja anotado el procesamiento posterior al `commit`: `CitaAgendada` dispara listeners para factura borrador, pago pendiente y notificaciones, segun `app/Providers/EventServiceProvider.php`.

### 06_sequence_clinical_flow.puml

La tercera secuencia une los endpoints clinicos que si forman un flujo continuo de atencion: el doctor abre la consulta SOAP, firma la nota clinica, marca la cita como realizada y, de forma opcional, genera la receta medica. El repositorio exige ese orden: `CitaController::realizar()` redirige al formulario SOAP si la nota no esta firmada, y `Doctor/RecetaController` solo permite crear receta cuando la cita ya esta en estado `realizada`.

Las pruebas de codigo estan en `app/Http/Controllers/Doctor/SoapController.php`, `app/Http/Controllers/CitaController.php`, `app/Http/Controllers/Doctor/RecetaController.php`, `resources/views/doctor/soap.blade.php` y `resources/js/doctor/soap.js`. La parte de receta se sostiene ademas en `resources/views/pdf/receta.blade.php`, `Storage` para el PDF y `Mail::to(...)` para el envio al paciente. El listener `GenerarOrdenCobroAlAtenderCita` se anota como efecto posterior real del evento `CitaAtendida`.

### 07_activity_appointments.puml

El diagrama de actividades resume el ciclo de vida de una cita desde la solicitud hasta el cierre. Se plasmaron decisiones que estan codificadas en el proyecto: bloqueo por pagos pendientes, validacion de anticipacion minima de una hora, validacion de disponibilidad, bifurcacion para laboratorio, aceptacion o rechazo por el doctor, exigencia de SOAP firmada para realizar la cita, rama de receta y rama de resultado de laboratorio.

La evidencia principal esta en `CitaController`, `Doctor/SoapController`, `Doctor/RecetaController`, `Doctor/LaboratorioController`, `Laboratorio/OrdenController` y `CitaNoShowService`. La decision de `no_se_presento` no es teorica: `app/Services/CitaNoShowService.php` cambia automaticamente el estado cuando una cita pendiente o confirmada ya vencio sin atencion.

### 08_deployment.puml

El diagrama de despliegue modela la infraestructura realmente observable en el repo: navegador web con assets Vite, servidor web/PHP que entra por `public/index.php`, aplicacion Laravel con rutas web, middlewares, controladores, servicios y jobs, base de datos SQLite, y almacenamiento local para PDFs, comprobantes, recetas y resultados. Se agregaron nodos externos reales para SMTP, Twilio/WhatsApp y el servicio HTTP de vision usado por el CAPTCHA.

Hay dos notas de rigor. La primera: no existe `routes/api.php`; los endpoints JSON `/api/...` se sirven desde `routes/web.php`, por eso el cliente sigue hablando con la misma aplicacion Laravel. La segunda: el tipo exacto de servidor web productivo no puede afirmarse con el repo, asi que se modelo un "Servidor web/PHP" generico y esa parte esta marcada como `Supuesto` controlado. No se encontro evidencia de Render, cPanel ni otro hosting especifico.

### 09_architecture_mvc.puml

Este diagrama sintetiza la arquitectura MVC real del proyecto. La presentacion se compone de Blade (`resources/views`) y assets Vite (`resources/js`, `resources/css`); las rutas web entran por `routes/web.php`; los middlewares filtran autenticacion, rol, estado de cuenta, feature access y pagos pendientes; los controladores coordinan servicios y modelos; y la persistencia se resuelve con Eloquent sobre SQLite. Tambien se incluyo la salida hacia jobs, mails e integraciones externas porque forman parte del flujo operativo.

La evidencia tecnica esta dispersa pero consistente: `vite.config.js` lista los assets por modulo; `resources/views/paciente/crear-cita.blade.php` y `resources/views/doctor/soap.blade.php` muestran el acople Blade + Vite; `routes/web.php` define los endpoints; `app/Http/Kernel.php` y los middlewares aplican la capa de acceso; `CitaController`, `Doctor/SoapController`, `Admin/PagoController` y `Paciente/LabOrderController` muestran la orquestacion; y `PagoService`, `PriorityEvaluator`, `WhatsAppService` y `VisionClient` justifican la capa de servicios.
