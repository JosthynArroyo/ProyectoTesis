<?php

namespace Tests\Feature;

use App\Models\CaptchaChallenge;
use App\Models\CaptchaImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptchaFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_captcha_can_be_verified_with_the_correct_image(): void
    {
        [$challengeId, $targetKey, $imageIds] = $this->createChallenge();

        $correctImage = CaptchaImage::query()
            ->whereIn('id', $imageIds)
            ->where('class_key', $targetKey)
            ->firstOrFail();

        $response = $this->postJson(route('captcha.verify'), [
            'challenge_id' => $challengeId,
            'selected_image_id' => $correctImage->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('verified', true)
            ->assertJsonPath('label', $targetKey)
            ->assertSessionHas('captcha_verified', true);

        $this->assertNotNull(CaptchaChallenge::query()->findOrFail($challengeId)->verified_at);
    }

    public function test_captcha_rejects_an_incorrect_image_and_consumes_an_attempt(): void
    {
        [$challengeId, $targetKey, $imageIds] = $this->createChallenge();

        $wrongImage = CaptchaImage::query()
            ->whereIn('id', $imageIds)
            ->where('class_key', '!=', $targetKey)
            ->firstOrFail();

        $response = $this->postJson(route('captcha.verify'), [
            'challenge_id' => $challengeId,
            'selected_image_id' => $wrongImage->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('verified', false)
            ->assertJsonPath('message', 'CAPTCHA incorrecto.')
            ->assertJsonPath('attempts', 1)
            ->assertJsonPath('remaining_attempts', 4);

        $challenge = CaptchaChallenge::query()->findOrFail($challengeId);
        $this->assertSame(1, $challenge->attempts);
        $this->assertNull($challenge->verified_at);
    }

    private function createChallenge(): array
    {
        $response = $this->getJson(route('captcha.challenge'));

        $response->assertOk()
            ->assertJsonCount(4, 'images')
            ->assertJsonStructure([
                'challenge_id',
                'target_key',
                'target_label_es',
                'images' => [
                    ['id', 'url'],
                ],
            ]);

        $imageIds = collect($response->json('images'))
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        return [
            (int) $response->json('challenge_id'),
            (string) $response->json('target_key'),
            $imageIds,
        ];
    }
}
