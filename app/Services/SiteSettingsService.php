<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SiteSettingsService
{
    private ?array $cache = null;

    private array $defaults = [
        // Branding
        'branding.name' => 'Clínica Don Bosco',
        'branding.logo' => 'img/logo-welcomeBlanco.jpg',
        'branding.footer_text' => 'Clínica Don Bosco (c) {year} - Todos los derechos reservados.',
        'branding.accent' => '#0f766e',
        'branding.accent_strong' => '#14b8a6',
        'branding.accent_soft' => '#ccfbf1',

        // Home hero
        'home.hero_badge' => 'Bienvenido a Clínica Don Bosco',
        'home.hero_title' => 'Gestiona tus citas médicas desde un solo lugar.',
        'home.hero_subtitle' => 'Agenda una cita, revisa resultados de laboratorio y consulta documentos médicos registrados en tu cuenta.',
        'home.hero_primary_text' => 'Agendar cita',
        'home.hero_secondary_text' => 'Explorar servicios',
        'home.hero_stat_1_label' => '',
        'home.hero_stat_1_value' => 'Citas médicas',
        'home.hero_stat_1_note' => 'Agenda según disponibilidad',
        'home.hero_stat_2_label' => '',
        'home.hero_stat_2_value' => 'Documentos médicos',
        'home.hero_stat_2_note' => 'Resultados, recetas y comprobantes',
        'home.hero_stat_3_label' => '',
        'home.hero_stat_3_value' => 'Recordatorios',
        'home.hero_stat_3_note' => 'Avisos sobre tus próximas citas',

        // Home about
        'home.about_label' => 'Qué hace el sistema',
        'home.about_title' => 'Qué puedes hacer en la plataforma.',
        'home.about_body' => 'La página reúne las opciones principales para pacientes: agendar, revisar citas, consultar documentos médicos y mantenerse informado.',

        // Home services highlight
        'home.services_label' => 'Servicios',
        'home.services_title' => 'Especialidades disponibles',
        'home.services_subtitle' => 'Explora las especialidades de la clínica y agenda una cita según los horarios registrados.',
        'home.services_cta_text' => 'Ver todos',

        // Home team
        'home.team_label' => 'Equipo',
        'home.team_title' => 'Profesionales disponibles',
        'home.team_subtitle' => 'Conoce el equipo registrado para las especialidades de la clínica.',
        'home.team_badge' => 'Atención presencial agendada',

        // Servicios page
        'services.title' => 'Especialidades y servicios disponibles',
        'services.subtitle' => 'Explora las opciones de la clínica y agenda una cita según los horarios registrados en el sistema.',
        'services.cta_text' => 'Agendar cita',

        // Contacto page
        'contact.title' => 'Clínica Don Bosco',
        'contact.info_badge' => 'Contacto',
        'contact.subtitle' => 'Canales de contacto de la clínica para consultas administrativas, horarios y orientación sobre el uso del sistema de citas.',
        'contact.address_label' => 'Dirección',
        'contact.address' => 'Quito, Av. Colón y 6 de Diciembre',
        'contact.phone_label' => 'Teléfono',
        'contact.phone' => '0998742410',
        'contact.hours_label' => 'Horario',
        'contact.hours' => 'Lunes a Viernes, 08:00 - 18:00',
        'contact.map_title' => 'Ubicación Clínica Don Bosco',
        'contact.map_embed' => 'https://www.google.com/maps/embedpb=!1m14!1m8!1m3!1d207.47773878695978!2d-78.47943247794669!3d-0.1385355730076882!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x91d5855715695e7b%3A0x2f91853277ceb246!2sConsultorio%20De%20Especialidades!5e1!3m2!1ses!2sus!4v1760994198832!5m2!1ses!2sus',
        'contact.form_section_badge' => 'Escríbenos',
        'contact.form_title' => 'Formulario de contacto',
        'contact.form_badge' => 'Mensaje para la clínica',
        'contact.form_submit_text' => 'Enviar',
        'contact.form_name_label' => 'Nombre',
        'contact.form_email_label' => 'Correo electrónico',
        'contact.form_phone_label' => 'Teléfono (10 dígitos)',
        'contact.form_subject_label' => 'Asunto',
        'contact.form_subject_placeholder' => 'Ej. Consulta sobre horarios',
        'contact.form_message_label' => 'Mensaje',
        'contact.form_message_placeholder' => 'Escribe el motivo de tu contacto y los detalles necesarios.',
        'contact.form_message_help' => 'Describe el motivo de tu contacto. Max. 1000 caracteres.',

        // Maintenance
        'maintenance.enabled' => '0',
        'maintenance.message' => 'Estamos realizando mantenimiento para mejorar tu experiencia. Vuelve en unos minutos.',
        'maintenance.until' => '',
        'maintenance.allow_ips' => '',
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();
        $value = $settings[$key] ?? null;

        if ($value === null || $value === '') {
            if ($default !== null) {
                return $default;
            }

            return $this->defaults[$key] ?? null;
        }

        return $this->normalizePublicCopy($key, $value);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default ? '1' : '0');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function getMany(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    public function setMany(array $values, array $meta = []): void
    {
        foreach ($values as $key => $value) {
            $payload = [
                'value' => $this->normalizeValue($value),
                'type' => $meta[$key]['type'] ?? 'text',
                'section' => $meta[$key]['section'] ?? null,
            ];
            SiteSetting::updateOrCreate(['key' => $key], $payload);
        }

        $this->cache = null;
    }

    public function defaults(): array
    {
        return $this->defaults;
    }

    public function emailBranding(): array
    {
        $branding = $this->getMany([
            'branding.name',
            'branding.logo',
            'branding.accent',
            'branding.accent_strong',
            'branding.accent_soft',
            'contact.phone',
            'contact.hours',
            'branding.footer_text',
        ]);

        $brandName = (string) ($branding['branding.name'] ?? $this->defaults['branding.name']);
        $footerTemplate = (string) ($branding['branding.footer_text'] ?? $this->defaults['branding.footer_text']);

        return [
            'brand_name' => $brandName,
            'brand_logo' => ltrim((string) ($branding['branding.logo'] ?? $this->defaults['branding.logo']), '/'),
            'accent' => (string) ($branding['branding.accent'] ?? $this->defaults['branding.accent']),
            'accent_strong' => (string) ($branding['branding.accent_strong'] ?? $this->defaults['branding.accent_strong']),
            'accent_soft' => (string) ($branding['branding.accent_soft'] ?? $this->defaults['branding.accent_soft']),
            'contact_phone' => (string) ($branding['contact.phone'] ?? ''),
            'contact_hours' => (string) ($branding['contact.hours'] ?? ''),
            'footer_text' => str_replace(
                ['{brand}', '{year}'],
                [$brandName, date('Y')],
                $footerTemplate
            ),
        ];
    }

    private function normalizePublicCopy(string $key, mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if ($value === 'Respuesta' && str_starts_with($key, 'home.hero_stat_')) {
            return '';
        }

        $replacements = [
            'Salud integral y tecnología humana' => $this->defaults['home.hero_badge'],
            'Tu clínica digital para una atención más cercana y rápida.' => $this->defaults['home.hero_title'],
            'Agenda consultas, revisa resultados y recibe recordatorios inteligentes desde cualquier dispositivo. Todo en un mismo lugar.' => $this->defaults['home.hero_subtitle'],
            'Agenda inteligente' => $key === 'home.hero_stat_1_value' ? $this->defaults['home.hero_stat_1_value'] : 'Citas médicas',
            'Reserva citas en pocos pasos' => $this->defaults['home.hero_stat_1_note'],
            'Gestión integral' => $this->defaults['home.hero_stat_2_value'],
            'Control de pacientes y doctores' => $this->defaults['home.hero_stat_2_note'],
            '15 min' => $this->defaults['home.hero_stat_3_value'],
            'Promedio en línea' => $this->defaults['home.hero_stat_3_note'],
            'Gestiona tus citas médicas en un entorno seguro.' => $this->defaults['home.about_title'],
            'Accede a consultas con médicos especializados desde cualquier lugar. Organiza tus visitas en pocos pasos y mantente informado.' => $this->defaults['home.about_body'],
            'Especialidades destacadas' => $this->defaults['home.services_title'],
            'Atención médica integral con profesionales certificados.' => $this->defaults['home.services_subtitle'],
            'Nuestros doctores' => $this->defaults['home.team_title'],
            'Profesionales comprometidos con tu bienestar.' => $this->defaults['home.team_subtitle'],
            'Atención personalizada' => $this->defaults['home.team_badge'],
            'Especialidades médicas para tu bienestar' => $this->defaults['services.title'],
            'Agenda en línea con médicos certificados y recibe seguimiento personalizado.' => $this->defaults['services.subtitle'],
            'Sistema de gestión médica para agendar citas fácilmente y recibir atención especializada.' => $this->defaults['contact.subtitle'],
            'Respuesta en menos de 24h' => $this->defaults['contact.form_badge'],
        ];

        return $replacements[$value] ?? $value;
    }

    private function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            if (! Schema::hasTable('site_settings')) {
                $this->cache = [];

                return $this->cache;
            }

            $this->cache = SiteSetting::query()->pluck('value', 'key')->toArray();
        } catch (Throwable) {
            $this->cache = [];
        }

        return $this->cache;
    }

    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
