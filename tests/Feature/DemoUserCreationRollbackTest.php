<?php

namespace Tests\Feature;

use App\Models\Especialidad;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoUserCreationRollbackTest extends TestCase
{
    use DatabaseTransactions;

    private function createDemoAdmin(): User
    {
        $adminRole = Role::firstOrCreate(['name' => 'administrador'], ['label' => 'Administrador']);
        $admin = User::firstOrCreate(
            ['email' => 'admin@demo-clinigest.test'],
            [
                'name' => 'Dra. Valeria Mendoza',
                'password' => \Illuminate\Support\Facades\Hash::make('Demo1234!'),
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => false,
                'email_verified_at' => now(),
            ]
        );
        $admin->update(['must_change_password' => false, 'status' => User::STATUS_ACTIVE]);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        return $admin->fresh(['roles']);
    }

    /**
     * TEST 1: En APP_MODE=demo, la creación de un doctor vía POST /admin/usuarios completa exitosamente el request pero NO persiste en mysql_demo ni en producción.
     */
    public function test_demo_user_creation_as_doctor_succeeds_and_rolls_back_cleanly(): void
    {
        config(['app.mode' => 'demo']);
        $admin = $this->createDemoAdmin();
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);
        $especialidad = Especialidad::firstOrCreate(
            ['nombre' => 'Medicina General'],
            ['descripcion' => 'General', 'activo' => true]
        );

        $testEmail = 'rollback.doctor.auditoria@demo-clinigest.test';
        $testDni = '0920000088';

        $payload = [
            'name' => 'Doctor Verificacion Rollback',
            'email' => $testEmail,
            'password' => 'Temporal123!',
            'password_confirmation' => 'Temporal123!',
            'telefono' => '0991234567',
            'tipo_documento' => 'cedula',
            'dni' => $testDni,
            'direccion' => 'Av. Francisco de Orellana 500',
            'fecha_nacimiento' => '1985-06-15',
            'sexo' => 'Masculino',
            'role_id' => $doctorRole->id,
            'especialidad_id' => $especialidad->id,
            'precio_consulta' => '45.00',
            'adulto_mayor' => '0',
            'embarazo' => '0',
            'discapacidad' => '0',
            'cronico' => '0',
        ];

        // 1. Ejecutar el request HTTP real a través del pipeline completo
        $response = $this->actingAs($admin)->post(route('admin.usuarios.store'), $payload);

        // 2. Verificar que el flujo completó validaciones y redirigió con éxito
        $response->assertRedirect(route('admin.usuarios.index'));
        $response->assertSessionHas('success', 'Usuario creado correctamente.');

        // 3. Verificar que el usuario NO existe en la base de datos después de terminar el request
        $this->assertDatabaseMissing('users', [
            'email' => $testEmail,
            'dni' => $testDni,
        ]);

        // 4. Verificar que NO quedaron relaciones en role_user
        $this->assertDatabaseMissing('role_user', [
            'role_id' => $doctorRole->id,
            'user_id' => User::where('email', $testEmail)->value('id') ?? 999999,
        ]);
    }

    /**
     * TEST 2: En APP_MODE=demo, la creación de un paciente con patientFlags completa exitosamente y no deja residuos en patient_flags.
     */
    public function test_demo_user_creation_as_patient_succeeds_and_leaves_no_flags_residue(): void
    {
        config(['app.mode' => 'demo']);
        $admin = $this->createDemoAdmin();
        $pacienteRole = Role::firstOrCreate(['name' => 'paciente']);

        $testEmail = 'rollback.paciente.auditoria@demo-clinigest.test';
        $testDni = '0920000089';

        $payload = [
            'name' => 'Paciente Verificacion Rollback',
            'email' => $testEmail,
            'password' => 'Temporal123!',
            'password_confirmation' => 'Temporal123!',
            'telefono' => '0987654321',
            'tipo_documento' => 'cedula',
            'dni' => $testDni,
            'direccion' => 'Calle Los Ceibos 230',
            'fecha_nacimiento' => '1995-10-20',
            'sexo' => 'Femenino',
            'role_id' => $pacienteRole->id,
            'adulto_mayor' => '1',
            'embarazo' => '0',
            'discapacidad' => '0',
            'cronico' => '1',
        ];

        // 1. Ejecutar el request HTTP real
        $response = $this->actingAs($admin)->post(route('admin.usuarios.store'), $payload);

        // 2. Verificar redirect y flash
        $response->assertRedirect(route('admin.usuarios.index'));
        $response->assertSessionHas('success', 'Usuario creado correctamente.');

        // 3. Verificar que el usuario no persistió
        $this->assertDatabaseMissing('users', [
            'email' => $testEmail,
            'dni' => $testDni,
        ]);

        // 4. Verificar que patient_flags no contiene residuos
        $this->assertDatabaseMissing('patient_flags', [
            'adulto_mayor' => 1,
            'cronico' => 1,
            'user_id' => User::where('email', $testEmail)->value('id') ?? 999999,
        ]);
    }

    /**
     * TEST 3: En APP_MODE=production, la creación de usuario SÍ persiste en la base de datos normalmente.
     */
    public function test_production_user_creation_persists_normally(): void
    {
        config(['app.mode' => 'production']);
        $adminRole = Role::firstOrCreate(['name' => 'administrador']);
        $admin = User::factory()->create();
        $admin->roles()->sync([$adminRole->id]);

        $pacienteRole = Role::firstOrCreate(['name' => 'paciente']);
        $testEmail = 'prod.paciente.persist@clinic.test';
        $testDni = '0920000077';

        $payload = [
            'name' => 'Paciente Produccion Persistente',
            'email' => $testEmail,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'telefono' => '0999888777',
            'tipo_documento' => 'cedula',
            'dni' => $testDni,
            'direccion' => 'Av. Amazonas 123',
            'fecha_nacimiento' => '1992-03-10',
            'sexo' => 'Masculino',
            'role_id' => $pacienteRole->id,
            'adulto_mayor' => '0',
            'embarazo' => '0',
            'discapacidad' => '0',
            'cronico' => '0',
        ];

        $response = $this->actingAs($admin)->post(route('admin.usuarios.store'), $payload);

        $response->assertRedirect(route('admin.usuarios.index'));
        $response->assertSessionHas('success', 'Usuario creado correctamente.');

        // En producción el usuario SÍ debe existir en la base de datos
        $this->assertDatabaseHas('users', [
            'email' => $testEmail,
            'dni' => $testDni,
        ]);
    }
}
