<?php

namespace Tests\Feature;

use App\Models\CaptchaChallenge;
use App\Models\CaptchaImage;
use App\Support\ChatbotSessionKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptchaFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Populate database with images for classes
        $classes = config('captcha.classes', ['giraffe', 'horse', 'koala', 'kangaroo']);
        foreach ($classes as $class) {
            CaptchaImage::create([
                'class_key' => $class,
                'dataset_split' => 'public',
                'image_path' => "captcha_animals/{$class}/img.jpg"
            ]);
        }
    }

    public function test_captcha_can_be_verified_with_the_correct_image(): void
    {
        // 1. Get challenge
        $response = $this->getJson(route('captcha.challenge'));
        $response->assertOk();
        
        $token = $response->json('token');
        
        // 2. Find correct position using the DB record
        $challenge = CaptchaChallenge::query()->orderBy('id', 'desc')->firstOrFail();
        $targetKey = $challenge->target_key;
        
        $correctImageId = CaptchaImage::query()
            ->whereIn('id', $challenge->option_image_ids)
            ->where('class_key', $targetKey)
            ->value('id');
            
        $correctPosition = array_search($correctImageId, $challenge->option_image_ids);

        // 3. Verify
        $verifyResponse = $this->postJson(route('captcha.verify'), [
            'token' => $token,
            'position' => $correctPosition,
        ]);

        $verifyResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('verified', true)
            ->assertSessionHas(ChatbotSessionKeys::SESSION_VERIFIED, true);

        $this->assertNotNull($challenge->refresh()->verified_at);
    }

    public function test_captcha_rejects_an_incorrect_image_and_consumes_an_attempt(): void
    {
        // 1. Get challenge
        $response = $this->getJson(route('captcha.challenge'));
        $response->assertOk();
        
        $token = $response->json('token');
        
        // 2. Find incorrect position
        $challenge = CaptchaChallenge::query()->orderBy('id', 'desc')->firstOrFail();
        $targetKey = $challenge->target_key;
        
        $wrongImageId = CaptchaImage::query()
            ->whereIn('id', $challenge->option_image_ids)
            ->where('class_key', '!=', $targetKey)
            ->value('id');
            
        $wrongPosition = array_search($wrongImageId, $challenge->option_image_ids);

        // 3. Verify incorrect selection
        $verifyResponse = $this->postJson(route('captcha.verify'), [
            'token' => $token,
            'position' => $wrongPosition,
        ]);

        $verifyResponse->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('verified', false)
            ->assertJsonPath('message', 'CAPTCHA incorrecto.')
            ->assertJsonPath('attempts', 1)
            ->assertJsonPath('remaining_attempts', 4);

        $this->assertSame(1, $challenge->refresh()->attempts);
        $this->assertNull($challenge->verified_at);
    }
}
