<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationModeService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DemoDatabaseConnectionIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private array $originalConfig = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConfig = [
            'app.mode' => config('app.mode'),
            'database.default' => config('database.default'),
            'database.connections' => config('database.connections'),
        ];
        foreach (['administrador', 'superadmin', 'doctor', 'paciente', 'laboratorio'] as $name) {
            Role::firstOrCreate(['name' => $name], ['label' => ucfirst($name)]);
        }
    }

    protected function tearDown(): void
    {
        if (! empty($this->originalConfig)) {
            config($this->originalConfig);
        }
        parent::tearDown();
    }

    /**
     * TEST 1: El nombre de la conexión por defecto se aísla automáticamente según APP_MODE.
     */
    public function test_default_database_connection_name_matches_mode(): void
    {
        $service = app(ApplicationModeService::class);

        config(['app.mode' => 'production']);
        $this->assertSame('mysql', $service->databaseConnectionName('mysql'));

        config(['app.mode' => 'demo']);
        $this->assertSame('mysql_demo', $service->databaseConnectionName('mysql'));
    }

    /**
     * TEST 2: Fail Closed — Si APP_MODE=demo y no hay DB_DEMO_DATABASE configurada o está vacía, aborta con excepción explícita.
     */
    public function test_fail_closed_when_demo_database_is_not_configured_or_empty(): void
    {
        $service = app(ApplicationModeService::class);

        config(['app.mode' => 'demo']);
        config(['database.default' => 'mysql_demo']);
        config(['database.connections.mysql_demo.database' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB_DEMO_DATABASE');

        $service->assertDemoDatabaseIsolation();
    }

    /**
     * TEST 3: Fail Closed — Si APP_MODE=demo y la base demo coincide con la base productiva, aborta inmediatamente.
     */
    public function test_fail_closed_when_demo_database_equals_production_database(): void
    {
        $service = app(ApplicationModeService::class);

        config(['app.mode' => 'demo']);
        config(['database.default' => 'mysql_demo']);
        config(['database.connections.mysql.host' => '127.0.0.1']);
        config(['database.connections.mysql.database' => 'proyecto_clinica_ja']);

        config(['database.connections.mysql_demo.host' => '127.0.0.1']);
        config(['database.connections.mysql_demo.database' => 'proyecto_clinica_ja']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no puede ser igual a DB_DATABASE');

        $service->assertDemoDatabaseIsolation();
    }

    /**
     * TEST 4: Fail Closed — Si la conexión demo configurada no existe en database.connections, aborta de forma segura.
     */
    public function test_fail_closed_when_demo_connection_is_not_defined(): void
    {
        $service = app(ApplicationModeService::class);

        config(['app.mode' => 'demo']);
        config(['database.connections.mysql_demo' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no está definida en database.connections');

        $service->assertDemoDatabaseIsolation();
    }

    /**
     * TEST 5: La configuración de database.php define la conexión mysql_demo con credenciales y parámetros de aislamiento.
     */
    public function test_mysql_demo_connection_is_configured_in_database_config(): void
    {
        $connections = config('database.connections');

        $this->assertArrayHasKey('mysql_demo', $connections);
        $this->assertSame('mysql', $connections['mysql_demo']['driver']);
        $this->assertArrayHasKey('database', $connections['mysql_demo']);
    }

    /**
     * TEST 6: En modo demo, DemoDatabaseIsolation ejecuta transacciones y rollback sobre la conexión activa.
     */
    public function test_demo_database_isolation_rolls_back_on_active_connection(): void
    {
        config(['app.mode' => 'demo']);
        $admin = $this->createRoleUser('administrador');
        $originalCount = User::count();

        $response = $this->actingAs($admin)->post(route('admin.usuarios.store'), [
            'name' => 'Usuario Temporal Demo',
            'email' => 'temporal.demo@invalid.test',
            'dni' => '09'.rand(10000000, 99999999),
            'password' => 'Temporal123*',
            'password_confirmation' => 'Temporal123*',
            'roles' => ['paciente'],
            'telefono' => '0999999999',
            'direccion' => 'Av. Principal',
            'sexo' => 'Masculino',
            'fecha_nacimiento' => '1995-01-01',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'temporal.demo@invalid.test']);
        $this->assertSame($originalCount, User::count());
    }

    /**
     * TEST 7: .env.example incluye la documentación de la variable obligatoria DB_DEMO_DATABASE.
     */
    public function test_env_example_documents_demo_database_configuration(): void
    {
        $envExampleContent = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('DB_DEMO_DATABASE=', $envExampleContent);
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
}
