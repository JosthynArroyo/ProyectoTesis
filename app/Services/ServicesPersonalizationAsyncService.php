<?php

namespace App\Services;

use App\Http\Requests\PersonalizacionServiciosRequest;
use App\Jobs\ProcessServicesPersonalizationImages;
use App\Models\MediaProcessingBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServicesPersonalizationAsyncService
{
    public function createAndDispatchBatch(
        PersonalizacionServiciosRequest $request,
        User $user,
        SiteSettingsService $settings
    ): MediaProcessingBatch {
        $data = $request->validated();
        $batchUuid = Str::uuid()->toString();
        $tempDir = "private/media-processing/services/{$batchUuid}";

        Storage::disk('local')->makeDirectory($tempDir);

        $imageCount = 0;
        $heroTempPath = null;
        $heroImageFile = $request->file('services_hero_image');

        if ($heroImageFile) {
            $ext = strtolower((string) ($heroImageFile->getClientOriginalExtension() ?: 'tmp'));
            $heroTempPath = "{$tempDir}/hero-{$batchUuid}.{$ext}";
            Storage::disk('local')->putFileAs($tempDir, $heroImageFile, "hero-{$batchUuid}.{$ext}");
            $imageCount++;
        }

        $especialidadesPayload = [];
        foreach (($data['especialidades'] ?? []) as $id => $payload) {
            $specTempPath = null;
            $specImageFile = $request->file("especialidades.{$id}.image");

            if ($specImageFile) {
                $ext = strtolower((string) ($specImageFile->getClientOriginalExtension() ?: 'tmp'));
                $specTempPath = "{$tempDir}/spec-{$id}-{$batchUuid}.{$ext}";
                Storage::disk('local')->putFileAs($tempDir, $specImageFile, "spec-{$id}-{$batchUuid}.{$ext}");
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
                $newTempPath = "{$tempDir}/new-{$newIndex}-{$batchUuid}.{$ext}";
                Storage::disk('local')->putFileAs($tempDir, $newImageFile, "new-{$newIndex}-{$batchUuid}.{$ext}");
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
    }
}
