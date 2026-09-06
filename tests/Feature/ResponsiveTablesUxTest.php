<?php

namespace Tests\Feature;

use App\Models\DatabaseBackup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResponsiveTablesUxTest extends TestCase
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

    /**
     * TEST 1: Admin Usuarios conserva la tabla desktop con sus columnas canónicas.
     */
    public function test_admin_usuarios_preserves_desktop_table_structure(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente', ['name' => 'Test Paciente Red']);

        $response = $this->actingAs($admin)->get(route('admin.usuarios.index'));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('table users', $content);
        $this->assertStringContainsString('<th>Usuario</th>', $content);
        $this->assertStringContainsString('<th>Contacto</th>', $content);
        $this->assertStringContainsString('<th>Rol</th>', $content);
        $this->assertStringContainsString('<th>Estado</th>', $content);
        $this->assertStringContainsString('<th>Especialidad</th>', $content);
        $this->assertStringContainsString('Acciones', $content);
        $this->assertStringContainsString('Test Paciente Red', $content);
    }

    /**
     * TEST 2: En Admin Usuarios, la información identificadora esencial (Rol y Contacto)
     * está directamente disponible en la card mobile (no confinada a user-detail-cell oculta).
     */
    public function test_admin_usuarios_mobile_cards_display_essential_identifying_information(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente', [
            'name' => 'Identificable Mobile User',
            'email' => 'identificable_mobile@example.com',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.usuarios.index'));
        $response->assertOk();

        $content = $response->getContent();

        // En la representación mobile, Rol y Contacto deben estar inmediatamente disponibles
        // sin depender de la clase oculta 'user-detail-cell'
        $this->assertMatchesRegularExpression(
            '/<td[^>]*data-label=["\']Rol["\'](?!.*class=["\'][^"\']*user-detail-cell)/i',
            $content,
            'La celda de Rol no debe estar oculta en user-detail-cell para mostrarse de inmediato en mobile.'
        );

        $this->assertMatchesRegularExpression(
            '/<td[^>]*data-label=["\']Contacto["\'](?!.*class=["\'][^"\']*user-detail-cell)/i',
            $content,
            'La celda de Contacto no debe estar oculta en user-detail-cell para mostrarse de inmediato en mobile.'
        );

        $this->assertStringContainsString('identificable_mobile@example.com', $content);
    }

    /**
     * TEST 3: Admin Usuarios preserva formularios de acciones y no tiene IDs duplicados en el DOM.
     */
    public function test_admin_usuarios_preserves_action_forms_without_duplicate_ids(): void
    {
        $admin = $this->createRoleUser('administrador');
        $paciente = $this->createRoleUser('paciente');

        $response = $this->actingAs($admin)->get(route('admin.usuarios.index'));
        $response->assertOk();

        $content = $response->getContent();

        // Verificar que los formularios de acción existen
        $this->assertStringContainsString("id=\"delete-{$paciente->id}\"", $content);
        $this->assertStringContainsString("id=\"block-{$paciente->id}\"", $content);
        $this->assertStringContainsString("id=\"activate-{$paciente->id}\"", $content);
        $this->assertStringContainsString("id=\"deactivate-{$paciente->id}\"", $content);

        // Verificar que NO hay IDs duplicados en el DOM para ese usuario
        $this->assertEquals(1, substr_count($content, "id=\"delete-{$paciente->id}\""));
        $this->assertEquals(1, substr_count($content, "id=\"block-{$paciente->id}\""));
        $this->assertEquals(1, substr_count($content, "id=\"activate-{$paciente->id}\""));
    }

    /**
     * TEST 4: Superadmin Respaldos conserva la tabla desktop en viewport md+ (hidden md:block).
     */
    public function test_superadmin_respaldos_preserves_desktop_table_structure(): void
    {
        $superadmin = $this->createRoleUser('superadmin');
        $backup = DatabaseBackup::create([
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'type' => DatabaseBackup::TYPE_DAILY,
            'status' => DatabaseBackup::STATUS_VERIFIED,
            'disk' => 'r2_backups',
            'file_path' => 'database/automatic/daily/2026/08/backup-test.zip',
            'file_size' => 1024000,
            'sha256' => 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
            'duration_seconds' => 10.5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($superadmin)->get(route('superadmin.respaldos.index'));
        $response->assertOk();

        $content = $response->getContent();

        // La tabla desktop debe existir dentro de un contenedor responsive md+
        $this->assertMatchesRegularExpression('/class=["\'][^"\']*hidden\s+md:block[^"\']*["\']/i', $content);
        $this->assertStringContainsString('<th class="px-4 py-3">Tipo</th>', $content);
        $this->assertStringContainsString('<th class="px-4 py-3">Estado</th>', $content);
        $this->assertStringContainsString('<th class="px-4 py-3">Fecha (Guayaquil)</th>', $content);
        $this->assertStringContainsString('<th class="px-4 py-3">Tamaño</th>', $content);
        $this->assertStringContainsString('<th class="px-4 py-3">Duración</th>', $content);
        $this->assertStringContainsString('<th class="px-4 py-3">Solicitado por</th>', $content);
        $this->assertStringContainsString('<th class="px-4 py-3">SHA-256</th>', $content);
        $this->assertStringContainsString('<th class="px-4 py-3">Última Verificación</th>', $content);
        $this->assertStringContainsString('Acciones', $content);
    }

    /**
     * TEST 5: Superadmin Respaldos implementa una representación de tarjetas específica para mobile (md:hidden).
     */
    public function test_superadmin_respaldos_renders_mobile_card_stack_with_essential_data(): void
    {
        $superadmin = $this->createRoleUser('superadmin');
        $backup = DatabaseBackup::create([
            'uuid' => '22222222-3333-4444-5555-666666666666',
            'type' => DatabaseBackup::TYPE_MANUAL,
            'status' => DatabaseBackup::STATUS_COMPLETED,
            'disk' => 'r2_backups',
            'file_path' => 'database/manual/2026/08/backup-manual-test.zip',
            'file_size' => 2048000,
            'sha256' => 'fedcba0987654321fedcba0987654321fedcba0987654321fedcba0987654321',
            'duration_seconds' => 15.2,
            'user_id' => $superadmin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($superadmin)->get(route('superadmin.respaldos.index'));
        $response->assertOk();

        $content = $response->getContent();

        // Debe existir el bloque md:hidden con card stack para mobile
        $this->assertMatchesRegularExpression('/class=["\'][^"\']*md:hidden[^"\']*["\']/i', $content);
        $this->assertStringContainsString('data-backup-card', $content);
    }

    /**
     * TEST 6: En producción, las tarjetas mobile contienen las acciones funcionales de Descargar y Verificar.
     */
    public function test_superadmin_respaldos_mobile_cards_contain_functional_actions_in_production(): void
    {
        config(['app.mode' => 'production']);
        $superadmin = $this->createRoleUser('superadmin');
        $backup = DatabaseBackup::create([
            'uuid' => '33333333-4444-5555-6666-777777777777',
            'type' => DatabaseBackup::TYPE_DAILY,
            'status' => DatabaseBackup::STATUS_VERIFIED,
            'disk' => 'r2_backups',
            'file_path' => 'database/automatic/daily/backup.zip',
            'file_size' => 1024000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($superadmin)->get(route('superadmin.respaldos.index'));
        $response->assertOk();

        $content = $response->getContent();
        $downloadUrl = route('superadmin.respaldos.download', $backup->id);
        $verifyUrl = route('superadmin.respaldos.verify', $backup->id);

        $this->assertStringContainsString($downloadUrl, $content);
        $this->assertStringContainsString($verifyUrl, $content);
    }

    /**
     * TEST 7: En demo mode, las tarjetas mobile renderizan botones de demostración sin endpoints activos.
     */
    public function test_superadmin_respaldos_mobile_cards_respect_demo_mode_restrictions(): void
    {
        config(['app.mode' => 'demo']);
        $superadmin = $this->createRoleUser('superadmin');
        $backup = DatabaseBackup::create([
            'uuid' => '44444444-5555-6666-7777-888888888888',
            'type' => DatabaseBackup::TYPE_DAILY,
            'status' => DatabaseBackup::STATUS_VERIFIED,
            'disk' => 'r2_backups',
            'file_path' => 'database/automatic/daily/backup-demo.zip',
            'file_size' => 1024000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($superadmin)->get(route('superadmin.respaldos.index'));
        $response->assertOk();

        $content = $response->getContent();

        $this->assertStringNotContainsString(route('superadmin.respaldos.download', $backup->id), $content);
        $this->assertStringNotContainsString(route('superadmin.respaldos.verify', $backup->id), $content);
        $this->assertStringContainsString('Descargar', $content);
        $this->assertStringContainsString('Verificar', $content);
    }

    /**
     * TEST 8: Ambas vistas preservan la paginación como un bloque compartido único.
     */
    public function test_both_views_share_single_pagination_block(): void
    {
        $admin = $this->createRoleUser('administrador');
        $superadmin = $this->createRoleUser('superadmin');

        // Crear usuarios para activar paginación (per_page default es 12)
        User::factory()->count(15)->create();

        // Crear respaldos para activar paginación (per_page es 15)
        for ($i = 0; $i < 16; $i++) {
            DatabaseBackup::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'type' => DatabaseBackup::TYPE_DAILY,
                'status' => DatabaseBackup::STATUS_VERIFIED,
                'disk' => 'r2_backups',
                'file_path' => "database/backup-{$i}.zip",
                'file_size' => 1024000,
                'created_at' => now()->subMinutes($i),
                'updated_at' => now()->subMinutes($i),
            ]);
        }

        $adminResponse = $this->actingAs($admin)->get(route('admin.usuarios.index'));
        $adminResponse->assertOk();
        $adminContent = $adminResponse->getContent();

        $superResponse = $this->actingAs($superadmin)->get(route('superadmin.respaldos.index'));
        $superResponse->assertOk();
        $superContent = $superResponse->getContent();

        // Verificar que la paginación existe
        $this->assertStringContainsString('aria-label="Pagination Navigation"', $adminContent);
        $this->assertStringContainsString('aria-label="Pagination Navigation"', $superContent);

        // Verificar que NO está duplicada en el HTML (aparece exactamente 1 nav de paginación)
        $this->assertEquals(1, substr_count($adminContent, 'aria-label="Pagination Navigation"'));
        $this->assertEquals(1, substr_count($superContent, 'aria-label="Pagination Navigation"'));
    }
}
