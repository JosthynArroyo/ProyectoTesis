@once
  <style>
    .personalizacion-public-editor [data-public-preview-open],
    .personalizacion-public-editor [data-public-preview-close],
    .personalizacion-public-editor [data-add-especialidad],
    .personalizacion-public-editor [data-remove-item],
    .personalizacion-public-editor [data-icon-option],
    .personalizacion-public-editor input[type='file'] {
      cursor: pointer;
    }

    .personalizacion-public-preview-modal[hidden] {
      display: none;
    }

    .personalizacion-public-preview-modal {
      position: fixed;
      inset: 0;
      z-index: 210;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      overflow: hidden;
      overscroll-behavior: contain;
      color-scheme: light;
    }

    .personalizacion-public-preview-backdrop {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.72);
      backdrop-filter: blur(10px);
    }

    .personalizacion-public-preview-dialog {
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      width: min(1440px, 100%);
      height: min(92vh, 980px);
      max-height: calc(100dvh - 2rem);
      overflow: hidden;
      border-radius: 2rem;
      border: 1px solid rgba(226, 232, 240, 0.96);
      background: #ffffff;
      box-shadow: 0 32px 80px rgba(15, 23, 42, 0.32);
      color: #0f172a;
    }

    html.panel-theme-dark .personalizacion-public-preview-dialog {
      border-color: rgba(75, 85, 99, 0.8);
      background: #111827;
      box-shadow: 0 32px 80px rgba(0, 0, 0, 0.6);
      color: #cbd5e1;
    }

    .personalizacion-public-preview-header {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid rgba(226, 232, 240, 0.9);
      background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    }

    html.panel-theme-dark .personalizacion-public-preview-header {
      border-color: rgba(75, 85, 99, 0.8);
      background: linear-gradient(180deg, #1f2937 0%, #111827 100%);
    }

    .personalizacion-public-preview-header p,
    .personalizacion-public-preview-header h3 {
      margin: 0;
      color: inherit;
    }

    .personalizacion-public-preview-scroll {
      flex: 1;
      padding: 1rem;
      overflow-y: auto;
      overflow-x: hidden;
      overscroll-behavior: contain;
      background:
        radial-gradient(circle at top left, rgba(191, 219, 254, 0.28), transparent 34%),
        radial-gradient(circle at top right, rgba(167, 243, 208, 0.22), transparent 26%),
        linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
    }

    html.panel-theme-dark .personalizacion-public-preview-scroll {
      background:
        radial-gradient(circle at top left, rgba(30, 41, 59, 0.4), transparent 32%),
        radial-gradient(circle at top right, rgba(20, 83, 45, 0.2), transparent 24%),
        linear-gradient(180deg, #0f172a 0%, #030712 100%);
    }

    .personalizacion-public-preview-stage {
      --public-preview-scale: 1;
      --public-preview-width: 1180px;
      --public-preview-height: 0px;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      width: 100%;
      min-width: 0;
      margin: 0 auto;
      overflow-x: hidden;
    }

    .personalizacion-public-preview-scale {
      position: relative;
      display: flex;
      justify-content: center;
      width: calc(var(--public-preview-width) * var(--public-preview-scale));
      min-height: calc(var(--public-preview-height) * var(--public-preview-scale));
      height: calc(var(--public-preview-height) * var(--public-preview-scale));
      flex: none;
      margin-inline: auto;
    }

    .personalizacion-public-preview-frame {
      position: absolute;
      top: 0;
      left: 0;
      width: var(--public-preview-width);
      transform: scale(var(--public-preview-scale));
      transform-origin: top left;
      will-change: transform;
    }

    .personalizacion-public-preview-surface {
      min-height: 760px;
      overflow: hidden;
      border-radius: 1.75rem;
      border: 1px solid rgba(203, 213, 225, 0.9);
      background: #ffffff;
      box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
    }

    .public-site-preview {
      min-height: 100%;
      background:
        radial-gradient(circle at top left, rgba(219, 234, 254, 0.32), transparent 32%),
        linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
      color: #0f172a;
    }

    .public-site-preview *,
    .personalizacion-public-preview-dialog * {
      box-sizing: border-box;
    }

    .public-site-preview img {
      display: block;
      width: 100%;
      max-width: 100%;
      object-fit: cover;
    }

    .public-site-preview__shell {
      display: flex;
      flex-direction: column;
      gap: 1.1rem;
      min-height: 100%;
      padding: 1rem;
    }

    .public-site-preview__card,
    .public-site-preview__footer,
    .public-site-preview__hero,
    .public-site-preview__toolbar,
    .public-site-preview__panel {
      min-width: 0;
      border: 1px solid rgba(226, 232, 240, 0.92);
      border-radius: 1.5rem;
      background: rgba(255, 255, 255, 0.96);
      box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
      overflow: hidden;
    }

    .public-site-preview__header {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 0.9rem;
      padding: 1rem 1.25rem;
      border-bottom: 1px solid rgba(226, 232, 240, 0.86);
      background: rgba(255, 255, 255, 0.96);
    }

    .public-site-preview__brand {
      display: flex;
      align-items: center;
      gap: 0.9rem;
      min-width: 0;
      flex: 1 1 220px;
    }

    .public-site-preview__brand-mark {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 3.2rem;
      height: 3.2rem;
      border-radius: 1rem;
      border: 1px solid rgba(226, 232, 240, 0.92);
      background: #ffffff;
      overflow: hidden;
      flex-shrink: 0;
    }

    .public-site-preview__brand-mark--icon {
      background: linear-gradient(135deg, rgba(20, 184, 166, 0.16), rgba(14, 165, 233, 0.14));
      color: #0f766e;
      font-size: 1.2rem;
    }

    .public-site-preview__brand-copy,
    .public-site-preview__nav,
    .public-site-preview__footer-column,
    .public-site-preview__media-copy {
      min-width: 0;
    }

    .public-site-preview__eyebrow,
    .public-site-preview__chip,
    .public-site-preview__badge,
    .public-site-preview__pill {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      width: fit-content;
      max-width: 100%;
      padding: 0.48rem 0.82rem;
      border-radius: 999px;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #0f766e;
      background: rgba(204, 251, 241, 0.8);
      white-space: normal;
    }

    .public-site-preview__chip,
    .public-site-preview__pill {
      letter-spacing: 0;
      text-transform: none;
      font-size: 0.78rem;
      background: rgba(248, 250, 252, 0.96);
      color: #475569;
      border: 1px solid rgba(226, 232, 240, 0.92);
    }

    .public-site-preview__nav {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 0.55rem;
      flex: 1 1 260px;
    }

    .public-site-preview__nav-item,
    .public-site-preview__button,
    .public-site-preview__ghost-button,
    .public-site-preview__footer-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.45rem;
      min-height: 2.35rem;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 600;
      line-height: 1.15;
      white-space: normal;
      text-align: center;
    }

    .public-site-preview__nav-item {
      padding: 0.7rem 0.95rem;
      background: rgba(240, 253, 250, 0.92);
      color: #0f766e;
    }

    .public-site-preview__ghost-button {
      padding: 0.75rem 1rem;
      border: 1px solid rgba(203, 213, 225, 0.95);
      background: rgba(255, 255, 255, 0.96);
      color: #334155;
    }

    .public-site-preview__button {
      padding: 0.82rem 1.08rem;
      border: 1px solid transparent;
      background: #0f766e;
      color: #ffffff;
      box-shadow: 0 14px 24px rgba(15, 118, 110, 0.22);
    }

    .public-site-preview__button--secondary {
      border-color: rgba(226, 232, 240, 0.94);
      background: rgba(255, 255, 255, 0.96);
      color: #0f172a;
      box-shadow: none;
    }

    .public-site-preview__heading,
    .public-site-preview__title {
      margin: 0;
      color: #0f172a;
      overflow-wrap: anywhere;
    }

    .public-site-preview__title {
      font-size: 1rem;
      font-weight: 700;
      line-height: 1.35;
    }

    .public-site-preview__heading {
      font-size: clamp(2rem, 3vw, 2.9rem);
      font-weight: 800;
      line-height: 1.06;
    }

    .public-site-preview__text,
    .public-site-preview__meta,
    .public-site-preview__footer-text,
    .public-site-preview__contact-text,
    .public-site-preview__field-label,
    .public-site-preview__help {
      margin: 0;
      color: #475569;
      font-size: 0.88rem;
      line-height: 1.65;
      overflow-wrap: anywhere;
    }

    .public-site-preview__hero-grid,
    .public-site-preview__cards-grid,
    .public-site-preview__footer-grid,
    .public-site-preview__contact-grid {
      display: grid;
      gap: 1rem;
    }

    .public-site-preview__hero-grid {
      grid-template-columns: minmax(0, 1fr) minmax(340px, 0.9fr);
      align-items: stretch;
    }

    .public-site-preview__hero-copy,
    .public-site-preview__hero-media,
    .public-site-preview__panel-body,
    .public-site-preview__footer-column,
    .public-site-preview__service-card-copy,
    .public-site-preview__contact-card,
    .public-site-preview__form-card {
      padding: 1.15rem;
    }

    .public-site-preview__hero-copy {
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 1rem;
    }

    .public-site-preview__hero-media {
      display: flex;
      align-items: stretch;
      min-height: 320px;
      border-left: 1px solid rgba(226, 232, 240, 0.88);
      background:
        radial-gradient(circle at top, rgba(20, 184, 166, 0.18), transparent 44%),
        linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
    }

    .public-site-preview__media-frame,
    .public-site-preview__service-image,
    .public-site-preview__contact-map {
      width: 100%;
      overflow: hidden;
      border-radius: 1.25rem;
      border: 1px solid rgba(226, 232, 240, 0.92);
      background: linear-gradient(135deg, rgba(224, 242, 254, 0.7), rgba(240, 253, 250, 0.8));
    }

    .public-site-preview__media-frame {
      min-height: 100%;
    }

    .public-site-preview__service-image {
      height: 13.5rem;
      border-radius: 0;
      border: 0;
    }

    .public-site-preview__service-image img,
    .public-site-preview__media-frame img {
      width: 100%;
      height: 100%;
      min-height: 100%;
      object-fit: cover;
    }

    .public-site-preview__image-fallback {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      width: 100%;
      height: 100%;
      min-height: inherit;
      color: #475569;
      text-align: center;
      padding: 1rem;
    }

    .public-site-preview__cards-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .public-site-preview__service-card {
      display: flex;
      flex-direction: column;
      min-width: 0;
      border: 1px solid rgba(226, 232, 240, 0.92);
      border-radius: 1.45rem;
      background: rgba(255, 255, 255, 0.98);
      box-shadow: 0 16px 32px rgba(15, 23, 42, 0.08);
      overflow: hidden;
    }

    .public-site-preview__service-card-copy {
      display: flex;
      flex: 1 1 auto;
      flex-direction: column;
      gap: 0.8rem;
    }

    .public-site-preview__service-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.75rem;
    }

    .public-site-preview__service-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 2.75rem;
      height: 2.75rem;
      border-radius: 1rem;
      background: rgba(241, 245, 249, 0.96);
      color: #475569;
      font-size: 1.18rem;
      flex-shrink: 0;
    }

    .public-site-preview__toolbar {
      padding: 1rem 1.15rem;
    }

    .public-site-preview__toolbar-row {
      display: flex;
      flex-wrap: wrap;
      gap: 0.8rem;
      align-items: center;
      justify-content: space-between;
    }

    .public-site-preview__search {
      display: flex;
      align-items: center;
      gap: 0.7rem;
      min-width: 0;
      flex: 1 1 320px;
      padding: 0.9rem 1rem;
      border: 1px solid rgba(226, 232, 240, 0.96);
      border-radius: 1rem;
      background: rgba(248, 250, 252, 0.94);
      color: #64748b;
    }

    .public-site-preview__search-input,
    .public-site-preview__input,
    .public-site-preview__textarea {
      width: 100%;
      min-width: 0;
      border: 0;
      background: transparent;
      color: #0f172a;
      outline: 0;
      font: inherit;
    }

    .public-site-preview__tabs {
      display: flex;
      flex-wrap: wrap;
      gap: 0.55rem;
    }

    .public-site-preview__tab {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 2.5rem;
      padding: 0.72rem 1rem;
      border-radius: 999px;
      border: 1px solid rgba(226, 232, 240, 0.96);
      background: #ffffff;
      color: #475569;
      font-size: 0.82rem;
      font-weight: 700;
    }

    .public-site-preview__tab.is-active {
      background: rgba(240, 253, 250, 0.96);
      border-color: rgba(45, 212, 191, 0.55);
      color: #0f766e;
    }

    .public-site-preview__contact-grid {
      grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    }

    .public-site-preview__contact-card,
    .public-site-preview__form-card {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .public-site-preview__contact-list,
    .public-site-preview__footer-links,
    .public-site-preview__field-grid,
    .public-site-preview__actions {
      display: grid;
      gap: 0.75rem;
    }

    .public-site-preview__field-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .public-site-preview__field {
      display: grid;
      gap: 0.45rem;
    }

    .public-site-preview__field--full {
      grid-column: 1 / -1;
    }

    .public-site-preview__input,
    .public-site-preview__textarea {
      padding: 0.9rem 1rem;
      border: 1px solid rgba(226, 232, 240, 0.96);
      border-radius: 1rem;
      background: rgba(248, 250, 252, 0.96);
      color: #0f172a;
    }

    .public-site-preview__textarea {
      min-height: 8rem;
      resize: none;
    }

    .public-site-preview__contact-map {
      position: relative;
      min-height: 16rem;
      padding: 1.15rem;
      background:
        linear-gradient(135deg, rgba(224, 242, 254, 0.72), rgba(240, 253, 250, 0.82)),
        linear-gradient(0deg, rgba(148, 163, 184, 0.16) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148, 163, 184, 0.16) 1px, transparent 1px);
      background-size: auto, 24px 24px, 24px 24px;
    }

    .public-site-preview__contact-map::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 1.15rem;
      height: 1.15rem;
      border-radius: 999px;
      background: #ef4444;
      border: 3px solid rgba(255, 255, 255, 0.96);
      box-shadow: 0 0 0 10px rgba(239, 68, 68, 0.16);
      transform: translate(-50%, -50%);
    }

    .public-site-preview__contact-map > div {
      position: relative;
      z-index: 1;
      max-width: 22rem;
      padding: 1rem;
      border-radius: 1rem;
      background: rgba(255, 255, 255, 0.92);
      border: 1px solid rgba(226, 232, 240, 0.94);
      box-shadow: 0 14px 26px rgba(15, 23, 42, 0.08);
    }

    .public-site-preview__footer {
      padding: 1.1rem 1.15rem;
    }

    .public-site-preview__footer-grid {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .public-site-preview__footer-link {
      justify-content: flex-start;
      min-height: auto;
      padding: 0;
      border-radius: 0;
      color: #475569;
      background: transparent;
      font-weight: 500;
    }

    .public-site-preview__empty {
      padding: 1.1rem;
      border-radius: 1.2rem;
      border: 1px dashed rgba(148, 163, 184, 0.65);
      background: rgba(248, 250, 252, 0.8);
      color: #64748b;
      font-size: 0.88rem;
      line-height: 1.6;
      text-align: center;
    }

    html.panel-theme-dark .personalizacion-public-editor .personalizacion-public-surface,
    html.panel-theme-dark .personalizacion-public-editor .personalizacion-public-surface .card,
    html.panel-theme-dark .personalizacion-public-editor .personalizacion-public-surface [data-icon-list],
    html.panel-theme-dark .personalizacion-public-editor .personalizacion-public-surface [data-public-preview-trigger-card],
    html.panel-theme-dark .personalizacion-public-editor .personalizacion-public-surface [data-public-preview-form-card],
    html.panel-theme-dark .personalizacion-public-editor .personalizacion-public-surface [data-public-preview-row-card],
    html.panel-theme-dark .personalizacion-public-editor .personalizacion-public-surface [data-public-preview-muted-card] {
      background: rgba(15, 23, 42, 0.88) !important;
      border-color: rgba(71, 85, 105, 0.92) !important;
      color: #e2e8f0 !important;
    }

    html.panel-theme-dark .personalizacion-public-editor .personalizacion-public-surface [data-public-preview-muted-card],
    html.panel-theme-dark .personalizacion-public-editor .personalizacion-public-surface [data-icon-preview],
    html.panel-theme-dark .personalizacion-public-editor .personalizacion-public-surface [data-public-preview-inline-image] {
      background: rgba(30, 41, 59, 0.92) !important;
    }

    html.panel-theme-dark .personalizacion-public-editor .personalizacion-public-surface [data-icon-option] {
      background: rgba(15, 23, 42, 0.9) !important;
      border-color: rgba(71, 85, 105, 0.9) !important;
      color: #e2e8f0 !important;
    }

    html.panel-theme-dark .personalizacion-public-editor .personalizacion-public-surface [data-icon-option] span[class*='bg-gray-50'] {
      background: rgba(30, 41, 59, 0.9) !important;
      color: #e2e8f0 !important;
    }

    @media (max-width: 1100px) {
      .public-site-preview__hero-grid,
      .public-site-preview__contact-grid {
        grid-template-columns: 1fr;
      }

      .public-site-preview__hero-media {
        min-height: 280px;
        border-left: 0;
        border-top: 1px solid rgba(226, 232, 240, 0.88);
      }

      .public-site-preview__cards-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .public-site-preview__footer-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 700px) {
      .personalizacion-public-preview-modal {
        padding: 0.75rem;
      }

      .personalizacion-public-preview-dialog {
        height: min(94vh, 980px);
        max-height: calc(100dvh - 1.5rem);
        border-radius: 1.35rem;
      }

      .personalizacion-public-preview-header {
        padding: 1rem;
      }

      .personalizacion-public-preview-scroll {
        padding-bottom: calc(1rem + env(safe-area-inset-bottom));
      }

      .public-site-preview__cards-grid,
      .public-site-preview__field-grid,
      .public-site-preview__footer-grid {
        grid-template-columns: 1fr;
      }

      .public-site-preview__header,
      .public-site-preview__toolbar-row {
        flex-direction: column;
        align-items: stretch;
      }

      .public-site-preview__nav {
        justify-content: flex-start;
      }
    }
  </style>
@endonce
