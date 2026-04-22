@once
@php
  $legalBrandName = $siteSettings->get('branding.name', 'Clínica Don Bosco');
  $legalAddress = $siteSettings->get('contact.address', 'Quito, Av. Colón y 6 de Diciembre');
  $legalPhone = $siteSettings->get('contact.phone', '0998742410');
  $legalEmail = $siteSettings->get('contact.email');
  $configuredContactEmail = config('mail.contact_to');
  if (! $legalEmail && is_string($configuredContactEmail) && ! str_contains($configuredContactEmail, 'example.com')) {
    $legalEmail = $configuredContactEmail;
  }
  $legalContact = $legalEmail ?: 'los canales institucionales publicados en el sitio web';
@endphp

<div id="privacy-policy-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="privacy-policy-title" aria-describedby="privacy-policy-summary" aria-hidden="true" data-legal-modal>
  <div class="modal-backdrop" data-legal-close></div>
  <div class="modal-dialog" role="document" tabindex="-1">
    <article class="card mx-auto flex w-full max-w-4xl flex-col !overflow-hidden">
      <header class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 sm:px-6">
        <div class="min-w-0">
          <p class="text-xs font-semibold uppercase tracking-widest text-teal-700">Políticas de privacidad</p>
          <h2 id="privacy-policy-title" class="mt-1 text-xl font-semibold text-slate-900">Tratamiento de datos personales y de salud</h2>
          <p id="privacy-policy-summary" class="mt-1 text-sm text-slate-500">Última actualización: abril de 2026</p>
        </div>
        <button type="button" class="btn btn-ghost px-2" aria-label="Cerrar políticas de privacidad" data-legal-close>
          <i class="ri-close-line text-lg" aria-hidden="true"></i>
        </button>
      </header>

      <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-8">
        <div class="space-y-5 text-sm leading-7 text-slate-600">
          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">1. Responsable del tratamiento</h3>
            <p>{{ $legalBrandName }}, con dirección en {{ $legalAddress }}, es responsable del tratamiento de los datos personales usados en esta plataforma de gestión médica. Para consultas sobre privacidad o ejercicio de derechos, el usuario puede comunicarse mediante {{ $legalContact }} o al teléfono {{ $legalPhone }}.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">2. Datos que tratamos</h3>
            <p>La plataforma puede recopilar datos de identificación y contacto, como nombres, cédula o documento de identidad, correo electrónico, teléfono, dirección, fecha de nacimiento, sexo, credenciales de acceso, tipo de cuenta asignado, estado de cuenta y datos necesarios para gestionar citas, pagos y comunicación con el usuario.</p>
            <p>Por la naturaleza del servicio, también se tratan datos sensibles de salud, incluidos motivo de consulta, historial clínico, antecedentes, alergias, diagnósticos, notas médicas, signos vitales, recetas, órdenes médicas, órdenes y resultados de laboratorio, documentos clínicos y comprobantes asociados a la atención.</p>
            <p>Además, se registran datos técnicos y de seguridad, como dirección IP, navegador, dispositivo, actividad de sesión, eventos de acceso, archivos cargados y registros necesarios para proteger la plataforma. Si el reconocimiento facial está habilitado y el usuario lo activa, se tratarán datos biométricos derivados de ese mecanismo de autenticación.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">3. Finalidad del uso de datos</h3>
            <p>Los datos se usan para crear y administrar cuentas, autenticar usuarios, aplicar permisos según el tipo de cuenta, agendar, confirmar, cancelar o reprogramar citas, mantener el historial clínico, emitir recetas, gestionar órdenes y resultados de laboratorio, registrar pagos, validar comprobantes y enviar comunicaciones relacionadas con la atención.</p>
            <p>También se usan para seguridad, prevención de accesos no autorizados, trazabilidad de operaciones, soporte, cumplimiento de obligaciones legales y atención de requerimientos de autoridades competentes.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">4. Base legal</h3>
            <p>El tratamiento se realiza conforme a la Ley Orgánica de Protección de Datos Personales de Ecuador, su reglamento y la normativa sanitaria aplicable. Según el caso, se fundamenta en la relación de prestación del servicio, el consentimiento del titular, el consentimiento explícito para datos sensibles cuando corresponda, el cumplimiento de obligaciones legales, la protección de intereses vitales y la gestión legítima de la seguridad de la plataforma.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">5. Cookies y comunicaciones</h3>
            <p>El sitio web utiliza cookies técnicas necesarias para mantener la sesión, proteger formularios y recordar preferencias indispensables de navegación. No se emplean para publicidad comportamental.</p>
            <p>La plataforma puede enviar correos electrónicos sobre citas, cambios de estado, recuperación de contraseña, recetas, resultados de laboratorio y avisos operativos. Si el envío por WhatsApp está habilitado, el número telefónico podrá usarse para notificaciones transaccionales y recordatorios relacionados con el servicio.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">6. Proveedores y comunicación de datos</h3>
            <p>Los datos podrán ser tratados por proveedores tecnológicos necesarios para correo electrónico, mensajería, almacenamiento, visualización de mapas, soporte o infraestructura. También podrán comunicarse a doctores, laboratorio, personal administrativo y superadministradores únicamente según la finalidad asistencial o administrativa y los permisos asignados.</p>
            <p>No se venden datos personales. Cualquier comunicación a autoridades o terceros se realizará cuando exista obligación legal, autorización válida, consentimiento aplicable o necesidad justificada para prestar el servicio.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">7. Seguridad y confidencialidad</h3>
            <p>La plataforma aplica autenticación, controles de acceso según el tipo de cuenta, validación de formularios, protección de contraseñas, restricciones sobre cuentas bloqueadas o suspendidas y medidas para limitar el acceso a documentos médicos, pagos y resultados. El personal con acceso a información clínica o administrativa debe mantener reserva y usar los datos solo para fines autorizados.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">8. Conservación</h3>
            <p>La información se conserva mientras sea necesaria para la atención médica, administración de citas, pagos, historial clínico, cumplimiento legal, auditoría, defensa de derechos o trazabilidad sanitaria. Cuando ya no exista una finalidad válida, los datos podrán eliminarse, anonimizarse, bloquearse o conservarse de forma restringida si la ley lo exige.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">9. Derechos del titular</h3>
            <p>El usuario puede ejercer los derechos de acceso, rectificación, actualización, eliminación, oposición, suspensión o limitación del tratamiento, portabilidad cuando sea aplicable y revocatoria del consentimiento. Para hacerlo, debe enviar una solicitud por los canales de contacto indicados, identificarse de forma suficiente y describir claramente el derecho que desea ejercer.</p>
            <p>La atención de la solicitud podrá estar sujeta a límites legales cuando la información sea necesaria para conservar historial clínico, cumplir obligaciones sanitarias, atender órdenes de autoridad o proteger derechos de terceros.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">10. Menores de edad y actualizaciones</h3>
            <p>Cuando se gestionen datos de niñas, niños o adolescentes, la información deberá ser proporcionada por su representante legal o por personal autorizado, con especial protección y finalidad sanitaria. Esta política puede actualizarse por cambios legales, operativos o del servicio; la versión vigente estará disponible desde los enlaces legales de la plataforma.</p>
          </section>
        </div>
      </div>

      <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 px-5 py-4 sm:px-6">
        <button type="button" class="btn btn-primary" data-legal-close>Entendido</button>
      </footer>
    </article>
  </div>
</div>

<div id="terms-service-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="terms-service-title" aria-describedby="terms-service-summary" aria-hidden="true" data-legal-modal>
  <div class="modal-backdrop" data-legal-close></div>
  <div class="modal-dialog" role="document" tabindex="-1">
    <article class="card mx-auto flex w-full max-w-4xl flex-col !overflow-hidden">
      <header class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 sm:px-6">
        <div class="min-w-0">
          <p class="text-xs font-semibold uppercase tracking-widest text-teal-700">Términos de servicio</p>
          <h2 id="terms-service-title" class="mt-1 text-xl font-semibold text-slate-900">Condiciones de uso de la plataforma</h2>
          <p id="terms-service-summary" class="mt-1 text-sm text-slate-500">Última actualización: abril de 2026</p>
        </div>
        <button type="button" class="btn btn-ghost px-2" aria-label="Cerrar términos de servicio" data-legal-close>
          <i class="ri-close-line text-lg" aria-hidden="true"></i>
        </button>
      </header>

      <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-8">
        <div class="space-y-5 text-sm leading-7 text-slate-600">
          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">1. Objeto del servicio</h3>
            <p>{{ $legalBrandName }} ofrece una plataforma web para apoyar la gestión de citas médicas y de laboratorio, administración de usuarios, historial clínico, recetas, órdenes médicas, resultados de laboratorio, comprobantes y pagos relacionados con la atención.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">2. Acceso y veracidad de la información</h3>
            <p>El acceso requiere una cuenta válida, credenciales personales y, cuando esté habilitado, mecanismos adicionales como reconocimiento facial. El usuario debe proporcionar información verdadera, completa y actualizada, especialmente datos de identificación, contacto, salud, citas y pagos.</p>
            <p>El usuario es responsable de proteger sus credenciales, cerrar sesión en equipos compartidos y notificar cualquier uso no autorizado de su cuenta.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">3. Uso según el tipo de cuenta</h3>
            <p>La plataforma aplica permisos diferenciados para pacientes, doctores, administradores, superadministradores y laboratorio. Cada usuario debe usar únicamente las funciones habilitadas para su cuenta y abstenerse de acceder, modificar, descargar o divulgar información que no le corresponda.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">4. Alcance médico del sistema</h3>
            <p>El sistema facilita la gestión administrativa y clínica, pero no reemplaza la valoración médica presencial, el criterio profesional ni la atención de emergencia. Ante síntomas graves o situaciones urgentes, el usuario debe acudir a un servicio médico de emergencia o comunicarse directamente con el establecimiento de salud.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">5. Citas, reprogramaciones e inasistencias</h3>
            <p>Las citas se registran según disponibilidad de horarios, profesional, especialidad, laboratorio, fecha, hora y motivo de atención. El sistema puede permitir confirmaciones, cancelaciones, reprogramaciones o marcación de inasistencia conforme a las reglas vigentes.</p>
            <p>El usuario debe revisar el estado de sus citas y acudir puntualmente. Las modificaciones pueden restringirse cuando la cita ya fue realizada, venció, fue cancelada, quedó marcada como inasistencia o existe una condición administrativa que impide nuevos agendamientos.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">6. Pagos y documentos</h3>
            <p>La plataforma puede gestionar pagos en efectivo o transferencia, registrar órdenes de cobro, comprobantes, verificaciones administrativas y recibos. Cuando se carguen comprobantes o documentos médicos, el usuario debe asegurarse de que sean auténticos, legibles y relacionados con la atención correspondiente.</p>
            <p>Los pagos pendientes, rechazados o en verificación pueden limitar determinadas acciones, incluido el agendamiento de nuevas citas, conforme a las reglas del sistema.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">7. Información médica y laboratorio</h3>
            <p>El historial clínico, recetas, órdenes médicas y resultados de laboratorio son información sensible y deben utilizarse únicamente para fines de atención, seguimiento y gestión sanitaria. El usuario no debe alterar, falsificar, ocultar, compartir indebidamente ni usar documentos médicos para fines ilícitos o ajenos al servicio.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">8. Responsabilidades del usuario</h3>
            <p>El usuario se compromete a usar la plataforma de forma lícita, respetuosa y segura; no vulnerar controles de acceso; no interferir con el funcionamiento del sitio web; no cargar archivos maliciosos; no suplantar identidades; no compartir credenciales; y no enviar información falsa, ofensiva o contraria a la ley.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">9. Disponibilidad y limitación de responsabilidad</h3>
            <p>La plataforma procurará mantener sus funciones disponibles y protegidas, pero pueden existir interrupciones por mantenimiento, actualizaciones, conectividad, fallas de proveedores externos, errores técnicos o eventos fuera de control razonable. Las notificaciones por correo o WhatsApp pueden depender de datos correctos, disponibilidad de terceros y configuración del servicio.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">10. Suspensión de cuentas</h3>
            <p>La administración puede suspender, bloquear o cancelar cuentas cuando exista uso indebido, datos falsos, riesgo de seguridad, incumplimiento de estos términos, requerimiento legal, inactividad operativa o necesidad administrativa justificada.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">11. Propiedad intelectual y confidencialidad</h3>
            <p>El diseño, contenidos, interfaces, signos distintivos y elementos del sistema pertenecen a sus titulares o licenciantes. El usuario recibe un permiso limitado para usar la plataforma conforme a estos términos. La información médica y administrativa debe tratarse con reserva y solo para fines autorizados.</p>
          </section>

          <section class="space-y-2">
            <h3 class="text-base font-semibold text-slate-900">12. Legislación aplicable y cambios</h3>
            <p>Estos términos se rigen por las leyes de la República del Ecuador. Cualquier desacuerdo se procurará resolver primero por los canales institucionales y, de no ser posible, ante las autoridades competentes. La plataforma puede actualizar estos términos por cambios legales, operativos o del servicio; la versión vigente estará disponible desde los enlaces legales del sistema.</p>
          </section>
        </div>
      </div>

      <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 px-5 py-4 sm:px-6">
        <button type="button" class="btn btn-primary" data-legal-close>Entendido</button>
      </footer>
    </article>
  </div>
</div>
<script>
(() => {
  const boot = () => {
    if (window.__legalModalsInitialized || typeof window.openLegalModal === 'function') {
      return;
    }

    window.__legalModalsInitialized = true;

    const aliases = {
      privacy: 'privacy-policy-modal',
      'privacy-policy': 'privacy-policy-modal',
      terms: 'terms-service-modal',
      'terms-service': 'terms-service-modal',
    };

    let activeModal = null;
    let previousFocus = null;

    const getModal = (value) => {
      const normalized = String(value || '').replace(/^#/, '').trim();
      const id = aliases[normalized] || normalized;

      return id ? document.getElementById(id) : null;
    };

    const setState = (modal, open) => {
      modal.classList.toggle('is-open', open);
      modal.setAttribute('aria-hidden', open ? 'false' : 'true');
      document.body.classList.toggle('modal-open', open);
    };

    const openLegalModal = (value) => {
      const modal = getModal(value);

      if (!modal || !modal.matches('[data-legal-modal]')) {
        return;
      }

      if (activeModal && activeModal !== modal) {
        setState(activeModal, false);
      }

      previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      activeModal = modal;
      setState(modal, true);

      const dialog = modal.querySelector('[role="document"]');
      requestAnimationFrame(() => (dialog || modal).focus({ preventScroll: true }));
    };

    const closeLegalModal = () => {
      if (!activeModal) {
        return;
      }

      const modal = activeModal;
      activeModal = null;
      setState(modal, false);

      if (previousFocus) {
        previousFocus.focus({ preventScroll: true });
      }

      previousFocus = null;
    };

    document.addEventListener('click', (event) => {
      const target = event.target instanceof Element ? event.target : null;

      if (!target) {
        return;
      }

      const opener = target.closest('[data-legal-open]');

      if (opener) {
        event.preventDefault();
        openLegalModal(opener.getAttribute('data-legal-open'));
        return;
      }

      if (target.closest('[data-legal-close]') && activeModal) {
        event.preventDefault();
        closeLegalModal();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && activeModal) {
        event.preventDefault();
        closeLegalModal();
      }
    });

    window.openLegalModal = openLegalModal;
    window.closeLegalModal = closeLegalModal;
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
</script>
@endonce
