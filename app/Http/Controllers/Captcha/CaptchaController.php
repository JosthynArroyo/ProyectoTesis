<?php

namespace App\Http\Controllers\Captcha;

use App\Http\Controllers\Controller;
use App\Models\CaptchaChallenge;
use App\Models\CaptchaImage;
use App\Support\ChatbotSessionKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CaptchaController extends Controller
{
    public function challenge(Request $request): JsonResponse
    {
        $sessionId = $request->session()->getId() ?: $request->ip();
        $lock = Cache::lock("captcha_lock:{$sessionId}", 5);

        try {
            return $lock->block(5, function () use ($request) {
                // 1. Clean previous challenge data from session
                $request->session()->forget([
                    ChatbotSessionKeys::SESSION_VERIFIED,
                    ChatbotSessionKeys::SESSION_VERIFIED_AT,
                    ChatbotSessionKeys::SESSION_CHALLENGE_ID,
                    ChatbotSessionKeys::SESSION_CHALLENGE_TOKEN,
                ]);

                $classKeys = $this->classKeys();
                $labelsEs = $this->labelsEs();

                if (count($classKeys) < 4) {
                    Log::error('Sistema CAPTCHA: Configuración de clases insuficiente en config/captcha.php.');
                    return response()->json([
                        'message' => 'La verificación no está disponible temporalmente',
                    ], 503);
                }

                // 2. Validate DB population (E.g. at least 1 image per class)
                $classCounts = CaptchaImage::query()
                    ->whereIn('class_key', $classKeys)
                    ->where('dataset_split', 'public')
                    ->selectRaw('class_key, COUNT(*) as total')
                    ->groupBy('class_key')
                    ->pluck('total', 'class_key');

                $missingClasses = [];
                foreach ($classKeys as $classKey) {
                    if ((int) ($classCounts[$classKey] ?? 0) < 1) {
                        $missingClasses[] = $classKey;
                    }
                }

                if (count($missingClasses) > 0) {
                    Log::warning('Sistema CAPTCHA: Faltan imágenes cargadas en base de datos para las clases: ' . implode(', ', $missingClasses) . '. Ejecute php artisan captcha:sync.');
                    return response()->json([
                        'message' => 'La verificación no está disponible temporalmente',
                    ], 503);
                }

                // 3. Generate challenge details
                $targetKey = Arr::random($classKeys);
                $correctImage = CaptchaImage::query()
                    ->where('class_key', $targetKey)
                    ->where('dataset_split', 'public')
                    ->inRandomOrder()
                    ->first();

                if (! $correctImage) {
                    Log::error("Sistema CAPTCHA: No se encontró imagen para la clase objetivo {$targetKey}");
                    return response()->json([
                        'message' => 'La verificación no está disponible temporalmente',
                    ], 503);
                }

                $distractorClasses = Arr::random(array_values(array_diff($classKeys, [$targetKey])), 3);
                $optionImages = collect([$correctImage]);

                foreach ($distractorClasses as $classKey) {
                    $distractorImage = CaptchaImage::query()
                        ->where('class_key', $classKey)
                        ->where('dataset_split', 'public')
                        ->inRandomOrder()
                        ->first();

                    if (! $distractorImage) {
                        Log::error("Sistema CAPTCHA: No se encontró imagen distractor para la clase {$classKey}");
                        return response()->json([
                            'message' => 'La verificación no está disponible temporalmente',
                        ], 503);
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

                // 4. Save new challenge state into the session
                $token = Str::random(40);
                $request->session()->put(ChatbotSessionKeys::SESSION_CHALLENGE_ID, $challenge->id);
                $request->session()->put(ChatbotSessionKeys::SESSION_CHALLENGE_TOKEN, $token);
                $request->session()->put(ChatbotSessionKeys::SESSION_VERIFIED, false);

                // Build position-based serialized urls
                $imagesUrls = [];
                for ($i = 0; $i < 4; $i++) {
                    $imagesUrls[] = [
                        'position' => $i,
                        'url' => route('captcha.image.show', ['token' => $token, 'position' => $i]),
                    ];
                }

                return response()->json([
                    'token' => $token,
                    'target_label_es' => $labelsEs[$targetKey] ?? $targetKey,
                    'images' => $imagesUrls,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Error al generar el CAPTCHA: ' . $e->getMessage());
            return response()->json([
                'message' => 'La verificación no está disponible temporalmente',
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

        if (!$sessionToken || !$challengeId || $sessionToken !== $data['token']) {
            return response()->json([
                'ok' => false,
                'message' => 'El token del CAPTCHA no corresponde con la sesión activa.',
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
                'message' => 'Se alcanzaron los intentos máximos del CAPTCHA.',
            ], 429);
        }

        $optionImageIds = $challenge->option_image_ids;
        $position = (int) $data['position'];

        if (!isset($optionImageIds[$position])) {
            return response()->json([
                'ok' => false,
                'message' => 'La posición seleccionada no es válida.',
            ], 422);
        }

        $selectedImageId = (int) $optionImageIds[$position];
        $selectedImage = CaptchaImage::query()->find($selectedImageId);

        if (! $selectedImage) {
            $challenge->increment('attempts');
            return response()->json([
                'ok' => false,
                'verified' => false,
                'message' => 'No se encontró la imagen seleccionada.',
                'attempts' => $challenge->attempts,
                'remaining_attempts' => max(0, $challenge->max_attempts - $challenge->attempts),
            ], 422);
        }

        if ($selectedImage->class_key === $challenge->target_key) {
            $challenge->update([
                'verified_at' => now(),
            ]);

            $request->session()->put(ChatbotSessionKeys::SESSION_VERIFIED, true);
            $request->session()->put(ChatbotSessionKeys::SESSION_VERIFIED_AT, now()->timestamp);

            return response()->json([
                'ok' => true,
                'verified' => true,
            ]);
        }

        $challenge->increment('attempts');

        // Check if exceeded max attempts after incrementing
        if ($challenge->attempts >= $challenge->max_attempts) {
            // Invalidate session keys to force new challenge
            $request->session()->forget([
                ChatbotSessionKeys::SESSION_VERIFIED,
                ChatbotSessionKeys::SESSION_VERIFIED_AT,
                ChatbotSessionKeys::SESSION_CHALLENGE_ID,
                ChatbotSessionKeys::SESSION_CHALLENGE_TOKEN,
            ]);
        }

        return response()->json([
            'ok' => false,
            'verified' => false,
            'message' => 'CAPTCHA incorrecto.',
            'attempts' => $challenge->attempts,
            'remaining_attempts' => max(0, $challenge->max_attempts - $challenge->attempts),
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

        $fullPath = $image->fullPath();
        abort_unless(is_file($fullPath), 404);

        return response()->file($fullPath, [
            'Cache-Control' => 'public, max-age=300',
        ])->setContentDisposition('inline', 'captcha.jpg');
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
