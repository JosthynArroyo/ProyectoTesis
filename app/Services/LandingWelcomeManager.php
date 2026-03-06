<?php

namespace App\Services;

use App\Http\Requests\LandingWelcomeRequest;
use App\Models\LandingWelcomeDoctor;
use App\Models\LandingWelcomeFeaturedSpecialty;
use App\Models\LandingWelcomeInfoCard;
use App\Models\LandingWelcomePrice;
use App\Models\LandingWelcomeSetting;
use App\Models\LandingWelcomeSlide;
use App\Models\LandingWelcomeStat;
use Illuminate\Support\Arr;

class LandingWelcomeManager
{
    public function __construct(
        private readonly ImageOptimizer $imageOptimizer
    ) {
    }

    public function update(LandingWelcomeRequest $request): void
    {
        $settings = LandingWelcomeSetting::query()->first() ?? new LandingWelcomeSetting();
        $pricesHighlightImagePath = $request->input('prices_highlight_image_path');
        $pricesHighlightImageFile = $request->file('prices_highlight_image');
        if ($pricesHighlightImageFile) {
            $baseName = $pricesHighlightImagePath
                ? pathinfo((string) $pricesHighlightImagePath, PATHINFO_FILENAME)
                : null;

            $pricesHighlightImagePath = $this->imageOptimizer->optimizeAndStore(
                $pricesHighlightImageFile,
                'banners',
                baseName: $baseName
            );
        }

        $settings->fill([
            'hero_badge' => $request->input('hero_badge'),
            'hero_title' => $request->input('hero_title'),
            'hero_subtitle' => $request->input('hero_subtitle'),
            'hero_followup_title' => $request->input('hero_followup_title'),
            'hero_followup_subtitle' => $request->input('hero_followup_subtitle'),
            'hero_primary_text' => $request->input('hero_primary_text'),
            'hero_secondary_text' => $request->input('hero_secondary_text'),
            'hero_show_primary' => $request->boolean('hero_show_primary'),
            'hero_show_secondary' => $request->boolean('hero_show_secondary'),
            'intro_badge' => $request->input('intro_badge'),
            'intro_title' => $request->input('intro_title'),
            'intro_subtitle' => $request->input('intro_subtitle'),
            'intro_feature_1_title' => $request->input('intro_feature_1_title'),
            'intro_feature_1_text' => $request->input('intro_feature_1_text'),
            'intro_feature_2_title' => $request->input('intro_feature_2_title'),
            'intro_feature_2_text' => $request->input('intro_feature_2_text'),
            'intro_feature_3_title' => $request->input('intro_feature_3_title'),
            'intro_feature_3_text' => $request->input('intro_feature_3_text'),
            'intro_feature_4_title' => $request->input('intro_feature_4_title'),
            'intro_feature_4_text' => $request->input('intro_feature_4_text'),
            'services_badge' => $request->input('services_badge'),
            'services_title' => $request->input('services_title'),
            'services_subtitle' => $request->input('services_subtitle'),
            'services_button_text' => $request->input('services_button_text'),
            'prices_badge' => $request->input('prices_badge'),
            'prices_title' => $request->input('prices_title'),
            'prices_subtitle' => $request->input('prices_subtitle'),
            'prices_button_text' => $request->input('prices_button_text'),
            'prices_highlight_title' => $request->input('prices_highlight_title'),
            'prices_highlight_subtitle' => $request->input('prices_highlight_subtitle'),
            'prices_highlight_image' => $pricesHighlightImagePath,
            'prices_visit_title' => $request->input('prices_visit_title'),
            'prices_visit_subtitle' => $request->input('prices_visit_subtitle'),
            'doctors_badge' => $request->input('doctors_badge'),
            'doctors_title' => $request->input('doctors_title'),
            'doctors_subtitle' => $request->input('doctors_subtitle'),
            'doctors_pill' => $request->input('doctors_pill'),
            'show_services_block' => $request->boolean('show_services_block'),
        ]);

        $settings->save();

        $this->syncStats($request);
        $this->syncSlides($request);
        $this->syncInfoCards($request);
        $this->syncDoctors($request);
        $this->syncPrices($request);
        $this->syncFeaturedSpecialties($request);
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
                'is_active' => !empty($item['is_active']),
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

            LandingWelcomeSlide::query()->create([
                'image_path' => $path,
                'is_active' => !empty($item['is_active']),
                'sort_order' => (int) ($item['sort_order'] ?? 0),
            ]);
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
                'is_active' => !empty($item['is_active']),
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

            LandingWelcomeDoctor::query()->create([
                'name' => $item['name'] ?? '',
                'specialty' => $item['specialty'] ?? null,
                'photo_path' => $path,
                'is_active' => !empty($item['is_active']),
                'sort_order' => (int) ($item['sort_order'] ?? 0),
            ]);
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
                'is_active' => !empty($item['is_active']),
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
        LandingWelcomeFeaturedSpecialty::query()->delete();

        foreach ($ids as $index => $id) {
            LandingWelcomeFeaturedSpecialty::query()->create([
                'especialidad_id' => (int) $id,
                'sort_order' => $index + 1,
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
}
