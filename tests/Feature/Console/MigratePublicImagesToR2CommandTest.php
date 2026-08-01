<?php

namespace Tests\Feature\Console;

use App\Console\Commands\MigratePublicImagesToR2;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigratePublicImagesToR2CommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('r2_public');
        Storage::fake('local');
    }

    public function test_default_mode_is_dry_run_and_does_not_write_to_r2(): void
    {
        Storage::disk('public')->put('images/banners/large/hero.webp', 'sample-hero-bytes');

        $this->artisan('app:migrate-public-images-to-r2')
            ->expectsOutputToContain('SIMULACIÓN: no se modificó ningún archivo')
            ->assertExitCode(0);

        Storage::disk('r2_public')->assertMissing('images/banners/large/hero.webp');
        Storage::disk('public')->assertExists('images/banners/large/hero.webp');
    }

    public function test_execute_mode_copies_allowed_roots_and_preserves_keys(): void
    {
        Storage::disk('public')->put('images/banners/large/hero.webp', 'banner-content');
        Storage::disk('public')->put('images/doctors/thumb/ana.webp', 'doctor-content');
        Storage::disk('public')->put('images/branding/logo.png', 'logo-content');
        Storage::disk('public')->put('images/services/service-1.webp', 'service-content');

        $this->artisan('app:migrate-public-images-to-r2', ['--execute' => true, '--no-interaction' => true])
            ->assertExitCode(0);

        Storage::disk('r2_public')->assertExists('images/banners/large/hero.webp');
        Storage::disk('r2_public')->assertExists('images/doctors/thumb/ana.webp');
        Storage::disk('r2_public')->assertExists('images/branding/logo.png');
        Storage::disk('r2_public')->assertExists('images/services/service-1.webp');

        $this->assertEquals('banner-content', Storage::disk('r2_public')->get('images/banners/large/hero.webp'));
        $this->assertEquals('doctor-content', Storage::disk('r2_public')->get('images/doctors/thumb/ana.webp'));

        // Source remains intact
        Storage::disk('public')->assertExists('images/banners/large/hero.webp');
        Storage::disk('public')->assertExists('images/doctors/thumb/ana.webp');
    }

    public function test_command_excludes_avatars_private_docs_and_system_assets(): void
    {
        Storage::disk('public')->put('images/avatars/user1.jpg', 'avatar-bytes');
        Storage::disk('public')->put('comprobantes/pago.pdf', 'comprobante-bytes');
        Storage::disk('public')->put('laboratorio_resultados/resultado.pdf', 'lab-bytes');
        Storage::disk('public')->put('certificados_medicos/cert.pdf', 'cert-bytes');
        Storage::disk('public')->put('ai/vision/image.png', 'ai-bytes');
        Storage::disk('public')->put('img/hero1.jpg', 'static-img-bytes');

        $this->artisan('app:migrate-public-images-to-r2', ['--execute' => true, '--no-interaction' => true])
            ->assertExitCode(0);

        Storage::disk('r2_public')->assertMissing('images/avatars/user1.jpg');
        Storage::disk('r2_public')->assertMissing('comprobantes/pago.pdf');
        Storage::disk('r2_public')->assertMissing('laboratorio_resultados/resultado.pdf');
        Storage::disk('r2_public')->assertMissing('certificados_medicos/cert.pdf');
        Storage::disk('r2_public')->assertMissing('ai/vision/image.png');
        Storage::disk('r2_public')->assertMissing('img/hero1.jpg');
    }

    public function test_identical_existing_file_in_r2_is_skipped(): void
    {
        Storage::disk('public')->put('images/banners/slide1.webp', 'identical-content');
        Storage::disk('r2_public')->put('images/banners/slide1.webp', 'identical-content');

        $this->artisan('app:migrate-public-images-to-r2', ['--execute' => true, '--no-interaction' => true])
            ->expectsOutputToContain('[OMITIDO - IDÉNTICO]')
            ->assertExitCode(0);

        $this->assertEquals('identical-content', Storage::disk('r2_public')->get('images/banners/slide1.webp'));
    }

    public function test_conflicting_different_content_in_r2_stops_and_fails(): void
    {
        Storage::disk('public')->put('images/banners/slide1.webp', 'source-content-v1');
        Storage::disk('r2_public')->put('images/banners/slide1.webp', 'target-content-v2-different');

        $this->artisan('app:migrate-public-images-to-r2', ['--execute' => true, '--no-interaction' => true])
            ->expectsOutputToContain('[CONFLICTO]')
            ->assertExitCode(1);

        // Content in R2 is preserved (not overwritten)
        $this->assertEquals('target-content-v2-different', Storage::disk('r2_public')->get('images/banners/slide1.webp'));
    }

    public function test_empty_public_disk_completes_successfully(): void
    {
        $this->artisan('app:migrate-public-images-to-r2')
            ->expectsOutputToContain('No se encontraron imágenes en el disco public')
            ->assertExitCode(0);
    }

    public function test_generates_json_manifest_without_secrets(): void
    {
        Storage::disk('public')->put('images/branding/header.png', 'header-bytes');

        $this->artisan('app:migrate-public-images-to-r2')
            ->assertExitCode(0);

        $manifestFiles = Storage::disk('local')->files('r2-migration-manifests');
        $this->assertNotEmpty($manifestFiles);

        $manifestContent = Storage::disk('local')->get($manifestFiles[0]);
        $this->assertStringContainsString('"source_disk": "public"', $manifestContent);
        $this->assertStringContainsString('"target_disk": "r2_public"', $manifestContent);
        $this->assertStringContainsString('images/branding/header.png', $manifestContent);

        $this->assertStringNotContainsString('R2_ACCESS_KEY', $manifestContent);
        $this->assertStringNotContainsString('R2_SECRET_ACCESS_KEY', $manifestContent);
    }

    public function test_is_key_allowed_method_validates_correctly(): void
    {
        $command = new MigratePublicImagesToR2();

        $this->assertTrue($command->isKeyAllowed('images/banners/test.webp'));
        $this->assertTrue($command->isKeyAllowed('images/doctors/large/ana.webp'));
        $this->assertTrue($command->isKeyAllowed('images/branding/logo.png'));
        $this->assertTrue($command->isKeyAllowed('images/services/hero.webp'));

        $this->assertFalse($command->isKeyAllowed('images/avatars/user.jpg'));
        $this->assertFalse($command->isKeyAllowed('comprobantes/pago.pdf'));
        $this->assertFalse($command->isKeyAllowed('ai/dataset/test.jpg'));
        $this->assertFalse($command->isKeyAllowed('img/hero1.jpg'));
    }
}
