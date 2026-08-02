<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\ProfileAvatarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class AvatarStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['image_optimization.avatar_disk' => 'r2_private']);
        Storage::fake('r2_private');
        Storage::fake('public');
        Storage::fake('r2_public');

        foreach (['superadmin', 'administrador', 'doctor', 'paciente', 'laboratorio'] as $r) {
            Role::firstOrCreate(['name' => $r]);
        }
    }

    private function createRoleUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'email_verified_at' => now(),
        ], $attributes));

        $roleModel = Role::where('name', $role)->first();
        if ($roleModel) {
            $user->roles()->attach($roleModel->id);
        }

        return $user;
    }

    public function test_valid_avatar_upload_writes_exclusively_to_r2_private(): void
    {
        $user = $this->createRoleUser('paciente');
        $service = app(ProfileAvatarService::class);

        $file = UploadedFile::fake()->image('avatar.jpg', 400, 400);

        $path = $service->replace($user, $file);

        $this->assertNotNull($path);
        $this->assertStringStartsWith("avatars/{$user->id}/", $path);
        $this->assertEquals($path, $user->fresh()->avatar);

        // Check all 3 variants exist in r2_private
        $disk = Storage::disk('r2_private');
        $this->assertTrue($disk->exists($path));
        $this->assertTrue($disk->exists(str_replace('/original.jpg', '/thumb.webp', $path)));
        $this->assertTrue($disk->exists(str_replace('/original.jpg', '/medium.webp', $path)));

        // Ensure nothing written to public or r2_public
        Storage::disk('public')->assertMissing($path);
        Storage::disk('r2_public')->assertMissing($path);
    }

    public function test_svg_and_invalid_mime_files_are_rejected(): void
    {
        $user = User::factory()->create();
        $service = app(ProfileAvatarService::class);

        $svgFile = UploadedFile::fake()->create('malicious.svg', 10, 'image/svg+xml');

        $this->expectException(InvalidArgumentException::class);
        $service->replace($user, $svgFile);
    }

    public function test_avatar_replacement_deletes_old_r2_variants_and_preserves_local_legacy_files(): void
    {
        $user = $this->createRoleUser('paciente');
        $service = app(ProfileAvatarService::class);

        // 1. Upload first avatar
        $file1 = UploadedFile::fake()->image('avatar1.png', 300, 300);
        $path1 = $service->replace($user, $file1);
        $disk = Storage::disk('r2_private');
        $this->assertTrue($disk->exists($path1));

        // 2. Upload second avatar
        $file2 = UploadedFile::fake()->image('avatar2.jpg', 300, 300);
        $path2 = $service->replace($user, $file2);

        // Path2 exists, Path1 variants removed from R2
        $this->assertTrue($disk->exists($path2));
        $this->assertFalse($disk->exists($path1));
        $this->assertNotEquals($path1, $path2);

        // Legacy local file test: previous local file remains intact
        $user->avatar = 'avatars/legacy_local.png';
        $user->save();
        Storage::disk('public')->put('avatars/legacy_local.png', 'legacy_data');

        $file3 = UploadedFile::fake()->image('avatar3.jpg', 300, 300);
        $service->replace($user, $file3);

        // Legacy file preserved in public disk
        $this->assertTrue(Storage::disk('public')->exists('avatars/legacy_local.png'));
    }

    public function test_authorization_matrix(): void
    {
        $owner = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $superadmin = $this->createRoleUser('superadmin');
        $unrelatedPatient = $this->createRoleUser('paciente');

        $service = app(ProfileAvatarService::class);
        $file = UploadedFile::fake()->image('avatar.jpg', 300, 300);
        $service->replace($owner, $file);

        // 1. Unauthenticated visitor: rejected (302)
        $this->get("/media/avatars/{$owner->id}/thumb")->assertStatus(302);

        // 2. Owner: allowed
        $this->actingAs($owner)->get("/media/avatars/{$owner->id}/thumb")->assertStatus(200);

        // 3. Admin: allowed
        $this->actingAs($admin)->get("/media/avatars/{$owner->id}/thumb")->assertStatus(200);

        // 4. Superadmin: allowed
        $this->actingAs($superadmin)->get("/media/avatars/{$owner->id}/thumb")->assertStatus(200);

        // 5. Unrelated patient: rejected (403)
        $this->actingAs($unrelatedPatient)->get("/media/avatars/{$owner->id}/thumb")->assertStatus(403);
    }

    public function test_traversal_and_invalid_variants_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get("/media/avatars/{$user->id}/unknown_variant")->assertStatus(404);
        $this->actingAs($user)->get("/media/avatars/{$user->id}/..%2fother")->assertStatus(404);
    }

    public function test_versioning_url_changes_when_avatar_is_replaced(): void
    {
        $user = $this->createRoleUser('paciente');
        $service = app(ProfileAvatarService::class);

        $file1 = UploadedFile::fake()->image('a1.jpg', 300, 300);
        $service->replace($user, $file1);
        $url1 = $user->fresh()->avatarUrl('thumb');

        $file2 = UploadedFile::fake()->image('a2.jpg', 300, 300);
        $service->replace($user, $file2);
        $url2 = $user->fresh()->avatarUrl('thumb');

        $this->assertNotEquals($url1, $url2);
        $this->assertStringContainsString('v=', $url1);
        $this->assertStringContainsString('v=', $url2);
    }

    public function test_migration_command_options_dry_run_execute_verify(): void
    {
        $user = User::factory()->create(['avatar' => 'avatars/legacy_test.png']);
        $img = UploadedFile::fake()->image('legacy_test.png', 200, 200);
        Storage::disk('public')->put('avatars/legacy_test.png', file_get_contents($img->getRealPath()));

        // --dry-run: Preview without execution
        $this->artisan('avatars:migrate-to-r2', ['--dry-run' => true])->assertExitCode(0);
        $this->assertEquals('avatars/legacy_test.png', $user->fresh()->avatar);

        // --execute: DB updated, R2 file created
        $this->artisan('avatars:migrate-to-r2', ['--execute' => true])->assertExitCode(0);
        $newPath = $user->fresh()->avatar;
        $this->assertStringStartsWith("avatars/{$user->id}/", $newPath);

        // --verify: Scan verifies R2 file
        $this->artisan('avatars:migrate-to-r2', ['--verify' => true])->assertExitCode(0);
    }

    public function test_doctor_profile_avatar_update_end_to_end_flow(): void
    {
        $doctor = $this->createRoleUser('doctor', [
            'name' => 'Doctor Test',
            'email' => 'doctor_e2e@example.com',
            'telefono' => '0999999999',
            'dni' => '1712345678',
            'direccion' => 'Av Principal',
            'sexo' => 'Masculino',
            'fecha_nacimiento' => '1990-01-01',
            'precio_consulta' => 50,
            'moneda' => 'USD',
        ]);

        $file = UploadedFile::fake()->image('new_doctor_avatar.png', 400, 400);

        $response = $this->actingAs($doctor)->post(route('doctor.perfil.update'), [
            'name' => $doctor->name,
            'email' => $doctor->email,
            'telefono' => $doctor->telefono,
            'dni' => $doctor->dni,
            'direccion' => $doctor->direccion,
            'sexo' => $doctor->sexo,
            'fecha_nacimiento' => '1990-01-01',
            'precio_consulta' => 50,
            'moneda' => 'USD',
            'avatar' => $file,
        ]);

        $response->assertRedirect();

        $doctor->refresh();
        $this->assertNotNull($doctor->avatar);
        $this->assertStringStartsWith("avatars/{$doctor->id}/", $doctor->avatar);

        // Binary response validation
        $avatarRoute = route('media.avatars.show', ['user' => $doctor->id, 'variant' => 'thumb']);
        $imgResponse = $this->actingAs($doctor)->get($avatarRoute);

        $imgResponse->assertStatus(200);
        $imgResponse->assertHeader('Content-Type', 'image/webp');
        $imgResponse->assertHeader('Content-Disposition', 'inline');
        $imgResponse->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
