<?php

namespace App\Http\Controllers\Captcha;

use App\Http\Controllers\Controller;
use App\Models\CaptchaChallenge;
use App\Models\CaptchaImage;
use App\Services\CaptchaImageSynchronizer;
use App\Support\ChatbotSessionKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CaptchaController extends Controller
{
    public function __construct(
        private readonly CaptchaImageSynchronizer $synchronizer,
    ) {
    }

    public function challenge(Request $request): JsonResponse
    {
        $sessionId = $request->session()->getId() ?: $request->ip();
        $lock = Cache::lock("captcha_lock:{$sessionId}", 5);

        try {
            return $lock->block(5, function () use ($request) {
                $this->resetCaptchaSession($request);
                $this->synchronizer->ensureSynchronized();

                $classKeys = $this->classKeys();
                $labelsEs = $this->labelsEs();

                return $this->buildChallengeResponse($request, $classKeys, $labelsEs);
            });
        } catch (\Throwable $e) {
            Log::error('Error al generar el CAPTCHA: ' . $e->getMessage(), [
                'exception' => $e::class,
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:40'],
            'position' => ['required', 'integer', 'between:0,3'],
        ]);

        $sessionToken = $request->session()->get(ChatbotSessionKeys::SESSION_CHALLENGE_TOKEN);
        $challengeId = $request->session()->get(ChatbotSessionKeys::SESSION_CHALLENGE_ID);

        if (! $sessionToken || ! $challengeId || $sessionToken !== $data['token']) {
            return response()->json([
                'ok' => false,
                'message' => 'El token del CAPTCHA no corresponde con la sesion activa.',
            ], 403);
        }

        $challenge = CaptchaChallenge::query()->find($challengeId);
        if (! $challenge) {
            return response()->json([
                'ok' => false,
                'message' => 'Reto CAPTCHA no encontrado.',
            ], 404);
        }

        if ($challenge->verified_at) {
            return response()->json([
                'ok' => false,
                'message' => 'Este reto CAPTCHA ya fue utilizado.',
            ], 422);
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

        $optionImageIds = collect($challenge->option_image_ids)
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();

        $position = (int) $data['position'];
        if (! isset($optionImageIds[$position])) {
            return response()->json([
                'ok' => false,
                'message' => 'La posicion seleccionada no es valida.',
            ], 422);
        }

        $selectedImageId = (int) $optionImageIds[$position];
        $selectedImage = CaptchaImage::query()->find($selectedImageId);

        if (! $selectedImage) {
            $challenge->increment('attempts');
            $challenge->update(['expires_at' => now()]);

            return response()->json([
                'ok' => false,
                'verified' => false,
                'message' => 'No se encontro la imagen seleccionada.',
                'attempts' => $challenge->attempts,
                'remaining_attempts' => max(0, $challenge->max_attempts - $challenge->attempts),
                'challenge' => $this->buildNextChallengePayload($request, $challenge),
            ], 422);
        }

        if ($selectedImage->class_key === $challenge->target_key) {
            $challenge->update([
                'verified_at' => now(),
                'expires_at' => now(),
            ]);

            $request->session()->put(ChatbotSessionKeys::SESSION_VERIFIED, true);
            $request->session()->put(ChatbotSessionKeys::SESSION_VERIFIED_AT, now()->timestamp);
            $request->session()->forget([
                ChatbotSessionKeys::SESSION_CHALLENGE_ID,
                ChatbotSessionKeys::SESSION_CHALLENGE_TOKEN,
            ]);

            return response()->json([
                'ok' => true,
                'verified' => true,
            ]);
        }

        $challenge->increment('attempts');
        $challenge->update(['expires_at' => now()]);

        $nextChallenge = $this->buildNextChallengePayload($request, $challenge);

        return response()->json([
            'ok' => false,
            'verified' => false,
            'message' => 'Seleccion incorrecta. Se genero un nuevo reto.',
            'attempts' => $challenge->attempts,
            'remaining_attempts' => max(0, $challenge->max_attempts - $challenge->attempts),
            'challenge' => $nextChallenge,
        ], 422);
    }

    public function image(Request $request, string $token, int $position): BinaryFileResponse
    {
        $sessionToken = $request->session()->get(ChatbotSessionKeys::SESSION_CHALLENGE_TOKEN);
        $challengeId = $request->session()->get(ChatbotSessionKeys::SESSION_CHALLENGE_ID);

        abort_unless($sessionToken && $sessionToken === $token, 403);
        abort_unless($position >= 0 && $position <= 3, 404);

        $challenge = CaptchaChallenge::query()->find($challengeId);
        abort_unless($challenge, 404);

        $optionImageIds = $challenge->option_image_ids;
        abort_unless(isset($optionImageIds[$position]), 404);

        $image = CaptchaImage::query()->find($optionImageIds[$position]);
        abort_unless($image, 404);

        $filename = basename((string) $image->image_path);
        $fullPath = base_path('ai/dataset/val/' . $image->class_key . '/' . $filename);
        abort_unless(is_file($fullPath), 404);

        $contentType = mime_content_type($fullPath) ?: 'application/octet-stream';

        return response()->file($fullPath, [
            'Cache-Control' => 'public, max-age=300',
            'Content-Type' => $contentType,
        ])->setContentDisposition('inline', 'captcha.jpg');
    }

    private function buildChallengeResponse(Request $request, array $classKeys, array $labelsEs, array $excludeTargetKeys = [], array $excludeImageIds = []): JsonResponse
    {
        $payload = $this->createChallengePayload($request, $classKeys, $labelsEs, $excludeTargetKeys, $excludeImageIds);

        return response()->json($payload);
    }

    private function buildNextChallengePayload(Request $request, CaptchaChallenge $challenge): array
    {
        $classKeys = $this->classKeys();
        $labelsEs = $this->labelsEs();
        $excludeImageIds = collect($challenge->option_image_ids)
            ->map(static fn ($id) => (int) $id)
            ->all();

        return $this->createChallengePayload(
            $request,
            $classKeys,
            $labelsEs,
            [$challenge->target_key],
            $excludeImageIds,
        );
    }

    private function createChallengePayload(Request $request, array $classKeys, array $labelsEs, array $excludeTargetKeys = [], array $excludeImageIds = []): array
    {
        if (count($classKeys) < 4) {
            throw new \RuntimeException('Se requieren al menos cuatro categorias CAPTCHA.');
        }

        $availableTargetKeys = array_values(array_diff($classKeys, $excludeTargetKeys));
        if ($availableTargetKeys === []) {
            $availableTargetKeys = $classKeys;
        }

        $targetKey = Arr::random($availableTargetKeys);
        $correctImage = $this->pickImageForClass($targetKey, $excludeImageIds);

        if (! $correctImage) {
            throw new \RuntimeException("No se pudo obtener una imagen valida para la categoria {$targetKey}.");
        }

        $availableDistractorClasses = array_values(array_diff($classKeys, [$targetKey]));
        if (count($availableDistractorClasses) < 3) {
            throw new \RuntimeException('No hay suficientes categorias para construir el reto CAPTCHA.');
        }

        $distractorClasses = Arr::random($availableDistractorClasses, 3);
        $optionImages = collect([$correctImage]);

        foreach ($distractorClasses as $classKey) {
            $excludedForClass = array_merge(
                $excludeImageIds,
                $optionImages->pluck('id')->map(static fn ($id) => (int) $id)->all(),
            );

            $distractorImage = $this->pickImageForClass($classKey, $excludedForClass);
            if (! $distractorImage) {
                throw new \RuntimeException("No se pudo obtener una imagen distractora valida para la categoria {$classKey}.");
            }

            $optionImages->push($distractorImage);
        }

        $optionImages = $optionImages->shuffle()->values();

        $challenge = CaptchaChallenge::query()->create([
            'target_key' => $targetKey,
            'option_image_ids' => $optionImages->pluck('id')->map(static fn ($id) => (int) $id)->all(),
            'attempts' => 0,
            'max_attempts' => (int) config('captcha.max_attempts', 5),
            'expires_at' => now()->addSeconds((int) config('captcha.expires_seconds', 180)),
        ]);

        $token = Str::random(40);
        $request->session()->put(ChatbotSessionKeys::SESSION_CHALLENGE_ID, $challenge->id);
        $request->session()->put(ChatbotSessionKeys::SESSION_CHALLENGE_TOKEN, $token);
        $request->session()->put(ChatbotSessionKeys::SESSION_VERIFIED, false);

        return [
            'challenge_id' => $challenge->id,
            'token' => $token,
            'target_label_es' => $labelsEs[$targetKey] ?? $targetKey,
            'images' => $this->serializeImages($token, $optionImages),
        ];
    }

    private function serializeImages(string $token, Collection $images): array
    {
        return $images->values()->map(static function (CaptchaImage $image, int $position) use ($token): array {
            return [
                'position' => $position,
                'url' => route('captcha.image.show', ['token' => $token, 'position' => $position], false),
            ];
        })->all();
    }

    private function pickImageForClass(string $classKey, array $excludeImageIds = []): ?CaptchaImage
    {
        $query = CaptchaImage::query()
            ->where('class_key', $classKey)
            ->where('dataset_split', 'public');

        $normalizedExclude = collect($excludeImageIds)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($normalizedExclude !== []) {
            $candidate = (clone $query)
                ->whereNotIn('id', $normalizedExclude)
                ->inRandomOrder()
                ->first();

            if ($candidate) {
                return $candidate;
            }
        }

        return $query->inRandomOrder()->first();
    }

    private function resetCaptchaSession(Request $request): void
    {
        $request->session()->forget([
            ChatbotSessionKeys::SESSION_VERIFIED,
            ChatbotSessionKeys::SESSION_VERIFIED_AT,
            ChatbotSessionKeys::SESSION_CHALLENGE_ID,
            ChatbotSessionKeys::SESSION_CHALLENGE_TOKEN,
        ]);
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
}
