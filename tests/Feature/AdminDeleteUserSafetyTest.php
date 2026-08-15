<?php

namespace Tests\Feature;

use App\Models\ClinicalRecord;
use App\Models\Dependiente;
use App\Models\Pago;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminDeleteUserSafetyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['administrador', 'superadmin', 'doctor', 'paciente', 'laboratorio'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }
    }

    private function createRoleUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'email' => strtolower($role).'_'.uniqid().'@example.com',
        ], $attributes));
        $roleId = Role::where('name', $role)->value('id');
        if ($roleId) {
            $user->roles()->sync([$roleId]);
        }

        return $user;
    }

    /** Test deleting an unprotected user deletes the row cleanly without 500. */
    public function test_deleting_unprotected_user_succeeds_and_removes_user(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');

        $response = $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $paciente));

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $paciente->id]);
    }

    /** Test deleting a doctor without any protected records performs a hard delete, not a deactivation. */
    public function test_deleting_doctor_without_records_performs_hard_delete_not_deactivation(): void
    {
        $admin  = $this->createRoleUser('administrador');
        $doctor = $this->createRoleUser('doctor');
        $doctorId = $doctor->id;

        $response = $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $doctor));

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        // Row must be gone — not merely set to inactive
        $this->assertDatabaseMissing('users', ['id' => $doctorId]);
    }

    /** Test deleting a user with ClinicalRecord rejects cleanly without 500. */
    public function test_deleting_user_with_clinical_record_rejects_cleanly(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');

        ClinicalRecord::create([
            'patient_id' => $paciente->id,
            'allergies_status' => ClinicalRecord::ALLERGIES_NONE,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $paciente));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('users', ['id' => $paciente->id]);
    }

    /** Test deleting a user with Pago record rejects cleanly without 500. */
    public function test_deleting_user_with_pago_record_rejects_cleanly(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        $esp = \App\Models\Especialidad::firstOrCreate(['nombre' => 'Medicina General']);

        $cita = \App\Models\Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => now()->format('Y-m-d'),
            'hora' => '10:00',
            'motivo_consulta' => 'Consulta de pago',
            'estado' => \App\Models\Cita::ESTADO_PENDIENTE,
        ]);

        Pago::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'monto' => 25.00,
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => Pago::ESTADO_PAGADO,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $paciente));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('users', ['id' => $paciente->id]);
    }

    /** Test deleting a user with Dependiente record rejects cleanly without 500. */
    public function test_deleting_user_with_dependiente_rejects_cleanly(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');

        Dependiente::create([
            'user_id' => $paciente->id,
            'nombre' => 'Hijo de prueba',
            'dni' => '0987654321',
            'fecha_nacimiento' => '2015-05-05',
            'parentesco' => 'hijo',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $paciente));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('users', ['id' => $paciente->id]);
    }

    /** Test admin trying to delete self rejects cleanly without 500. */
    public function test_admin_deleting_self_rejects_cleanly(): void
    {
        $admin = $this->createRoleUser('administrador');

        $response = $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $admin));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    /** Test deleting an unprotected user allows reusing their email for a new user. */
    public function test_deleted_user_email_can_be_reused_by_new_user(): void
    {
        $admin = $this->createRoleUser('administrador');
        $email = 'reusable_user_' . uniqid() . '@example.com';
        $paciente = $this->createRoleUser('paciente', ['email' => $email]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $paciente));

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $paciente->id, 'email' => $email]);

        // Creating a new user with the same email must succeed
        $newUser = User::factory()->create([
            'email' => $email,
            'name' => 'Nuevo Usuario Mismo Email',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $newUser->id,
            'email' => $email,
        ]);
    }

    /** Test deleting an protected doctor rejects with redirect and keeps status/active unchanged. */
    public function test_deleting_protected_doctor_rejects_and_preserves_status_and_active(): void
    {
        $admin = $this->createRoleUser('administrador');
        $doctor = $this->createRoleUser('doctor', [
            'status' => User::STATUS_ACTIVE,
            'active' => true,
        ]);
        $paciente = $this->createRoleUser('paciente');
        $esp = \App\Models\Especialidad::firstOrCreate(['nombre' => 'Medicina General']);

        \App\Models\Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => now()->format('Y-m-d'),
            'hora' => '11:00',
            'motivo_consulta' => 'Consulta medica doctor protegido',
            'estado' => \App\Models\Cita::ESTADO_CONFIRMADA,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $doctor));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();

        $doctorFresh = $doctor->fresh();
        $this->assertNotNull($doctorFresh);
        $this->assertSame(User::STATUS_ACTIVE, $doctorFresh->status);
        $this->assertTrue($doctorFresh->active);
    }

    /** Test deleting an unprotected user frees both email and cedula, allowing recreating another user with same email and cedula. */
    public function test_deleting_unprotected_user_frees_email_and_cedula_allowing_recreation_with_same_identity(): void
    {
        $admin = $this->createRoleUser('administrador');
        $email = 'test_free_identity_' . uniqid() . '@example.com';
        $cedula = '1710034115';

        $paciente = $this->createRoleUser('paciente', [
            'email' => $email,
            'dni' => $cedula,
        ]);

        $this->assertDatabaseHas('users', ['id' => $paciente->id, 'email' => $email, 'dni' => $cedula]);
        $this->assertDatabaseHas('identity_documents', ['numero_documento' => $cedula]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $paciente));

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $paciente->id]);
        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertDatabaseMissing('users', ['dni' => $cedula]);
        $this->assertDatabaseMissing('identity_documents', ['numero_documento' => $cedula]);

        $pacienteRoleId = Role::where('name', 'paciente')->value('id');

        // Creating a new user via Admin store endpoint with exact SAME email and SAME cedula must succeed
        $createResponse = $this->actingAs($admin)
            ->post(route('admin.usuarios.store'), [
                'name' => 'Paciente Recreado Misma Identidad',
                'email' => $email,
                'dni' => $cedula,
                'role_id' => $pacienteRoleId,
                'telefono' => '0991234567',
                'direccion' => 'Av. Principal 123',
                'sexo' => 'Masculino',
                'fecha_nacimiento' => '1995-05-15',
                'status' => 'active',
                'adulto_mayor' => 0,
                'embarazo' => 0,
                'discapacidad' => 0,
                'cronico' => 0,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $createResponse->assertStatus(302);
        $createResponse->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'dni' => $cedula,
        ]);
        $this->assertDatabaseHas('identity_documents', [
            'numero_documento' => $cedula,
        ]);
    }

    /** Test deleting a protected user rejects delete, keeping email and cedula reserved. */
    public function test_deleting_protected_user_rejects_keeping_email_and_cedula_reserved(): void
    {
        $admin = $this->createRoleUser('administrador');
        $email = 'protected_identity_' . uniqid() . '@example.com';
        $cedula = '1723456789';

        $paciente = $this->createRoleUser('paciente', [
            'email' => $email,
            'dni' => $cedula,
        ]);

        ClinicalRecord::create([
            'patient_id' => $paciente->id,
            'allergies_status' => ClinicalRecord::ALLERGIES_NONE,
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $paciente));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();

        // User, email and cedula remain in DB
        $this->assertDatabaseHas('users', ['id' => $paciente->id, 'email' => $email, 'dni' => $cedula]);
        $this->assertDatabaseHas('identity_documents', ['numero_documento' => $cedula]);

        // Attempting to create another user with same cedula must be rejected with validation error
        $createResponse = $this->actingAs($admin)
            ->post(route('admin.usuarios.store'), [
                'name' => 'Intento Duplicado Cedula',
                'email' => 'otro_email_' . uniqid() . '@example.com',
                'dni' => $cedula,
                'role' => 'paciente',
                'telefono' => '0997654321',
                'fecha_nacimiento' => '1990-10-10',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $createResponse->assertStatus(302);
        $createResponse->assertSessionHasErrors(['dni']);
    }

    /** Test GlobalUniqueCedulaRule directly after deleting user via Admin endpoint and with orphan row. */
    public function test_global_unique_cedula_rule_passes_after_admin_hard_delete_and_handles_orphans(): void
    {
        $admin = $this->createRoleUser('administrador');
        $email = 'rule_test_' . uniqid() . '@example.com';
        $cedula = '1710034115';

        $paciente = $this->createRoleUser('paciente', [
            'email' => $email,
            'dni' => $cedula,
        ]);

        $this->assertDatabaseHas('users', ['id' => $paciente->id, 'email' => $email, 'dni' => $cedula]);
        $this->assertDatabaseHas('identity_documents', ['numero_documento' => $cedula]);

        // Delete user via Admin endpoint
        $response = $this->actingAs($admin)
            ->delete(route('admin.usuarios.destroy', $paciente));

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $paciente->id]);
        $this->assertDatabaseMissing('identity_documents', ['numero_documento' => $cedula]);

        // Evaluate GlobalUniqueCedulaRule directly with the same cedula
        $rule = new \App\Rules\GlobalUniqueCedulaRule(User::class, null, 'guardar_usuario', 'usuarios');
        $failed = false;
        $failCallback = function (string $message) use (&$failed) {
            $failed = true;
        };

        $rule->validate('dni', $cedula, $failCallback);

        $this->assertFalse($failed, 'GlobalUniqueCedulaRule should pass after user is deleted');

        // Simulate an orphan row manually inserted (e.g. from legacy DB state)
        \Illuminate\Support\Facades\DB::table('identity_documents')->insert([
            'documentable_type' => 'App\Models\User',
            'documentable_id' => 999999,
            'tipo_documento' => 'CEDULA',
            'pais' => 'EC',
            'numero_documento' => $cedula,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $failedOrphan = false;
        $rule->validate('dni', $cedula, function () use (&$failedOrphan) {
            $failedOrphan = true;
        });

        $this->assertFalse($failedOrphan, 'GlobalUniqueCedulaRule should ignore orphan identity_documents rows');

        // Cleanup orphan
        \Illuminate\Support\Facades\DB::table('identity_documents')->where('documentable_id', 999999)->delete();
    }
}