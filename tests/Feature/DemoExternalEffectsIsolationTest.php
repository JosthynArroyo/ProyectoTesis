<?php

namespace Tests\Feature;

use App\Jobs\EnviarConfirmacionCitaJob;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Role;
use App\Models\User;
use App\Services\DemoExternalEffectsGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config as FlysystemConfig;
use Tests\TestCase;

class DemoExternalEffectsIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DemoExternalEffectsGuard::reset();

        foreach (['administrador', 'superadmin', 'doctor', 'paciente', 'laboratorio'] as $name) {
            Role::firstOrCreate(['name' => $name], ['label' => ucfirst($name)]);
        }
    }

    protected function tearDown(): void
    {
        DemoExternalEffectsGuard::reset();
        parent::tearDown();
    }

    private function createRoleUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'email' => strtolower($role).'_'.uniqid().'@example.com',
            'dni' => '09'.rand(10000000, 99999999),
            'direccion' => 'Av. Principal 123',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'Masculino',
            'telefono' => '0999999999',
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ], $attributes));

        $roleModel = Role::where('name', $role)->first();
        if ($roleModel) {
            $user->roles()->syncWithoutDetaching([$roleModel->id]);
        }

        return $user;
    }

    public function test_storage_read_existing_files_succeeds_in_demo_mode(): void
    {
        config(['app.mode' => 'demo']);
        app(DemoExternalEffectsGuard::class)->apply();

        $disk = Storage::disk('public');
        $testFileName = 'demo_read_test_'.uniqid().'.txt';

        // Escribir físicamente en el adaptador interno para simular archivo preexistente
        $realAdapter = $disk->getAdapter();
        // Si ya está envuelto, obtener el innerAdapter
        $reflection = new \ReflectionClass($realAdapter);
        if ($reflection->hasProperty('innerAdapter')) {
            $prop = $reflection->getProperty('innerAdapter');
            $prop->setAccessible(true);
            $inner = $prop->getValue($realAdapter);
            $inner->write($testFileName, 'PREEXISTING_DEMO_CONTENT', new FlysystemConfig);
        }

        try {
            $this->assertTrue($disk->exists($testFileName));
            $this->assertSame('PREEXISTING_DEMO_CONTENT', $disk->get($testFileName));
        } finally {
            if (isset($inner)) {
                $inner->delete($testFileName);
            }
        }
    }

    public function test_storage_writes_in_demo_mode_do_not_persist_to_physical_disk(): void
    {
        config(['app.mode' => 'demo']);
        app(DemoExternalEffectsGuard::class)->apply();

        $disk = Storage::disk('public');
        $fileName = 'ephemeral_file_'.uniqid().'.txt';

        $disk->put($fileName, 'EPHEMERAL_CONTENT_123');

        // Legible a través del disco protegido durante la sesión demo
        $this->assertTrue($disk->exists($fileName));
        $this->assertSame('EPHEMERAL_CONTENT_123', $disk->get($fileName));

        // Pero NO existe en el sistema de archivos físico subyacente
        $physicalPath = storage_path('app/public/'.$fileName);
        $this->assertFileDoesNotExist($physicalPath);
    }

    public function test_storage_deletion_in_demo_mode_does_not_delete_physical_file(): void
    {
        config(['app.mode' => 'demo']);

        // 1. Crear archivo real en storage
        $testFileName = 'permanent_logo_'.uniqid().'.png';
        $physicalPath = storage_path('app/public/'.$testFileName);
        file_put_contents($physicalPath, 'PERMANENT_LOGO_BYTES');

        $this->assertFileExists($physicalPath);

        // 2. Aplicar guard demo
        app(DemoExternalEffectsGuard::class)->apply();
        $disk = Storage::disk('public');

        // 3. Ejecutar delete en modo demo
        $disk->delete($testFileName);

        // 4. El archivo físico REAL permanece intacto
        $this->assertFileExists($physicalPath);
        $this->assertSame('PERMANENT_LOGO_BYTES', file_get_contents($physicalPath));

        // Limpieza del archivo físico
        @unlink($physicalPath);
    }

    public function test_avatar_upload_in_demo_mode_does_not_leave_persistent_file(): void
    {
        config(['app.mode' => 'demo']);
        $admin = $this->createRoleUser('administrador');

        $fakeAvatar = UploadedFile::fake()->image('test_avatar.jpg', 200, 200);

        $response = $this->actingAs($admin)->post(route('admin.perfil.update'), [
            'name' => $admin->name,
            'email' => $admin->email,
            'dni' => $admin->dni,
            'direccion' => $admin->direccion,
            'fecha_nacimiento' => $admin->fecha_nacimiento->format('Y-m-d'),
            'sexo' => $admin->sexo,
            'telefono' => '0987654321',
            'avatar' => $fakeAvatar,
        ]);

        $response->assertSessionHasNoErrors();

        // En BD no se persistió por DemoDatabaseIsolation
        $this->assertNull($admin->fresh()->avatar_path);
    }

    public function test_mail_is_diverted_to_array_mailer_in_demo_mode(): void
    {
        config(['app.mode' => 'demo']);
        app(DemoExternalEffectsGuard::class)->apply();

        $this->assertSame('array', config('mail.default'));

        Mail::raw('Contenido de prueba demo', function ($msg) {
            $msg->to('destinatario_real@example.com')->subject('Prueba Demo');
        });

        // No lanza excepción ni se conecta a red SMTP externa
        $this->assertTrue(true);
    }

    public function test_mail_retains_production_config_in_production_mode(): void
    {
        config(['app.mode' => 'production']);
        DemoExternalEffectsGuard::reset();

        $this->assertNotSame('demo', config('app.mode'));
        $this->assertContains(config('mail.default'), ['log', 'smtp', 'array', 'failover']);
    }

    public function test_queue_is_neutralized_in_demo_mode(): void
    {
        config(['app.mode' => 'demo']);
        app(DemoExternalEffectsGuard::class)->apply();

        $this->assertSame('null', config('queue.default'));
        $this->assertSame('null', config('queue.media_connection'));
        $this->assertSame('null', config('queue.backup_queue_connection'));

        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $esp = Especialidad::factory()->create();

        $cita = Cita::create([
            'doctor_id' => $doctor->id,
            'paciente_id' => $paciente->id,
            'especialidad_id' => $esp->id,
            'fecha' => now()->addDays(2)->format('Y-m-d'),
            'hora' => '10:00:00',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        // Despacho de job se descarta sin efectos secundarios ni errores
        EnviarConfirmacionCitaJob::dispatch($cita);
        $this->assertTrue(true);
    }

    public function test_queue_retains_production_config_in_production_mode(): void
    {
        config(['app.mode' => 'production']);
        DemoExternalEffectsGuard::reset();

        $this->assertSame(env('QUEUE_CONNECTION', 'database'), config('queue.default'));
    }
}
