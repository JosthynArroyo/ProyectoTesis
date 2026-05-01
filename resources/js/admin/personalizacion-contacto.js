import {
  createPreviewModalController,
  escapeHtml,
  getValue,
} from './personalizacion-preview-utils';

document.addEventListener('DOMContentLoaded', () => {
  const formRoot = document.querySelector('[data-contacto-form]');

  if (!formRoot) return;

  const previewConfig = parseJson(formRoot.querySelector('[data-contact-preview-config]')?.textContent || '{}');
  const modalController = createPreviewModalController(formRoot);
  let renderToken = null;

  const scheduleRender = () => {
    if (renderToken !== null) return;

    renderToken = window.requestAnimationFrame(() => {
      renderToken = null;
      renderPreview();
      modalController?.syncScale();
    });
  };

  const renderPreview = () => {
    if (!modalController?.previewRoot) return;

    const state = {
      infoBadge: getValue(formRoot, 'contact_info_badge', 'Contacto'),
      title: getValue(formRoot, 'contact_title', 'Nombre de la clinica'),
      subtitle: getValue(
        formRoot,
        'contact_subtitle',
        'Canales de contacto de la clinica para consultas administrativas, horarios y orientacion sobre el uso del sistema de citas.',
      ),
      addressLabel: getValue(formRoot, 'contact_address_label', 'Direccion'),
      address: getValue(formRoot, 'contact_address', ''),
      phoneLabel: getValue(formRoot, 'contact_phone_label', 'Telefono'),
      phone: getValue(formRoot, 'contact_phone', ''),
      email: getValue(formRoot, 'contact_email', ''),
      hoursLabel: getValue(formRoot, 'contact_hours_label', 'Horario'),
      hours: getValue(formRoot, 'contact_hours', ''),
      mapTitle: getValue(formRoot, 'contact_map_title', 'Ubicacion de la clinica'),
      formSectionBadge: getValue(formRoot, 'contact_form_section_badge', 'Escribenos'),
      formTitle: getValue(formRoot, 'contact_form_title', 'Formulario de contacto'),
      formBadge: getValue(formRoot, 'contact_form_badge', 'Mensaje para la clinica'),
      submitText: getValue(formRoot, 'contact_form_submit_text', 'Enviar'),
      nameLabel: getValue(formRoot, 'contact_form_name_label', 'Nombre'),
      namePlaceholder: getValue(formRoot, 'contact_form_name_placeholder', 'Ej. Josthyn Arroyo'),
      emailLabel: getValue(formRoot, 'contact_form_email_label', 'Correo electronico'),
      emailPlaceholder: getValue(formRoot, 'contact_form_email_placeholder', 'Ej. contacto@clinica.test'),
      phoneFieldLabel: getValue(formRoot, 'contact_form_phone_label', 'Telefono'),
      phonePlaceholder: getValue(formRoot, 'contact_form_phone_placeholder', 'Ej. 0991234567'),
      subjectLabel: getValue(formRoot, 'contact_form_subject_label', 'Asunto'),
      subjectPlaceholder: getValue(formRoot, 'contact_form_subject_placeholder', 'Ej. Consulta sobre horarios'),
      messageLabel: getValue(formRoot, 'contact_form_message_label', 'Mensaje'),
      messagePlaceholder: getValue(
        formRoot,
        'contact_form_message_placeholder',
        'Escribe el motivo de tu contacto y los detalles necesarios.',
      ),
      messageHelp: getValue(
        formRoot,
        'contact_form_message_help',
        'Describe el motivo de tu contacto. Max. 1000 caracteres.',
      ),
    };

    const brand = previewConfig.branding || {};
    const footer = previewConfig.footer || {};
    const navigation = (previewConfig.navigation || [])
      .map((label) => `<span class="public-site-preview__nav-item">${escapeHtml(label)}</span>`)
      .join('');
    const footerLinks = (footer.links || [])
      .map((label) => `<span class="public-site-preview__footer-link">${escapeHtml(label)}</span>`)
      .join('');
    const footerLegal = (footer.legal || [])
      .map((label) => `<span class="public-site-preview__footer-link">${escapeHtml(label)}</span>`)
      .join('');

    const brandLogoMarkup = brand.logo
      ? `<img src="${escapeHtml(brand.logo)}" alt="${escapeHtml(brand.name || 'Clinica')}">`
      : '<div class="public-site-preview__image-fallback"><i class="ri-hospital-line"></i></div>';

    modalController.previewRoot.innerHTML = `
      <div class="public-site-preview">
        <div class="public-site-preview__shell">
          <section class="public-site-preview__card">
            <header class="public-site-preview__header">
              <div class="public-site-preview__brand">
                <div class="public-site-preview__brand-mark">
                  ${brandLogoMarkup}
                </div>
                <div class="public-site-preview__brand-copy">
                  <p class="public-site-preview__title">${escapeHtml(brand.name || 'Nombre de la clinica')}</p>
                  <p class="public-site-preview__meta">${escapeHtml(brand.navbar_text || 'Tu salud, nuestra mision')}</p>
                </div>
              </div>
              <nav class="public-site-preview__nav">
                ${navigation || '<span class="public-site-preview__meta">Sin enlaces visibles</span>'}
              </nav>
              <span class="public-site-preview__ghost-button">${escapeHtml(brand.login_text || 'Ingresar')}</span>
            </header>
          </section>

          <section class="public-site-preview__panel">
            <div class="public-site-preview__panel-body">
              <div class="public-site-preview__contact-grid">
                <aside class="public-site-preview__contact-card">
                  <span class="public-site-preview__eyebrow">${escapeHtml(state.infoBadge)}</span>
                  <h1 class="public-site-preview__heading">${escapeHtml(state.title)}</h1>
                  <p class="public-site-preview__text">${escapeHtml(state.subtitle)}</p>

                  <div class="public-site-preview__contact-list">
                    <div>
                      <p class="public-site-preview__meta">${escapeHtml(state.addressLabel)}</p>
                      <p class="public-site-preview__contact-text">${escapeHtml(state.address)}</p>
                    </div>
                    <div>
                      <p class="public-site-preview__meta">${escapeHtml(state.phoneLabel)}</p>
                      <p class="public-site-preview__contact-text">${escapeHtml(state.phone)}</p>
                    </div>
                    <div>
                      <p class="public-site-preview__meta">Correo</p>
                      <p class="public-site-preview__contact-text">${escapeHtml(state.email)}</p>
                    </div>
                    <div>
                      <p class="public-site-preview__meta">${escapeHtml(state.hoursLabel)}</p>
                      <p class="public-site-preview__contact-text">${escapeHtml(state.hours)}</p>
                    </div>
                  </div>

                  <div class="public-site-preview__contact-map" aria-hidden="true">
                    <div>
                      <span class="public-site-preview__chip">Mapa visual</span>
                      <p class="public-site-preview__title" style="margin-top:0.75rem;">${escapeHtml(state.mapTitle)}</p>
                      <p class="public-site-preview__text">${escapeHtml(state.address)}</p>
                      <p class="public-site-preview__help" style="margin-top:0.5rem;">Preview sin iframe. La vista publica real mantiene el embed configurado.</p>
                    </div>
                  </div>
                </aside>

                <div class="public-site-preview__form-card">
                  <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                      <span class="public-site-preview__eyebrow">${escapeHtml(state.formSectionBadge)}</span>
                      <h2 class="public-site-preview__heading" style="font-size:2rem; margin-top:0.7rem;">${escapeHtml(state.formTitle)}</h2>
                    </div>
                    <span class="public-site-preview__pill">${escapeHtml(state.formBadge)}</span>
                  </div>

                  <form novalidate onsubmit="return false;">
                    <div class="public-site-preview__field-grid">
                      <label class="public-site-preview__field">
                        <span class="public-site-preview__field-label">${escapeHtml(state.nameLabel)}</span>
                        <input class="public-site-preview__input" type="text" placeholder="${escapeHtml(state.namePlaceholder)}" readonly>
                      </label>
                      <label class="public-site-preview__field">
                        <span class="public-site-preview__field-label">${escapeHtml(state.emailLabel)}</span>
                        <input class="public-site-preview__input" type="email" placeholder="${escapeHtml(state.emailPlaceholder)}" readonly>
                      </label>
                      <label class="public-site-preview__field">
                        <span class="public-site-preview__field-label">${escapeHtml(state.phoneFieldLabel)}</span>
                        <input class="public-site-preview__input" type="tel" placeholder="${escapeHtml(state.phonePlaceholder)}" readonly>
                      </label>
                      <label class="public-site-preview__field">
                        <span class="public-site-preview__field-label">${escapeHtml(state.subjectLabel)}</span>
                        <input class="public-site-preview__input" type="text" placeholder="${escapeHtml(state.subjectPlaceholder)}" readonly>
                      </label>
                      <label class="public-site-preview__field public-site-preview__field--full">
                        <span class="public-site-preview__field-label">${escapeHtml(state.messageLabel)}</span>
                        <textarea class="public-site-preview__textarea" placeholder="${escapeHtml(state.messagePlaceholder)}" readonly></textarea>
                        <p class="public-site-preview__help">${escapeHtml(state.messageHelp)}</p>
                      </label>
                    </div>

                    <div class="public-site-preview__actions" style="margin-top:1rem;">
                      <button type="button" class="public-site-preview__button">${escapeHtml(state.submitText)}</button>
                      <p class="public-site-preview__help">Boton visual del preview. No ejecuta envio ni validaciones reales.</p>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </section>

          <section class="public-site-preview__footer">
            <div class="public-site-preview__footer-grid">
              <article class="public-site-preview__footer-column">
                <p class="public-site-preview__title">${escapeHtml(footer.name || 'Nombre de la clinica')}</p>
                <p class="public-site-preview__footer-text">${escapeHtml(
                  footer.text || '© 2026 - Todos los derechos reservados.',
                )}</p>
              </article>
              <article class="public-site-preview__footer-column">
                <p class="public-site-preview__title">Enlaces</p>
                <div class="public-site-preview__footer-links">
                  ${footerLinks || '<span class="public-site-preview__meta">Sin enlaces configurados</span>'}
                </div>
              </article>
              <article class="public-site-preview__footer-column">
                <p class="public-site-preview__title">Legales</p>
                <div class="public-site-preview__footer-links">
                  ${footerLegal || '<span class="public-site-preview__meta">Sin legales configurados</span>'}
                </div>
              </article>
              <article class="public-site-preview__footer-column">
                <p class="public-site-preview__title">Contacto</p>
                <p class="public-site-preview__contact-text">${escapeHtml(footer.address || state.address)}</p>
                <p class="public-site-preview__contact-text">${escapeHtml(footer.phone || state.phone)}</p>
                <p class="public-site-preview__contact-text">${escapeHtml(footer.email || state.email)}</p>
              </article>
            </div>
          </section>
        </div>
      </div>
    `;
  };

  renderPreview();

  formRoot.addEventListener('input', scheduleRender);
  formRoot.addEventListener('change', scheduleRender);
});

function parseJson(raw) {
  try {
    return JSON.parse(raw);
  } catch (_error) {
    return {};
  }
}
