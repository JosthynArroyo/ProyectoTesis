<?php

namespace Tests\Feature;

use App\Models\CaptchaChallenge;
use App\Models\CaptchaImage;
use App\Models\Role;
use App\Models\User;
use App\Services\CaptchaImageSynchronizer;
use App\Support\ChatbotSessionKeys;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CaptchaFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->bindCaptchaSynchronizerMock();
        $this->seedCaptchaImagesFromDataset();
    }

    public function test_writing_hola_generates_a_valid_captcha_challenge(): void
    {
        $response = $this->getJson('/captcha/challenge');

        $response->assertOk();
        $response->assertJsonStructure([
            'challenge_id',
            'token',
            'target_label_es',
            'images' => [
                '*' => ['position', 'url'],
            ],
        ]);

        $token = (string) $response->json('token');
        $this->assertSame(40, strlen($token));
        $this->assertCount(4, $response->json('images'));

        foreach ($response->json('images') as $image) {
            $this->assertStringStartsWith('/captcha/challenge/', (string) $image['url']);
            $this->assertStringNotContainsString('localhost', (string) $image['url']);
            $this->assertStringNotContainsString('127.0.0.1', (string) $image['url']);
        }
    }

    public function test_captcha_images_come_only_from_ai_dataset(): void
    {
        $response = $this->getJson('/captcha/challenge');
        $challenge = CaptchaChallenge::query()->latest('id')->firstOrFail();

        foreach ($challenge->option_image_ids as $imageId) {
            $image = CaptchaImage::query()->findOrFail($imageId);
            $this->assertStringStartsWith('ai/dataset/val/', $image->image_path);
            $this->assertSame(
                base_path(str_replace('/', DIRECTORY_SEPARATOR, $image->image_path)),
                $image->fullPath()
            );
            $this->assertFileExists($image->fullPath());
        }

        $response->assertOk();
    }

    public function test_correct_answer_verifies_the_session_and_allows_the_chatbot_flow_to_continue(): void
    {
        $patient = $this->createPacienteFixture();

        $challengeResponse = $this->getJson('/captcha/challenge');
        $challenge = CaptchaChallenge::query()->latest('id')->firstOrFail();
        $correctPosition = $this->findCorrectPosition($challenge);

        $verifyResponse = $this->postJson('/captcha/verify', [
            'token' => $challengeResponse->json('token'),
            'position' => $correctPosition,
        ]);

        $verifyResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('verified', true);

        $this->assertTrue((bool) session(ChatbotSessionKeys::SESSION_VERIFIED));
        $this->assertNotNull(session(ChatbotSessionKeys::SESSION_VERIFIED_AT));
        $this->assertNull(session(ChatbotSessionKeys::SESSION_CHALLENGE_ID));
        $this->assertNull(session(ChatbotSessionKeys::SESSION_CHALLENGE_TOKEN));

        $chatbotResponse = $this->postJson('/chatbot/verificar-paciente', [
            'cedula' => $patient->dni,
        ]);

        $chatbotResponse->assertOk();
    }

    public function test_wrong_answer_blocks_progress_and_rotates_the_challenge_and_token(): void
    {
        $challengeResponse = $this->getJson('/captcha/challenge');
        $challenge = CaptchaChallenge::query()->latest('id')->firstOrFail();
        $wrongPosition = $this->findWrongPosition($challenge);

        $verifyResponse = $this->postJson('/captcha/verify', [
            'token' => $challengeResponse->json('token'),
            'position' => $wrongPosition,
        ]);

        $verifyResponse->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('verified', false)
            ->assertJsonPath('challenge.images.0.position', 0);

        $newToken = (string) $verifyResponse->json('challenge.token');
        $newChallenge = CaptchaChallenge::query()->latest('id')->firstOrFail();

        $this->assertNotSame($challengeResponse->json('token'), $newToken);
        $this->assertSame(40, strlen($newToken));
        $this->assertNotSame($challenge->target_key, $newChallenge->target_key);
        $this->assertSame($newToken, session(ChatbotSessionKeys::SESSION_CHALLENGE_TOKEN));
    }

    public function test_previous_captcha_cannot_be_reused_after_a_wrong_attempt(): void
    {
        $challengeResponse = $this->getJson('/captcha/challenge');
        $challenge = CaptchaChallenge::query()->latest('id')->firstOrFail();
        $wrongPosition = $this->findWrongPosition($challenge);

        $this->postJson('/captcha/verify', [
            'token' => $challengeResponse->json('token'),
            'position' => $wrongPosition,
        ])->assertStatus(422);

        $reuseResponse = $this->postJson('/captcha/verify', [
            'token' => $challengeResponse->json('token'),
            'position' => $wrongPosition,
        ]);

        $reuseResponse->assertStatus(403);
    }

    public function test_manipulated_captcha_submission_is_rejected_by_the_server(): void
    {
        $challengeResponse = $this->getJson('/captcha/challenge');
        $challenge = CaptchaChallenge::query()->latest('id')->firstOrFail();

        $manipulatedResponse = $this->postJson('/captcha/verify', [
            'token' => str_repeat('a', 40),
            'position' => $this->findCorrectPosition($challenge),
        ]);

        $manipulatedResponse->assertStatus(403);
    }

    public function test_local_network_requests_do_not_emit_localhost_urls(): void
    {
        $response = $this->withServerVariables([
            'HTTP_HOST' => '192.168.18.60:8000',
        ])->getJson('/captcha/challenge');

        $response->assertOk();

        foreach ($response->json('images') as $image) {
            $this->assertStringStartsWith('/captcha/challenge/', (string) $image['url']);
            $this->assertStringNotContainsString('localhost', (string) $image['url']);
            $this->assertStringNotContainsString('127.0.0.1', (string) $image['url']);
        }
    }

    public function test_captcha_image_endpoint_returns_real_image_content_type(): void
    {
        $response = $this->getJson('/captcha/challenge');
        $response->assertOk();

        $imageResponse = $this->get(route('captcha.image.show', [
            'token' => (string) $response->json('token'),
            'position' => 0,
        ]));

        $imageResponse->assertOk();
        $cacheControl = (string) $imageResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
        $this->assertStringStartsWith('image/', (string) $imageResponse->headers->get('Content-Type'));
    }

    public function test_the_cached_image_index_can_be_reused_without_rescanning_files(): void
    {
        $synchronizer = new CaptchaImageSynchronizer();
        $first = $synchronizer->ensureSynchronized();
        $this->assertGreaterThan(0, $first);

        File::partialMock();
        File::shouldReceive('files')->never();

        $second = $synchronizer->ensureSynchronized();
        $this->assertSame($first, $second);
    }

    public function test_synchronizer_indexes_all_eight_categories_and_reconciles_stale_rows(): void
    {
        CaptchaImage::query()->updateOrCreate([
            'image_path' => 'ai/dataset/val/giraffe/missing-from-dataset.jpg',
        ], [
            'class_key' => 'giraffe',
            'dataset_split' => 'val',
        ]);

        $total = (new CaptchaImageSynchronizer())->ensureSynchronized();
        $classes = config('captcha.classes', []);
        $counts = CaptchaImage::query()
            ->where('dataset_split', 'val')
            ->selectRaw('class_key, COUNT(*) as total')
            ->groupBy('class_key')
            ->pluck('total', 'class_key')
            ->map(static fn ($count): int => (int) $count)
            ->all();

        $this->assertCount(8, $classes);
        $this->assertCount(8, $counts);
        $this->assertEqualsCanonicalizing($classes, array_keys($counts));
        $this->assertSame($total, array_sum($counts));
        $this->assertFalse(CaptchaImage::query()->where('image_path', 'ai/dataset/val/giraffe/missing-from-dataset.jpg')->exists());
        $this->assertSame(0, CaptchaImage::query()->where('dataset_split', '!=', 'val')->count());
        $this->assertDirectoryDoesNotExist(storage_path('app/private/captcha_animals'));
    }

    public function test_synchronizer_rejects_a_configuration_without_all_eight_categories(): void
    {
        config(['captcha.classes' => array_slice(config('captcha.classes', []), 0, 7)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exactamente 8 categorias');

        (new CaptchaImageSynchronizer())->ensureSynchronized();
    }

    private function bindCaptchaSynchronizerMock(): void
    {
        $mock = \Mockery::mock(CaptchaImageSynchronizer::class);
        $mock->shouldReceive('ensureSynchronized')->andReturn(1);
        $this->app->instance(CaptchaImageSynchronizer::class, $mock);
    }

    private function seedCaptchaImagesFromDataset(): void
    {
        $classes = config('captcha.classes', []);

        foreach ($classes as $class) {
            $sourceDir = base_path('ai/dataset/val/' . $class);
            $files = array_values(array_filter(File::files($sourceDir), static fn ($file) => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)));
            $selectedFiles = array_slice($files, 0, 2);

            foreach ($selectedFiles as $file) {
                CaptchaImage::query()->updateOrCreate([
                    'image_path' => 'ai/dataset/val/' . $class . '/' . $file->getFilename(),
                ], [
                    'class_key' => $class,
                    'dataset_split' => 'val',
                ]);
            }
        }
    }

    private function createPacienteFixture(): User
    {
        $role = Role::firstOrCreate(['name' => 'paciente']);

        $user = User::factory()->create([
            'dni' => '1234567890',
            'email' => 'paciente.captcha@test.com',
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function findCorrectPosition(CaptchaChallenge $challenge): int
    {
        $imageId = CaptchaImage::query()
            ->whereIn('id', $challenge->option_image_ids)
            ->where('class_key', $challenge->target_key)
            ->value('id');

        $position = array_search($imageId, $challenge->option_image_ids, true);

        return $position === false ? 0 : (int) $position;
    }

    private function findWrongPosition(CaptchaChallenge $challenge): int
    {
        $imageId = CaptchaImage::query()
            ->whereIn('id', $challenge->option_image_ids)
            ->where('class_key', '!=', $challenge->target_key)
            ->value('id');

        $position = array_search($imageId, $challenge->option_image_ids, true);

        return $position === false ? 0 : (int) $position;
    }
}
