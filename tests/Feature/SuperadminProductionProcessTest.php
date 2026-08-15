<?php

namespace Tests\Feature;

use App\Models\PatientFlag;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperadminProductionProcessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $superadmins = User::whereHas('roles', fn($q) => $q->where('name', 'superadmin'))->get();
        foreach ($superadmins as $sa) {
            $sa->roles()->detach();
            $sa->delete();
        }

        // Create roles needed for tests
        Role::firstOrCreate(['name' => 'superadmin']);
        Role::firstOrCreate(['name' => 'administrador']);
        Role::firstOrCreate(['name' => 'paciente']);
    }

    private function safeWipeUsers(): void
    {
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        User::query()->delete();
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function test_database_seeder_does_not_create_users(): void
    {
        $this->safeWipeUsers();

        $this->seed(DatabaseSeeder::class);

        $this->assertEquals(0, User::count());
    }

    public function test_production_seeder_does_not_create_users(): void
    {
        // Make sure no users exist
        $this->safeWipeUsers();

        $this->seed(ProductionSeeder::class);

        $this->assertEquals(0, User::count());
    }

    public function test_command_creates_superadmin_correctly(): void
    {
        $this->safeWipeUsers();

        // Run the command
        $this->artisan('app:create-superadmin')
            ->expectsQuestion('Nombre completo', 'Super Administrador')
            ->expectsQuestion('Correo electrónico', 'SUPERADMIN@clinic.test')
            ->expectsQuestion('Contraseña', 'ComplexPass123!')
            ->expectsQuestion('Confirmar contraseña', 'ComplexPass123!')
            ->expectsConfirmation('¿Está seguro de que desea crear este superadministrador?', 'yes')
            ->expectsOutput('Superadministrador creado correctamente.')
            ->assertExitCode(0);

        $user = User::where('email', 'superadmin@clinic.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('ComplexPass123!', $user->password));
        $this->assertEquals('superadmin@clinic.test', $user->email); // Normalized
        $this->assertEquals(User::STATUS_ACTIVE, $user->status);
        $this->assertTrue($user->active);
        $this->assertTrue($user->must_change_password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('superadmin'));
        $this->assertEquals(1, $user->roles()->count());
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->artisan('app:create-superadmin')
            ->expectsQuestion('Nombre completo', 'Super Administrador')
            ->expectsQuestion('Correo electrónico', 'superadmin2@clinic.test')
            ->expectsQuestion('Contraseña', 'weak')
            ->expectsQuestion('Confirmar contraseña', 'weak')
            ->expectsOutput('La contraseña debe tener al menos 12 caracteres.')
            ->expectsOutput('La contraseña debe contener al menos una letra mayúscula, una minúscula, un número y un símbolo especial.')
            ->assertExitCode(1);
    }

    public function test_mismatched_confirmation_is_rejected(): void
    {
        $this->artisan('app:create-superadmin')
            ->expectsQuestion('Nombre completo', 'Super Administrador')
            ->expectsQuestion('Correo electrónico', 'superadmin2@clinic.test')
            ->expectsQuestion('Contraseña', 'ComplexPass123!')
            ->expectsQuestion('Confirmar contraseña', 'DifferentPass123!')
            ->expectsOutput('La confirmación de la contraseña no coincide.')
            ->assertExitCode(1);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        // Pre-create patient
        $role = Role::where('name', 'paciente')->first();
        $existing = User::create([
            'name' => 'Existing Patient',
            'email' => 'superadmin@clinic.test',
            'password' => 'ComplexPass123!',
            'dni' => '1710034115',
        ]);
        $existing->roles()->sync([$role->id]);

        // Attempt creation with duplicate email
        $this->artisan('app:create-superadmin')
            ->expectsQuestion('Nombre completo', 'Super Administrador')
            ->expectsQuestion('Correo electrónico', 'superadmin@clinic.test')
            ->expectsQuestion('Contraseña', 'ComplexPass123!')
            ->expectsQuestion('Confirmar contraseña', 'ComplexPass123!')
            ->expectsOutput('El correo electrónico ya está registrado.')
            ->assertExitCode(1);
    }

    public function test_second_attempt_denied_if_superadmin_exists(): void
    {
        $role = Role::where('name', 'superadmin')->first();
        $existing = User::create([
            'name' => 'Existing',
            'email' => 'superadmin@clinic.test',
            'password' => 'ComplexPass123!',
        ]);
        $existing->roles()->sync([$role->id]);

        $this->artisan('app:create-superadmin')
            ->expectsOutput('Ya existe un usuario con el rol de superadministrador.')
            ->assertExitCode(1);
    }

    public function test_missing_role_aborts(): void
    {
        $this->safeWipeUsers();
        Role::where('name', 'superadmin')->delete();

        $this->artisan('app:create-superadmin')
            ->expectsOutput('El rol "superadmin" no existe. Por favor ejecute ProductionSeeder primero.')
            ->assertExitCode(1);
    }

    public function test_no_interaction_aborts_in_production(): void
    {
        config(['app.env' => 'production']);

        $this->artisan('app:create-superadmin', ['--no-interaction' => true])
            ->expectsOutput('El comando debe ser ejecutado interactivamente en producción.')
            ->assertExitCode(1);
    }

    public function test_recovery_command_flow(): void
    {
        // 1. Create single superadmin
        $role = Role::where('name', 'superadmin')->first();
        $superadmin = User::create([
            'name' => 'Super',
            'email' => 'superadmin@clinic.test',
            'password' => 'OldPassword123!',
            'must_change_password' => false,
            'remember_token' => 'token123',
        ]);
        $superadmin->roles()->sync([$role->id]);

        // 2. Recover
        $this->artisan('app:recover-superadmin')
            ->expectsQuestion('Para continuar, escriba exactamente "RECUPERAR SUPERADMIN"', 'RECUPERAR SUPERADMIN')
            ->expectsQuestion('Nueva contraseña', 'NewComplexPass123!')
            ->expectsQuestion('Confirmar nueva contraseña', 'NewComplexPass123!')
            ->expectsOutput('Superadministrador recuperado correctamente. Se requerirá cambio de contraseña en su próximo inicio de sesión.')
            ->assertExitCode(0);

        $superadmin->refresh();
        $this->assertTrue(Hash::check('NewComplexPass123!', $superadmin->password));
        $this->assertTrue($superadmin->must_change_password);
        $this->assertNull($superadmin->remember_token);
    }

    public function test_first_login_forces_change_password(): void
    {
        $role = Role::where('name', 'superadmin')->first();
        $superadmin = User::create([
            'name' => 'Super',
            'email' => 'superadmin@clinic.test',
            'password' => 'ComplexPass123!',
            'must_change_password' => true,
        ]);
        $superadmin->roles()->sync([$role->id]);

        // Access dashboard
        $response = $this->actingAs($superadmin)->get(route('superadmin.dashboard'));

        // Must redirect to must-change-password screen
        $response->assertRedirect(route('auth.must-change-password'));
    }

    public function test_submitting_password_change(): void
    {
        $role = Role::where('name', 'superadmin')->first();
        $superadmin = User::create([
            'name' => 'Super',
            'email' => 'superadmin@clinic.test',
            'password' => 'ComplexPass123!',
            'must_change_password' => true,
        ]);
        $superadmin->roles()->sync([$role->id]);

        // Access must change password screen
        $response = $this->actingAs($superadmin)->get(route('auth.must-change-password'));
        $response->assertOk();

        // Submit new password
        $response = $this->actingAs($superadmin)->post(route('auth.must-change-password.update'), [
            'current_password' => 'ComplexPass123!',
            'password' => 'NewComplexPass123!',
            'password_confirmation' => 'NewComplexPass123!',
        ]);

        $response->assertRedirect($superadmin->dashboardPath());

        $superadmin->refresh();
        $this->assertFalse($superadmin->must_change_password);
        $this->assertTrue(Hash::check('NewComplexPass123!', $superadmin->password));
    }

    public function test_last_superadmin_cannot_be_deleted(): void
    {
        $role = Role::where('name', 'superadmin')->first();
        $superadmin = User::create([
            'name' => 'Super',
            'email' => 'superadmin@clinic.test',
            'password' => 'ComplexPass123!',
            'dni' => '1710034115',
            'status' => User::STATUS_ACTIVE,
        ]);
        $superadmin->roles()->sync([$role->id]);
        PatientFlag::create([
            'user_id' => $superadmin->id,
            'adulto_mayor' => false,
            'embarazo' => false,
            'discapacidad' => false,
            'cronico' => false,
        ]);

        $this->assertDatabaseHas('users', ['id' => $superadmin->id, 'dni' => '1710034115']);
        $this->assertDatabaseHas('role_user', ['user_id' => $superadmin->id, 'role_id' => $role->id]);
        $this->assertDatabaseHas('identity_documents', [
            'documentable_type' => User::class,
            'documentable_id' => $superadmin->id,
            'numero_documento' => '1710034115',
        ]);
        $this->assertDatabaseHas('patient_flags', ['user_id' => $superadmin->id]);

        try {
            $superadmin->delete();
            $this->fail('Se esperaba una RuntimeException al intentar eliminar el último superadmin.');
        } catch (\RuntimeException $e) {
            $this->assertSame('No se puede eliminar el único superadministrador activo del sistema.', $e->getMessage());
        }

        $this->assertDatabaseHas('users', ['id' => $superadmin->id, 'dni' => '1710034115']);
        $this->assertDatabaseHas('role_user', ['user_id' => $superadmin->id, 'role_id' => $role->id]);
        $this->assertDatabaseHas('identity_documents', [
            'documentable_type' => User::class,
            'documentable_id' => $superadmin->id,
            'numero_documento' => '1710034115',
        ]);
        $this->assertDatabaseHas('patient_flags', ['user_id' => $superadmin->id]);
    }

    public function test_last_superadmin_cannot_be_deactivated(): void
    {
        $role = Role::where('name', 'superadmin')->first();
        $superadmin = User::create([
            'name' => 'Super',
            'email' => 'superadmin@clinic.test',
            'password' => 'ComplexPass123!',
            'status' => User::STATUS_ACTIVE,
        ]);
        $superadmin->roles()->sync([$role->id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se puede desactivar o bloquear el único superadministrador activo del sistema.');

        $superadmin->update(['status' => User::STATUS_INACTIVE]);
    }
}
