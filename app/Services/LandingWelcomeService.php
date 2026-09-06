<?php

namespace App\Services;

use App\Models\Especialidad;
use App\Models\LandingWelcomeDoctor;
use App\Models\LandingWelcomeFeaturedSpecialty;
use App\Models\LandingWelcomeInfoCard;
use App\Models\LandingWelcomePrice;
use App\Models\LandingWelcomeSetting;
use App\Models\LandingWelcomeSlide;
use App\Models\LandingWelcomeStat;
use App\Support\ImageUrl;
use Illuminate\Support\Facades\Schema;

class LandingWelcomeService
{
    private ?LandingWelcomeSetting $settings = null;

    private ?array $settingsArray = null;

    private ?array $statsCache = null;

    private ?array $slidesCache = null;

    private ?array $infoCardsCache = null;

    private ?array $doctorsCache = null;

    private ?array $pricesCache = null;

    private ?array $featuredIdsCache = null;

    private bool $settingsLoaded = false;

    private array $defaults = [
        'header_logo' => null,
        'header_name' => 'Nombre de la clínica',
        'header_login_text' => 'Ingresar',
        'header_show_socials' => true,
        'hero_title' => 'Gestiona tus citas médicas desde un solo lugar.',
        'hero_subtitle' => 'Agenda una cita, revisa resultados de laboratorio y consulta documentos médicos registrados en tu cuenta.',
        'hero_primary_text' => 'Agendar cita',
        'hero_show_primary' => true,
        'hero_secondary_text' => 'Explorar servicios',
        'hero_show_secondary' => true,
        'intro_badge' => 'Qué hace el sistema',
        'intro_feature_1_title' => 'Agendar citas',
        'intro_feature_1_text' => 'Selecciona especialidad, profesional, fecha y hora según la disponibilidad registrada.',
        'intro_feature_4_title' => 'Avisos de seguimiento',
        'intro_feature_4_text' => 'Recibe recordatorios y notificaciones relacionadas con tus citas y resultados.',
        'services_badge' => 'Servicios',
        'services_title' => 'Especialidades disponibles',
        'services_subtitle' => 'Explora las especialidades de la clínica y agenda una cita según los horarios registrados.',
        'services_button_text' => 'Ver todos',
        'prices_badge' => 'Tarifario',
        'prices_title' => 'Valores de referencia',
        'prices_subtitle' => 'Revisa los valores registrados para orientar tu agendamiento. La clínica puede confirmar el monto final antes de la atención.',
        'prices_highlight_title' => 'Atención en clínica',
        'prices_highlight_subtitle' => 'El sistema organiza la cita y la información necesaria para tu atención presencial.',
        'prices_visit_title' => 'Agenda paso a paso',
        'prices_visit_subtitle' => 'Selecciona especialidad, profesional, fecha y hora disponible antes de confirmar tu cita.',
        'doctors_badge' => 'Nuestro equipo médico',
        'doctors_title' => 'Profesionales comprometidos con tu salud',
        'doctors_subtitle' => 'Contamos con especialistas altamente calificados para brindarte la mejor atención médica en un entorno seguro y confiable.',
        'doctors_pill' => 'Atención segura y confidencial',
        'show_services_block' => true,
    ];

    private array $defaultStats = [
        [
            'label' => null,
            'value' => 'Citas médicas',
            'note' => 'Agenda según disponibilidad',
            'is_active' => true,
            'sort_order' => 1,
        ],
        [
            'label' => null,
            'value' => 'Documentos médicos',
            'note' => 'Resultados, recetas y comprobantes',
            'is_active' => true,
            'sort_order' => 2,
        ],
        [
            'label' => null,
            'value' => 'Recordatorios',
            'note' => 'Avisos sobre tus próximas citas',
            'is_active' => true,
            'sort_order' => 3,
        ],
    ];

    private array $defaultSlides = [
        [
            'image_path' => 'img/hero1.jpg',
            'alt' => 'Fachada principal de la clínica',
            'title' => 'Agenda de citas',
            'subtitle' => null,
            'text' => 'Elige especialidad, profesional, fecha y hora según la disponibilidad registrada.',
            'sort_order' => 1,
            'is_active' => true,
        ],
        [
            'image_path' => 'img/hero2.jpg',
            'alt' => 'Recepción y sala de espera',
            'title' => 'Seguimiento de atenciones',
            'subtitle' => null,
            'text' => 'Revisa el estado de tus citas y los documentos asociados a tu cuenta.',
            'sort_order' => 2,
            'is_active' => true,
        ],
        [
            'image_path' => 'img/hero3.jpg',
            'alt' => 'Equipos médicos y consultorios',
            'title' => 'Laboratorio y resultados',
            'subtitle' => null,
            'text' => 'Consulta solicitudes y resultados cuando estén disponibles en el sistema.',
            'sort_order' => 3,
            'is_active' => true,
        ],
    ];

    private array $defaultInfoCards = [
        [
            'title' => 'Agendamiento de citas',
            'value' => null,
            'description' => 'Solicita una cita desde tu cuenta.',
            'icon' => 'ri-time-line',
            'is_active' => true,
            'sort_order' => 1,
        ],
        [
            'title' => 'Especialidades disponibles',
            'value' => null,
            'description' => 'Elige el servicio medico que necesitas.',
            'icon' => 'ri-stethoscope-line',
            'is_active' => true,
            'sort_order' => 2,
        ],
        [
            'title' => 'Informacion protegida',
            'value' => null,
            'description' => 'Acceso seguro para tus datos medicos.',
            'icon' => 'ri-shield-check-line',
            'is_active' => true,
            'sort_order' => 3,
        ],
        [
            'title' => 'Recordatorios de citas',
            'value' => null,
            'description' => 'Avisos para ayudarte a llegar a tiempo.',
            'icon' => 'ri-notification-4-line',
            'is_active' => true,
            'sort_order' => 4,
        ],
    ];

    private array $defaultDoctors = [
        [
            'name' => 'Dra. Ana Martinez',
            'specialty' => 'Dermatología',
            'photo_path' => 'img/doctora1.jpg',
            'experience_label' => '8+ años de experiencia',
            'featured_label' => 'Destacada',
            'attendance_label' => 'Atención presencial',
            'availability_label' => 'Agenda disponible',
            'cta_text' => 'Agendar cita',
            'pill_text' => 'Atención segura y confidencial',
            'is_active' => true,
            'sort_order' => 1,
        ],
        [
            'name' => 'Dr. Carlos Perez',
            'specialty' => 'Pediatría',
            'photo_path' => 'img/doctor1.jpg',
            'experience_label' => '6+ años de experiencia',
            'featured_label' => 'Destacado',
            'attendance_label' => 'Atención presencial',
            'availability_label' => 'Agenda disponible',
            'cta_text' => 'Agendar cita',
            'pill_text' => 'Atención segura y confidencial',
            'is_active' => true,
            'sort_order' => 2,
        ],
        [
            'name' => 'Dr. Juan Torres',
            'specialty' => 'Medicina General',
            'photo_path' => 'img/doctor2.jpg',
            'experience_label' => '15+ años de experiencia',
            'featured_label' => 'Destacado',
            'attendance_label' => 'Atención presencial',
            'availability_label' => 'Agenda disponible',
            'cta_text' => 'Agendar cita',
            'pill_text' => 'Atención segura y confidencial',
            'is_active' => true,
            'sort_order' => 3,
        ],
    ];

    private array $defaultPrices = [
        [
            'service' => 'Dermatología',
            'price' => 'Consultar',
            'is_active' => true,
            'sort_order' => 1,
        ],
        [
            'service' => 'Ginecología',
            'price' => 'Consultar',
            'is_active' => true,
            'sort_order' => 2,
        ],
        [
            'service' => 'Laboratorio Clínico',
            'price' => '$10',
            'is_active' => true,
            'sort_order' => 3,
        ],
        [
            'service' => 'Medicina General',
            'price' => '$20',
            'is_active' => true,
            'sort_order' => 4,
        ],
        [
            'service' => 'Odontología',
            'price' => 'Consultar',
            'is_active' => true,
            'sort_order' => 5,
        ],
        [
            'service' => 'Pediatría',
            'price' => 'Consultar',
            'is_active' => true,
            'sort_order' => 6,
        ],
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->settings()?->{$key} ?? null;

        if ($value === null || $value === '') {
            if ($default !== null) {
                return $default;
            }

            return $this->defaults[$key] ?? null;
        }

        return $this->normalizeSettingCopy($key, $value);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default ? '1' : '0');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function all(): array
    {
        if ($this->settingsArray !== null) {
            return $this->settingsArray;
        }

        $this->settingsArray = [];
        foreach ($this->defaults as $key => $value) {
            $this->settingsArray[$key] = $this->get($key);
        }

        return $this->settingsArray;
    }

    public function stats(bool $onlyActive = false): array
    {
        if ($this->statsCache === null) {
            $this->statsCache = \Illuminate\Support\Facades\Cache::remember(
                'landing_welcome.stats',
                now()->addSeconds(86400),
                function (): array {
                    if (! Schema::hasTable('landing_welcome_stats')) {
                        return $this->defaultStats;
                    }

                    $stats = LandingWelcomeStat::query()
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->get()
                        ->map(fn (LandingWelcomeStat $stat) => [
                            'id' => $stat->id,
                            'label' => $stat->label,
                            'value' => $stat->value,
                            'note' => $stat->note,
                            'is_active' => $stat->is_active,
                            'sort_order' => $stat->sort_order,
                        ])
                        ->all();

                    return empty($stats)
                        ? $this->defaultStats
                        : array_map(fn (array $stat) => $this->normalizeStatCopy($stat), $stats);
                }
            );
        }

        return $onlyActive
            ? array_values(array_filter($this->statsCache, fn ($stat) => $stat['is_active']))
            : $this->statsCache;
    }

    public function slides(): array
    {
        if ($this->slidesCache === null) {
            $this->slidesCache = \Illuminate\Support\Facades\Cache::remember(
                'landing_welcome.slides',
                now()->addSeconds(86400),
                function (): array {
                    if (! Schema::hasTable('landing_welcome_slides')) {
                        return $this->defaultSlides;
                    }

                    $slides = LandingWelcomeSlide::query()
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->get()
                        ->map(fn (LandingWelcomeSlide $slide) => [
                            'id' => $slide->id,
                            'image_path' => $slide->image_path,
                            'alt' => $slide->alt,
                            'title' => $slide->title,
                            'subtitle' => $slide->subtitle,
                            'text' => $slide->text,
                            'is_active' => $slide->is_active,
                            'sort_order' => $slide->sort_order,
                        ])
                        ->all();

                    return empty($slides) ? $this->defaultSlides : $slides;
                }
            );
        }

        return $this->slidesCache;
    }

    public function infoCards(bool $onlyActive = false): array
    {
        if ($this->infoCardsCache === null) {
            $this->infoCardsCache = \Illuminate\Support\Facades\Cache::remember(
                'landing_welcome.info_cards',
                now()->addSeconds(86400),
                function (): array {
                    if (! Schema::hasTable('landing_welcome_info_cards')) {
                        return $this->defaultInfoCards;
                    }

                    $cards = LandingWelcomeInfoCard::query()
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->get()
                        ->map(fn (LandingWelcomeInfoCard $card) => [
                            'id' => $card->id,
                            'title' => $card->title,
                            'value' => $card->value,
                            'description' => $card->description,
                            'icon' => $card->icon,
                            'is_active' => $card->is_active,
                            'sort_order' => $card->sort_order,
                        ])
                        ->all();

                    return empty($cards)
                        ? $this->defaultInfoCards
                        : array_map(fn (array $card) => $this->normalizeInfoCardCopy($card), $cards);
                }
            );
        }

        return $onlyActive
            ? array_values(array_filter($this->infoCardsCache, fn ($card) => $card['is_active']))
            : $this->infoCardsCache;
    }

    public function doctors(bool $onlyActive = false): array
    {
        if ($this->doctorsCache === null) {
            $this->doctorsCache = \Illuminate\Support\Facades\Cache::remember(
                'landing_welcome.doctors',
                now()->addSeconds(86400),
                function (): array {
                    if (! Schema::hasTable('landing_welcome_doctors')) {
                        return $this->defaultDoctors;
                    }

                    $doctors = LandingWelcomeDoctor::query()
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->get()
                        ->map(fn (LandingWelcomeDoctor $doctor) => [
                            'id' => $doctor->id,
                            'name' => $doctor->name,
                            'specialty' => $doctor->specialty,
                            'photo_path' => $doctor->photo_path,
                            'experience_label' => $doctor->experience_label,
                            'featured_label' => $doctor->featured_label,
                            'attendance_label' => $doctor->attendance_label,
                            'availability_label' => $doctor->availability_label,
                            'cta_text' => $doctor->cta_text,
                            'pill_text' => $doctor->pill_text,
                            'is_active' => $doctor->is_active,
                            'sort_order' => $doctor->sort_order,
                        ])
                        ->all();

                    return empty($doctors) ? $this->defaultDoctors : $doctors;
                }
            );
        }

        return $onlyActive
            ? array_values(array_filter($this->doctorsCache, fn ($doctor) => $doctor['is_active']))
            : $this->doctorsCache;
    }

    public function prices(bool $onlyActive = false): array
    {
        if ($this->pricesCache === null) {
            $this->pricesCache = \Illuminate\Support\Facades\Cache::remember(
                'landing_welcome.prices',
                now()->addSeconds(86400),
                function (): array {
                    if (! Schema::hasTable('landing_welcome_prices')) {
                        return $this->defaultPrices;
                    }

                    $rows = LandingWelcomePrice::query()
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->get()
                        ->map(fn (LandingWelcomePrice $row) => [
                            'id' => $row->id,
                            'service' => $row->service,
                            'price' => $row->price,
                            'is_active' => $row->is_active,
                            'sort_order' => $row->sort_order,
                        ])
                        ->all();

                    return empty($rows) ? $this->defaultPrices : $rows;
                }
            );
        }

        return $onlyActive
            ? array_values(array_filter($this->pricesCache, fn ($row) => $row['is_active']))
            : $this->pricesCache;
    }

    public function featuredSpecialtyIds(): array
    {
        if ($this->featuredIdsCache === null) {
            $this->featuredIdsCache = \Illuminate\Support\Facades\Cache::remember(
                'landing_welcome.featured_specialties',
                now()->addSeconds(86400),
                function (): array {
                    if (! Schema::hasTable('landing_welcome_featured_specialties')) {
                        return [];
                    }

                    $ids = LandingWelcomeFeaturedSpecialty::query()
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->pluck('especialidad_id')
                        ->all();

                    return array_slice(array_values(array_unique(array_filter($ids))), 0, 3);
                }
            );
        }

        return $this->featuredIdsCache;
    }

    public function featuredSpecialties(): array
    {
        $ids = $this->featuredSpecialtyIds();
        if (empty($ids)) {
            return [];
        }

        $especialidades = Especialidad::query()
            ->whereIn('id', $ids)
            ->where('activo', true)
            ->get()
            ->keyBy('id');

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($especialidades[$id])) {
                $ordered[] = $especialidades[$id];
            }
        }

        return $ordered;
    }

    public function defaultStats(): array
    {
        return $this->defaultStats;
    }

    public function defaultSlides(): array
    {
        return $this->defaultSlides;
    }

    public function defaultInfoCards(): array
    {
        return $this->defaultInfoCards;
    }

    public function defaultDoctors(): array
    {
        return $this->defaultDoctors;
    }

    public function defaultPrices(): array
    {
        return $this->defaultPrices;
    }

    public function resolveImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return app(ImageUrl::class)->url($path, entity: 'banner', size: 'large');
    }

    private function normalizeSettingCopy(string $key, mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $replacements = [
            'Tu clínica digital para una atención más cercana y rápida.' => $this->defaults['hero_title'],
            'Agenda consultas, revisa resultados y recibe recordatorios inteligentes desde cualquier dispositivo. Todo en un mismo lugar.' => $this->defaults['hero_subtitle'],
            'Agenda inteligente' => $key === 'intro_feature_1_title' ? $this->defaults['intro_feature_1_title'] : 'Citas médicas',
            'Confirmaciones automáticas, recordatorios y reprogramación sencilla.' => $this->defaults['intro_feature_1_text'],
            'Atención humana' => $this->defaults['intro_feature_4_title'],
            'Personal médico listo para responder tus dudas.' => $this->defaults['intro_feature_4_text'],
            'Especialidades destacadas' => $this->defaults['services_title'],
            'Atención médica integral con profesionales certificados.' => $this->defaults['services_subtitle'],
            'Precios transparentes' => $this->defaults['prices_title'],
            'Consulta los valores aproximados y pregunta por promociones actuales.' => $this->defaults['prices_subtitle'],
            'Equipo profesional' => $this->defaults['prices_highlight_title'],
            'Especialistas enfocados en un trato cercano y humano.' => $this->defaults['prices_highlight_subtitle'],
            'Agenda tu visita en minutos' => $this->defaults['prices_visit_title'],
            'Nuestro sistema te guía paso a paso para seleccionar especialista, fecha y hora.' => $this->defaults['prices_visit_subtitle'],
            'Nuestros doctores' => $this->defaults['doctors_title'],
            'Profesionales comprometidos con tu bienestar.' => $this->defaults['doctors_subtitle'],
            'Atención personalizada' => $this->defaults['doctors_pill'],
            'Equipo' => $this->defaults['doctors_badge'],
            'Profesionales disponibles' => $this->defaults['doctors_title'],
            'Conoce el equipo registrado para las especialidades de la clínica.' => $this->defaults['doctors_subtitle'],
            'Atención presencial agendada' => $this->defaults['doctors_pill'],
        ];

        return $replacements[$value] ?? $replacements[utf8_decode($value)] ?? $value;
    }

    private function normalizeStatCopy(array $stat): array
    {
        $label = $stat['label'] ?? null;
        $value = $stat['value'] ?? null;
        $note = $stat['note'] ?? null;

        $knownTemplateStats = [
            [0, 'Agenda inteligente', 'Reserva citas en pocos pasos'],
            [1, 'Gestión integral', 'Control de pacientes y doctores'],
            [2, '15 min', 'Promedio en línea'],
            [0, '+6.2k', 'Atención continua'],
            [1, '32', 'Equipo dedicado'],
        ];

        foreach ($knownTemplateStats as [$targetIndex, $templateValue, $templateNote]) {
            if ($value === $templateValue || $note === $templateNote) {
                $replacement = $this->defaultStats[$targetIndex];
                $stat['label'] = $replacement['label'];
                $stat['value'] = $replacement['value'];
                $stat['note'] = $replacement['note'];

                return $stat;
            }
        }

        if ($label === 'Respuesta') {
            $stat['label'] = null;
        }

        return $stat;
    }

    private function normalizeInfoCardCopy(array $card): array
    {
        $replacements = [
            'Atención 24/7' => 'Agendamiento de citas',
            'Soporte y seguimiento continuo.' => 'Solicita una cita desde tu cuenta.',
            'Especialistas certificados' => 'Especialidades disponibles',
            'Médicos con experiencia comprobada.' => 'Elige el servicio médico que necesitas.',
            'Datos protegidos' => 'Información protegida',
            'Seguridad y privacidad priorizadas.' => 'Acceso seguro para tus datos médicos.',
            'Recordatorios automáticos' => 'Recordatorios de citas',
            'Alertas claras para tus citas.' => 'Avisos para ayudarte a llegar a tiempo.',
        ];

        foreach (['title', 'value', 'description'] as $field) {
            if (isset($card[$field]) && is_string($card[$field])) {
                $card[$field] = $replacements[$card[$field]] ?? $card[$field];
            }
        }

        return $card;
    }

    public function forgetCache(): void
    {
        $this->settings = null;
        $this->settingsArray = null;
        $this->statsCache = null;
        $this->slidesCache = null;
        $this->infoCardsCache = null;
        $this->doctorsCache = null;
        $this->pricesCache = null;
        $this->featuredIdsCache = null;
        $this->settingsLoaded = false;

        try {
            \Illuminate\Support\Facades\Cache::forget('landing_welcome.stats');
            \Illuminate\Support\Facades\Cache::forget('landing_welcome.slides');
            \Illuminate\Support\Facades\Cache::forget('landing_welcome.info_cards');
            \Illuminate\Support\Facades\Cache::forget('landing_welcome.doctors');
            \Illuminate\Support\Facades\Cache::forget('landing_welcome.prices');
            \Illuminate\Support\Facades\Cache::forget('landing_welcome.featured_specialties');
            \Illuminate\Support\Facades\Cache::forget('landing_welcome.settings');
        } catch (\Throwable) {
        }
    }

    private function settings(): ?LandingWelcomeSetting
    {
        if ($this->settingsLoaded) {
            return $this->settings;
        }

        $this->settingsLoaded = true;

        try {
            $this->settings = \Illuminate\Support\Facades\Cache::remember(
                'landing_welcome.settings',
                now()->addSeconds(86400),
                function (): ?LandingWelcomeSetting {
                    if (! Schema::hasTable('landing_welcome_settings')) {
                        return null;
                    }

                    return LandingWelcomeSetting::query()->first();
                }
            );
        } catch (\Throwable) {
            $this->settings = null;
        }

        return $this->settings;
    }
}


