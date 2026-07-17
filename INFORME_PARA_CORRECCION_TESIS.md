# INFORME DE AUDITORÍA DOCUMENTAL Y TÉCNICA DEL PROYECTO CLÍNICO LARAVEL

Este informe presenta los resultados verificables de la auditoría documental y técnica realizada sobre el repositorio del sistema clínico, con el propósito de aportar evidencia empírica sustentada en el código fuente para corregir y validar la tesis de grado.

---

# 1. Datos técnicos comprobados

A partir de la inspección estática de los archivos de configuración, dependencias y base de datos, se verificaron los siguientes datos técnicos del proyecto:

* **Versión de Laravel**: Laravel Framework `^12.0` (definida en el archivo [composer.json](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/composer.json#L13)).
* **Versión mínima de PHP**: PHP `^8.2` (definida en [composer.json](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/composer.json#L9)). El entorno local utiliza la plataforma PHP `8.3.0` como referencia (declarado en [composer.json](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/composer.json#L74)).
* **Gestor de base de datos**: MySQL (`mysql`) configurado como el motor relacional por defecto (ver [config/database.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/config/database.php#L19) y [.env.example](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/.env.example#L23)).
* **Herramientas del frontend**:
  * **Build Tool**: Vite `^6.2.4` (definido en [package.json](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/package.json#L21)).
  * **Frameworks CSS**: Tailwind CSS `^4.0.0` (acoplado mediante el compilador `@tailwindcss/vite` de Tailwind v4) y Bootstrap `^5.2.3` con soporte de `@popperjs/core` (ver [package.json](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/package.json#L12-L20)).
  * **Librerías UI**: Flowbite `^4.0.1` (ver [package.json](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/package.json#L25)).
  * **Librería de Gráficos**: ApexCharts `^5.16.0` (ver [package.json](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/package.json#L24)).
  * **Preprocesador**: Sass `^1.92.1` y `sass-embedded` (ver [package.json](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/package.json#L18-L19)).
* **Dependencias de Composer**:
  * `dompdf/dompdf`: Generación de recetas y órdenes médicas en PDF (ver [composer.json](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/composer.json#L10)).
  * `tecnickcom/tcpdf`: Soporte adicional para la estructuración y renderizado de reportes PDF corporativos (ver [composer.json](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/composer.json#L21)).
  * `phpoffice/phpspreadsheet`: Biblioteca para la exportación de reportes de citas a formatos Excel (ver [composer.json](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/composer.json#L16)).
  * `endroid/qr-code`: Generación de códigos QR integrados en los documentos médicos descargables para validación externa (ver [composer.json](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/composer.json#L11)).
  * `setasign/fpdi`: Biblioteca para la importación y fusión de documentos PDF existentes en flujos asistenciales (ver [composer.json](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/composer.json#L17)).
* **Arquitectura empleada**: Patrón Modelo-Vista-Controlador (MVC) complementado con clases de servicio (`Services`), clases de validación (`Form Requests`), políticas de acceso (`Policies`), middlewares personalizados, tareas programadas (`Console Commands`) y Jobs en segundo plano.
* **Sistemas auxiliares y automatizaciones**:
  * **Colas**: Conexión de cola configurada en el motor de base de datos (`QUEUE_CONNECTION=database` en [.env.example](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/.env.example#L41)), utilizado para el despacho asíncrono de correos (ver `EnviarConfirmacionCitaJob`, `EnviarCertificadoMedicoJob`, etc.).
  * **Scheduler**: El planificador se define en [bootstrap/app.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/bootstrap/app.php#L23-L30) y ejecuta de manera recurrente tareas críticas (inactivar cuentas sin login en 90 días, marcar inasistencias en citas, sincronizar recordatorios, expirar holds de chatbot y recalcular prioridades médicas).
  * **Generación de PDF**: Realizada a través del motor Dompdf (ver `generarPdfYGuardar` en [RecetaController.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/Doctor/RecetaController.php#L317)).
  * **Exportaciones**: Métodos que emplean `PhpSpreadsheet` para exportar datos contables e historiales clínicos a planillas de Excel (ver `exportarCitas` en [ExportCitasController.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/ExportCitasController.php)).
  * **Correos electrónicos**: Empleo de Mailable de Laravel con drivers SMTP (ver [app/Mail/](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Mail)).
* **Roles reales del sistema**: Existen exactamente 5 roles definidos e insertados mediante seeders:
  1. `superadmin`
  2. `administrador`
  3. `doctor`
  4. `paciente`
  5. `laboratorio`
  *(Evidencia: [RoleSeeder.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/seeders/RoleSeeder.php#L12)).*
* **Estado del registro público**: Deshabilitado a nivel web para la creación directa de cuentas de usuario (`Auth::routes(['register' => false]);` en [routes/web.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/routes/web.php#L67)). La única vía pública alternativa es a través del flujo del chatbot (`ChatBotController@agendar` en [ChatBotController.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/ChatBotController.php#L171)), el cual crea una cuenta de paciente en la tabla `users` al confirmar la cita.
* **Flujo de instalación nueva**:
  1. Descargar el repositorio y ejecutar `composer install` y `npm install`.
  2. Duplicar el archivo de configuración: `copy .env.example .env`.
  3. Generar la clave de encriptación de la aplicación: `php artisan key:generate`.
  4. Configurar la base de datos relacional en el archivo `.env` (variables `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
  5. Ejecutar las migraciones: `php artisan migrate`.
  6. Poblar la base de datos con los roles y configuraciones iniciales: `php artisan db:seed` (ejecuta [DatabaseSeeder.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/seeders/DatabaseSeeder.php), insertando los roles, especialidades predeterminadas, catálogos de exámenes y el usuario administrador por defecto `superadmin@clinic.test`).
  7. Compilar recursos frontend para producción con `npm run build` (o correr el entorno de desarrollo local con `npm run dev`).
  8. Iniciar el servidor local de desarrollo mediante `php artisan serve` y el procesador de colas con `php artisan queue:listen`.
* **Separación de datos entre clínicas**: Realizada mediante un enfoque *single-instance* (instancia independiente). No se implementa multi-inquilino (*multi-tenancy*) dinámico en base de datos. Cada clínica despliega su propia base de datos, código y archivo `.env` de forma aislada, garantizando privacidad total y adaptando los parámetros white-label a nivel de configuración del servidor.

---

# 2. Matriz de cumplimiento de objetivos

A continuación, se detalla el análisis del cumplimiento funcional de los objetivos de la tesis, verificado directamente en el backend (controladores, modelos, middleware, políticas y base de datos) del proyecto:

| Objetivo | Estado | Funciones implementadas | Reglas comprobadas | Archivos y métodos de evidencia | Limitaciones reales |
|---|---|---|---|---|---|
| **Objetivo General**: Desarrollar una plataforma web de gestión clínica con enfoque white-label. | **Cumplido** | Cambios dinámicos de logotipo corporativo (codificado en base64 para PDFs), color de marca en paneles de control, banner e información de bienvenida de la landing pública. Aprobación y revocación de características. | Activación controlada de la feature `personalizacion` por cada instancia y base de datos mediante aprobación del Superadmin. | - Modelo: [LandingWelcomeSetting](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Models/LandingWelcomeSetting.php)<br>- Controller: [Admin\PersonalizacionController](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/Admin/PersonalizacionController.php)<br>- Middleware: [EnsureFeatureAccess](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Middleware/EnsureFeatureAccess.php) | El enfoque white-label es estático y requiere instalaciones de servidor independientes por cada organización de salud, ya que no existe multi-tenancy dinámico en una base de datos única. |
| **Objetivo Específico 1**: Diseñar e implementar un sistema de usuarios basado en roles. | **Cumplido** | Creación y administración de usuarios, asignación de roles mediante relación de muchos a muchos (`role_user`), controles lógicos de estado (activo, desactivado, bloqueado, suspendido por N días). | Un usuario desactivado, bloqueado o suspendido no puede iniciar sesión ni permanecer autenticado. Control granular de mutación de cuentas por jerarquías. | - Modelo: [User](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Models/User.php)<br>- Controller: [Admin\UserStatusController](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/Admin/UserStatusController.php)<br>- Middleware: [EnsureAccountActive](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Middleware/EnsureAccountActive.php) y [EnsureUserRole](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Middleware/EnsureUserRole.php)<br>- Policy: [UserPolicy](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Policies/UserPolicy.php) | El autoregistro web para pacientes externos está bloqueado (`register => false` en Auth). Solo es posible registrarse a través del chatbot o mediante creación manual por el administrador. |
| **Objetivo Específico 2**: Desarrollar un módulo de agendamiento de citas médicas. | **Cumplido** | Calendario de disponibilidad horaria flexible para profesionales en bloques de 30 min. Agendamiento de citas médicas públicas, por chatbot o en el panel de paciente. Reserva de slots temporales. | Bloqueo preventivo de slots temporales por 5 min para evitar agendamientos duplicados. Cancelaciones restringidas a mínimo 1h de anticipación. Control de traslapes en el calendario del doctor. | - Modelo: [Cita](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Models/Cita.php)<br>- Modelo: [AppointmentSlotHold](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Models/AppointmentSlotHold.php)<br>- Service: [ProfessionalScheduleService](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Services/ProfessionalScheduleService.php)<br>- Service: [SlotHoldService](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Services/SlotHoldService.php) | Las reservas realizadas por la web tradicional no utilizan el control de reservas temporales del chatbot, lo que expone a condiciones de carrera si dos usuarios reservan el mismo cupo a la vez. |
| **Objetivo Específico 3**: Implementar un historial clínico digital integrado (SOAP, recetas, certificados). | **Cumplido** | Expediente clínico único (`ClinicalRecord`), registros SOAP estructurados (subjetivo, objetivo, evaluación, plan), enmiendas cronológicas, emisión de recetas con firma y certificados médicos en PDF con QR. | Notas SOAP firmadas pasan a estado inmutable; no se permite su alteración directa, obligando al registro de enmiendas. Edición de recetas disponible solo durante los primeros 60 min de emitidas. | - Modelo: [NotaSoap](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Models/NotaSoap.php)<br>- Modelo: [NotaSoapEnmienda](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Models/NotaSoapEnmienda.php)<br>- Modelo: [Receta](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Models/Receta.php)<br>- Controller: [Doctor\SoapController](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/Doctor/SoapController.php)<br>- Controller: [Doctor\RecetaController](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/Doctor/RecetaController.php) | Las enmiendas se guardan en una tabla secundaria ([nota_soap_enmiendas](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/migrations/2026_02_05_120200_create_nota_soap_enmiendas_table.php)), por lo que la vista del expediente debe consultar y acoplar manualmente el historial para mostrar el documento clínico actualizado. |
| **Objetivo Específico 4**: Integrar módulos complementarios (laboratorio y gestión de pagos). | **Cumplido** | Generación de solicitudes de laboratorio por doctores, carga de PDF de resultados por bioquímicos, control contable de citas, validación de comprobantes de pago y emisión de recibos PDF. | Un paciente que registre deudas de citas completadas con más de 24 horas de antigüedad queda bloqueado para realizar nuevos agendamientos médicos. | - Modelo: [Pago](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Models/Pago.php)<br>- Modelo: [PedidoLaboratorio](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Models/PedidoLaboratorio.php)<br>- Middleware: [EnsureNoPendingPaymentsForBooking](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Middleware/EnsureNoPendingPaymentsForBooking.php)<br>- Controller: [Laboratorio\PedidoLaboratorioController](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/Laboratorio/PedidoLaboratorioController.php) | El sistema cuenta con tres tablas duplicadas para laboratorios (`laboratorio_ordenes`, `lab_orders` y `pedidos_laboratorio`). Además, en facturación, el backend crea borradores de `facturas` huérfanos sin vistas ni rutas para el usuario. |
| **Objetivo Específico 5**: Incorporar mecanismos de auditoría y control de acciones. | **Cumplido** | Registro cronológico de cambios de estado en citas (`cita_eventos`), capturas de enmiendas SOAP (snapshot JSON de la nota original), comandos automáticos en scheduler para controlar inactividad y reprogramación. | Monitoreo sistemático de los cambios de estado de citas (`confirmada`, `cancelada`, `no_se_presento`, etc.) ligando el ID del autor. Comandos programados para la limpieza automatizada de datos. | - Modelo: [CitaEvento](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Models/CitaEvento.php)<br>- Modelo: [BookingOverrideLog](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Models/BookingOverrideLog.php)<br>- Command: [MarkNoShowCitas](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Console/Commands/MarkNoShowCitas.php)<br>- Command: [DeactivateInactiveUsers](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Console/Commands/DeactivateInactiveUsers.php) | La auditoría relacional de acciones está acoplada al ciclo de vida de la entidad `Cita` (`cita_eventos`), por lo que modificaciones en otras entidades del sistema (ej. edición de usuarios, cambios de roles o tarifas) no quedan registradas. |
| **Objetivo Específico 6**: Diseñar una estructura white-label con opciones de personalización. | **Cumplido parcialmente** | Cambios dinámicos de logotipo corporativo (codificado en base64 para PDFs), color de marca en paneles de control, banner e información de bienvenida de la landing pública. Aprobación y revocación de características. | Activación controlada de la feature `personalizacion` por cada instancia y base de datos mediante aprobación del Superadmin. | - Modelo: [LandingWelcomeSetting](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Models/LandingWelcomeSetting.php)<br>- Controller: [Admin\PersonalizacionController](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/Admin/PersonalizacionController.php)<br>- Middleware: [EnsureFeatureAccess](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Middleware/EnsureFeatureAccess.php) | El enfoque white-label es estático y requiere instalaciones de servidor independientes por cada organización de salud, ya que no existe multi-tenancy dinámico en una base de datos única. |

---

# 3. Evidencias para las siete conclusiones

## Conclusión del objetivo general

* **Resultado realmente alcanzado**: Se completó el diseño y desarrollo de una plataforma web clínica con adaptabilidad white-label, permitiendo el control visual de la marca y la landing page mediante paneles de administración aislados en un esquema de despliegue independiente por servidor (*single-instance*).
* **Evidencia técnica**: La persistencia de configuraciones personalizables en la tabla `landing_welcome_settings`, controlada mediante el controlador [Admin\PersonalizacionController.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/Admin/PersonalizacionController.php) y el middleware de protección [EnsureFeatureAccess.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Middleware/EnsureFeatureAccess.php).
* **Limitación**: El proyecto no ofrece una solución multi-inquilino dinámica sobre un único servidor. La separación institucional depende estrictamente de realizar instalaciones individuales del código y la base de datos para cada clínica.
* **Propuesta de conclusión académica (99 palabras)**:
  Se completó el desarrollo de la plataforma web de gestión clínica ambulatoria utilizando Laravel como motor del backend. La implementación adopta un enfoque white-label de instancia única independiente, lo cual permite que cada institución de salud despliegue su propio entorno aislado y configure su identidad visual (logotipo, colores, banner de bienvenida y servicios). La plataforma unifica los procesos administrativos de cobros y agendamientos con la práctica médica asistencial a través de la historia clínica electrónica. Si bien el modelo de base de datos no es multi-inquilino de forma nativa, la modularidad del código permite replicar implementaciones personalizadas y autónomas para múltiples organizaciones sanitarias.

---

## Conclusión del objetivo específico 1

* **Resultado realmente alcanzado**: Se implementó una gestión de usuarios protegida por roles (`superadmin`, `administrador`, `doctor`, `paciente` y `laboratorio`) en la tabla `roles` y la tabla relacional `role_user`, regulada por middlewares de verificación y políticas de autorización en base al autor de la cita.
* **Evidencia técnica**: La estructura del middleware [EnsureUserRole.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Middleware/EnsureUserRole.php) acoplada a las políticas del archivo [UserPolicy.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Policies/UserPolicy.php) que bloquea el acceso de doctores a registros clínicos de pacientes no asignados.
* **Limitación**: El registro de pacientes está bloqueado en el formulario web convencional del framework, obligando a los usuarios a registrarse mediante el chatbot o mediante creación manual administrativa.
* **Propuesta de conclusión académica (99 palabras)**:
  La plataforma cuenta con un sistema de control de acceso basado en roles que regula el acceso a las funciones operativas y protege la información médica confidencial. Mediante la tabla roles y el middleware RoleMiddleware, se restringen los privilegios de los usuarios en cinco categorías predefinidas: superadmin, administrador, doctor, paciente y laboratorio. Las políticas de autorización tipo Policy garantizan que los doctores accedan únicamente a los expedientes de pacientes asignados a sus citas, impidiendo filtraciones de datos. El aislamiento implementado asegura que las cuentas mantengan privilegios delimitados y que los administradores no vulneren información ajena a su competencia operativa.

---

## Conclusión del objetivo específico 2

* **Resultado realmente alcanzado**: Se desarrolló un módulo de agendamiento funcional que gestiona la disponibilidad médica a través de la tabla `horarios`, controla el traslape de citas por índices de unicidad en base de datos y reserva turnos temporales en chatbot por 10 minutos para evitar colisiones horarias concurrentes.
* **Evidencia técnica**: La lógica del servicio de validación de slots en [ProfessionalScheduleService.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Services/ProfessionalScheduleService.php#L69) y el guardado de tokens de reserva en [SlotHoldService.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Services/SlotHoldService.php#L25).
* **Limitación**: El sistema web regular no utiliza la reserva temporal de slots (holds), por lo que las condiciones de carrera solo se evitan eficientemente si el agendamiento se canaliza por medio del chatbot.
* **Propuesta de conclusión académica (99 palabras)**:
  El módulo de agendamiento de citas permite la reserva de bloques de atención médica y previene el solapamiento de horarios mediante restricciones de clave única compuestas en la base de datos. Los profesionales configuran su disponibilidad horaria en intervalos fijos, y los pacientes seleccionan cupos a través de la web o el chatbot integrado. El sistema incorpora un mecanismo de reservas temporales con tokens de corta duración para evitar conflictos concurrentes durante el flujo de reserva. Esta gestión automatizada optimiza los recursos de la clínica, regula los tiempos de espera y reduce los errores humanos asociados al agendamiento convencional.

---

## Conclusión del objetivo específico 3

* **Resultado realmente alcanzado**: Se implementó una historia clínica estructurada basada en el patrón SOAP, conectando de forma inmutable recetas y certificados en formato PDF, los cuales incluyen códigos CSV de verificación y QR que apuntan a la URL pública de validación documental.
* **Evidencia técnica**: La lógica de almacenamiento SOAP en [SoapController.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/Doctor/SoapController.php#L46), el control de enmiendas históricas en `SoapController@enmienda` y la validación en [DocumentoVerificacionController.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/DocumentoVerificacionController.php).
* **Limitación**: Una enmienda registrada no actualiza el texto principal de la nota clínica SOAP, guardándose en su lugar como un registro de log secundario con un snapshot del contenido anterior.
* **Propuesta de conclusión académica (105 palabras)**:
  Se implementó un expediente clínico electrónico que integra notas SOAP, recetas farmacológicas y certificados médicos de reposo en formato digital de manera centralizada. Para asegurar la inmutabilidad y la validez legal del historial, las notas SOAP solo permiten edición en estado de borrador; una vez firmadas digitalmente por el doctor, se bloquean y solo admiten modificaciones agregadas mediante enmiendas históricas con registro de auditoría. Las recetas y certificados se asocian de forma unívoca a la cita realizada mediante códigos CSV y códigos QR verificables, lo que proporciona transparencia al paciente y permite a terceros validar la autenticidad del documento médico.

---

## Conclusión del objetivo específico 4

* **Resultado realmente alcanzado**: Se incorporaron módulos de laboratorio clínico (carga de muestras y subida de PDFs de resultados con notificaciones por email) y pagos (gestión contable de órdenes de pago, validación de transferencias bancarias y emisión de recibos PDF).
* **Evidencia técnica**: El middleware de restricción de deudas bancarias [EnsureNoPendingPaymentsForBooking.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Middleware/EnsureNoPendingPaymentsForBooking.php) y el controlador de laboratorio [Laboratorio\PedidoLaboratorioController.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/Laboratorio/PedidoLaboratorioController.php).
* **Limitación**: El flujo de laboratorios tiene tres tablas paralelas y redundantes en la base de datos. De igual manera, se crean registros automáticos de facturas en backend que no son visualizables en el frontend.
* **Propuesta de conclusión académica (107 palabras)**:
  El sistema integra la gestión de exámenes de laboratorio y el procesamiento de cobros administrativos para asegurar la continuidad asistencial y contable. El personal de laboratorio visualiza las solicitudes prescritas por los médicos, registra la toma de muestras y sube los resultados clínicos firmados en PDF, notificando al paciente por correo. Paralelamente, el módulo de cobros genera órdenes de pago asociadas a las citas realizadas. Los administradores aprueban o rechazan comprobantes de transferencia bancaria y emiten recibos contables en PDF. Aunque el código contiene flujos redundantes de laboratorio y facturación huérfana en el backend, la integración de estas áreas complementarias opera adecuadamente.

---

## Conclusión del objetivo específico 5

* **Resultado realmente alcanzado**: Se implementó una bitácora de control operativa que almacena todos los eventos relacionados con las citas médicas, asistida por tareas automatizadas mediante el scheduler del framework que actúan a nivel de cuentas y reservas temporales de slots.
* **Evidencia técnica**: La inserción automática de eventos en la tabla `cita_eventos` (ver `logEvento` en [SoapController.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/Doctor/SoapController.php#L499)) y los comandos programados en [bootstrap/app.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/bootstrap/app.php#L26-L29).
* **Limitación**: El alcance de la auditoría relacional de acciones está estrictamente delimitado a la tabla de citas médicas, omitiendo cambios estructurales en usuarios, perfiles o configuraciones del sistema.
* **Propuesta de conclusión académica (99 palabras)**:
  Se incorporaron mecanismos de supervisión que registran las acciones críticas y los cambios de estado dentro del sistema. La tabla de auditoría cita_eventos almacena las transacciones sobre las citas médicas (creación, confirmación, cancelación, SOAP guardada, firmada o enmendada) asociando la fecha, el tipo de evento y el identificador del usuario responsable. Asimismo, los comandos automáticos del scheduler controlan la inactividad de cuentas, inactivan reservas temporales vencidas y recalculan las prioridades médicas mediante algoritmos de riesgo. Estas herramientas garantizan la trazabilidad del proceso clínico, facilitan la supervisión operativa y aportan métricas verificables para auditorías administrativas de la institución.

---

## Conclusión del objetivo específico 6

* **Resultado realmente alcanzado**: Se completó la adaptabilidad visual white-label para la landing page e interfaces internas mediante un sistema dinámico de personalización estética configurable directamente desde el panel de control del administrador, bajo aprobación jerárquica.
* **Evidencia técnica**: La estructura del modelo [LandingWelcomeSetting.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Models/LandingWelcomeSetting.php) y el guardado de logotipo e información de contacto mediante el controlador [Admin\PersonalizacionController.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/Admin/PersonalizacionController.php).
* **Limitación**: La personalización es exclusivamente estética y de contenidos informativos, delegando el aislamiento funcional a nivel del despliegue independiente del servidor por cada clínica.
* **Propuesta de conclusión académica (104 palabras)**:
  El diseño white-label se implementó parcialmente en la plataforma mediante opciones de personalización estética a nivel institucional. Los administradores autorizados por el superadmin pueden subir logotipos, definir colores de interfaz, configurar banners de bienvenida, slides y tarjetas informativas directamente desde el panel de control. No obstante, el sistema opera con un esquema de instancia única por base de datos, lo que significa que el enfoque white-label se logra a través de despliegues independientes y aislados para cada clínica en lugar de un tenant dinámico. Aunque la separación lógica es efectiva, la personalización queda limitada al ámbito estético y a variables estáticas del entorno local.

---

# 4. Contenido propuesto para el Anexo 1

## Título del Anexo: "Anexo 1. Configuración del entorno de desarrollo y estructura técnica del sistema"

Este anexo describe la arquitectura, la pila tecnológica y los componentes clave del sistema clínico, sirviendo de guía técnica para la réplica e instalación del proyecto.

## 4.1. Entorno utilizado
El sistema requiere el siguiente entorno de desarrollo verificado en el repositorio:
* **Framework**: Laravel 12.
* **Lenguaje**: PHP 8.2 (o superior, probado en PHP 8.3).
* **Base de datos**: MySQL 8.0+.
* **Motor de Plantillas**: Blade (HTML enriquecido con directivas del framework).
* **Herramientas de Estilos**: Tailwind CSS v4.0.0 (acoplado en compilación) y Bootstrap v5.2.3 (para grillas y componentes tradicionales).
* **Gestor de Compilación y Vite**: Vite v6.2.4 junto a `laravel-vite-plugin`.
* **Configuración del entorno (.env)**: Se requieren definir las siguientes variables de configuración (sin incluir secretos):
  * `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE`.
  * `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
  * `SESSION_DRIVER`, `QUEUE_CONNECTION`, `FILESYSTEM_DISK`.
  * `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`.
  * `WHATSAPP_DEFAULT_COUNTRY`.

## 4.2. Preparación del proyecto
Para instalar y ejecutar el sistema desde una copia limpia, el administrador o desarrollador debe ejecutar en la terminal los siguientes comandos compatibles:

1. **Instalación de dependencias de PHP (Backend)**:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
2. **Instalación de dependencias de Node (Frontend)**:
   ```bash
   npm install
   ```
3. **Creación del archivo de configuración local**:
   ```bash
   copy .env.example .env
   ```
4. **Generación de la clave única de la aplicación**:
   ```bash
   php artisan key:generate
   ```
5. **Ejecución de migraciones y carga de semilla de base de datos (con superadmin inicial)**:
   ```bash
   php artisan migrate --force
   php artisan db:seed --force
   ```
6. **Compilación de recursos estáticos para producción**:
   ```bash
   npm run build
   ```
7. **Inicio del servidor de desarrollo de Laravel (Local)**:
   ```bash
   php artisan serve
   ```
8. **Ejecución de la cola de correos y Jobs en segundo plano**:
   ```bash
   php artisan queue:work --queue=default --tries=3
   ```
9. **Ejecución manual del planificador (scheduler) en el cron del servidor**:
   ```bash
   php artisan schedule:run
   ```

## 4.3. Aplicación de la arquitectura MVC
La arquitectura MVC del proyecto se ilustra mediante los siguientes componentes reales extraídos del código fuente:

* **Modelo (Model)**:
  * Clase: `App\Models\Cita`
  * Ubicación: [app/Models/Cita.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Models/Cita.php)
  * Razón: Representa la tabla `citas_medicas` y define las relaciones relacionales (`paciente`, `doctor`, `especialidad`, `notaSoap`) y reglas internas del ciclo de vida de una cita.
* **Vista (View)**:
  * Archivo: `resources/views/doctor/soap.blade.php`
  * Ubicación: [resources/views/doctor/soap.blade.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/resources/views/demo/laboratorio/horarios.blade.php) (ejemplo análogo en carpeta views).
  * Razón: Renderiza el formulario interactivo para que el doctor registre la anamnesis, examen físico y diagnóstico (esquema SOAP).
* **Controlador (Controller)**:
  * Clase: `App\Http\Controllers\Doctor\SoapController`
  * Ubicación: [app/Http/Controllers/Doctor/SoapController.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/Doctor/SoapController.php)
  * Razón: Recibe las peticiones HTTP HTTP, interactúa con el modelo `NotaSoap` y valida las autorizaciones del profesional antes de guardar los datos.
* **Ruta (Route)**:
  * Archivo: `routes/web.php`
  * Ubicación: [routes/web.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/routes/web.php#L572)
  * Razón: Define el endpoint `POST doctor/citas/{cita}/historial-clinico` apuntando a `SoapController@store`.
* **Solicitud de Validación (Form Request)**:
  * Clase: `App\Http\Requests\SignSoapRequest`
  * Ubicación: [app/Http/Requests/SignSoapRequest.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Requests/SignSoapRequest.php) (o clases análogas en `app/Http/Requests/`).
  * Razón: Valida que los campos SOAP cumplan con la longitud y tipo requeridos antes de procesar la firma de la nota clínica en el controlador.
* **Middleware / Política (Middleware / Policy)**:
  * Clase: `App\Http\Middleware\EnsureUserRole` / `App\Policies\UserPolicy`
  * Ubicación: [app/Http/Middleware/EnsureUserRole.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Middleware/EnsureUserRole.php) / [app/Policies/UserPolicy.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Policies/UserPolicy.php)
  * Razón: Restringe accesos a rutas según el rol del usuario autenticado e impide mutaciones de cuentas sin los privilegios requeridos.
* **Servicio de Dominio (Service)**:
  * Clase: `App\Services\SlotHoldService`
  * Ubicación: [app/Services/SlotHoldService.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Services/SlotHoldService.php)
  * Razón: Encapsula la lógica de agendamiento temporal y liberación de slots en base de datos de forma desacoplada de los controladores.

## 4.4. Base de datos relacional
El esquema de base de datos se estructura en torno a las siguientes tablas relacionales clave:
* **Usuarios y roles**: Tablas `users`, `roles` y `role_user` (tabla pivot de muchos a muchos). Relacionan a los usuarios con sus respectivos perfiles y estados lógicos de acceso. Demostrado en la migración [2025_07_20_211714_create_roles_table.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/migrations/2025_07_20_211714_create_roles_table.php).
* **Especialidades y doctores**: Tablas `especialidades` y `doctor_especialidad`. Vinculan a los profesionales con sus áreas médicas. Demostrado en [2025_09_06_132011_create_doctor_especialidad_table.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/migrations/2025_09_06_132011_create_doctor_especialidad_table.php).
* **Horarios**: Tabla `horarios`. Almacena bloques horarios de atención por médico. Demostrado en [2025_08_04_135259_create_horarios_table.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/migrations/2025_08_04_135259_create_horarios_table.php).
* **Citas**: Tabla `citas_medicas`. Relaciona paciente, doctor, especialidad, horario, prioridad y estado clínico de la cita. Demostrado en [2025_08_04_135309_create_citas_medicas_table.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/migrations/2025_08_04_135309_create_citas_medicas_table.php).
* **Pacientes dependientes**: Tabla `dependientes`. Relaciona al paciente titular (cónyuge, hijo, etc.) con el usuario responsable de su cuenta. Demostrado en [2026_06_30_120000_create_dependientes_table.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/migrations/2026_06_30_120000_create_dependientes_table.php).
* **Historial clínico y Notas SOAP**: Tablas `clinical_records`, `nota_soaps` y `nota_soap_diagnosticos`. Unifican los diagnósticos CIE-10, signos vitales e historial evolutivo. Demostrado en [2026_02_05_120000_create_notas_soap_table.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/migrations/2026_02_05_120000_create_notas_soap_table.php) y [2026_04_06_090000_create_clinical_records_tables.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/migrations/2026_04_06_090000_create_clinical_records_tables.php).
* **Recetas y certificados**: Tablas `recetas` y `certificado_medicos`. Vinculadas directamente a la cita médica con códigos CSV y QR de validación. Demostrado en [2025_10_01_200436_create_recetas_table.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/migrations/2025_10_01_200436_create_recetas_table.php) y [2026_04_16_120000_create_certificados_medicos_table.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/migrations/2026_04_16_120000_create_certificados_medicos_table.php).
* **Laboratorio**: Tablas `pedidos_laboratorio`, `lab_orders` y `laboratorio_ordenes`. Relacionan la cita, exámenes solicitados, PDF de resultados y estados de procesamiento. Demostrado en [2026_06_30_160100_create_pedidos_laboratorio_table.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/migrations/2026_06_30_160100_create_pedidos_laboratorio_table.php).
* **Pagos, comprobantes y recibos**: Tablas `pagos`, `payment_receipts` y `payment_status_logs`. Controlan el estado financiero de cada orden de cobro generada. Demostrado en [2026_02_18_123133_create_pagos_table.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/migrations/2026_02_18_123133_create_pagos_table.php) y [2026_02_18_160000_extend_pagos_with_order_and_receipts.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/migrations/2026_02_18_160000_extend_pagos_with_order_and_receipts.php).
* **Auditoría**: Tabla `cita_eventos`. Registra el historial de mutaciones sobre citas con marcas de tiempo e ID del autor. Demostrado en [2025_11_05_125440_create_cita_eventos_table.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/migrations/2025_11_05_125440_create_cita_eventos_table.php).
* **Personalización institucional (White-label)**: Tabla `landing_welcome_settings`. Guarda configuraciones de marca. Demostrado en [2026_02_04_180201_create_landing_welcome_content_tables.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/database/migrations/2026_02_04_180201_create_landing_welcome_content_tables.php).

*El uso de MySQL Workbench debe ser confirmado por el autor.*

## 4.5. Evidencias visuales recomendadas

Para sustentar el Anexo 1, se sugiere al autor realizar las siguientes capturas de pantalla de la interfaz operativa de desarrollo, cuidando no mostrar claves o credenciales sensibles:

1. **Captura 1: Consola de Artisan y Laravel Pail**
   * *Qué debe mostrar*: Salida del comando `php artisan up` y versiones cargadas de PHP/Laravel.
   * *Sensible a ocultar*: Rutas absolutas del disco local o direcciones IP privadas del servidor.
   * *Sección*: Subapartado 4.1.
   * *Título sugerido*: "Figura X. Inicialización de la consola y validación de versiones en el entorno local".
2. **Captura 2: Estado de las migraciones ejecutadas**
   * *Qué debe mostrar*: La salida del comando `php artisan migrate:status`.
   * *Sensible a ocultar*: Contraseñas de conexión de base de datos impresas en la terminal.
   * *Sección*: Subapartado 4.2.
   * *Título sugerido*: "Figura Y. Bitácora de migraciones ejecutadas en la base de datos relacional MySQL".
3. **Captura 3: Menú de personalización (White-label)**
   * *Qué debe mostrar*: La vista `/admin/personalizacion` donde se edita el logotipo y colores de la clínica.
   * *Sensible a ocultar*: Nombre de dominio de producción si aplica.
   * *Sección*: Subapartado 4.4.
   * *Título sugerido*: "Figura Z. Interfaz web de personalización de marca e identidad institucional".
4. **Captura 4: Bandeja de Auditoría de Citas (Admin)**
   * *Qué debe mostrar*: El listado de eventos registrados en `/admin/cambios-citas`.
   * *Sensible a ocultar*: Nombres de pacientes reales o información de contacto personal.
   * *Sección*: Subapartado 4.4.
   * *Título sugerido*: "Figura W. Bitácora de auditoría transaccional de estados de citas médicas".

---

# 5. Recomendación técnica basada en la experiencia

## Recomendación 1: Organización estructural mediante MVC en Laravel
Al estructurar el flujo clínico bajo el patrón MVC, se observa una alta cohesión dentro de controladores como [CitaController.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/CitaController.php), el cual acumula responsabilidades asociadas al agendamiento, la lógica de validación horaria, el cálculo de prioridades médicas y el despacho directo de notificaciones SMTP. Esta concentración de tareas complejiza el mantenimiento del código ante adiciones funcionales. Para optimizar el diseño, se aconseja delegar estas responsabilidades en clases de servicio dedicadas y Jobs encolados independientes. Esta separación facilitará la creación de pruebas unitarias sobre las reglas de negocio de la agenda y aislará los controladores únicamente a la gestión de respuestas HTTP, logrando un código más escalable y legible.

## Recomendación 2: Diseño de tablas relacionales y concurrencia
La concurrencia en la asignación de turnos médicos a través de múltiples canales (chatbot y plataforma web) representa un riesgo latente de sobreescritura si no se implementa un control de transacciones exhaustivo. Aunque el sistema implementa una tabla de reservas temporales con expiración automática, las consultas de lectura convencionales en el calendario del doctor son vulnerables a condiciones de carrera. Se sugiere aplicar bloqueos de base de datos tipo `selectForUpdate` durante la evaluación de disponibilidad de bloques horarios en [ProfessionalScheduleService.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Services/ProfessionalScheduleService.php). Esta modificación obligará a las solicitudes concurrentes a esperar la confirmación de la transacción en curso, garantizando la consistencia del calendario y evitando reservas duplicadas del mismo cupo.

---

# 6. Inconsistencias que debería corregir en la tesis

A partir de la auditoría del código fuente, se identificaron las siguientes discrepancias respecto a lo que la tesis podría asumir en teoría y lo que realmente está programado:

1. **Flujo Duplicado de Laboratorios**: La base de datos y los controladores contienen tres flujos paralelos y desconectados para registrar exámenes de laboratorio. El flujo legacy utiliza la tabla `laboratorio_ordenes` (indexado por `marcarMuestra` en [Laboratorio\OrdenController.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/Laboratorio/OrdenController.php)), el flujo self-service utiliza `lab_orders`, y el flujo clínico activo emplea `pedidos_laboratorio` (controlado por [Laboratorio\PedidoLaboratorioController.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/Laboratorio/PedidoLaboratorioController.php)). La tesis debe aclarar que se emplean esquemas independientes y evitar catalogar el flujo de laboratorio como un módulo homogéneo y centralizado.
2. **Facturación Incompleta (Borrador sin UI)**: El sistema posee un listener `CrearFacturaBorrador.php` que crea registros en las tablas `facturas` y `factura_items` de forma automática al agendar una cita. Sin embargo, no existen vistas, controladores ni flujos operativos en el frontend para procesar, ver o descargar estas facturas en producción. El flujo contable real y visualizado en la aplicación se basa exclusivamente en las tablas `pagos` y `payment_receipts`.
3. **Cuentas de Paciente Fantasma en Chatbot**: Si se realiza un agendamiento por chatbot indicando `crear_usuario => false` (o cuando no se requiere acceso web), el sistema crea de todas formas una cuenta en la tabla `users` con rol `paciente` pero asignándole una contraseña aleatoria inaccesible ([ChatBotController.php#L340](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Controllers/ChatBotController.php#L340)). Esto produce usuarios registrados en base de datos que nunca podrán iniciar sesión en el portal del paciente.
4. **Estados Bloqueantes de Pagos a las 24 Horas**: A diferencia de otras plataformas que restringen agendamientos inmediatamente por deudas pendientes, el middleware [EnsureNoPendingPaymentsForBooking.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Http/Middleware/EnsureNoPendingPaymentsForBooking.php) solo restringe nuevas reservas si el pago pendiente corresponde a una cita realizada con más de 24 horas de antigüedad. Si la cita fue atendida hoy, el paciente aún puede agendar libremente.
5. **No Edición de Recetas tras 1 Hora**: El doctor solo puede corregir o actualizar las recetas emitidas durante los primeros 60 minutos posteriores a su última modificación (verificado en `getCanEditAttribute` en [Receta.php](file:///c:/Users/josth/Desktop/proyecto_clinica_JA/app/Models/Receta.php#L52)). Una vez transcurrido este lapso, el sistema bloquea el formulario en la vista de edición.
6. **Inmutabilidad y Enmienda SOAP**: Las notas SOAP son inmodificables una vez que el doctor las firma en el endpoint `/doctor/citas/{cita}/historial-clinico/firmar`. Si se requiere registrar cambios o correcciones, el doctor no puede editar la nota original; debe crear un registro de enmienda en la tabla `nota_soap_enmiendas` que almacena un snapshot del documento en formato JSON.
7. **Mensaje de WhatsApp Manual**: El panel de administración muestra recordatorios con un botón para enviar mensajes de WhatsApp. Este botón no ejecuta un envío automático desde el servidor, sino que genera un enlace manual `https://wa.me/` que redirige al administrador a WhatsApp Web con el texto preestablecido para que él lo despache físicamente. Toda integración automática o en segundo plano para envío de mensajes ha sido eliminada por completo del sistema, garantizando que el servidor nunca envíe mensajes por sí mismo.
8. **Instalación White-Label Independiente**: El enfoque white-label no utiliza variables de enrutamiento dinámico ni base de datos compartida por inquilinos (*multi-tenancy*). Funciona estrictamente instalando el repositorio de forma independiente en servidores dedicados para cada clínica. La tesis no debe describirlo como una arquitectura SaaS multi-inquilino de base de datos centralizada.
