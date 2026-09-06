<?php

namespace App\Jobs;

use App\Models\Especialidad;
use App\Models\LandingWelcomeDoctor;
use App\Models\LandingWelcomeFeaturedSpecialty;
use App\Models\LandingWelcomeInfoCard;
use App\Models\LandingWelcomePrice;
use App\Models\LandingWelcomeSetting;
use App\Models\LandingWelcomeSlide;
use App\Models\LandingWelcomeStat;
use App\Models\MediaProcessingBatch;
use App\Services\ImageOptimizer;
use App\Services\LandingWelcomeManager;
use App\Services\SiteSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessWelcomePersonalizationImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public string $batchUuid
    ) {
        $this->onConnection(config('queue.media_connection', 'database'));
        $this->onQueue(config('queue.media_queue', 'media'));
    }

    public function handle(ImageOptimizer $imageOptimizer, SiteSettingsService $settings): void
    {
        $batch = MediaProcessingBatch::query()->where('uuid', $this->batchUuid)->first();
        $defaultTempDirectory = 'media-processing/welcome/'.$this->batchUuid;

        if (! $batch) {
            $this->cleanupStaging($defaultTempDirectory);
            return;
        }

        $payload = $batch->payload ?? [];
        $tempDirectory = $payload['temp_directory'] ?? $defaultTempDirectory;

        if ($batch->isCompleted() || $batch->isFailed()) {
            $this->cleanupStaging($tempDirectory);
            return;
        }

        $batch->update([
            'status'     => 'processing',
            'started_at' => now(),
        ]);

        $newlyUploadedPaths = [];
        $pathsToDelete      = [];
        $processedCount     = 0;
        $generatedVariantCount  = 0;
        $approximateWriteCount  = 0;
        $webpGenerationSeconds  = 0.0;

        try {
            if (! in_array($imageOptimizer->disk(), ['r2_public', 'public'], true)) {
                throw new \RuntimeException('Welcome requiere un disco público válido (r2_public o public).');
            }

            // ─────────────────────────────────────────────────────────────
            // 1. Process logo
            // ─────────────────────────────────────────────────────────────
            $logoTempPath    = $payload['logo_temp_path'] ?? null;
            $currentLogoPath = $payload['logo_current_path'] ?? null;
            $finalLogoPath   = $currentLogoPath;
            $logoStagingDisk = $this->resolveStagingDisk($logoTempPath);

            if ($logoTempPath && Storage::disk($logoStagingDisk)->exists($logoTempPath)) {
                $logoBaseName    = 'branding-header-logo-'.Str::uuid()->toString();
                $t0 = microtime(true);
                $newLogoPath = $imageOptimizer->optimizeStoredPath(
                    sourceDisk: $logoStagingDisk,
                    sourcePath: $logoTempPath,
                    folder: 'branding',
                    baseName: $logoBaseName,
                    profile: 'branding_asset',
                    storeOriginal: true
                );
                $webpGenerationSeconds += microtime(true) - $t0;

                if ($newLogoPath) {
                    $finalLogoPath = $newLogoPath;
                    $newlyUploadedPaths[] = $newLogoPath;
                    $generatedVariantCount += count($imageOptimizer->profileSizes('branding_asset'));
                    $approximateWriteCount += $generatedVariantCount + 1;
                    if ($currentLogoPath && $currentLogoPath !== $newLogoPath) {
                        $pathsToDelete[] = $currentLogoPath;
                    }
                }

                $processedCount++;
                $batch->update(['processed_items' => $processedCount]);
            }

            // ─────────────────────────────────────────────────────────────
            // 2. Process favicon
            // ─────────────────────────────────────────────────────────────
            $faviconTempPath    = $payload['favicon_temp_path'] ?? null;
            $currentFaviconPath = $payload['favicon_current_path'] ?? null;
            $finalFaviconPath   = $currentFaviconPath;
            $faviconStagingDisk = $this->resolveStagingDisk($faviconTempPath);

            if ($faviconTempPath && Storage::disk($faviconStagingDisk)->exists($faviconTempPath)) {
                $faviconBase  = 'favicon-'.Str::uuid()->toString();
                $t0 = microtime(true);
                $newFaviconPath = $imageOptimizer->optimizeStoredPath(
                    sourceDisk: $faviconStagingDisk,
                    sourcePath: $faviconTempPath,
                    folder: 'branding',
                    baseName: $faviconBase,
                    profile: 'branding_asset',
                    storeOriginal: true
                );
                $webpGenerationSeconds += microtime(true) - $t0;

                if ($newFaviconPath) {
                    $finalFaviconPath = $newFaviconPath;
                    $newlyUploadedPaths[] = $newFaviconPath;
                    $generatedVariantCount += count($imageOptimizer->profileSizes('branding_asset'));
                    $approximateWriteCount += $generatedVariantCount + 1;
                    if ($currentFaviconPath && $currentFaviconPath !== $newFaviconPath) {
                        $pathsToDelete[] = $currentFaviconPath;
                    }
                }

                $processedCount++;
                $batch->update(['processed_items' => $processedCount]);
            }

            // ─────────────────────────────────────────────────────────────
            // 3. Process slides
            // ─────────────────────────────────────────────────────────────
            $slidesData       = $payload['slides'] ?? [];
            $processedSlides  = [];

            foreach ($slidesData as $index => $slide) {
                $tempPath    = $slide['temp_path'] ?? null;
                $currentPath = $slide['image_path'] ?? null;
                $finalPath   = $currentPath;
                $slideStagingDisk = $this->resolveStagingDisk($tempPath);

                if ($tempPath && Storage::disk($slideStagingDisk)->exists($tempPath)) {
                    $slideBase   = 'slide-'.Str::uuid()->toString();
                    $t0 = microtime(true);
                    $newSlidePath = $imageOptimizer->optimizeStoredPath(
                        sourceDisk: $slideStagingDisk,
                        sourcePath: $tempPath,
                        folder: 'banners',
                        baseName: $slideBase,
                        profile: 'public_hero',
                        storeOriginal: true
                    );
                    $webpGenerationSeconds += microtime(true) - $t0;

                    if ($newSlidePath) {
                        $finalPath = $newSlidePath;
                        $newlyUploadedPaths[] = $newSlidePath;
                        $variantCount = count($imageOptimizer->profileSizes('public_hero'));
                        $generatedVariantCount += $variantCount;
                        $approximateWriteCount += $variantCount + 1;
                        if ($currentPath && $currentPath !== $newSlidePath) {
                            $pathsToDelete[] = $currentPath;
                        }
                    }

                    $processedCount++;
                    $batch->update(['processed_items' => $processedCount]);
                }

                $processedSlides[$index] = array_merge($slide, ['final_path' => $finalPath]);
            }

            // ─────────────────────────────────────────────────────────────
            // 4. Process doctors
            // ─────────────────────────────────────────────────────────────
            $doctorsData       = $payload['doctors'] ?? [];
            $processedDoctors  = [];

            foreach ($doctorsData as $index => $doctor) {
                $tempPath    = $doctor['temp_path'] ?? null;
                $currentPath = $doctor['photo_path'] ?? null;
                $finalPath   = $currentPath;
                $doctorStagingDisk = $this->resolveStagingDisk($tempPath);

                if ($tempPath && Storage::disk($doctorStagingDisk)->exists($tempPath)) {
                    $doctorBase = 'doctor-'.Str::uuid()->toString();
                    $t0 = microtime(true);
                    $newDoctorPath = $imageOptimizer->optimizeStoredPath(
                        sourceDisk: $doctorStagingDisk,
                        sourcePath: $tempPath,
                        folder: 'doctors',
                        baseName: $doctorBase,
                        profile: 'public_doctor',
                        storeOriginal: true
                    );
                    $webpGenerationSeconds += microtime(true) - $t0;

                    if ($newDoctorPath) {
                        $finalPath = $newDoctorPath;
                        $newlyUploadedPaths[] = $newDoctorPath;
                        $variantCount = count($imageOptimizer->profileSizes('public_doctor'));
                        $generatedVariantCount += $variantCount;
                        $approximateWriteCount += $variantCount + 1;
                        if ($currentPath && $currentPath !== $newDoctorPath) {
                            $pathsToDelete[] = $currentPath;
                        }
                    }

                    $processedCount++;
                    $batch->update(['processed_items' => $processedCount]);
                }

                $processedDoctors[$index] = array_merge($doctor, ['final_path' => $finalPath]);
            }

            $databaseCommitted = false;

            // ─────────────────────────────────────────────────────────────
            // 5. Atomic database transaction — commit all changes at once
            // ─────────────────────────────────────────────────────────────
            DB::transaction(function () use (
                $settings,
                $payload,
                $finalLogoPath,
                $finalFaviconPath,
                $processedSlides,
                $processedDoctors
            ): void {
                \App\Services\WelcomePersonalizationAsyncService::saveToDatabase(
                    $settings,
                    $payload,
                    $finalLogoPath,
                    $finalFaviconPath,
                    $processedSlides,
                    $processedDoctors
                );
            });
            $settings->forgetCache();
            $databaseCommitted = true;

            // ─────────────────────────────────────────────────────────────
            // 6. Mark completed
            // ─────────────────────────────────────────────────────────────
            $batch->update([
                'status'               => 'completed',
                'processed_items'      => $processedCount,
                'error_message'        => null,
                'finished_at'          => now(),
                'approximate_r2_writes'=> $approximateWriteCount,
            ]);

            // ─────────────────────────────────────────────────────────────
            // 7. Prune superseded / replaced image files
            // ─────────────────────────────────────────────────────────────
            if (! empty($pathsToDelete)) {
                $imageOptimizer->deleteManyByStoredPaths($pathsToDelete, 'welcome');
            }

            $this->logBatchSummary(
                $batch,
                $processedCount,
                $generatedVariantCount,
                $approximateWriteCount,
                $webpGenerationSeconds
            );

        } catch (Throwable $exception) {
            Log::error('Fallo en procesamiento asíncrono de imágenes de bienvenida:', [
                'batch_uuid' => $this->batchUuid,
                'error'      => $exception->getMessage(),
            ]);

            // Roll back newly created R2 objects ONLY if the database transaction did not commit
            if (! ($databaseCommitted ?? false) && ! empty($newlyUploadedPaths)) {
                try {
                    $imageOptimizer->deleteManyByStoredPaths($newlyUploadedPaths, 'welcome');
                } catch (Throwable) {
                    // ignore secondary cleanup error
                }
            }

            $batch->update([
                'status'        => 'failed',
                'error_message' => 'No pudimos procesar todas las imágenes. Tus imágenes anteriores se conservaron. Intenta nuevamente.',
                'finished_at'   => now(),
            ]);

            $this->logBatchSummary(
                $batch,
                $processedCount,
                $generatedVariantCount,
                $approximateWriteCount,
                $webpGenerationSeconds,
                'failed'
            );

            throw $exception;
        } finally {
            $this->cleanupStaging($tempDirectory);
        }
    }

    private function logBatchSummary(
        MediaProcessingBatch $batch,
        int $imageCount,
        int $variantCount,
        int $approximateWriteCount,
        float $webpGenerationSeconds,
        ?string $statusOverride = null
    ): void {
        Log::info('Welcome batch summary', [
            'batch_uuid' => $batch->uuid,
            'status' => $statusOverride ?: $batch->status,
            'images' => $imageCount,
            'variants' => $variantCount,
            'waiting_seconds' => $batch->waiting_seconds,
            'processing_seconds' => $batch->processing_seconds,
            'webp_generation_seconds' => round($webpGenerationSeconds, 3),
            'approximate_r2_writes' => $approximateWriteCount,
            'total_seconds' => $batch->elapsed_seconds,
        ]);
    }

    private function resolveStagingDisk(?string $path = null): string
    {
        if ($path && Storage::disk('r2_private')->exists($path)) {
            return 'r2_private';
        }

        if ($path && Storage::disk('local')->exists($path)) {
            return 'local';
        }

        return config('private_documents.disk', 'local') === 'r2_private' ? 'r2_private' : 'local';
    }

    private function cleanupStaging(?string $directory): void
    {
        if (! $directory) {
            return;
        }

        $stagingDisk = $this->resolveStagingDisk();
        try {
            if (Storage::disk($stagingDisk)->exists($directory)) {
                Storage::disk($stagingDisk)->deleteDirectory($directory);
            }
        } catch (Throwable $exception) {
            Log::warning('No se pudo limpiar staging de Welcome.', [
                'batch_uuid' => $this->batchUuid,
                'directory' => $directory,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function filterExistingColumns(string $table, array $payload): array
    {
        static $columnsByTable = [];
        $columns = $columnsByTable[$table] ??= Schema::getColumnListing($table);
        return array_intersect_key($payload, array_flip($columns));
    }
}
