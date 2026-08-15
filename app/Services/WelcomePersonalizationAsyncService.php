<?php

namespace App\Services;

use App\Http\Requests\LandingWelcomeRequest;
use App\Jobs\ProcessWelcomePersonalizationImages;
use App\Models\MediaProcessingBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class WelcomePersonalizationAsyncService
{
    /**
     * Check if the request contains any newly uploaded image files.
     */
    public function hasNewImages(LandingWelcomeRequest $request): bool
    {
        return $this->countNewImages($request) > 0;
    }

    /**
     * Count total newly uploaded image files in the request.
     */
    public function countNewImages(LandingWelcomeRequest $request): int
    {
        $count = 0;
        if ($request->hasFile('header_logo')) {
            $count++;
        }
        if ($request->hasFile('branding_favicon')) {
            $count++;
        }
        foreach ($request->input('slides', []) as $index => $slide) {
            if ($request->hasFile("slides.{$index}.image")) {
                $count++;
            }
        }
        foreach ($request->input('doctors', []) as $index => $doctor) {
            if ($request->hasFile("doctors.{$index}.photo")) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Synchronously save welcome personalization when no new images are uploaded.
     * Does NOT create a MediaProcessingBatch, dispatch Jobs, or require queue worker.
     */
    public function saveDirectly(LandingWelcomeRequest $request): void
    {
        $textSettings = $this->extractTextSettings($request);

        $currentLogoPath = $request->input('header_logo_path');
        $currentFaviconPath = $request->input('branding_favicon_path');

        $slidesInput = $request->input('slides', []);
        $processedSlides = [];
        foreach ($slidesInput as $index => $slide) {
            $processedSlides[$index] = [
                'final_path' => $slide['image_path'] ?? null,
                'alt'        => $slide['alt'] ?? null,
                'title'      => $slide['title'] ?? null,
                'subtitle'   => $slide['subtitle'] ?? null,
                'text'       => $slide['text'] ?? null,
                'is_active'  => ! empty($slide['is_active']),
                'sort_order' => (int) ($slide['sort_order'] ?? 0),
            ];
        }

        $doctorsInput = $request->input('doctors', []);
        $processedDoctors = [];
        foreach ($doctorsInput as $index => $doctor) {
            $processedDoctors[$index] = [
                'final_path'         => $doctor['photo_path'] ?? null,
                'name'               => $doctor['name'] ?? null,
                'specialty'          => $doctor['specialty'] ?? null,
                'experience_label'   => $doctor['experience_label'] ?? null,
                'featured_label'     => $doctor['featured_label'] ?? null,
                'attendance_label'   => $doctor['attendance_label'] ?? null,
                'availability_label' => $doctor['availability_label'] ?? null,
                'cta_text'           => $doctor['cta_text'] ?? null,
                'pill_text'          => $doctor['pill_text'] ?? null,
                'is_active'          => ! empty($doctor['is_active']),
                'sort_order'         => (int) ($doctor['sort_order'] ?? 0),
            ];
        }

        $payload = [
            'text_settings' => $textSettings,
        ];

        $settings = app(SiteSettingsService::class);

        DB::transaction(function () use (
            $settings,
            $payload,
            $currentLogoPath,
            $currentFaviconPath,
            $processedSlides,
            $processedDoctors
        ): void {
            self::saveToDatabase(
                $settings,
                $payload,
                $currentLogoPath,
                $currentFaviconPath,
                $processedSlides,
                $processedDoctors
            );
        });

        $settings->forgetCache();
    }

    /**
     * Execute atomic DB save of non-image and image settings.
     * Shared by synchronous save and async job execution.
     */
    public static function saveToDatabase(
        SiteSettingsService $settings,
        array $payload,
        ?string $finalLogoPath,
        ?string $finalFaviconPath,
        array $processedSlides,
        array $processedDoctors
    ): void {
        $textSettings = $payload['text_settings'] ?? [];

        // — LandingWelcomeSetting ——————————————————————————————
        $setting = \App\Models\LandingWelcomeSetting::query()->first() ?? new \App\Models\LandingWelcomeSetting;
        $settingFields = self::filterExistingColumns('landing_welcome_settings', [
            'header_logo'             => $finalLogoPath,
            'header_name'             => $textSettings['header_name'] ?? null,
            'header_login_text'       => $textSettings['header_login_text'] ?? null,
            'hero_title'              => $textSettings['hero_title'] ?? null,
            'hero_subtitle'           => $textSettings['hero_subtitle'] ?? null,
            'hero_primary_text'       => $textSettings['hero_primary_text'] ?? null,
            'hero_secondary_text'     => $textSettings['hero_secondary_text'] ?? null,
            'hero_show_primary'       => $textSettings['hero_show_primary'] ?? false,
            'hero_show_secondary'     => $textSettings['hero_show_secondary'] ?? false,
            'intro_badge'             => $textSettings['intro_badge'] ?? null,
            'intro_feature_1_title'   => $textSettings['intro_feature_1_title'] ?? null,
            'intro_feature_1_text'    => $textSettings['intro_feature_1_text'] ?? null,
            'intro_feature_4_title'   => $textSettings['intro_feature_4_title'] ?? null,
            'intro_feature_4_text'    => $textSettings['intro_feature_4_text'] ?? null,
            'services_badge'          => $textSettings['services_badge'] ?? null,
            'services_title'          => $textSettings['services_title'] ?? null,
            'services_subtitle'       => $textSettings['services_subtitle'] ?? null,
            'services_button_text'    => $textSettings['services_button_text'] ?? null,
            'prices_badge'            => $textSettings['prices_badge'] ?? null,
            'prices_title'            => $textSettings['prices_title'] ?? null,
            'prices_subtitle'         => $textSettings['prices_subtitle'] ?? null,
            'prices_highlight_title'  => $textSettings['prices_highlight_title'] ?? null,
            'prices_highlight_subtitle' => $textSettings['prices_highlight_subtitle'] ?? null,
            'prices_visit_title'      => $textSettings['prices_visit_title'] ?? null,
            'prices_visit_subtitle'   => $textSettings['prices_visit_subtitle'] ?? null,
            'doctors_badge'           => $textSettings['doctors_badge'] ?? null,
            'doctors_title'           => $textSettings['doctors_title'] ?? null,
            'doctors_subtitle'        => $textSettings['doctors_subtitle'] ?? null,
            'doctors_pill'            => $textSettings['doctors_pill'] ?? null,
            'show_services_block'     => $textSettings['show_services_block'] ?? false,
        ]);
        $setting->fill($settingFields);
        $setting->save();

        // — Stats ——————————————————————————————————————————————
        \App\Models\LandingWelcomeStat::query()->delete();
        foreach ((array) ($textSettings['stats'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (empty($item['label']) && empty($item['value']) && empty($item['note'])) {
                continue;
            }
            \App\Models\LandingWelcomeStat::query()->create([
                'label'      => $item['label'] ?? null,
                'value'      => $item['value'] ?? null,
                'note'       => $item['note'] ?? null,
                'is_active'  => ! empty($item['is_active']),
                'sort_order' => (int) ($item['sort_order'] ?? 0),
            ]);
        }

        // — Slides ——————————————————————————————————————————————
        \App\Models\LandingWelcomeSlide::query()->delete();
        foreach ($processedSlides as $slide) {
            $path = $slide['final_path'] ?? null;
            if (! $path) {
                continue;
            }
            \App\Models\LandingWelcomeSlide::query()->create(self::filterExistingColumns('landing_welcome_slides', [
                'image_path' => $path,
                'alt'        => $slide['alt'] ?? null,
                'title'      => $slide['title'] ?? null,
                'subtitle'   => $slide['subtitle'] ?? null,
                'text'       => $slide['text'] ?? null,
                'is_active'  => ! empty($slide['is_active']),
                'sort_order' => (int) ($slide['sort_order'] ?? 0),
            ]));
        }

        // — Info Cards ——————————————————————————————————————————
        \App\Models\LandingWelcomeInfoCard::query()->delete();
        foreach ((array) ($textSettings['cards'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (empty($item['title']) && empty($item['description']) && empty($item['value'])) {
                continue;
            }
            \App\Models\LandingWelcomeInfoCard::query()->create([
                'title'       => $item['title'] ?? null,
                'value'       => $item['value'] ?? null,
                'description' => $item['description'] ?? null,
                'icon'        => $item['icon'] ?? 'ri-information-line',
                'is_active'   => ! empty($item['is_active']),
                'sort_order'  => (int) ($item['sort_order'] ?? 0),
            ]);
        }

        // — Doctors ——————————————————————————————————————————————
        \App\Models\LandingWelcomeDoctor::query()->delete();
        foreach ($processedDoctors as $doctor) {
            $path = $doctor['final_path'] ?? null;
            if (! $path) {
                continue;
            }
            \App\Models\LandingWelcomeDoctor::query()->create(self::filterExistingColumns('landing_welcome_doctors', [
                'name'               => $doctor['name'] ?? '',
                'specialty'          => $doctor['specialty'] ?? null,
                'photo_path'         => $path,
                'experience_label'   => $doctor['experience_label'] ?? null,
                'featured_label'     => $doctor['featured_label'] ?? null,
                'attendance_label'   => $doctor['attendance_label'] ?? null,
                'availability_label' => $doctor['availability_label'] ?? null,
                'cta_text'           => $doctor['cta_text'] ?? null,
                'pill_text'          => $doctor['pill_text'] ?? null,
                'is_active'          => ! empty($doctor['is_active']),
                'sort_order'         => (int) ($doctor['sort_order'] ?? 0),
            ]));
        }

        // — Prices ——————————————————————————————————————————————
        \App\Models\LandingWelcomePrice::query()->delete();
        foreach ((array) ($textSettings['prices'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (empty($item['service']) && empty($item['price'])) {
                continue;
            }
            \App\Models\LandingWelcomePrice::query()->create([
                'service'    => $item['service'] ?? '',
                'price'      => $item['price'] ?? null,
                'is_active'  => ! empty($item['is_active']),
                'sort_order' => (int) ($item['sort_order'] ?? 0),
            ]);
        }

        // — Featured Specialties ————————————————————————————————
        $showServicesBlock = ! empty($textSettings['show_services_block']);
        if (! $showServicesBlock) {
            \App\Models\LandingWelcomeFeaturedSpecialty::query()->delete();
        } else {
            $ids          = array_slice(array_values(array_filter((array) ($textSettings['featured_specialties'] ?? []))), 0, 3);
            $descriptions = (array) ($textSettings['featured_specialty_descriptions'] ?? []);
            \App\Models\LandingWelcomeFeaturedSpecialty::query()->delete();
            foreach ($ids as $idx => $id) {
                \App\Models\LandingWelcomeFeaturedSpecialty::query()->create([
                    'especialidad_id' => (int) $id,
                    'sort_order'      => $idx + 1,
                ]);
                if (array_key_exists($idx, $descriptions)) {
                    \App\Models\Especialidad::query()
                        ->whereKey((int) $id)
                        ->update([
                            'descripcion' => filled($descriptions[$idx] ?? null)
                                ? trim((string) $descriptions[$idx])
                                : null,
                        ]);
                }
            }
        }

        // — SiteSettings ———————————————————————————————————————
        $siteSettingsKeys = [
            'branding.name', 'branding.logo', 'branding.favicon',
            'branding.navbar_text', 'branding.institutional_name', 'branding.institutional_badge',
            'branding.accent', 'branding.accent_strong', 'branding.accent_soft',
            'header.show_home', 'header.show_services', 'header.show_contact',
            'header.navigation_order', 'header.sticky_enabled', 'branding.footer_text',
            'footer.institutional_text', 'footer.show_home_link', 'footer.show_services_link',
            'footer.show_contact_link', 'footer.show_assistant_link', 'footer.show_privacy_link',
            'footer.show_terms_link', 'footer.home_label', 'footer.services_label',
            'footer.contact_label', 'footer.assistant_label', 'footer.privacy_label',
            'footer.terms_label', 'contact.address', 'contact.phone', 'contact.email',
            'legal.privacy_title', 'legal.privacy_updated_at', 'legal.privacy_body',
            'legal.terms_title', 'legal.terms_updated_at', 'legal.terms_body',
            'visual.soft_primary', 'visual.soft_secondary', 'visual.gradient_start',
            'visual.gradient_end', 'visual.badge_soft',
        ];

        $sitePayload = [];
        foreach ($siteSettingsKeys as $key) {
            $sitePayload[$key] = $textSettings[$key] ?? null;
        }
        $sitePayload['branding.logo']    = $finalLogoPath;
        $sitePayload['branding.favicon']  = $finalFaviconPath;

        $meta = [];
        foreach (array_keys($sitePayload) as $key) {
            $meta[$key] = [
                'section' => str_contains($key, '.') ? explode('.', $key, 2)[0] : 'branding',
                'type'    => str_contains($key, 'show_') || str_ends_with($key, '_enabled') ? 'boolean' : 'text',
            ];
        }

        $settings->setMany($sitePayload, $meta);
    }

    private static function filterExistingColumns(string $table, array $payload): array
    {
        static $columnsByTable = [];
        $columns = $columnsByTable[$table] ??= \Illuminate\Support\Facades\Schema::getColumnListing($table);
        return array_intersect_key($payload, array_flip($columns));
    }

    /**
     * Validate, store temp files, create batch, dispatch Job.
     * Returns quickly — no image processing inside the HTTP request.
     */
    public function createAndDispatchBatch(
        LandingWelcomeRequest $request,
        User $user
    ): MediaProcessingBatch {
        if (! $this->hasNewImages($request)) {
            throw new \InvalidArgumentException('No hay imágenes nuevas para procesar en lote.');
        }

        $batchUuid = Str::uuid()->toString();
        $tempDir   = "media-processing/welcome/{$batchUuid}";

        $imageCount = 0;

        // ── Logo ────────────────────────────────────────────────────────────────
        $logoTempPath = null;
        $logoFile     = $request->file('header_logo');
        if ($logoFile) {
            $ext         = strtolower((string) ($logoFile->getClientOriginalExtension() ?: 'tmp'));
            $logoTempPath = $this->stageUpload($tempDir, $logoFile, "logo-{$batchUuid}.{$ext}");
            $imageCount++;
        }

        // ── Favicon ─────────────────────────────────────────────────────────────
        $faviconTempPath = null;
        $faviconFile     = $request->file('branding_favicon');
        if ($faviconFile) {
            $ext             = strtolower((string) ($faviconFile->getClientOriginalExtension() ?: 'tmp'));
            $faviconTempPath = $this->stageUpload($tempDir, $faviconFile, "favicon-{$batchUuid}.{$ext}");
            $imageCount++;
        }

        // ── Slides ──────────────────────────────────────────────────────────────
        $slidesPayload = [];
        $slidesInput   = $request->input('slides', []);
        foreach ($slidesInput as $index => $slide) {
            $file     = $request->file("slides.{$index}.image");
            $tempPath = null;
            if ($file) {
                $ext      = strtolower((string) ($file->getClientOriginalExtension() ?: 'tmp'));
                $tempPath = $this->stageUpload($tempDir, $file, "slide-{$index}-{$batchUuid}.{$ext}");
                $imageCount++;
            }
            $slidesPayload[$index] = [
                'image_path'  => $slide['image_path'] ?? null,
                'alt'         => $slide['alt'] ?? null,
                'title'       => $slide['title'] ?? null,
                'subtitle'    => $slide['subtitle'] ?? null,
                'text'        => $slide['text'] ?? null,
                'is_active'   => ! empty($slide['is_active']),
                'sort_order'  => (int) ($slide['sort_order'] ?? 0),
                'temp_path'   => $tempPath,
            ];
        }

        // ── Doctors ─────────────────────────────────────────────────────────────
        $doctorsPayload = [];
        $doctorsInput   = $request->input('doctors', []);
        foreach ($doctorsInput as $index => $doctor) {
            $file     = $request->file("doctors.{$index}.photo");
            $tempPath = null;
            if ($file) {
                $ext      = strtolower((string) ($file->getClientOriginalExtension() ?: 'tmp'));
                $tempPath = $this->stageUpload($tempDir, $file, "doctor-{$index}-{$batchUuid}.{$ext}");
                $imageCount++;
            }
            $doctorsPayload[$index] = [
                'photo_path'         => $doctor['photo_path'] ?? null,
                'name'               => $doctor['name'] ?? null,
                'specialty'          => $doctor['specialty'] ?? null,
                'experience_label'   => $doctor['experience_label'] ?? null,
                'featured_label'     => $doctor['featured_label'] ?? null,
                'attendance_label'   => $doctor['attendance_label'] ?? null,
                'availability_label' => $doctor['availability_label'] ?? null,
                'cta_text'           => $doctor['cta_text'] ?? null,
                'pill_text'          => $doctor['pill_text'] ?? null,
                'is_active'          => ! empty($doctor['is_active']),
                'sort_order'         => (int) ($doctor['sort_order'] ?? 0),
                'temp_path'          => $tempPath,
            ];
        }

        // ── Text / non-image payload ─────────────────────────────────────────────
        $textSettings = $this->extractTextSettings($request);

        $payload = [
            'temp_directory'       => $tempDir,
            'logo_temp_path'       => $logoTempPath,
            'logo_current_path'    => $request->input('header_logo_path'),
            'favicon_temp_path'    => $faviconTempPath,
            'favicon_current_path' => $request->input('branding_favicon_path'),
            'slides'               => $slidesPayload,
            'doctors'              => $doctorsPayload,
            'text_settings'        => $textSettings,
        ];

        $batch = null;
        try {
            /** @var MediaProcessingBatch $batch */
            $batch = DB::transaction(function () use ($batchUuid, $user, $imageCount, $payload) {
                return MediaProcessingBatch::query()->create([
                    'uuid'            => $batchUuid,
                    'user_id'         => $user->id,
                    'type'            => 'welcome_personalization',
                    'status'          => 'pending',
                    'total_items'     => $imageCount,
                    'processed_items' => 0,
                    'payload'         => $payload,
                ]);
            });

            ProcessWelcomePersonalizationImages::dispatch($batchUuid);

            return $batch;
        } catch (Throwable $exception) {
            $this->cleanupStaging($tempDir);
            if ($batch) {
                $batch->update([
                    'status' => 'failed',
                    'error_message' => 'No se pudo iniciar el procesamiento de imágenes.',
                    'finished_at' => now(),
                ]);
            }
            throw $exception;
        }
    }

    private function stageUpload(string $directory, object $file, string $filename): string
    {
        try {
            $path = Storage::disk('r2_private')->putFileAs($directory, $file, $filename);
            if (! is_string($path) || $path === '') {
                throw new \RuntimeException('No se pudo guardar el staging de Welcome en R2.');
            }

            return $path;
        } catch (Throwable $exception) {
            $this->cleanupStaging($directory);
            throw $exception;
        }
    }

    private function cleanupStaging(string $directory): void
    {
        try {
            Storage::disk('r2_private')->deleteDirectory($directory);
        } catch (Throwable $exception) {
            Log::warning('No se pudo limpiar staging R2 de Welcome.', [
                'directory' => $directory,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Collect every non-image field that must be saved to LandingWelcomeSetting
     * and SiteSettings after image processing completes.
     */
    private function extractTextSettings(LandingWelcomeRequest $request): array
    {
        $navigationOrder = array_values(array_filter($request->input('header_navigation_order', [])));
        if (empty($navigationOrder)) {
            $navigationOrder = ['home', 'services', 'contact'];
        }

        return [
            // LandingWelcomeSetting fields
            'header_name'             => $request->input('branding_name'),
            'header_login_text'       => $request->input('header_login_text'),
            'hero_title'              => $request->input('hero_title'),
            'hero_subtitle'           => $request->input('hero_subtitle'),
            'hero_primary_text'       => $request->input('hero_primary_text'),
            'hero_secondary_text'     => $request->input('hero_secondary_text'),
            'hero_show_primary'       => $request->boolean('hero_show_primary'),
            'hero_show_secondary'     => $request->boolean('hero_show_secondary'),
            'intro_badge'             => $request->input('intro_badge'),
            'intro_feature_1_title'   => $request->input('intro_feature_1_title'),
            'intro_feature_1_text'    => $request->input('intro_feature_1_text'),
            'intro_feature_4_title'   => $request->input('intro_feature_4_title'),
            'intro_feature_4_text'    => $request->input('intro_feature_4_text'),
            'services_badge'          => $request->input('services_badge'),
            'services_title'          => $request->input('services_title'),
            'services_subtitle'       => $request->input('services_subtitle'),
            'services_button_text'    => $request->input('services_button_text'),
            'prices_badge'            => $request->input('prices_badge'),
            'prices_title'            => $request->input('prices_title'),
            'prices_subtitle'         => $request->input('prices_subtitle'),
            'prices_highlight_title'  => $request->input('prices_highlight_title'),
            'prices_highlight_subtitle' => $request->input('prices_highlight_subtitle'),
            'prices_visit_title'      => $request->input('prices_visit_title'),
            'prices_visit_subtitle'   => $request->input('prices_visit_subtitle'),
            'doctors_badge'           => $request->input('doctors_badge'),
            'doctors_title'           => $request->input('doctors_title'),
            'doctors_subtitle'        => $request->input('doctors_subtitle'),
            'doctors_pill'            => $request->input('doctors_pill'),
            'show_services_block'     => $request->boolean('show_services_block'),
            // SiteSettings
            'branding.name'           => $request->input('branding_name'),
            'branding.navbar_text'    => $request->input('branding_navbar_text'),
            'branding.institutional_name'  => $request->input('branding_institutional_name'),
            'branding.institutional_badge' => $request->input('branding_institutional_badge'),
            'branding.accent'         => $request->input('branding_accent'),
            'branding.accent_strong'  => $request->input('branding_accent_strong'),
            'branding.accent_soft'    => $request->input('branding_accent_soft'),
            'header.show_home'        => $request->boolean('header_show_home'),
            'header.show_services'    => $request->boolean('header_show_services'),
            'header.show_contact'     => $request->boolean('header_show_contact'),
            'header.navigation_order' => implode(',', $navigationOrder),
            'header.sticky_enabled'   => $request->boolean('header_sticky_enabled'),
            'branding.footer_text'    => $request->input('footer_legal_text'),
            'footer.institutional_text'    => $request->input('footer_institutional_text'),
            'footer.show_home_link'        => $request->boolean('footer_show_home_link'),
            'footer.show_services_link'    => $request->boolean('footer_show_services_link'),
            'footer.show_contact_link'     => $request->boolean('footer_show_contact_link'),
            'footer.show_assistant_link'   => $request->boolean('footer_show_assistant_link'),
            'footer.show_privacy_link'     => $request->boolean('footer_show_privacy_link'),
            'footer.show_terms_link'       => $request->boolean('footer_show_terms_link'),
            'footer.home_label'       => $request->input('footer_home_label'),
            'footer.services_label'   => $request->input('footer_services_label'),
            'footer.contact_label'    => $request->input('footer_contact_label'),
            'footer.assistant_label'  => $request->input('footer_assistant_label'),
            'footer.privacy_label'    => $request->input('footer_privacy_label'),
            'footer.terms_label'      => $request->input('footer_terms_label'),
            'contact.address'         => $request->input('footer_contact_address'),
            'contact.phone'           => $request->input('footer_contact_phone'),
            'contact.email'           => $request->input('footer_contact_email'),
            'legal.privacy_title'     => $request->input('legal_privacy_title'),
            'legal.privacy_updated_at' => $request->input('legal_privacy_updated_at'),
            'legal.privacy_body'      => $request->input('legal_privacy_body'),
            'legal.terms_title'       => $request->input('legal_terms_title'),
            'legal.terms_updated_at'  => $request->input('legal_terms_updated_at'),
            'legal.terms_body'        => $request->input('legal_terms_body'),
            'visual.soft_primary'     => $request->input('visual_soft_primary'),
            'visual.soft_secondary'   => $request->input('visual_soft_secondary'),
            'visual.gradient_start'   => $request->input('visual_gradient_start'),
            'visual.gradient_end'     => $request->input('visual_gradient_end'),
            'visual.badge_soft'       => $request->input('visual_badge_soft'),
            // Stats / cards / prices / featured specialties are non-image, passed raw
            'stats'                   => $request->input('stats', []),
            'cards'                   => $request->input('cards', []),
            'prices'                  => $request->input('prices', []),
            'featured_specialties'    => $request->input('featured_specialties', []),
            'featured_specialty_descriptions' => $request->input('featured_specialty_descriptions', []),
        ];
    }
}
