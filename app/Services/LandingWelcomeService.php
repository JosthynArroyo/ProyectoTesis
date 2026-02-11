<?php

namespace App\Services;

use App\Models\LandingWelcomeSetting;
use App\Models\LandingWelcomeSlide;
use App\Models\LandingWelcomeStat;
use App\Models\LandingWelcomeInfoCard;
use App\Models\LandingWelcomeDoctor;
use App\Models\LandingWelcomePrice;
use App\Models\LandingWelcomeFeaturedSpecialty;
use App\Models\Especialidad;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

    private array $defaults = [
        'header_logo' => 'img/logo-welcomeBlanco.jpg',
        'header_name' => 'Clinica Don Bosco',
        'header_show_socials' => true,
        'hero_badge' => 'Salud integral y tecnologia humana',
        'hero_title' => 'Tu clinica digital para una atencion mas cercana y rapida.',
        'hero_subtitle' => 'Agenda consultas, revisa resultados y recibe recordatorios inteligentes desde cualquier dispositivo. Todo en un mismo lugar.',
        'hero_primary_text' => 'Agendar cita',
        'hero_show_primary' => true,
        'hero_secondary_text' => 'Explorar servicios',
        'hero_show_secondary' => true,
        'prices_subtitle' => 'Consulta los valores aproximados y pregunta por promociones actuales.',
        'show_services_block' => true,
    ];

    private array $defaultStats = [
        [
            'label' => 'Pacientes activos',
            'value' => '+6.2k',
            'note' => 'Atencion continua',
            'is_active' => true,
            'sort_order' => 1,
        ],
        [
            'label' => 'Especialistas',
            'value' => '32',
            'note' => 'Equipo dedicado',
            'is_active' => true,
            'sort_order' => 2,
        ],
        [
            'label' => 'Respuesta',
            'value' => '15 min',
            'note' => 'Promedio en linea',
            'is_active' => true,
            'sort_order' => 3,
        ],
    ];

    private array $defaultSlides = [
        [
            'image_path' => 'img/hero1.jpg',
            'alt' => 'Fachada principal de la clinica',
            'sort_order' => 1,
            'is_active' => true,
        ],
        [
            'image_path' => 'img/hero2.jpg',
            'alt' => 'Recepcion y sala de espera',
            'sort_order' => 2,
            'is_active' => true,
        ],
        [
            'image_path' => 'img/hero3.jpg',
            'alt' => 'Equipos medicos y consultorios',
            'sort_order' => 3,
            'is_active' => true,
        ],
    ];

    private array $defaultInfoCards = [
        [
            'title' => 'Atencion 24/7',
            'value' => null,
            'description' => 'Soporte y seguimiento continuo.',
            'icon' => 'ri-time-line',
            'is_active' => true,
            'sort_order' => 1,
        ],
        [
            'title' => 'Especialistas certificados',
            'value' => null,
            'description' => 'Medicos con experiencia comprobada.',
            'icon' => 'ri-stethoscope-line',
            'is_active' => true,
            'sort_order' => 2,
        ],
        [
            'title' => 'Datos protegidos',
            'value' => null,
            'description' => 'Seguridad y privacidad priorizadas.',
            'icon' => 'ri-shield-check-line',
            'is_active' => true,
            'sort_order' => 3,
        ],
        [
            'title' => 'Recordatorios automaticos',
            'value' => null,
            'description' => 'Alertas claras para tus citas.',
            'icon' => 'ri-notification-4-line',
            'is_active' => true,
            'sort_order' => 4,
        ],
    ];

    private array $defaultDoctors = [
        [
            'name' => 'Dra. Ana Martinez',
            'specialty' => 'Dermatologia',
            'photo_path' => 'img/doctora1.jpg',
            'is_active' => true,
            'sort_order' => 1,
        ],
        [
            'name' => 'Dr. Carlos Perez',
            'specialty' => 'Pediatria',
            'photo_path' => 'img/doctor1.jpg',
            'is_active' => true,
            'sort_order' => 2,
        ],
        [
            'name' => 'Dr. Juan Torres',
            'specialty' => 'Cardiologia',
            'photo_path' => 'img/doctor2.jpg',
            'is_active' => true,
            'sort_order' => 3,
        ],
    ];

    private array $defaultPrices = [
        [
            'service' => 'Medicina General',
            'price' => '$25',
            'is_active' => true,
            'sort_order' => 1,
        ],
        [
            'service' => 'Cardiologia',
            'price' => '$40',
            'is_active' => true,
            'sort_order' => 2,
        ],
        [
            'service' => 'Pediatria',
            'price' => '$35',
            'is_active' => true,
            'sort_order' => 3,
        ],
        [
            'service' => 'Dermatologia',
            'price' => '$30',
            'is_active' => true,
            'sort_order' => 4,
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

        return $value;
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
        if ($this->statsCache !== null) {
            return $onlyActive
                ? array_values(array_filter($this->statsCache, fn ($stat) => $stat['is_active']))
                : $this->statsCache;
        }

        if (! Schema::hasTable('landing_welcome_stats')) {
            $this->statsCache = $this->defaultStats;
            return $onlyActive
                ? array_values(array_filter($this->statsCache, fn ($stat) => $stat['is_active']))
                : $this->statsCache;
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

        $this->statsCache = empty($stats) ? $this->defaultStats : $stats;

        return $onlyActive
            ? array_values(array_filter($this->statsCache, fn ($stat) => $stat['is_active']))
            : $this->statsCache;
    }

    public function slides(): array
    {
        if ($this->slidesCache !== null) {
            return $this->slidesCache;
        }

        if (! Schema::hasTable('landing_welcome_slides')) {
            $this->slidesCache = $this->defaultSlides;
            return $this->slidesCache;
        }

        $slides = LandingWelcomeSlide::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (LandingWelcomeSlide $slide) => [
                'id' => $slide->id,
                'image_path' => $slide->image_path,
                'alt' => null,
                'is_active' => $slide->is_active,
                'sort_order' => $slide->sort_order,
            ])
            ->all();

        $this->slidesCache = empty($slides) ? $this->defaultSlides : $slides;

        return $this->slidesCache;
    }

    public function infoCards(bool $onlyActive = false): array
    {
        if ($this->infoCardsCache !== null) {
            return $onlyActive
                ? array_values(array_filter($this->infoCardsCache, fn ($card) => $card['is_active']))
                : $this->infoCardsCache;
        }

        if (! Schema::hasTable('landing_welcome_info_cards')) {
            $this->infoCardsCache = $this->defaultInfoCards;
            return $onlyActive
                ? array_values(array_filter($this->infoCardsCache, fn ($card) => $card['is_active']))
                : $this->infoCardsCache;
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

        $this->infoCardsCache = empty($cards) ? $this->defaultInfoCards : $cards;

        return $onlyActive
            ? array_values(array_filter($this->infoCardsCache, fn ($card) => $card['is_active']))
            : $this->infoCardsCache;
    }

    public function doctors(bool $onlyActive = false): array
    {
        if ($this->doctorsCache !== null) {
            return $onlyActive
                ? array_values(array_filter($this->doctorsCache, fn ($doctor) => $doctor['is_active']))
                : $this->doctorsCache;
        }

        if (! Schema::hasTable('landing_welcome_doctors')) {
            $this->doctorsCache = $this->defaultDoctors;
            return $onlyActive
                ? array_values(array_filter($this->doctorsCache, fn ($doctor) => $doctor['is_active']))
                : $this->doctorsCache;
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
                'is_active' => $doctor->is_active,
                'sort_order' => $doctor->sort_order,
            ])
            ->all();

        $this->doctorsCache = empty($doctors) ? $this->defaultDoctors : $doctors;

        return $onlyActive
            ? array_values(array_filter($this->doctorsCache, fn ($doctor) => $doctor['is_active']))
            : $this->doctorsCache;
    }

    public function prices(bool $onlyActive = false): array
    {
        if ($this->pricesCache !== null) {
            return $onlyActive
                ? array_values(array_filter($this->pricesCache, fn ($row) => $row['is_active']))
                : $this->pricesCache;
        }

        if (! Schema::hasTable('landing_welcome_prices')) {
            $this->pricesCache = $this->defaultPrices;
            return $onlyActive
                ? array_values(array_filter($this->pricesCache, fn ($row) => $row['is_active']))
                : $this->pricesCache;
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

        $this->pricesCache = empty($rows) ? $this->defaultPrices : $rows;

        return $onlyActive
            ? array_values(array_filter($this->pricesCache, fn ($row) => $row['is_active']))
            : $this->pricesCache;
    }

    public function featuredSpecialtyIds(): array
    {
        if ($this->featuredIdsCache !== null) {
            return $this->featuredIdsCache;
        }

        if (! Schema::hasTable('landing_welcome_featured_specialties')) {
            $this->featuredIdsCache = [];
            return $this->featuredIdsCache;
        }

        $ids = LandingWelcomeFeaturedSpecialty::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('especialidad_id')
            ->all();

        $this->featuredIdsCache = $ids;

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

    public function resolveImageUrl(string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (Str::startsWith($path, ['img/', 'storage/'])) {
            return asset($path);
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    private function settings(): ?LandingWelcomeSetting
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        if (! Schema::hasTable('landing_welcome_settings')) {
            return null;
        }

        $this->settings = LandingWelcomeSetting::query()->first();
        return $this->settings;
    }
}
