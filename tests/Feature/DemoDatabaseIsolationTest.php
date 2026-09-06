<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoDatabaseIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['administrador', 'superadmin', 'doctor', 'paciente', 'laboratorio'] as $name) {
            Role::firstOrCreate(['name' => $name], ['label' => ucfirst($name)]);
        }
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

    public function test_create_in_demo_mode_does_not_persist_records(): void
    {
        config(['app.mode' => 'demo']);
        $paciente = $this->createRoleUser('paciente');

        $initialDependientesCount = Dependiente::count();
        $uniqueDni = '09'.rand(10000000, 99999999);

        $response = $this->actingAs($paciente)->post(route('paciente.dependientes.store'), [
            'nombre' => 'Hijo de Prueba Demo',
            'dni' => $uniqueDni,
            'fecha_nacimiento' => '2015-05-10',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('dependientes', ['dni' => $uniqueDni]);
        $this->assertSame($initialDependientesCount, Dependiente::count());
    }

    public function test_create_in_production_mode_persists_records(): void
    {
        config(['app.mode' => 'production']);
        $paciente = $this->createRoleUser('paciente');

        $uniqueDni = '09'.rand(10000000, 99999999);

        $response = $this->actingAs($paciente)->post(route('paciente.dependientes.store'), [
            'nombre' => 'Hijo de Prueba Prod',
            'dni' => $uniqueDni,
            'fecha_nacimiento' => '2015-05-10',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('dependientes', ['dni' => $uniqueDni]);
    }

    public function test_update_in_demo_mode_does_not_persist_changes(): void
    {
        config(['app.mode' => 'demo']);
        $admin = $this->createRoleUser('administrador');
        $originalName = $admin->name;

        $response = $this->actingAs($admin)->post(route('admin.perfil.update'), [
            'name' => 'NOMBRE MODIFICADO EN DEMO',
            'email' => $admin->email,
            'dni' => $admin->dni,
            'direccion' => $admin->direccion ?? 'Av. Principal 123',
            'fecha_nacimiento' => $admin->fecha_nacimiento ? $admin->fecha_nacimiento->format('Y-m-d') : '1990-01-01',
            'sexo' => $admin->sexo ?? 'Masculino',
            'telefono' => '0987654321',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame($originalName, $admin->fresh()->name);
        $this->assertDatabaseMissing('users', ['name' => 'NOMBRE MODIFICADO EN DEMO']);
    }

    public function test_update_in_production_mode_persists_changes(): void
    {
        config(['app.mode' => 'production']);
        $admin = $this->createRoleUser('administrador');

        $response = $this->actingAs($admin)->post(route('admin.perfil.update'), [
            'name' => 'NOMBRE MODIFICADO EN PRODUCCION',
            'email' => $admin->email,
            'dni' => $admin->dni,
            'direccion' => $admin->direccion ?? 'Av. Principal 123',
            'fecha_nacimiento' => $admin->fecha_nacimiento ? $admin->fecha_nacimiento->format('Y-m-d') : '1990-01-01',
            'sexo' => $admin->sexo ?? 'Masculino',
            'telefono' => '0987654321',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('NOMBRE MODIFICADO EN PRODUCCION', $admin->fresh()->name);
        $this->assertDatabaseHas('users', ['name' => 'NOMBRE MODIFICADO EN PRODUCCION']);
    }

    public function test_delete_in_demo_mode_does_not_delete_records(): void
    {
        config(['app.mode' => 'demo']);
        $admin = $this->createRoleUser('administrador');
        $targetUser = $this->createRoleUser('paciente');

        $response = $this->actingAs($admin)->delete(route('admin.usuarios.destroy', $targetUser));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $targetUser->id]);
        $this->assertNotNull($targetUser->fresh());
    }

    public function test_status_change_in_demo_mode_does_not_persist(): void
    {
        config(['app.mode' => 'demo']);
        $admin = $this->createRoleUser('administrador');
        $doctor = $this->createRoleUser('doctor');

        $this->assertSame(User::STATUS_ACTIVE, $doctor->status);

        $response = $this->actingAs($admin)->patch(route('admin.usuarios.deactivate', $doctor), [
            'reason' => 'Prueba de desactivacion en demo',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(User::STATUS_ACTIVE, $doctor->fresh()->status);
    }

    public function test_appointment_priority_change_in_demo_mode_does_not_persist(): void
    {
        config(['app.mode' => 'demo']);
        $admin = $this->createRoleUser('administrador');
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $especialidad = Especialidad::factory()->create();

        $cita = Cita::create([
            'doctor_id' => $doctor->id,
            'paciente_id' => $paciente->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->addDays(2)->format('Y-m-d'),
            'hora' => '09:00:00',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
            'prioridad_nivel' => 'BAJA',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.citas.prioridad.update', $cita), [
            'prioridad_nivel' => 'ALTA',
            'prioridad_motivo' => 'Motivo de urgencia medica simulado en demo',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('BAJA', $cita->fresh()->prioridad_nivel);
    }

    public function test_multi_table_operation_in_demo_mode_does_not_persist_any_table(): void
    {
        config(['app.mode' => 'demo']);
        $admin = $this->createRoleUser('administrador');
        $doctorRole = Role::where('name', 'doctor')->first();
        $especialidad = Especialidad::factory()->create();

        $initialUsersCount = User::count();
        $initialRoleUserCount = DB::table('role_user')->count();
        $initialFlagsCount = DB::table('patient_flags')->count();

        $uniqueEmail = 'multi_demo_'.uniqid().'@example.com';
        $uniqueDni = '09'.rand(10000000, 99999999);

        $response = $this->actingAs($admin)->post(route('admin.usuarios.store'), [
            'name' => 'Doctor Multi Demo',
            'email' => $uniqueEmail,
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'telefono' => '0999999999',
            'tipo_documento' => 'cedula',
            'dni' => $uniqueDni,
            'direccion' => 'Av. Principal 456',
            'fecha_nacimiento' => '1985-05-20',
            'sexo' => 'Masculino',
            'role_id' => $doctorRole->id,
            'especialidad_id' => $especialidad->id,
            'precio_consulta' => 35.00,
            'adulto_mayor' => 0,
            'embarazo' => 0,
            'discapacidad' => 0,
            'cronico' => 0,
        ]);

        $response->assertSessionHasNoErrors();

        // Verificar que NINGUNA tabla involucrada persistió registros
        $this->assertDatabaseMissing('users', ['email' => $uniqueEmail]);
        $this->assertSame($initialUsersCount, User::count());
        $this->assertSame($initialRoleUserCount, DB::table('role_user')->count());
        $this->assertSame($initialFlagsCount, DB::table('patient_flags')->count());
    }

    public function test_validation_errors_are_still_returned_in_demo_mode(): void
    {
        config(['app.mode' => 'demo']);
        $paciente = $this->createRoleUser('paciente');

        // Formulario inválido: nombre y dni vacíos
        $response = $this->actingAs($paciente)->post(route('paciente.dependientes.store'), [
            'nombre' => '',
            'dni' => '',
            'fecha_nacimiento' => '',
            'sexo' => '',
            'parentesco' => '',
        ]);

        $response->assertSessionHasErrors(['nombre', 'dni', 'fecha_nacimiento', 'parentesco']);
    }

    public function test_authorization_policies_are_enforced_in_demo_mode(): void
    {
        config(['app.mode' => 'demo']);
        $patient = $this->createRoleUser('paciente');
        $targetUser = $this->createRoleUser('doctor');

        // Paciente intentando desactivar a un doctor
        $response = $this->actingAs($patient)->patch(route('admin.usuarios.deactivate', $targetUser), [
            'reason' => 'Intento no autorizado',
        ]);

        $this->assertTrue($response->isRedirect() || $response->isForbidden());
        $this->assertSame(User::STATUS_ACTIVE, $targetUser->fresh()->status);
    }
}
