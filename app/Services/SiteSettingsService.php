<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;

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
        'home.hero_badge' => 'Salud integral y tecnología humana',
        'home.hero_title' => 'Tu clínica digital para una atención más cercana y rápida.',
        'home.hero_subtitle' => 'Agenda consultas, revisa resultados y recibe recordatorios inteligentes desde cualquier dispositivo. Todo en un mismo lugar.',
        'home.hero_primary_text' => 'Agendar cita',
        'home.hero_secondary_text' => 'Explorar servicios',
        'home.hero_stat_1_label' => 'Pacientes activos',
        'home.hero_stat_1_value' => '+6.2k',
        'home.hero_stat_1_note' => 'Atención continua',
        'home.hero_stat_2_label' => 'Especialistas',
        'home.hero_stat_2_value' => '32',
        'home.hero_stat_2_note' => 'Equipo dedicado',
        'home.hero_stat_3_label' => 'Respuesta',
        'home.hero_stat_3_value' => '15 min',
        'home.hero_stat_3_note' => 'Promedio en línea',

        // Home about
        'home.about_label' => 'Bienvenida',
        'home.about_title' => 'Gestiona tus citas médicas en un entorno seguro.',
        'home.about_body' => 'Accede a consultas con médicos especializados desde cualquier lugar. Organiza tus visitas en pocos pasos y mantente informado.',

        // Home services highlight
        'home.services_label' => 'Servicios',
        'home.services_title' => 'Especialidades destacadas',
        'home.services_subtitle' => 'Atención médica integral con profesionales certificados.',
        'home.services_cta_text' => 'Ver todos',

        // Home team
        'home.team_label' => 'Equipo',
        'home.team_title' => 'Nuestros doctores',
        'home.team_subtitle' => 'Profesionales comprometidos con tu bienestar.',
        'home.team_badge' => 'Atención personalizada',

        // Servicios page
        'services.title' => 'Especialidades médicas para tu bienestar',
        'services.subtitle' => 'Agenda en línea con médicos certificados y recibe seguimiento personalizado.',
        'services.cta_text' => 'Agendar cita',

        // Contacto page
        'contact.title' => 'Clínica Don Bosco',
        'contact.info_badge' => 'Contacto',
        'contact.subtitle' => 'Sistema de gestión médica para agendar citas fácilmente y recibir atención especializada.',
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
        'contact.form_badge' => 'Respuesta en menos de 24h',
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

        return $value;
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

    private function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (! Schema::hasTable('site_settings')) {
            $this->cache = [];
            return $this->cache;
        }

        $this->cache = SiteSetting::query()->pluck('value', 'key')->toArray();
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
