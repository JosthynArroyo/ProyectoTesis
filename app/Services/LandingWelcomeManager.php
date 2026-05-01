<?php

namespace App\Services;

use App\Http\Requests\LandingWelcomeRequest;
use App\Models\Especialidad;
use App\Models\LandingWelcomeDoctor;
use App\Models\LandingWelcomeFeaturedSpecialty;
use App\Models\LandingWelcomeInfoCard;
use App\Models\LandingWelcomePrice;
use App\Models\LandingWelcomeSetting;
use App\Models\LandingWelcomeSlide;
use App\Models\LandingWelcomeStat;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class LandingWelcomeManager
{
    public function __construct(
        private readonly ImageOptimizer $imageOptimizer,
        private readonly SiteSettingsService $siteSettings
    ) {}

    public function update(LandingWelcomeRequest $request): void
    {
        $settings = LandingWelcomeSetting::query()->first() ?? new LandingWelcomeSetting;
        $headerLogoPath = $request->input('header_logo_path');
        $headerLogoFile = $request->file('header_logo');
        if ($headerLogoFile) {
            $baseName = $headerLogoPath
                ? pathinfo((string) $headerLogoPath, PATHINFO_FILENAME)
                : 'branding-header-logo';

            $headerLogoPath = $this->imageOptimizer->optimizeAndStore(
                $headerLogoFile,
                'branding',
                baseName: $baseName
            );
        }

        $settings->fill($this->filterExistingColumns('landing_welcome_settings', [
            'header_logo' => $headerLogoPath,
            'header_name' => $request->input('branding_name'),
            'header_login_text' => $request->input('header_login_text'),
            'hero_title' => $request->input('hero_title'),
            'hero_subtitle' => $request->input('hero_subtitle'),
            'hero_primary_text' => $request->input('hero_primary_text'),
            'hero_secondary_text' => $request->input('hero_secondary_text'),
            'hero_show_primary' => $request->boolean('hero_show_primary'),
            'hero_show_secondary' => $request->boolean('hero_show_secondary'),
            'intro_badge' => $request->input('intro_badge'),
            'intro_feature_1_title' => $request->input('intro_feature_1_title'),
            'intro_feature_1_text' => $request->input('intro_feature_1_text'),
            'intro_feature_4_title' => $request->input('intro_feature_4_title'),
            'intro_feature_4_text' => $request->input('intro_feature_4_text'),
            'services_badge' => $request->input('services_badge'),
            'services_title' => $request->input('services_title'),
            'services_subtitle' => $request->input('services_subtitle'),
            'services_button_text' => $request->input('services_button_text'),
            'prices_badge' => $request->input('prices_badge'),
            'prices_title' => $request->input('prices_title'),
            'prices_subtitle' => $request->input('prices_subtitle'),
            'prices_highlight_title' => $request->input('prices_highlight_title'),
            'prices_highlight_subtitle' => $request->input('prices_highlight_subtitle'),
            'prices_visit_title' => $request->input('prices_visit_title'),
            'prices_visit_subtitle' => $request->input('prices_visit_subtitle'),
            'doctors_badge' => $request->input('doctors_badge'),
            'doctors_title' => $request->input('doctors_title'),
            'doctors_subtitle' => $request->input('doctors_subtitle'),
            'doctors_pill' => $request->input('doctors_pill'),
            'show_services_block' => $request->boolean('show_services_block'),
        ]));

        $settings->save();

        $this->syncSiteSettings($request, $headerLogoPath);
        $this->syncStats($request);
        $this->syncSlides($request);
        $this->syncInfoCards($request);
        $this->syncDoctors($request);
        $this->syncPrices($request);
        $this->syncFeaturedSpecialties($request);
    }

    private function syncSiteSettings(LandingWelcomeRequest $request, ?string $headerLogoPath): void
    {
        $faviconPath = $request->input('branding_favicon_path');
        $faviconFile = $request->file('branding_favicon');
        if ($faviconFile) {
            $baseName = $faviconPath
                ? pathinfo((string) $faviconPath, PATHINFO_FILENAME)
                : 'branding-favicon';

            $faviconPath = $this->imageOptimizer->optimizeAndStore(
                $faviconFile,
                'branding',
                baseName: $baseName
            );
        }

        $navigationOrder = array_values(array_filter($request->input('header_navigation_order', [])));
        if (empty($navigationOrder)) {
            $navigationOrder = ['home', 'services', 'contact'];
        }

        $payload = [
            'branding.name' => $request->input('branding_name'),
            'branding.logo' => $headerLogoPath,
            'branding.favicon' => $faviconPath,
            'branding.navbar_text' => $request->input('branding_navbar_text'),
            'branding.institutional_name' => $request->input('branding_institutional_name'),
            'branding.institutional_badge' => $request->input('branding_institutional_badge'),
            'branding.accent' => $request->input('branding_accent'),
            'branding.accent_strong' => $request->input('branding_accent_strong'),
            'branding.accent_soft' => $request->input('branding_accent_soft'),
            'header.show_home' => $request->boolean('header_show_home'),
            'header.show_services' => $request->boolean('header_show_services'),
            'header.show_contact' => $request->boolean('header_show_contact'),
            'header.navigation_order' => implode(',', $navigationOrder),
            'header.sticky_enabled' => $request->boolean('header_sticky_enabled'),
            'branding.footer_text' => $request->input('footer_legal_text'),
            'footer.institutional_text' => $request->input('footer_institutional_text'),
            'footer.show_home_link' => $request->boolean('footer_show_home_link'),
            'footer.show_services_link' => $request->boolean('footer_show_services_link'),
            'footer.show_contact_link' => $request->boolean('footer_show_contact_link'),
            'footer.show_assistant_link' => $request->boolean('footer_show_assistant_link'),
            'footer.show_privacy_link' => $request->boolean('footer_show_privacy_link'),
            'footer.show_terms_link' => $request->boolean('footer_show_terms_link'),
            'footer.home_label' => $request->input('footer_home_label'),
            'footer.services_label' => $request->input('footer_services_label'),
            'footer.contact_label' => $request->input('footer_contact_label'),
            'footer.assistant_label' => $request->input('footer_assistant_label'),
            'footer.privacy_label' => $request->input('footer_privacy_label'),
            'footer.terms_label' => $request->input('footer_terms_label'),
            'contact.address' => $request->input('footer_contact_address'),
            'contact.phone' => $request->input('footer_contact_phone'),
            'contact.email' => $request->input('footer_contact_email'),
            'legal.privacy_title' => $request->input('legal_privacy_title'),
            'legal.privacy_updated_at' => $request->input('legal_privacy_updated_at'),
            'legal.privacy_body' => $request->input('legal_privacy_body'),
            'legal.terms_title' => $request->input('legal_terms_title'),
            'legal.terms_updated_at' => $request->input('legal_terms_updated_at'),
            'legal.terms_body' => $request->input('legal_terms_body'),
            'visual.soft_primary' => $request->input('visual_soft_primary'),
            'visual.soft_secondary' => $request->input('visual_soft_secondary'),
            'visual.gradient_start' => $request->input('visual_gradient_start'),
            'visual.gradient_end' => $request->input('visual_gradient_end'),
            'visual.badge_soft' => $request->input('visual_badge_soft'),
        ];

        $meta = [];
        foreach (array_keys($payload) as $key) {
            $meta[$key] = [
                'section' => str_contains($key, '.') ? explode('.', $key, 2)[0] : 'branding',
                'type' => str_contains($key, 'show_') || str_ends_with($key, '_enabled') ? 'boolean' : 'text',
            ];
        }

        $this->siteSettings->setMany($payload, $meta);
    }

    private function syncStats(LandingWelcomeRequest $request): void
    {
        $items = $this->normalizeList($request->input('stats', []));
        LandingWelcomeStat::query()->delete();

        foreach ($items as $item) {
            if (empty($item['label']) && empty($item['value']) && empty($item['note'])) {
                continue;
            }
            LandingWelcomeStat::query()->create([
                'label' => $item['label'] ?? null,
                'value' => $item['value'] ?? null,
                'note' => $item['note'] ?? null,
                'is_active' => ! empty($item['is_active']),
                'sort_order' => (int) ($item['sort_order'] ?? 0),
            ]);
        }
    }

    private function syncSlides(LandingWelcomeRequest $request): void
    {
        $items = $this->normalizeList($request->input('slides', []));
        LandingWelcomeSlide::query()->delete();

        foreach ($items as $index => $item) {
            $path = $item['image_path'] ?? null;
            $file = $request->file("slides.$index.image");
            if ($file) {
                $baseName = $path ? pathinfo((string) $path, PATHINFO_FILENAME) : null;
                $path = $this->imageOptimizer->optimizeAndStore($file, 'banners', baseName: $baseName);
            }

            if (! $path) {
                continue;
            }

            LandingWelcomeSlide::query()->create($this->filterExistingColumns('landing_welcome_slides', [
                'image_path' => $path,
                'alt' => $item['alt'] ?? null,
                'title' => $item['title'] ?? null,
                'subtitle' => $item['subtitle'] ?? null,
                'text' => $item['text'] ?? null,
                'is_active' => ! empty($item['is_active']),
                'sort_order' => (int) ($item['sort_order'] ?? 0),
            ]));
        }
    }

    private function syncInfoCards(LandingWelcomeRequest $request): void
    {
        $items = $this->normalizeList($request->input('cards', []));
        LandingWelcomeInfoCard::query()->delete();

        foreach ($items as $item) {
            if (empty($item['title']) && empty($item['description']) && empty($item['value'])) {
                continue;
            }
            LandingWelcomeInfoCard::query()->create([
                'title' => $item['title'] ?? null,
                'value' => $item['value'] ?? null,
                'description' => $item['description'] ?? null,
                'icon' => $item['icon'] ?? 'ri-information-line',
                'is_active' => ! empty($item['is_active']),
                'sort_order' => (int) ($item['sort_order'] ?? 0),
            ]);
        }
    }

    private function syncDoctors(LandingWelcomeRequest $request): void
    {
        $items = $this->normalizeList($request->input('doctors', []));
        LandingWelcomeDoctor::query()->delete();

        foreach ($items as $index => $item) {
            $path = $item['photo_path'] ?? null;
            $file = $request->file("doctors.$index.photo");
            if ($file) {
                $baseName = $path ? pathinfo((string) $path, PATHINFO_FILENAME) : null;
                $path = $this->imageOptimizer->optimizeAndStore($file, 'doctors', baseName: $baseName);
            }

            if (! $path) {
                continue;
            }

            LandingWelcomeDoctor::query()->create($this->filterExistingColumns('landing_welcome_doctors', [
                'name' => $item['name'] ?? '',
                'specialty' => $item['specialty'] ?? null,
                'photo_path' => $path,
                'experience_label' => $item['experience_label'] ?? null,
                'featured_label' => $item['featured_label'] ?? null,
                'attendance_label' => $item['attendance_label'] ?? null,
                'availability_label' => $item['availability_label'] ?? null,
                'cta_text' => $item['cta_text'] ?? null,
                'pill_text' => $item['pill_text'] ?? null,
                'is_active' => ! empty($item['is_active']),
                'sort_order' => (int) ($item['sort_order'] ?? 0),
            ]));
        }
    }

    private function syncPrices(LandingWelcomeRequest $request): void
    {
        $items = $this->normalizeList($request->input('prices', []));
        LandingWelcomePrice::query()->delete();

        foreach ($items as $item) {
            if (empty($item['service']) && empty($item['price'])) {
                continue;
            }

            LandingWelcomePrice::query()->create([
                'service' => $item['service'] ?? '',
                'price' => $item['price'] ?? null,
                'is_active' => ! empty($item['is_active']),
                'sort_order' => (int) ($item['sort_order'] ?? 0),
            ]);
        }
    }

    private function syncFeaturedSpecialties(LandingWelcomeRequest $request): void
    {
        if (! $request->boolean('show_services_block')) {
            LandingWelcomeFeaturedSpecialty::query()->delete();

            return;
        }

        $ids = array_slice(array_values(array_filter($request->input('featured_specialties', []))), 0, 3);
        $descriptions = $request->input('featured_specialty_descriptions', []);
        LandingWelcomeFeaturedSpecialty::query()->delete();

        foreach ($ids as $index => $id) {
            LandingWelcomeFeaturedSpecialty::query()->create([
                'especialidad_id' => (int) $id,
                'sort_order' => $index + 1,
            ]);

            if (! array_key_exists($index, $descriptions)) {
                continue;
            }

            Especialidad::query()
                ->whereKey((int) $id)
                ->update([
                    'descripcion' => filled($descriptions[$index] ?? null)
                        ? trim((string) $descriptions[$index])
                        : null,
                ]);
        }
    }

    private function normalizeList(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $normalized[] = Arr::map($item, fn ($value) => is_string($value) ? trim($value) : $value);
        }

        return $normalized;
    }

    private function filterExistingColumns(string $table, array $payload): array
    {
        static $columnsByTable = [];

        $columns = $columnsByTable[$table] ??= Schema::getColumnListing($table);

        return array_intersect_key($payload, array_flip($columns));
    }
}
