<?php

namespace App\Jobs;

use App\Models\Especialidad;
use App\Models\MediaProcessingBatch;
use App\Services\ImageOptimizer;
use App\Services\SiteSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessServicesPersonalizationImages implements ShouldQueue
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
        $defaultTempDirectory = 'media-processing/services/'.$this->batchUuid;

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
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $newlyUploadedPaths = [];
        $pathsToDelete = [];
        $processedCount = 0;
        $generatedVariantCount = 0;
        $approximateWriteCount = 0;
        $webpGenerationSeconds = 0.0;

        try {
            if (! in_array($imageOptimizer->disk(), ['r2_public', 'public'], true)) {
                throw new \RuntimeException('Services requiere un disco público válido (r2_public o public).');
            }

            $settingsPayload = $payload['text_settings'] ?? [];
            $meta = [
                'services.title' => ['section' => 'services', 'type' => 'text'],
                'services.subtitle' => ['section' => 'services', 'type' => 'text'],
                'services.cta_text' => ['section' => 'services', 'type' => 'text'],
                'services.hero_image' => ['section' => 'services', 'type' => 'image'],
            ];

            // 1. Process Hero Image if provided
            $heroTempPath = $payload['hero_image_temp_path'] ?? null;
            $currentHeroImage = $payload['hero_image_current_path'] ?? $settings->get('services.hero_image');
            $heroStagingDisk = $this->resolveStagingDisk($heroTempPath);

            if ($heroTempPath && Storage::disk($heroStagingDisk)->exists($heroTempPath)) {
                $heroProfile = 'public_hero';
                $heroVariantCount = count($imageOptimizer->profileSizes($heroProfile));
                $heroStartedAt = microtime(true);
                // Use UUID baseName so each upload creates a unique key.
                $heroBaseName = 'services-hero-'.Str::uuid()->toString();
                $newHeroPath = $imageOptimizer->optimizeStoredPath(
                    sourceDisk: $heroStagingDisk,
                    sourcePath: $heroTempPath,
                    folder: 'services',
                    baseName: $heroBaseName,
                    profile: $heroProfile,
                    storeOriginal: true
                );
                $webpGenerationSeconds += microtime(true) - $heroStartedAt;

                if ($newHeroPath) {
                    $settingsPayload['services.hero_image'] = $newHeroPath;
                    $newlyUploadedPaths[] = $newHeroPath;
                    $generatedVariantCount += $heroVariantCount;
                    $approximateWriteCount += $heroVariantCount + 1;

                    if ($currentHeroImage && $currentHeroImage !== $newHeroPath) {
                        $pathsToDelete[] = $currentHeroImage;
                    }
                }

                $processedCount++;
                $batch->update(['processed_items' => $processedCount]);
            } else {
                $settingsPayload['services.hero_image'] = $currentHeroImage;
            }

            $especialidadesData = $payload['especialidades'] ?? [];
            $nuevasData = $payload['nuevas'] ?? [];

            // Arrays for final DB transaction
            $updatedEspecialidades = [];
            $newEspecialidades = [];

            // 2. Process Existing Specialties
            foreach ($especialidadesData as $id => $specData) {
                $specTempPath = $specData['temp_path'] ?? null;
                $currentImagePath = $specData['current_path'] ?? null;
                $finalImagePath = $currentImagePath;
                $specStagingDisk = $this->resolveStagingDisk($specTempPath);

                if ($specTempPath && Storage::disk($specStagingDisk)->exists($specTempPath)) {
                    $specProfile = 'public_card';
                    $specVariantCount = count($imageOptimizer->profileSizes($specProfile));
                    $specStartedAt = microtime(true);
                    $specBaseName = 'service-'.$id.'-'.Str::uuid()->toString();
                    $newSpecPath = $imageOptimizer->optimizeStoredPath(
                        sourceDisk: $specStagingDisk,
                        sourcePath: $specTempPath,
                        folder: 'services',
                        baseName: $specBaseName,
                        profile: $specProfile,
                        storeOriginal: true
                    );
                    $webpGenerationSeconds += microtime(true) - $specStartedAt;

                    if ($newSpecPath) {
                        $finalImagePath = $newSpecPath;
                        $newlyUploadedPaths[] = $newSpecPath;
                        $generatedVariantCount += $specVariantCount;
                        $approximateWriteCount += $specVariantCount + 1;

                        if ($currentImagePath && $currentImagePath !== $newSpecPath) {
                            $pathsToDelete[] = $currentImagePath;
                        }
                    }

                    $processedCount++;
                    $batch->update(['processed_items' => $processedCount]);
                }

                $specData['final_image_path'] = $finalImagePath;
                $updatedEspecialidades[$id] = $specData;
            }

            // 3. Process New Specialties
            foreach ($nuevasData as $newIndex => $newData) {
                $newTempPath = $newData['temp_path'] ?? null;
                $finalImagePath = null;
                $newSpecStagingDisk = $this->resolveStagingDisk($newTempPath);

                if ($newTempPath && Storage::disk($newSpecStagingDisk)->exists($newTempPath)) {
                    $newProfile = 'public_card';
                    $newVariantCount = count($imageOptimizer->profileSizes($newProfile));
                    $newStartedAt = microtime(true);
                    $newSpecBaseName = 'service-new-'.$newIndex.'-'.Str::uuid()->toString();
                    $newSpecPath = $imageOptimizer->optimizeStoredPath(
                        sourceDisk: $newSpecStagingDisk,
                        sourcePath: $newTempPath,
                        folder: 'services',
                        baseName: $newSpecBaseName,
                        profile: $newProfile,
                        storeOriginal: true
                    );
                    $webpGenerationSeconds += microtime(true) - $newStartedAt;

                    if ($newSpecPath) {
                        $finalImagePath = $newSpecPath;
                        $newlyUploadedPaths[] = $newSpecPath;
                        $generatedVariantCount += $newVariantCount;
                        $approximateWriteCount += $newVariantCount + 1;
                    }

                    $processedCount++;
                    $batch->update(['processed_items' => $processedCount]);
                }

                $newData['final_image_path'] = $finalImagePath;
                $newEspecialidades[$newIndex] = $newData;
            }

            $databaseCommitted = false;

            // 4. Atomic Database Transaction
            \App\Services\ServicesPersonalizationAsyncService::saveToDatabase(
                $settings,
                $settingsPayload,
                $settingsPayload['services.hero_image'] ?? null,
                $updatedEspecialidades,
                $newEspecialidades
            );
            $databaseCommitted = true;

            // 5. Mark batch completed & trigger cleanup
            $batch->update([
                'status' => 'completed',
                'processed_items' => $batch->total_items,
                'finished_at' => now(),
            ]);

            $this->logBatchSummary(
                batch: $batch,
                imageCount: $batch->total_items,
                variantCount: $generatedVariantCount,
                approximateWriteCount: $approximateWriteCount,
                webpGenerationSeconds: $webpGenerationSeconds,
            );

            if (! empty($pathsToDelete)) {
                CleanupReplacedServiceImagesJob::dispatch($pathsToDelete, 'services');
            }

        } catch (Throwable $exception) {
            Log::error('Fallo en procesamiento asíncrono de imágenes de servicios:', [
                'batch_uuid' => $this->batchUuid,
                'error' => $exception->getMessage(),
            ]);

            // Clean up newly created variants on R2/public storage ONLY if DB transaction did not commit
            if (! ($databaseCommitted ?? false) && ! empty($newlyUploadedPaths)) {
                try {
                    $imageOptimizer->deleteManyByStoredPaths($newlyUploadedPaths, 'services');
                } catch (Throwable) {
                    // Ignore secondary cleanup error
                }
            }

            $batch->update([
                'status' => 'failed',
                'error_message' => 'No pudimos procesar todas las imágenes. Tus imágenes anteriores se conservaron. Intenta nuevamente.',
                'finished_at' => now(),
            ]);

            $this->logBatchSummary(
                batch: $batch,
                imageCount: $processedCount,
                variantCount: $generatedVariantCount,
                approximateWriteCount: $approximateWriteCount,
                webpGenerationSeconds: $webpGenerationSeconds,
                statusOverride: 'failed',
            );

            throw $exception;
        } finally {
            $this->cleanupStaging($tempDirectory);
        }
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
            Log::warning('No se pudo limpiar staging de Services.', [
                'batch_uuid' => $this->batchUuid,
                'directory' => $directory,
                'error' => $exception->getMessage(),
            ]);
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
        Log::info('Servicios batch summary', [
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
}
