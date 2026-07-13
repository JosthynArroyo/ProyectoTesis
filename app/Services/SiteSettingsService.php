<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SiteSettingsService
{
    public const ALL_CACHE_KEY = 'site_settings.all';

    public const MAINTENANCE_CACHE_KEY = 'site_settings.maintenance';

    private const CACHE_TTL_SECONDS = 300;

    private const MAINTENANCE_CACHE_TTL_SECONDS = 60;

    private ?array $cache = null;

    private array $defaults = [
        // Branding
        'branding.name' => 'Nombre de la clínica',
        'branding.logo' => null,
        'branding.favicon' => null,
        'branding.navbar_text' => 'Sistema web de gestión médica',
        'branding.institutional_name' => 'Nombre de la clínica',
        'branding.institutional_badge' => 'Atención médica organizada y cercana.',
        'branding.footer_text' => '© {year} - Todos los derechos reservados.',
        'branding.accent' => '#0f766e',
        'branding.accent_strong' => '#14b8a6',
        'branding.accent_soft' => '#ccfbf1',

        // Public header
        'header.show_home' => '1',
        'header.show_services' => '1',
        'header.show_contact' => '1',
        'header.navigation_order' => 'home,services,contact',
        'header.sticky_enabled' => '1',

        // Home hero
        'home.hero_badge' => 'Bienvenido',
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
        'services.hero_image' => 'images/servicios/hero-servicios.png',

        // Contacto page
        'contact.title' => 'Nombre de la clínica',
        'contact.info_badge' => 'Contacto',
        'contact.subtitle' => 'Canales de contacto de la clínica para consultas administrativas, horarios y orientación sobre el uso del sistema de citas.',
        'contact.address_label' => 'Dirección',
        'contact.address' => null,
        'contact.phone_label' => 'Teléfono',
        'contact.phone' => null,
        'contact.email' => null,
        'contact.hours_label' => 'Horario',
        'contact.hours' => null,
        'contact.map_title' => 'Ubicación de la clínica',
        'contact.map_embed' => null,
        'contact.form_section_badge' => 'Escríbenos',
        'contact.form_title' => 'Formulario de contacto',
        'contact.form_badge' => 'Mensaje para la clínica',
        'contact.form_submit_text' => 'Enviar',
        'contact.form_name_label' => 'Nombre',
        'contact.form_name_placeholder' => 'Ej. Nombre del paciente',
        'contact.form_email_placeholder' => 'Ej. contacto@clinica.test',
        'contact.form_phone_placeholder' => 'Ej. 0991234567',
        'contact.form_email_label' => 'Correo electrónico',
        'contact.form_phone_label' => 'Teléfono (10 dígitos)',
        'contact.form_subject_label' => 'Asunto',
        'contact.form_subject_placeholder' => 'Ej. Consulta sobre horarios',
        'contact.form_message_label' => 'Mensaje',
        'contact.form_message_placeholder' => 'Escribe el motivo de tu contacto y los detalles necesarios.',
        'contact.form_message_help' => 'Describe el motivo de tu contacto. Máx. 1000 caracteres.',

        // Footer
        'footer.institutional_text' => 'Atención médica cercana, organizada y segura para cada paciente.',
        'footer.show_home_link' => '0',
        'footer.show_services_link' => '1',
        'footer.show_contact_link' => '1',
        'footer.show_assistant_link' => '1',
        'footer.show_privacy_link' => '1',
        'footer.show_terms_link' => '1',
        'footer.home_label' => 'Inicio',
        'footer.services_label' => 'Servicios',
        'footer.contact_label' => 'Contacto',
        'footer.assistant_label' => 'Asistente virtual',
        'footer.privacy_label' => 'Políticas de privacidad',
        'footer.terms_label' => 'Términos de servicio',

        // Legal
        'legal.privacy_title' => 'Tratamiento de datos personales y de salud',
        'legal.privacy_updated_at' => 'Última actualización: abril de 2026',
        'legal.privacy_body' => null,
        'legal.terms_title' => 'Condiciones de uso de la plataforma',
        'legal.terms_updated_at' => 'Última actualización: abril de 2026',
        'legal.terms_body' => null,

        // Visual
        'visual.soft_primary' => '#dff6f2',
        'visual.soft_secondary' => '#e8f8ef',
        'visual.gradient_start' => '#dff4ff',
        'visual.gradient_end' => '#ecfdf5',
        'visual.badge_soft' => '#d9f7ef',

        // Maintenance
        'maintenance.enabled' => '0',
        'maintenance.message' => 'Estamos realizando mantenimiento para mejorar tu experiencia. Vuelve en unos minutos.',
        'maintenance.until' => '',
        'maintenance.allow_ips' => '',

        // Clinic hours
        'clinic_hours.1.status' => '1',
        'clinic_hours.1.opening' => '08:00',
        'clinic_hours.1.closing' => '18:00',
        'clinic_hours.2.status' => '1',
        'clinic_hours.2.opening' => '08:00',
        'clinic_hours.2.closing' => '18:00',
        'clinic_hours.3.status' => '1',
        'clinic_hours.3.opening' => '08:00',
        'clinic_hours.3.closing' => '18:00',
        'clinic_hours.4.status' => '1',
        'clinic_hours.4.opening' => '08:00',
        'clinic_hours.4.closing' => '18:00',
        'clinic_hours.5.status' => '1',
        'clinic_hours.5.opening' => '08:00',
        'clinic_hours.5.closing' => '18:00',
        'clinic_hours.6.status' => '1',
        'clinic_hours.6.opening' => '08:00',
        'clinic_hours.6.closing' => '13:00',
        'clinic_hours.7.status' => '0',
        'clinic_hours.7.opening' => '08:00',
        'clinic_hours.7.closing' => '18:00',
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

        $this->forgetCache();
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
            'brand_logo' => ($branding['branding.logo'] ?? $this->defaults['branding.logo']) ?: null,
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

    public function maintenanceSnapshot(): array
    {
        try {
            return Cache::remember(
                self::MAINTENANCE_CACHE_KEY,
                now()->addSeconds(self::MAINTENANCE_CACHE_TTL_SECONDS),
                function (): array {
                    $settings = $this->all();

                    return [
                        'enabled' => filter_var($settings['maintenance.enabled'] ?? '0', FILTER_VALIDATE_BOOLEAN),
                        'message' => $this->normalizePublicCopy(
                            'maintenance.message',
                            $settings['maintenance.message'] ?? $this->defaults['maintenance.message']
                        ),
                        'until' => $settings['maintenance.until'] ?? $this->defaults['maintenance.until'],
                        'allow_ips' => $settings['maintenance.allow_ips'] ?? $this->defaults['maintenance.allow_ips'],
                    ];
                }
            );
        } catch (Throwable) {
            return [
                'enabled' => $this->getBool('maintenance.enabled', false),
                'message' => $this->get('maintenance.message'),
                'until' => $this->get('maintenance.until'),
                'allow_ips' => $this->get('maintenance.allow_ips', ''),
            ];
        }
    }

    public function forgetCache(): void
    {
        $this->cache = null;

        try {
            Cache::forget(self::ALL_CACHE_KEY);
            Cache::forget(self::MAINTENANCE_CACHE_KEY);
        } catch (Throwable) {
            //
        }
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
            'Salud integral y tecnologia humana' => $this->defaults['home.hero_badge'],
            'Tu clinica digital para una atencion mas cercana y rapida.' => $this->defaults['home.hero_title'],
            'Agenda consultas, revisa resultados y recibe recordatorios inteligentes desde cualquier dispositivo. Todo en un mismo lugar.' => $this->defaults['home.hero_subtitle'],
            'Agenda inteligente' => $key === 'home.hero_stat_1_value' ? $this->defaults['home.hero_stat_1_value'] : 'Citas médicas',
            'Reserva citas en pocos pasos' => $this->defaults['home.hero_stat_1_note'],
            'Gestion integral' => $this->defaults['home.hero_stat_2_value'],
            'Control de pacientes y doctores' => $this->defaults['home.hero_stat_2_note'],
            '15 min' => $this->defaults['home.hero_stat_3_value'],
            'Promedio en linea' => $this->defaults['home.hero_stat_3_note'],
            'Gestiona tus citas medicas en un entorno seguro.' => $this->defaults['home.about_title'],
            'Accede a consultas con medicos especializados desde cualquier lugar. Organiza tus visitas en pocos pasos y mantente informado.' => $this->defaults['home.about_body'],
            'Especialidades destacadas' => $this->defaults['home.services_title'],
            'Atencion medica integral con profesionales certificados.' => $this->defaults['home.services_subtitle'],
            'Nuestros doctores' => $this->defaults['home.team_title'],
            'Profesionales comprometidos con tu bienestar.' => $this->defaults['home.team_subtitle'],
            'Atencion personalizada' => $this->defaults['home.team_badge'],
            'Especialidades medicas para tu bienestar' => $this->defaults['services.title'],
            'Agenda en linea con medicos certificados y recibe seguimiento personalizado.' => $this->defaults['services.subtitle'],
            'Sistema de gestion medica para agendar citas facilmente y recibir atencion especializada.' => $this->defaults['contact.subtitle'],
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
            $this->cache = Cache::remember(
                self::ALL_CACHE_KEY,
                now()->addSeconds(self::CACHE_TTL_SECONDS),
                function (): array {
                    if (! Schema::hasTable('site_settings')) {
                        return [];
                    }

                    return SiteSetting::query()->pluck('value', 'key')->toArray();
                }
            );
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
