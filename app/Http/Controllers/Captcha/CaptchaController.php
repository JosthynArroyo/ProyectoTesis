<?php

namespace App\Http\Controllers\Captcha;

use App\Http\Controllers\Controller;
use App\Models\CaptchaChallenge;
use App\Models\CaptchaImage;
use App\Services\VisionClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class CaptchaController extends Controller
{
    public function challenge(Request $request): JsonResponse
    {
        $this->ensureCaptchaImagesLoaded();

        $classKeys = $this->classKeys();
        $labelsEs = $this->labelsEs();

        if (count($classKeys) < 4) {
            return response()->json([
                'message' => 'Configuracion de clases CAPTCHA insuficiente.',
            ], 500);
        }

        $classCounts = CaptchaImage::query()
            ->whereIn('class_key', $classKeys)
            ->where('dataset_split', 'public')
            ->selectRaw('class_key, COUNT(*) as total')
            ->groupBy('class_key')
            ->pluck('total', 'class_key');

        foreach ($classKeys as $classKey) {
            if ((int) ($classCounts[$classKey] ?? 0) < 1) {
                return response()->json([
                    'message' => "No hay imagenes disponibles para la clase: {$classKey}",
                ], 500);
            }
        }

        $targetKey = Arr::random($classKeys);
        $correctImage = CaptchaImage::query()
            ->where('class_key', $targetKey)
            ->where('dataset_split', 'public')
            ->inRandomOrder()
            ->first();

        if (!$correctImage) {
            return response()->json([
                'message' => 'No se pudo generar el reto CAPTCHA.',
            ], 500);
        }

        $distractorClasses = Arr::random(array_values(array_diff($classKeys, [$targetKey])), 3);
        $optionImages = collect([$correctImage]);

        foreach ($distractorClasses as $classKey) {
            $distractorImage = CaptchaImage::query()
                ->where('class_key', $classKey)
                ->where('dataset_split', 'public')
                ->inRandomOrder()
                ->first();

            if (!$distractorImage) {
                return response()->json([
                    'message' => 'No se pudieron obtener distractores para el reto.',
                ], 500);
            }

            $optionImages->push($distractorImage);
        }

        /** @var \Illuminate\Support\Collection<int, \App\Models\CaptchaImage> $optionImages */
        $optionImages = $optionImages->shuffle()->values();

        $challenge = CaptchaChallenge::query()->create([
            'target_key' => $targetKey,
            'option_image_ids' => $optionImages->pluck('id')->all(),
            'attempts' => 0,
            'max_attempts' => (int) config('captcha.max_attempts', 5),
            'expires_at' => now()->addSeconds((int) config('captcha.expires_seconds', 180)),
        ]);

        $request->session()->put('captcha_verified', false);

        return response()->json([
            'challenge_id' => $challenge->id,
            'target_key' => $targetKey,
            'target_label_es' => $labelsEs[$targetKey] ?? $targetKey,
            'images' => $this->serializeImages($optionImages),
        ]);
    }

    public function verify(Request $request, VisionClient $visionClient): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'integer', 'exists:captcha_challenges,id'],
            'selected_image_id' => ['required', 'integer', 'exists:captcha_images,id'],
        ]);

        $challenge = CaptchaChallenge::query()->find($data['challenge_id']);
        if (!$challenge) {
            return response()->json([
                'ok' => false,
                'message' => 'Reto CAPTCHA no encontrado.',
            ], 404);
        }

        if ($challenge->verified_at) {
            $request->session()->put('captcha_verified', true);

            return response()->json([
                'ok' => true,
                'verified' => true,
            ]);
        }

        if ($challenge->expires_at && $challenge->expires_at->isPast()) {
            return response()->json([
                'ok' => false,
                'message' => 'El reto CAPTCHA ha expirado.',
            ], 422);
        }

        if ($challenge->attempts >= $challenge->max_attempts) {
            return response()->json([
                'ok' => false,
                'message' => 'Se alcanzaron los intentos maximos del CAPTCHA.',
            ], 429);
        }

        $selectedImageId = (int) $data['selected_image_id'];
        $optionImageIds = collect($challenge->option_image_ids)
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();

        if (!in_array($selectedImageId, $optionImageIds, true)) {
            $challenge->increment('attempts');
            $challenge->refresh();

            return response()->json([
                'ok' => false,
                'verified' => false,
                'message' => 'La imagen seleccionada no pertenece al reto actual.',
                'attempts' => $challenge->attempts,
                'remaining_attempts' => max(0, $challenge->max_attempts - $challenge->attempts),
            ], 422);
        }

        $selectedImage = CaptchaImage::query()->find($selectedImageId);
        if (!$selectedImage) {
            $challenge->increment('attempts');
            $challenge->refresh();

            return response()->json([
                'ok' => false,
                'verified' => false,
                'message' => 'No se encontro la imagen seleccionada.',
                'attempts' => $challenge->attempts,
                'remaining_attempts' => max(0, $challenge->max_attempts - $challenge->attempts),
            ], 422);
        }

        try {
            $prediction = $visionClient->classify($selectedImage->fullPath());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo validar el CAPTCHA con el servicio de vision.',
            ], 502);
        }

        $predictedLabel = (string) ($prediction['label'] ?? '');
        $confidence = (float) ($prediction['confidence'] ?? 0.0);
        $threshold = (float) config('captcha.confidence_threshold', 0.75);

        $isCorrect = $predictedLabel === $challenge->target_key
            && $confidence >= $threshold;

        if ($isCorrect) {
            $challenge->update([
                'verified_at' => now(),
            ]);

            $request->session()->put('captcha_verified', true);

            return response()->json([
                'ok' => true,
                'verified' => true,
                'label' => $predictedLabel,
                'confidence' => $confidence,
            ]);
        }

        $challenge->increment('attempts');
        $challenge->refresh();

        return response()->json([
            'ok' => false,
            'verified' => false,
            'label' => $predictedLabel,
            'confidence' => $confidence,
            'message' => 'CAPTCHA incorrecto.',
            'attempts' => $challenge->attempts,
            'remaining_attempts' => max(0, $challenge->max_attempts - $challenge->attempts),
        ], 422);
    }

    public function image(CaptchaImage $image): BinaryFileResponse
    {
        $fullPath = $image->fullPath();
        abort_unless(is_file($fullPath), 404);

        return response()->file($fullPath, [
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    private function serializeImages(Collection $images): array
    {
        return $images->map(static function (CaptchaImage $image): array {
            return [
                'id' => $image->id,
                'url' => route('captcha.image.show', ['image' => $image->id], false),
            ];
        })->values()->all();
    }

    private function classKeys(): array
    {
        $classKeys = config('captcha.classes', []);
        return is_array($classKeys) ? array_values($classKeys) : [];
    }

    private function labelsEs(): array
    {
        $labels = config('captcha.labels_es', []);
        return is_array($labels) ? $labels : [];
    }

    private function ensureCaptchaImagesLoaded(): void
    {
        $classKeys = $this->classKeys();
        if ($classKeys === []) {
            return;
        }

        $rows = [];
        $now = now();

        foreach ($classKeys as $classKey) {
            $dirPath = public_path("captcha_animals/{$classKey}");
            if (!is_dir($dirPath)) {
                continue;
            }

            foreach (File::files($dirPath) as $file) {
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }

                $rows[] = [
                    'class_key' => $classKey,
                    'dataset_split' => 'public',
                    'image_path' => "captcha_animals/{$classKey}/{$file->getFilename()}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            CaptchaImage::query()->upsert(
                $rows,
                ['image_path'],
                ['class_key', 'dataset_split', 'updated_at']
            );
        }
    }
}
