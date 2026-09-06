<?php

namespace App\Services;

use App\Http\Requests\PersonalizacionServiciosRequest;
use App\Jobs\ProcessServicesPersonalizationImages;
use App\Models\MediaProcessingBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ServicesPersonalizationAsyncService
{
    /**
     * Check if the request contains any newly uploaded image files.
     */
    public function hasNewImages(PersonalizacionServiciosRequest $request): bool
    {
        return $this->countNewImages($request) > 0;
    }

    /**
     * Count total newly uploaded image files in the request.
     */
    public function countNewImages(PersonalizacionServiciosRequest $request): int
    {
        $count = 0;
        if ($request->hasFile('services_hero_image')) {
            $count++;
        }
        foreach (($request->input('especialidades') ?? []) as $id => $payload) {
            if ($request->hasFile("especialidades.{$id}.image")) {
                $count++;
            }
        }
        foreach (($request->input('nuevas') ?? []) as $newIndex => $payload) {
            if ($request->hasFile("nuevas.{$newIndex}.image")) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Synchronously save services personalization when no new images are uploaded.
     * Does NOT create a MediaProcessingBatch, dispatch Jobs, or require queue worker.
     */
    public function saveDirectly(
        PersonalizacionServiciosRequest $request,
        SiteSettingsService $settings
    ): void {
        $data = $request->validated();

        $textSettings = [
            'services.title' => $data['services_title'] ?? null,
            'services.subtitle' => $data['services_subtitle'] ?? null,
            'services.cta_text' => $data['services_cta_text'] ?? null,
        ];

        $currentHeroImage = $data['services_hero_image_path'] ?? $settings->get('services.hero_image');

        $especialidadesData = [];
        foreach (($data['especialidades'] ?? []) as $id => $payload) {
            $currentImagePath = $payload['image_path'] ?? $settings->get("services.specialty_image.{$id}");
            $especialidadesData[$id] = [
                'nombre' => $payload['nombre'] ?? null,
                'descripcion' => $payload['descripcion'] ?? null,
                'icono' => $payload['icono'] ?? null,
                'activo' => ! empty($payload['activo']),
                'orden' => $payload['orden'] ?? null,
                'final_image_path' => $currentImagePath,
            ];
        }

        $nuevasData = [];
        foreach (($data['nuevas'] ?? []) as $newIndex => $payload) {
            $nombre = isset($payload['nombre']) ? trim((string) $payload['nombre']) : '';
            $descripcion = isset($payload['descripcion']) ? trim((string) $payload['descripcion']) : '';
            $icono = $payload['icono'] ?? null;
            $icono = is_string($icono) ? trim($icono) : $icono;

            if ($nombre === '') {
                continue;
            }

            $nuevasData[$newIndex] = [
                'nombre' => $nombre,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'icono' => $icono !== '' ? $icono : null,
                'activo' => ! empty($payload['activo']),
                'orden' => (int) ($payload['orden'] ?? 0),
                'final_image_path' => null,
            ];
        }

        self::saveToDatabase(
            $settings,
            $textSettings,
            $currentHeroImage,
            $especialidadesData,
            $nuevasData
        );

        $settings->forgetCache();
    }

    /**
     * Execute atomic DB save of non-image and image settings for services.
     * Shared by synchronous save and async job execution.
     */
    public static function saveToDatabase(
        SiteSettingsService $settings,
        array $textSettings,
        ?string $currentHeroImage,
        array $updatedEspecialidades,
        array $newEspecialidades
    ): void {
        $meta = [
            'services.title' => ['section' => 'services', 'type' => 'text'],
            'services.subtitle' => ['section' => 'services', 'type' => 'text'],
            'services.cta_text' => ['section' => 'services', 'type' => 'text'],
            'services.hero_image' => ['section' => 'services', 'type' => 'image'],
        ];

        $settingsPayload = $textSettings;
        $settingsPayload['services.hero_image'] = $currentHeroImage;

        DB::transaction(function () use (
            $settings,
            &$settingsPayload,
            &$meta,
            $updatedEspecialidades,
            $newEspecialidades
        ): void {
            foreach ($updatedEspecialidades as $id => $specData) {
                $especialidad = \App\Models\Especialidad::query()->find($id);
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

                $especialidad = \App\Models\Especialidad::query()->create([
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
    }

    public function createAndDispatchBatch(
        PersonalizacionServiciosRequest $request,
        User $user,
        SiteSettingsService $settings
    ): MediaProcessingBatch {
        if (! $this->hasNewImages($request)) {
            throw new \InvalidArgumentException('No hay imágenes nuevas para procesar en lote.');
        }

        $data = $request->validated();
        $batchUuid = Str::uuid()->toString();
        $tempDir = "media-processing/services/{$batchUuid}";

        $imageCount = 0;
        $heroTempPath = null;
        $heroImageFile = $request->file('services_hero_image');

        if ($heroImageFile) {
            $ext = strtolower((string) ($heroImageFile->getClientOriginalExtension() ?: 'tmp'));
            $heroTempPath = $this->stageUpload($tempDir, $heroImageFile, "hero-{$batchUuid}.{$ext}");
            $imageCount++;
        }

        $especialidadesPayload = [];
        foreach (($data['especialidades'] ?? []) as $id => $payload) {
            $specTempPath = null;
            $specImageFile = $request->file("especialidades.{$id}.image");

            if ($specImageFile) {
                $ext = strtolower((string) ($specImageFile->getClientOriginalExtension() ?: 'tmp'));
                $specTempPath = $this->stageUpload($tempDir, $specImageFile, "spec-{$id}-{$batchUuid}.{$ext}");
                $imageCount++;
            }

            $currentImagePath = $payload['image_path'] ?? $settings->get("services.specialty_image.{$id}");

            $especialidadesPayload[$id] = [
                'nombre' => $payload['nombre'] ?? null,
                'descripcion' => $payload['descripcion'] ?? null,
                'icono' => $payload['icono'] ?? null,
                'activo' => $payload['activo'] ?? null,
                'orden' => $payload['orden'] ?? null,
                'current_path' => $currentImagePath,
                'temp_path' => $specTempPath,
            ];
        }

        $nuevasPayload = [];
        foreach (($data['nuevas'] ?? []) as $newIndex => $payload) {
            $nombre = isset($payload['nombre']) ? trim((string) $payload['nombre']) : '';
            $descripcion = isset($payload['descripcion']) ? trim((string) $payload['descripcion']) : '';
            $icono = $payload['icono'] ?? null;
            $icono = is_string($icono) ? trim($icono) : $icono;

            if ($nombre === '' && $descripcion === '' && ($icono === null || $icono === '')) {
                continue;
            }

            if ($nombre === '') {
                continue;
            }

            $newTempPath = null;
            $newImageFile = $request->file("nuevas.{$newIndex}.image");

            if ($newImageFile) {
                $ext = strtolower((string) ($newImageFile->getClientOriginalExtension() ?: 'tmp'));
                $newTempPath = $this->stageUpload($tempDir, $newImageFile, "new-{$newIndex}-{$batchUuid}.{$ext}");
                $imageCount++;
            }

            $nuevasPayload[$newIndex] = [
                'nombre' => $nombre,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'icono' => $icono !== '' ? $icono : null,
                'activo' => ! empty($payload['activo']),
                'orden' => (int) ($payload['orden'] ?? 0),
                'temp_path' => $newTempPath,
            ];
        }

        $payload = [
            'temp_directory' => $tempDir,
            'text_settings' => [
                'services.title' => $data['services_title'] ?? null,
                'services.subtitle' => $data['services_subtitle'] ?? null,
                'services.cta_text' => $data['services_cta_text'] ?? null,
            ],
            'hero_image_temp_path' => $heroTempPath,
            'hero_image_current_path' => $data['services_hero_image_path'] ?? $settings->get('services.hero_image'),
            'especialidades' => $especialidadesPayload,
            'nuevas' => $nuevasPayload,
        ];

        $batch = null;
        try {
            /** @var MediaProcessingBatch $batch */
            $batch = DB::transaction(function () use ($batchUuid, $user, $imageCount, $payload) {
                return MediaProcessingBatch::query()->create([
                    'uuid' => $batchUuid,
                    'user_id' => $user->id,
                    'type' => 'services',
                    'status' => 'pending',
                    'total_items' => $imageCount,
                    'processed_items' => 0,
                    'payload' => $payload,
                ]);
            });

            ProcessServicesPersonalizationImages::dispatch($batchUuid);

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

    private function stagingDisk(): string
    {
        return config('private_documents.disk', 'local') === 'r2_private' ? 'r2_private' : 'local';
    }

    private function stageUpload(string $directory, object $file, string $filename): string
    {
        $disk = $this->stagingDisk();
        try {
            $path = Storage::disk($disk)->putFileAs($directory, $file, $filename);
            if (! is_string($path) || $path === '') {
                throw new \RuntimeException('No se pudo guardar el staging de Services.');
            }

            return $path;
        } catch (Throwable $exception) {
            $this->cleanupStaging($directory);
            throw $exception;
        }
    }

    private function cleanupStaging(string $directory): void
    {
        $disk = $this->stagingDisk();
        try {
            Storage::disk($disk)->deleteDirectory($directory);
        } catch (Throwable $exception) {
            Log::warning('No se pudo limpiar staging de Services.', [
                'directory' => $directory,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
