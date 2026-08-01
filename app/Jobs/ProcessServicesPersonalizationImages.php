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
        $this->onConnection(config('queue.media_connection', env('MEDIA_QUEUE_CONNECTION', 'database')));
        $this->onQueue(env('MEDIA_QUEUE', 'media'));
    }

    public function handle(ImageOptimizer $imageOptimizer, SiteSettingsService $settings): void
    {
        $batch = MediaProcessingBatch::query()->where('uuid', $this->batchUuid)->first();

        if (! $batch || $batch->isCompleted()) {
            return;
        }

        $batch->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $payload = $batch->payload ?? [];
        $tempDirectory = $payload['temp_directory'] ?? null;
        $newlyUploadedPaths = [];
        $pathsToDelete = [];
        $processedCount = 0;
        $generatedVariantCount = 0;
        $approximateWriteCount = 0;
        $webpGenerationSeconds = 0.0;

        try {
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

            if ($heroTempPath && Storage::disk('local')->exists($heroTempPath)) {
                $absPath = Storage::disk('local')->path($heroTempPath);
                $heroProfile = 'public_hero';
                $heroVariantCount = count($imageOptimizer->profileSizes($heroProfile));
                $heroStartedAt = microtime(true);
                // Use UUID baseName so each upload creates a unique R2 key.
                // This prevents any stale-path guard from treating a legitimate
                // R2 path (images/services/medium/services-hero.webp) as the
                // un-uploaded default and falling back to the local static file.
                $heroBaseName = 'services-hero-'.Str::uuid()->toString();
                $newHeroPath = $imageOptimizer->optimizeAbsolutePath(
                    absolutePath: $absPath,
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

                if ($specTempPath && Storage::disk('local')->exists($specTempPath)) {
                    $absPath = Storage::disk('local')->path($specTempPath);
                    $specProfile = 'public_card';
                    $specVariantCount = count($imageOptimizer->profileSizes($specProfile));
                    $specStartedAt = microtime(true);
                    $specBaseName = 'service-'.$id.'-'.Str::uuid()->toString();
                    $newSpecPath = $imageOptimizer->optimizeAbsolutePath(
                        absolutePath: $absPath,
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

                if ($newTempPath && Storage::disk('local')->exists($newTempPath)) {
                    $absPath = Storage::disk('local')->path($newTempPath);
                    $newProfile = 'public_card';
                    $newVariantCount = count($imageOptimizer->profileSizes($newProfile));
                    $newStartedAt = microtime(true);
                    $newSpecBaseName = 'service-new-'.$newIndex.'-'.Str::uuid()->toString();
                    $newSpecPath = $imageOptimizer->optimizeAbsolutePath(
                        absolutePath: $absPath,
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

            // 4. Atomic Database Transaction
            DB::transaction(function () use (
                $settings,
                &$settingsPayload,
                &$meta,
                $updatedEspecialidades,
                $newEspecialidades
            ): void {
                foreach ($updatedEspecialidades as $id => $specData) {
                    $especialidad = Especialidad::query()->find($id);
                    if (! $especialidad) {
                        continue;
                    }

                    $nombre = isset($specData['nombre']) ? trim((string) $specData['nombre']) : '';
                    if ($nombre !== '') {
                        $especialidad->nombre = $nombre;
                    }

                    $icono = $specData['icono'] ?? null;
                    $icono = is_string($icono) ? trim($icono) : $icono;

                    $especialidad->descripcion = $specData['descripcion'] ?? null;
                    $especialidad->icono = $icono === '' ? null : $icono;
                    $especialidad->activo = ! empty($specData['activo']);
                    $especialidad->orden = (int) ($specData['orden'] ?? $especialidad->orden);
                    $especialidad->save();

                    $imageKey = "services.specialty_image.{$especialidad->id}";
                    $settingsPayload[$imageKey] = $specData['final_image_path'];
                    $meta[$imageKey] = ['section' => 'services', 'type' => 'image'];
                }

                foreach ($newEspecialidades as $newData) {
                    $nombre = isset($newData['nombre']) ? trim((string) $newData['nombre']) : '';
                    if ($nombre === '') {
                        continue;
                    }

                    $icono = $newData['icono'] ?? null;
                    $icono = is_string($icono) ? trim($icono) : $icono;

                    $especialidad = Especialidad::query()->create([
                        'nombre' => $nombre,
                        'descripcion' => $newData['descripcion'] ?? null,
                        'icono' => $icono === '' ? null : $icono,
                        'activo' => ! empty($newData['activo']),
                        'orden' => (int) ($newData['orden'] ?? 0),
                    ]);

                    $imageKey = "services.specialty_image.{$especialidad->id}";
                    $settingsPayload[$imageKey] = $newData['final_image_path'];
                    $meta[$imageKey] = ['section' => 'services', 'type' => 'image'];
                }

                $settings->setMany($settingsPayload, $meta);
            });

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

            // Cleanup local temp directory
            if ($tempDirectory && Storage::disk('local')->exists($tempDirectory)) {
                Storage::disk('local')->deleteDirectory($tempDirectory);
            }
        } catch (Throwable $exception) {
            Log::error('Fallo en procesamiento asíncrono de imágenes de servicios:', [
                'batch_uuid' => $this->batchUuid,
                'error' => $exception->getMessage(),
            ]);

            // Clean up newly created variants on R2/public storage
            if (! empty($newlyUploadedPaths)) {
                try {
                    $imageOptimizer->deleteManyByStoredPaths($newlyUploadedPaths, 'services');
                } catch (Throwable) {
                    // Ignore secondary cleanup error
                }
            }

            // Clean up local temp files
            if ($tempDirectory && Storage::disk('local')->exists($tempDirectory)) {
                try {
                    Storage::disk('local')->deleteDirectory($tempDirectory);
                } catch (Throwable) {
                    //
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
