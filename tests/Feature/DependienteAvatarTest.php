<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DependienteAvatarTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['image_optimization.avatar_disk' => 'r2_private']);
        Storage::fake('r2_private');
        Storage::fake('public');

        $this->seedRoles();
    }

    private function seedRoles(): void
    {
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

    public function test_dependent_avatar_upload_stores_in_r2_private_with_relative_key(): void
    {
        $titular = $this->createRoleUser('paciente');
        $file = UploadedFile::fake()->image('dependent.png', 400, 400);

        $response = $this->actingAs($titular)->post(route('paciente.dependientes.store'), [
            'nombre' => 'Anabel Arroyo',
            'tipo_documento' => 'cedula',
            'dni' => '1754504635',
            'fecha_nacimiento' => '2015-05-10',
            'sexo' => 'Femenino',
            'parentesco' => 'hija',
            'avatar' => $file,
        ]);

        $response->assertRedirect(route('paciente.dependientes.index'));

        $dependiente = Dependiente::where('nombre', 'Anabel Arroyo')->firstOrFail();
        $rawAvatar = (string) $dependiente->getRawOriginal('avatar');

        $this->assertStringStartsWith("avatars/dependents/{$dependiente->id}/", $rawAvatar);
        $this->assertStringEndsWith('/original.png', $rawAvatar);

        $disk = Storage::disk('r2_private');
        $dir = dirname($rawAvatar);

        $this->assertTrue($disk->exists($rawAvatar));
        $this->assertTrue($disk->exists("{$dir}/thumb.webp"));
        $this->assertTrue($disk->exists("{$dir}/medium.webp"));

        // Ensure no storage or r2.dev public URLs used
        $this->assertStringNotContainsString('/storage/', $dependiente->avatar_thumb_url);
        $this->assertStringNotContainsString('r2.dev', $dependiente->avatar_thumb_url);
        $this->assertStringContainsString("/media/dependent-avatars/{$dependiente->id}/thumb", $dependiente->avatar_thumb_url);
    }

    public function test_titular_and_dependent_sharing_same_contact_have_independent_avatars(): void
    {
        $titular = $this->createRoleUser('paciente', [
            'email' => 'josthynarroyo627@gmail.com',
            'avatar' => 'avatars/1/uuid1/original.jpg',
        ]);

        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'nombre' => 'Anabel',
            'tipo_documento' => 'cedula',
            'dni' => '1754504635',
            'fecha_nacimiento' => '2015-05-10',
            'sexo' => 'Femenino',
            'parentesco' => 'hija',
            'avatar' => 'avatars/dependents/2/uuid2/original.jpg',
            'activo' => true,
        ]);

        $this->assertNotEquals($titular->getRawOriginal('avatar'), $dependiente->getRawOriginal('avatar'));
        $this->assertStringContainsString('/media/avatars/', $titular->avatar_thumb_url);
        $this->assertStringContainsString('/media/dependent-avatars/', $dependiente->avatar_thumb_url);
    }

    public function test_dependent_avatar_replacement_deletes_old_r2_variants(): void
    {
        $titular = $this->createRoleUser('paciente');
        $service = app(\App\Services\ProfileAvatarService::class);

        $file1 = UploadedFile::fake()->image('photo1.png', 400, 400);
        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'nombre' => 'Pedro',
            'tipo_documento' => 'cedula',
            'dni' => '1754504635',
            'fecha_nacimiento' => '2018-01-01',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
            'activo' => true,
        ]);

        $key1 = $service->replaceForDependent($dependiente, $file1);
        $dir1 = dirname($key1);
        $disk = Storage::disk('r2_private');

        $this->assertTrue($disk->exists($key1));
        $this->assertTrue($disk->exists("{$dir1}/thumb.webp"));

        // Replace avatar
        $file2 = UploadedFile::fake()->image('photo2.png', 400, 400);
        $response = $this->actingAs($titular)->put(route('paciente.dependientes.update', $dependiente->id), [
            'nombre' => 'Pedro',
            'tipo_documento' => 'cedula',
            'dni' => '1754504635',
            'fecha_nacimiento' => '2018-01-01',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
            'avatar' => $file2,
        ]);

        $response->assertRedirect(route('paciente.dependientes.index'));

        $dependiente->refresh();
        $key2 = (string) $dependiente->getRawOriginal('avatar');

        $this->assertNotEquals($key1, $key2);
        $this->assertTrue($disk->exists($key2));
        $this->assertFalse($disk->exists($key1));
        $this->assertFalse($disk->exists("{$dir1}/thumb.webp"));
    }

    public function test_dependent_deletion_cleans_r2_variants(): void
    {
        $titular = $this->createRoleUser('paciente');
        $service = app(\App\Services\ProfileAvatarService::class);

        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'nombre' => 'Lucía',
            'tipo_documento' => 'cedula',
            'dni' => '1754504635',
            'fecha_nacimiento' => '2019-03-15',
            'sexo' => 'Femenino',
            'parentesco' => 'hija',
            'activo' => true,
        ]);

        $key = $service->replaceForDependent($dependiente, UploadedFile::fake()->image('photo.jpg', 300, 300));
        $disk = Storage::disk('r2_private');
        $this->assertTrue($disk->exists($key));

        $response = $this->actingAs($titular)->delete(route('paciente.dependientes.destroy', $dependiente->id));
        $response->assertRedirect(route('paciente.dependientes.index'));

        $this->assertDatabaseMissing('dependientes', ['id' => $dependiente->id]);
        $this->assertFalse($disk->exists($key));
    }

    public function test_if_database_deletion_fails_r2_objects_remain_intact(): void
    {
        $titular = $this->createRoleUser('paciente');
        $service = app(\App\Services\ProfileAvatarService::class);

        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'nombre' => 'Mateo',
            'tipo_documento' => 'cedula',
            'dni' => '1754504635',
            'fecha_nacimiento' => '2021-01-01',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
            'activo' => true,
        ]);

        $key = $service->replaceForDependent($dependiente, UploadedFile::fake()->image('photo.jpg', 300, 300));
        $disk = Storage::disk('r2_private');
        $this->assertTrue($disk->exists($key));

        // Event listener throws to simulate DB deletion failure
        Dependiente::deleting(function () {
            throw new \RuntimeException('DB Deletion Failure Simulated');
        });

        try {
            $this->actingAs($titular)->delete(route('paciente.dependientes.destroy', $dependiente->id));
        } catch (\Throwable $e) {
            // Expected exception
        }

        $this->assertDatabaseHas('dependientes', ['id' => $dependiente->id]);
        $this->assertTrue($disk->exists($key));
    }

    public function test_if_r2_cleanup_fails_after_deletion_error_is_logged_without_reverting_deleted_entity(): void
    {
        $titular = $this->createRoleUser('paciente');
        $service = app(\App\Services\ProfileAvatarService::class);

        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'nombre' => 'Camila',
            'tipo_documento' => 'cedula',
            'dni' => '1754504635',
            'fecha_nacimiento' => '2020-05-05',
            'sexo' => 'Femenino',
            'parentesco' => 'hija',
            'activo' => true,
        ]);

        $key = $service->replaceForDependent($dependiente, UploadedFile::fake()->image('photo.jpg', 300, 300));

        // Mock Storage disk delete to throw exception
        Storage::shouldReceive('disk')->with('r2_private')->andReturnSelf();
        Storage::shouldReceive('exists')->andReturn(true);
        Storage::shouldReceive('delete')->andThrow(new \RuntimeException('R2 Deletion Failure'));

        \Illuminate\Support\Facades\Log::spy();

        $response = $this->actingAs($titular)->delete(route('paciente.dependientes.destroy', $dependiente->id));
        $response->assertRedirect(route('paciente.dependientes.index'));

        $this->assertDatabaseMissing('dependientes', ['id' => $dependiente->id]);
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning');
    }

    public function test_creation_failure_leaves_no_partial_dependent_or_orphaned_r2_objects(): void
    {
        $titular = $this->createRoleUser('paciente');
        $disk = Storage::disk('r2_private');

        $invalidFile = UploadedFile::fake()->create('malicious.txt', 100, 'text/plain');

        $response = $this->actingAs($titular)->post(route('paciente.dependientes.store'), [
            'nombre' => 'Carlos Fallo',
            'tipo_documento' => 'cedula',
            'dni' => '1754504635',
            'fecha_nacimiento' => '2016-03-20',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
            'avatar' => $invalidFile,
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('dependientes', ['nombre' => 'Carlos Fallo']);
        $this->assertEmpty($disk->allFiles());
    }

    public function test_original_jpeg_variant_responds_with_image_jpeg(): void
    {
        $titular = $this->createRoleUser('paciente');
        $service = app(\App\Services\ProfileAvatarService::class);

        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'nombre' => 'Daniel',
            'tipo_documento' => 'cedula',
            'dni' => '1754504635',
            'fecha_nacimiento' => '2017-07-07',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
            'activo' => true,
        ]);

        $service->replaceForDependent($dependiente, UploadedFile::fake()->image('photo.jpg', 300, 300));

        $route = route('media.dependent-avatars.show', ['dependiente' => $dependiente->id, 'variant' => 'original']);
        $response = $this->actingAs($titular)->get($route);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_original_png_variant_responds_with_image_png(): void
    {
        $titular = $this->createRoleUser('paciente');
        $service = app(\App\Services\ProfileAvatarService::class);

        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'nombre' => 'Elena',
            'tipo_documento' => 'cedula',
            'dni' => '1754504635',
            'fecha_nacimiento' => '2017-08-08',
            'sexo' => 'Femenino',
            'parentesco' => 'hija',
            'activo' => true,
        ]);

        $service->replaceForDependent($dependiente, UploadedFile::fake()->image('photo.png', 300, 300));

        $route = route('media.dependent-avatars.show', ['dependiente' => $dependiente->id, 'variant' => 'original']);
        $response = $this->actingAs($titular)->get($route);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');
    }

    public function test_thumb_and_medium_variants_respond_with_image_webp(): void
    {
        $titular = $this->createRoleUser('paciente');
        $service = app(\App\Services\ProfileAvatarService::class);

        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'nombre' => 'Gabriel',
            'tipo_documento' => 'cedula',
            'dni' => '1754504635',
            'fecha_nacimiento' => '2017-09-09',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
            'activo' => true,
        ]);

        $service->replaceForDependent($dependiente, UploadedFile::fake()->image('photo.png', 300, 300));

        $thumbRoute = route('media.dependent-avatars.show', ['dependiente' => $dependiente->id, 'variant' => 'thumb']);
        $mediumRoute = route('media.dependent-avatars.show', ['dependiente' => $dependiente->id, 'variant' => 'medium']);

        $this->actingAs($titular)->get($thumbRoute)->assertStatus(200)->assertHeader('Content-Type', 'image/webp');
        $this->actingAs($titular)->get($mediumRoute)->assertStatus(200)->assertHeader('Content-Type', 'image/webp');
    }

    public function test_dependent_avatar_authorization_matrix(): void
    {
        $titular = $this->createRoleUser('paciente');
        $otherPatient = $this->createRoleUser('paciente');
        $doctorRelated = $this->createRoleUser('doctor');
        $doctorUnrelated = $this->createRoleUser('doctor');

        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'nombre' => 'Sofía',
            'tipo_documento' => 'cedula',
            'dni' => '1754504635',
            'fecha_nacimiento' => '2020-01-01',
            'sexo' => 'Femenino',
            'parentesco' => 'hija',
            'avatar' => "avatars/dependents/99/uuid99/original.png",
            'activo' => true,
        ]);

        $disk = Storage::disk('r2_private');
        $disk->put("avatars/dependents/99/uuid99/thumb.webp", "binary-thumb-data");

        $esp = \App\Models\Especialidad::firstOrCreate(['nombre' => 'Medicina General']);

        // Doctor appointment relation
        Cita::create([
            'paciente_id' => $titular->id,
            'dependiente_id' => $dependiente->id,
            'doctor_id' => $doctorRelated->id,
            'especialidad_id' => $esp->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
        ]);

        $route = route('media.dependent-avatars.show', ['dependiente' => $dependiente->id, 'variant' => 'thumb']);

        // 1. Owner Titular: 200 OK
        $this->actingAs($titular)->get($route)->assertStatus(200);

        // 2. Doctor Related: 200 OK
        $this->actingAs($doctorRelated)->get($route)->assertStatus(200);

        // 3. Unrelated Doctor: 403 Forbidden
        $this->actingAs($doctorUnrelated)->get($route)->assertStatus(403);

        // 4. Other Patient: 403 Forbidden
        $this->actingAs($otherPatient)->get($route)->assertStatus(403);

        // 5. Unauthenticated visitor: 302 Redirect or 401 Unauthorized
        $response = $this->get($route);
        $this->assertTrue($response->isRedirect() || in_array($response->status(), [401, 403], true));
    }
}
