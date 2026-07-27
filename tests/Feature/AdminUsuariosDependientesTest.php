<?php

namespace Tests\Feature;

use App\Models\Dependiente;
use App\Models\IdentityDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUsuariosDependientesTest extends TestCase
{
    use DatabaseTransactions;

    private array $createdUserEmails = [];

    public function test_admin_can_view_users_index_and_expand_the_correct_dependientes_without_n_plus_one(): void
    {
        $admin = $this->userWithRole('administrador', ['name' => 'Admin QA', 'email' => 'admin.qa@example.test']);
        $titularConDependientes = $this->userWithRole('paciente', [
            'name' => 'Josthyn Arroyo',
            'email' => 'josthyn.arroyo@example.test',
        ]);
        $titularSinDependientes = $this->userWithRole('paciente', [
            'name' => 'Paciente Sin Dependientes',
            'email' => 'sin.dependientes@example.test',
        ]);
        $otroTitular = $this->userWithRole('paciente', [
            'name' => 'Paciente B',
            'email' => 'paciente.b@example.test',
        ]);
        $doctor = $this->userWithRole('doctor', [
            'name' => 'Josthyn Doctor',
            'email' => 'doctor.qa@example.test',
        ]);
        $laboratorio = $this->userWithRole('laboratorio', [
            'name' => 'Laboratorio Clinico',
            'email' => 'laboratorio.qa@example.test',
        ]);

        Dependiente::create([
            'user_id' => $titularConDependientes->id,
            'nombre' => 'Anabel Arroyo',
            'dni' => '0987654329',
            'fecha_nacimiento' => '2010-06-15',
            'sexo' => 'Femenino',
            'parentesco' => 'hija',
            'activo' => true,
        ]);

        Dependiente::create([
            'user_id' => $otroTitular->id,
            'nombre' => 'Bruno Perez',
            'dni' => '0911122233',
            'fecha_nacimiento' => '2012-03-21',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
            'activo' => true,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($admin)->get(route('admin.usuarios.index'));

        $response->assertOk();
        $response->assertSee('data-dependent-toggle', false);
        $response->assertSee('data-confirm-form', false);
        $response->assertSee('Anabel Arroyo', false);
        $response->assertSee('No tiene pacientes dependientes asociados', false);
        $response->assertDontSee('dependientes-panel-'.$doctor->id, false);
        $response->assertDontSee('dependientes-panel-'.$laboratorio->id, false);

        $html = $response->getContent();

        $panelPrincipal = $this->extractPanelHtml($html, $titularConDependientes->id);
        $this->assertStringContainsString('Anabel Arroyo', $panelPrincipal);
        $this->assertStringContainsString('Documento', $panelPrincipal);
        $this->assertStringContainsString('Fecha de nacimiento', $panelPrincipal);
        $this->assertStringContainsString('Sexo', $panelPrincipal);
        $this->assertStringContainsString('Estado', $panelPrincipal);
        $this->assertStringNotContainsString('Bruno Perez', $panelPrincipal);

        $panelVacio = $this->extractPanelHtml($html, $titularSinDependientes->id);
        $this->assertStringContainsString('No tiene pacientes dependientes asociados', $panelVacio);

        $dependentQueries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $query) => str_contains($query, 'dependientes'))
            ->values();

        $this->assertCount(2, $dependentQueries, 'La pagina de usuarios no debe cargar dependientes por cada fila.');
        DB::disableQueryLog();
    }

    public function test_non_authorized_user_cannot_access_users_index(): void
    {
        $patient = $this->userWithRole('paciente', [
            'name' => 'Paciente QA',
            'email' => 'paciente.qa@example.test',
        ]);

        $this->actingAs($patient)
            ->get(route('admin.usuarios.index'))
            ->assertRedirect('/');
    }

    public function test_local_cleanup_preserves_valid_users_and_resets_auto_increment(): void
    {
        $this->cleanupCreatedUsers();
        $this->purgeExistingValidUsers();

        $this->insertFixedUser(1, 'superadmin', [
            'name' => 'Superadmin',
            'email' => 'superadmin@clinic.test',
        ]);
        $this->insertFixedUser(2, 'administrador', [
            'name' => 'Josthyn Admin',
            'email' => 'manuellandazuri778@gmail.com',
        ]);
        $patient = $this->insertFixedUser(3, 'paciente', [
            'name' => 'Josthyn Arroyo',
            'email' => 'josthynarroyo627@gmail.com',
        ]);
        $this->insertFixedUser(4, 'doctor', [
            'name' => 'Josthyn Doctor',
            'email' => 'alejandroucenriquez@gmail.com',
        ]);
        $this->insertFixedUser(5, 'laboratorio', [
            'name' => 'Laboratorio Clinico',
            'email' => 'laboratorio@clinic.test',
        ]);

        Dependiente::create([
            'user_id' => $patient->id,
            'nombre' => 'Anabel Arroyo',
            'dni' => '0987654329',
            'fecha_nacimiento' => '2010-06-15',
            'sexo' => 'Femenino',
            'parentesco' => 'hija',
            'activo' => true,
        ]);

        $alienOne = $this->userWithRole(null, [
            'name' => 'Usuario Falso 1',
            'email' => 'falso1@example.test',
        ]);
        $alienTwo = $this->userWithRole(null, [
            'name' => 'Usuario Falso 2',
            'email' => 'falso2@example.test',
        ]);

        $alienUsers = User::query()
            ->with('roles')
            ->where('id', '>', 5)
            ->orderBy('id')
            ->get();

        foreach ($alienUsers as $user) {
            $user->roles()->detach();
            $user->especialidades()->detach();
            DB::table('users')->where('id', $user->id)->delete();
        }

        DB::getPdo()->exec('ALTER TABLE users AUTO_INCREMENT = 6');

        $this->assertSame(5, (int) User::query()->max('id'));
        $this->assertSame(5, User::query()->count());
        $this->assertDatabaseMissing('users', ['id' => $alienOne->id]);
        $this->assertDatabaseMissing('users', ['id' => $alienTwo->id]);

        $users = User::query()->with(['roles', 'dependientes'])->whereBetween('id', [1, 5])->orderBy('id')->get();
        $this->assertCount(5, $users);
        $this->assertTrue($users->firstWhere('id', 1)->hasRole('superadmin'));
        $this->assertTrue($users->firstWhere('id', 2)->hasRole('administrador'));
        $this->assertTrue($users->firstWhere('id', 3)->hasRole('paciente'));
        $this->assertTrue($users->firstWhere('id', 4)->hasRole('doctor'));
        $this->assertTrue($users->firstWhere('id', 5)->hasRole('laboratorio'));

        $autoIncrement = DB::selectOne("SELECT AUTO_INCREMENT AS next_id FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'");
        $this->assertSame(6, (int) ($autoIncrement->next_id ?? 0));
    }

    private function userWithRole(?string $roleName, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => User::STATUS_ACTIVE,
            'suspended_until' => null,
        ], $attributes));

        if ($roleName !== null) {
            $role = Role::query()->firstOrCreate(['name' => $roleName]);
            $user->roles()->sync([$role->id]);
        }

        $this->createdUserEmails[] = $user->email;

        return $user;
    }

    private function insertFixedUser(int $id, string $roleName, array $attributes = []): User
    {
        $now = now();
        DB::table('identity_documents')
            ->where('documentable_type', User::class)
            ->where('documentable_id', $id)
            ->delete();

        DB::table('users')->insert(array_merge([
            'id' => $id,
            'name' => $attributes['name'] ?? sprintf('User %d', $id),
            'email' => $attributes['email'] ?? sprintf('user%d@example.test', $id),
            'password' => Hash::make('password'),
            'active' => true,
            'remember_token' => 'fixedtoken'.$id,
            'theme_preference' => 'light',
            'moneda' => 'USD',
            'status' => User::STATUS_ACTIVE,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_diff_key($attributes, array_flip(['name', 'email']))));

        $role = Role::query()->firstOrCreate(['name' => $roleName]);
        DB::table('role_user')->insert([
            'user_id' => $id,
            'role_id' => $role->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return User::query()->findOrFail($id);
    }

    protected function tearDown(): void
    {
        $this->cleanupCreatedUsers();

        DB::disableQueryLog();

        parent::tearDown();
    }

    private function cleanupCreatedUsers(): void
    {
        $emails = array_values(array_unique(array_filter($this->createdUserEmails)));
        if ($emails === []) {
            return;
        }

        $users = User::query()->with(['roles', 'especialidades'])->whereIn('email', $emails)->get();

        foreach ($users as $user) {
            IdentityDocument::query()
                ->where('documentable_type', get_class($user))
                ->where('documentable_id', $user->id)
                ->delete();
            $user->roles()->detach();
            $user->especialidades()->detach();
            DB::table('users')->where('id', $user->id)->delete();
        }

        $this->createdUserEmails = [];
    }

    private function purgeExistingValidUsers(): void
    {
        $validEmails = [
            'superadmin@clinic.test',
            'manuellandazuri778@gmail.com',
            'josthynarroyo627@gmail.com',
            'alejandroucenriquez@gmail.com',
            'laboratorio@clinic.test',
        ];

        $users = User::query()
            ->with(['roles', 'especialidades'])
            ->whereIn('email', $validEmails)
            ->orWhereBetween('id', [1, 5])
            ->get();

        foreach ($users as $user) {
            IdentityDocument::query()
                ->where('documentable_type', get_class($user))
                ->where('documentable_id', $user->id)
                ->delete();
            $user->roles()->detach();
            $user->especialidades()->detach();
            DB::table('users')->where('id', $user->id)->delete();
        }
    }

    private function extractPanelHtml(string $html, int $userId): string
    {
        $pattern = sprintf('/<tr id="dependientes-panel-%d"[^>]*>(.*?)<\/tr>/s', $userId);

        if (! preg_match($pattern, $html, $matches)) {
            $this->fail("No se encontro el panel de dependientes para el usuario {$userId}.");
        }

        return $matches[1];
    }
}
