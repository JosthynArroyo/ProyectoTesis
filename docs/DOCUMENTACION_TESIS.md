# Documentación técnica del sistema de gestión clínica

## 1. Descripción general del sistema
El sistema corresponde a una plataforma web para la gestión integral de procesos clínicos ambulatorios. Su propósito es organizar, en un mismo entorno, el ciclo de atención desde la programación de citas hasta el registro clínico, la emisión de recetas y el seguimiento posterior del paciente. Además de la operación asistencial, integra funciones administrativas para control de usuarios, horarios y monitoreo del estado de las citas.

En el contexto de una clínica, el problema principal que aborda es la fragmentación del proceso asistencial cuando se trabaja con registros manuales o con herramientas aisladas. La plataforma reduce duplicidad de datos, mejora la trazabilidad de cada consulta y disminuye demoras en tareas recurrentes como confirmar citas, registrar evolución clínica o compartir resultados.

Los roles de usuario implementados de forma explícita son: superadmin, administrador, doctor, paciente y laboratorio. Cada rol tiene accesos diferenciados y acciones propias, lo que permite separar funciones clínicas, operativas y de gobernanza del sistema.

## 2. Arquitectura del sistema
La solución se implementa con arquitectura MVC en Laravel. En esta arquitectura, las rutas reciben la solicitud, los controladores aplican reglas del negocio y validaciones, los modelos gestionan la interacción con la base de datos y las vistas presentan la información al usuario final. Este flujo organiza el sistema de forma modular y facilita su mantenimiento.

Aplicado al proyecto, la operación sigue la secuencia rutas, controladores, modelos, vistas y base de datos. Por ejemplo, una solicitud de cita inicia en una ruta protegida por rol, continúa con validaciones de disponibilidad y estado de cuenta en el controlador, persiste la información en entidades clínicas y administrativas, y finalmente muestra el resultado en paneles diferenciados para paciente o profesional.

Como servicios complementarios, el sistema incorpora un asistente conversacional para autogestión de citas y perfil, notificaciones por correo y por WhatsApp, validación humana mediante CAPTCHA con clasificación de imágenes, reconocimiento facial para autenticación opcional y tareas programadas para control de no presentación y recalculo de prioridad de atención.

## 3. Módulos del sistema

### Autenticación y roles
El módulo de autenticación controla el acceso mediante credenciales personales y redirige al panel correspondiente según rol. El registro público está deshabilitado, por lo que el alta de cuentas se realiza por flujos internos de administración o por escenarios controlados del asistente conversacional. La información clave se almacena en `users`, con relación de roles en `roles` y `role_user`, incluyendo además estado de cuenta, actividad reciente y suspensión temporal. Como acciones disponibles, los usuarios pueden iniciar y cerrar sesión, actualizar su perfil y, de forma opcional, registrar biometría facial para acceso por reconocimiento.

### Gestión de pacientes
Este módulo administra el ciclo de vida del paciente dentro de la institución, desde su registro hasta su actualización clínica y administrativa. Conserva datos de identificación, contacto y demográficos, así como banderas clínicas de priorización. Opera sobre `users`, `roles`, `role_user` y `patient_flags`. El paciente puede actualizar su información personal; el administrador puede crear, editar, activar, bloquear, suspender, desactivar o eliminar cuentas no privilegiadas, con controles específicos para impedir cambios indebidos en cuentas de alto nivel.

### Gestión de citas médicas
La gestión de citas coordina agenda, confirmación, cancelación, reprogramación, cierre de consulta y seguimiento. Registra paciente, profesional, especialidad, fecha, hora, estado, vigencia y atributos de prioridad clínica en `citas_medicas`, apoyándose en `horarios`, `especialidades`, `doctor_especialidad` y `cita_eventos` para disponibilidad y trazabilidad. El paciente agenda o modifica citas; el doctor acepta, rechaza o marca la atención como realizada; el sistema registra no presentación cuando la cita vence y mantiene historial de cambios para auditoría.

### Historial clínico profesional
El historial clínico profesional consolida la información asistencial de cada paciente desde la perspectiva del equipo médico. Integra notas clínicas firmadas, diagnósticos, evolución, signos vitales, antecedentes relevantes, resultados de laboratorio y recetas vinculadas a consultas previas. Utiliza principalmente `notas_soap`, `nota_soap_diagnosticos`, `nota_soap_enmiendas`, `citas_medicas`, `laboratorio_ordenes` y `recetas`. El doctor puede consultar historial de pacientes previamente atendidos; el paciente accede a sus propias notas firmadas; el administrador dispone de consulta de lectura para seguimiento institucional.

### Registro clínico (SOAP)
El registro SOAP estructura la documentación clínica de la consulta en componentes subjetivos, objetivos, evaluación diagnóstica y plan terapéutico. Guarda motivo de consulta, antecedentes, alergias, examen físico, signos vitales, diagnóstico y plan en `notas_soap`, con diagnósticos codificados en `nota_soap_diagnosticos` y trazabilidad de correcciones en `nota_soap_enmiendas`. El profesional puede guardar borrador, firmar la nota y registrar enmiendas justificadas; además, el cierre de la cita como consulta realizada exige que la nota se encuentre firmada.

### Recetas médicas
Este módulo formaliza la prescripción posterior a una consulta concluida. Almacena diagnóstico, medicamentos, indicaciones, ubicación del documento y fecha de envío en `recetas`, vinculada de manera unívoca a `citas_medicas`. El doctor puede crear, editar dentro de una ventana temporal controlada, reenviar y descargar la receta; el sistema genera el documento en formato PDF y lo remite al correo del paciente para continuidad terapéutica.

### Laboratorio
El componente de laboratorio está implementado y opera con dos circuitos complementarios. El primero vincula órdenes de examen a citas de laboratorio mediante `laboratorio_ordenes` y `citas_medicas`; el segundo gestiona solicitudes estructuradas mediante catálogo de pruebas y órdenes médicas en `lab_orders`, `lab_order_items`, `lab_tests` y `medical_orders`. Se registran tipo de examen, prioridad, preparación, indicaciones, estado de muestra y resultado. El doctor solicita exámenes para sus pacientes, el área de laboratorio registra toma de muestra y publica resultados, y el paciente puede solicitar exámenes de rutina autorizados y descargar reportes disponibles.

### Chatbot asistente clínico
El asistente clínico conversacional funciona como canal de autogestión para pacientes y usuarios no autenticados. Permite validar identidad, consultar disponibilidad, agendar, cancelar o reprogramar citas, revisar citas vigentes y actualizar datos de perfil. Procesa datos de identificación y contacto en `users`, agenda en `citas_medicas` y referencias de especialidad en `especialidades`, con apoyo de `captcha_challenges` y `captcha_images` para verificación humana. También contempla creación controlada de cuenta de paciente cuando no existe registro previo y se cumplen las validaciones de identidad.

### Notificaciones (correo y WhatsApp)
El módulo de notificaciones comunica eventos clínicos y operativos relevantes. Envía correos por creación o cambio de estado de citas, receta médica y resultados de laboratorio, y registra mensajería de WhatsApp en `whatsapp_messages` para trazabilidad de estado, error y proveedor. Como acciones automatizadas, el sistema notifica aceptación de cita y envía recordatorios por WhatsApp en ventana temporal definida antes de la consulta, además de procesar alertas por no presentación y cambios de prioridad.

## 4. Flujo de atención médica dentro del sistema
El proceso de atención inicia cuando el paciente agenda una cita, ya sea desde su panel o por el asistente conversacional, seleccionando especialidad, profesional y franja horaria disponible. En ese punto, el sistema verifica reglas clínicas y operativas de agenda para evitar conflictos y registra la solicitud en estado pendiente.

Posteriormente, el doctor revisa su bandeja de citas y confirma la atención. Durante la consulta, se documentan motivo, antecedentes, alergias, signos vitales y hallazgos clínicos siguiendo estructura SOAP. Esta etapa permite dejar la nota en borrador mientras evoluciona la entrevista y el examen, para firmarla al finalizar la valoración.

Con la nota firmada, la consulta puede cerrarse como realizada y pasa a formar parte del expediente longitudinal del paciente. Si la condición clínica lo requiere, se generan receta médica y solicitud de laboratorio dentro del mismo proceso de atención. El sistema conserva los resultados y documentos asociados para consultas futuras.

Finalmente, el seguimiento se ejecuta mediante el plan registrado por el profesional y la programación de una próxima cita cuando corresponde. De esta manera, cada atención queda conectada con la anterior y con la siguiente, sosteniendo continuidad asistencial y control evolutivo.

## 5. Base de datos
La estructura de datos se organiza alrededor de entidades clínicas y administrativas. La tabla `users` concentra identidad, datos de contacto, estado de cuenta y atributos de perfil; la tabla `roles` define el tipo de usuario y `role_user` materializa la asignación de roles. Las citas se almacenan en `citas_medicas`, vinculando paciente, profesional, especialidad y estado operativo.

La documentación clínica se almacena en `notas_soap`, con extensiones para diagnóstico en `nota_soap_diagnosticos` y enmiendas en `nota_soap_enmiendas`. En lugar de una tabla independiente de signos vitales, el sistema usa un esquema equivalente: los signos se guardan como estructura clínica dentro del registro SOAP, lo que mantiene unidos contexto de consulta y parámetros fisiológicos.

La prescripción farmacológica se registra en `recetas`, asociada a una consulta específica. El dominio de laboratorio se materializa con `laboratorio_ordenes` para órdenes ligadas a citas y con `lab_orders`, `lab_order_items`, `lab_tests` y `medical_orders` para solicitudes por catálogo y por orden médica. En lenguaje relacional natural, un paciente puede tener múltiples citas; cada cita puede generar una nota SOAP, una receta y una orden de laboratorio; y cada orden de laboratorio puede contener una o más pruebas según su circuito operativo.

## 6. Historial clínico electrónico
El expediente clínico electrónico se construye de forma incremental a partir de cada consulta firmada. La información subjetiva y objetiva del método SOAP, los diagnósticos, los signos vitales, los planes de seguimiento y las enmiendas posteriores se integran en una secuencia temporal que permite comprender la evolución del caso.

Este historial no se limita a texto clínico: también enlaza resultados de laboratorio y recetas emitidas, con lo cual el profesional puede revisar antecedentes terapéuticos y respuesta clínica en un mismo contexto. El modelo favorece continuidad asistencial al evitar pérdida de información entre consultas separadas.

La evolución del paciente queda respaldada por fechas, estados y autores de registro, lo que fortalece trazabilidad y soporte para decisiones médicas futuras. Así, el sistema funciona como expediente longitudinal y no solo como repositorio aislado de atenciones.

## 7. Seguridad y validaciones
La seguridad de acceso se apoya en autenticación por credenciales, sesiones controladas y verificación de estado de cuenta para impedir operación con cuentas bloqueadas, inactivas o suspendidas. También existe autenticación facial opcional, con registro biométrico previo, como mecanismo complementario.

Los permisos se aplican por rol y por funcionalidad específica. Esto limita el alcance de cada usuario según su responsabilidad institucional y restringe acciones críticas a perfiles autorizados. Además, el acceso al historial clínico aplica reglas de pertinencia asistencial, de modo que la consulta profesional se habilita sobre pacientes efectivamente atendidos.

La protección de datos médicos se refuerza mediante validaciones de entrada en formularios clínicos y administrativos, protección de sesión y token anti-CSRF, enlaces firmados para acciones sensibles, limitación de tasa de solicitudes y verificación humana en el canal conversacional. En conjunto, el sistema prioriza control de acceso, integridad del registro y trazabilidad de cambios.

## 8. Tecnologías utilizadas
El núcleo de la solución está desarrollado con Laravel sobre PHP 8.2, combinación adecuada para aplicaciones clínicas que requieren estructura modular, validaciones robustas y manejo claro de roles y procesos de negocio. En el entorno analizado, la base de datos activa es SQLite, con configuración preparada para motores como MySQL cuando se necesite escalar despliegue.

En la capa de interacción se emplea JavaScript para comportamiento dinámico de agendas, chatbot, flujos clínicos y autenticación facial, junto con CSS y utilidades de interfaz para mantener una experiencia consistente en los distintos paneles de usuario. Esta elección permite una operación web fluida sin abandonar la simplicidad de mantenimiento.

El sistema integra además servicios y bibliotecas especializadas: Twilio para mensajería WhatsApp, servicio de visión para validación de CAPTCHA, biblioteca de reconocimiento facial en cliente para autenticación biométrica, generación de PDF para documentos clínicos y exportación tabular para reportes. La combinación tecnológica resulta pertinente para una clínica porque equilibra costo de operación, capacidad de automatización y trazabilidad documental.

## 9. Conclusión técnica
Frente al registro manual, la plataforma ofrece trazabilidad continua, estandarización del acto clínico y reducción de errores operativos asociados a agendas dispersas o expedientes fragmentados. La integración de cita, SOAP, receta, laboratorio y notificaciones transforma procesos aislados en un flujo asistencial coherente.

En términos de atención médica, el sistema mejora oportunidad de respuesta, continuidad del seguimiento y disponibilidad de información clínica relevante en el momento de la consulta. Esto impacta directamente en calidad de decisión y en comunicación entre paciente, doctor y áreas de apoyo diagnóstico.

Desde la organización clínica, el resultado es una operación más ordenada y medible: existe control por roles, evidencia de cambios, automatización de recordatorios y acceso estructurado al historial electrónico. En consecuencia, la institución incrementa su capacidad de gestión y fortalece su modelo de atención centrado en el paciente.
