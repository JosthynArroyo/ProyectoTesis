<?php

namespace Tests\Feature;

use App\Models\DatabaseBackup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoBackupFunctionalityTest extends TestCase
{
    use DatabaseTransactions;

    private function createSuperadmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'superadmin']);
        $superadmin = User::factory()->create();
        $superadmin->roles()->sync([$role->id]);

        return $superadmin;
    }

    private function createSampleBackup(): DatabaseBackup
    {
        return DatabaseBackup::create([
            'uuid' => (string) Str::uuid(),
            'type' => DatabaseBackup::TYPE_DAILY,
            'status' => DatabaseBackup::STATUS_COMPLETED,
            'disk' => 'r2_backups',
            'file_path' => 'database/automatic/daily/2026/08/backup.zip',
            'manifest_path' => 'database/automatic/daily/2026/08/backup.manifest.json',
            'file_size' => 2048,
            'sha256' => hash('sha256', 'test'),
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHours(2),
            'duration_seconds' => 10.0,
            'user_id' => null,
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);
    }

    /**
     * TEST 1: En APP_MODE=demo, el listado muestra respaldos con botones inertes (sin href ni action).
     */
    public function test_demo_mode_displays_inert_buttons_without_download_or_verify_actions(): void
    {
        config(['app.mode' => 'demo']);
        $superadmin = $this->createSuperadmin();
        $this->createSampleBackup();

        $response = $this->actingAs($superadmin)->get(route('superadmin.respaldos.index'));

        $response->assertOk();
        $response->assertSee('Al día');
        $content = $response->getContent();

        // En demo NO debe existir enlace href hacia descargar ni action hacia verificar
        $this->assertStringNotContainsString('/descargar', $content);
        $this->assertStringNotContainsString('/verificar', $content);

        // Los botones deben renderizarse como <button type="button">
        $this->assertStringContainsString('type="button"', $content);
        $this->assertStringContainsString('Descargar', $content);
        $this->assertStringContainsString('Verificar', $content);
    }

    /**
     * TEST 2: En APP_MODE=production, los botones conservan los endpoints y formularios reales.
     */
    public function test_production_mode_preserves_real_download_and_verify_actions(): void
    {
        config(['app.mode' => 'production']);
        $superadmin = $this->createSuperadmin();
        $backup = $this->createSampleBackup();

        $response = $this->actingAs($superadmin)->get(route('superadmin.respaldos.index'));

        $response->assertOk();
        $content = $response->getContent();

        // En production DEBE existir el href real de descarga y el action real de verificación
        $this->assertStringContainsString(route('superadmin.respaldos.download', $backup->id), $content);
        $this->assertStringContainsString(route('superadmin.respaldos.verify', $backup->id), $content);
        $this->assertStringContainsString('<form method="POST"', $content);
    }
}
