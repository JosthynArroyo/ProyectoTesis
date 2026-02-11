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
    public function update(LandingWelcomeRequest $request): void
    {
        $settings = LandingWelcomeSetting::query()->first() ?? new LandingWelcomeSetting();

        $settings->fill([
            'hero_badge' => $request->input('hero_badge'),
            'hero_title' => $request->input('hero_title'),
            'hero_subtitle' => $request->input('hero_subtitle'),
            'hero_primary_text' => $request->input('hero_primary_text'),
            'hero_secondary_text' => $request->input('hero_secondary_text'),
            'hero_show_primary' => $request->boolean('hero_show_primary'),
            'hero_show_secondary' => $request->boolean('hero_show_secondary'),
            'prices_subtitle' => $request->input('prices_subtitle'),
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
                $path = $file->store('landing/slides', 'public');
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
                $path = $file->store('landing/doctores', 'public');
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

        $ids = array_values(array_filter($request->input('featured_specialties', [])));
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
