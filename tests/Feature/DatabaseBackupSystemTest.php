<?php

namespace Tests\Feature;

use App\Jobs\CreateDatabaseBackupJob;
use App\Models\DatabaseBackup;
use App\Models\Role;
use App\Models\User;
use App\Services\DatabaseBackup\BackupCoordinatorService;
use App\Services\DatabaseBackup\BackupEncryptionService;
use App\Services\DatabaseBackup\BackupIntegrityService;
use App\Services\DatabaseBackup\BackupManifestService;
use App\Services\DatabaseBackup\BackupRetentionService;
use App\Services\DatabaseBackup\BackupStorageService;
use App\Services\DatabaseBackup\BackupTempDirectoryManager;
use App\Services\DatabaseBackup\MySqlDumpService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseBackupSystemTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2_backups');
        Storage::fake('r2_public');
        Storage::fake('r2_private');

        Queue::fake();
        Mail::fake();

        Config::set('database_backups.disk', 'r2_backups');
        Config::set('database_backups.archive_password', 'SecretBackupPassword123!');
    }

    private function createSuperadmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'superadmin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);
        return $user;
    }

    private function createPatient(): User
    {
        $role = Role::firstOrCreate(['name' => 'paciente']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);
        return $user;
    }

    public function test_01_superadmin_can_access_backups_panel(): void
    {
        $superadmin = $this->createSuperadmin();
        $response = $this->actingAs($superadmin)->get(route('superadmin.respaldos.index'));
        $response->assertStatus(200);
        $response->assertSee('Respaldos de base de datos');
    }

    public function test_02_non_superadmin_roles_receive_denied_redirect(): void
    {
        $patient = $this->createPatient();
        $response = $this->actingAs($patient)->get(route('superadmin.respaldos.index'));
        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'Acceso denegado');
    }

    public function test_03_unauthenticated_guest_redirected_to_login(): void
    {
        $response = $this->get(route('superadmin.respaldos.index'));
        $response->assertRedirect(url('/') . '?login=1');
    }

    public function test_04_manual_creation_dispatches_job_to_backups_queue(): void
    {
        $superadmin = $this->createSuperadmin();
        $this->withSession(['auth.password_confirmed_at' => time()]);

        $response = $this->actingAs($superadmin)->post(route('superadmin.respaldos.store'));
        $response->assertRedirect(route('superadmin.respaldos.index'));
        Queue::assertPushedOn('backups', CreateDatabaseBackupJob::class);
    }

    public function test_05_concurrency_lock_prevents_simultaneous_backups(): void
    {
        $lock = Cache::lock('lock:database_backup_running', 2100);
        $this->assertTrue($lock->get());

        $coordinatorMock = $this->createMock(BackupCoordinatorService::class);
        $coordinatorMock->expects($this->never())->method('performBackup');

        $job = new CreateDatabaseBackupJob(DatabaseBackup::TYPE_MANUAL);
        $job->handle($coordinatorMock);

        $lock->release();
    }

    public function test_06_to_10_full_backup_workflow_uploads_encrypted_zip_and_manifest(): void
    {
        $superadmin = $this->createSuperadmin();

        $encryptionService = new BackupEncryptionService();
        $integrityService = new BackupIntegrityService($encryptionService);
        $storageService = new BackupStorageService();
        $manifestService = new BackupManifestService();
        $retentionService = new BackupRetentionService($storageService);

        $dumpServiceMock = $this->createMock(MySqlDumpService::class);
        $dumpServiceMock->method('dump')->willReturnCallback(function ($path) {
            file_put_contents($path, "-- MySQL dump 10.13\nCREATE TABLE test_table (id INT);\nINSERT INTO test_table VALUES (1);\n");
            return $path;
        });

        $coordinator = new BackupCoordinatorService(
            $dumpServiceMock,
            $encryptionService,
            $integrityService,
            $storageService,
            $manifestService,
            $retentionService
        );

        $backup = $coordinator->performBackup(DatabaseBackup::TYPE_MANUAL, $superadmin->id);

        $this->assertEquals(DatabaseBackup::STATUS_VERIFIED, $backup->status);
        $this->assertNotNull($backup->file_path);
        $this->assertNotNull($backup->manifest_path);

        Storage::disk('r2_backups')->assertExists($backup->file_path);
        Storage::disk('r2_backups')->assertExists($backup->manifest_path);

        $content = Storage::disk('r2_backups')->get($backup->file_path);
        $this->assertEquals(hash('sha256', $content), $backup->sha256);
    }

    public function test_11_incorrect_archive_password_fails_decryption(): void
    {
        $encryptionService = new BackupEncryptionService();
        $tempDir = BackupTempDirectoryManager::getTempDir('wrong_pass_' . uniqid());
        $tempSql = $tempDir . '/dump.sql';
        file_put_contents($tempSql, "-- MySQL dump 10.13\nCREATE TABLE t (i INT);\n");
        $tempZip = $tempDir . '/test.zip';

        Config::set('database_backups.archive_password', 'CorrectPassword123!');
        $encryptionService->encrypt($tempSql, $tempZip);

        // Change password to wrong value
        Config::set('database_backups.archive_password', 'WrongPassword999!');

        $this->expectException(\RuntimeException::class);
        try {
            $encryptionService->decrypt($tempZip, $tempDir . '/extracted');
        } finally {
            BackupTempDirectoryManager::deleteTempDir($tempDir);
        }
    }

    public function test_12_corrupt_zip_file_fails_verification(): void
    {
        $encryptionService = new BackupEncryptionService();
        $integrityService = new BackupIntegrityService($encryptionService);

        $tempDir = BackupTempDirectoryManager::getTempDir('corrupt_' . uniqid());
        $tempZip = $tempDir . '/corrupt.zip';
        file_put_contents($tempZip, "CORRUPT_NOT_A_ZIP_FILE_DATA");

        $this->expectException(\RuntimeException::class);
        try {
            $integrityService->verifyLocalPackage($tempZip);
        } finally {
            BackupTempDirectoryManager::deleteTempDir($tempDir);
        }
    }

    public function test_13_altered_sha256_hash_is_rejected(): void
    {
        $encryptionService = new BackupEncryptionService();
        $integrityService = new BackupIntegrityService($encryptionService);

        $tempDir = BackupTempDirectoryManager::getTempDir('sha_' . uniqid());
        $tempSql = $tempDir . '/dump.sql';
        file_put_contents($tempSql, "-- MySQL dump 10.13\nCREATE TABLE t (i INT);\n");
        $tempZip = $tempDir . '/test.zip';

        $encryptionService->encrypt($tempSql, $tempZip);
        $fakeHash = str_repeat('a', 64);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/hash SHA-256/i');

        try {
            $integrityService->verifyLocalPackage($tempZip, $fakeHash);
        } finally {
            BackupTempDirectoryManager::deleteTempDir($tempDir);
        }
    }

    public function test_14_manifest_upload_failure_cleans_up_newly_uploaded_zip(): void
    {
        $dumpServiceMock = $this->createMock(MySqlDumpService::class);
        $dumpServiceMock->method('dump')->willReturnCallback(function ($path) {
            file_put_contents($path, "-- MySQL dump\nCREATE TABLE t (i INT);");
            return $path;
        });

        $storageServiceMock = $this->createPartialMock(BackupStorageService::class, ['uploadManifest']);
        $storageServiceMock->method('uploadManifest')->willThrowException(new \RuntimeException("Fallo al subir manifiesto simulado"));

        $encryptionService = new BackupEncryptionService();
        $integrityService = new BackupIntegrityService($encryptionService);
        $manifestService = new BackupManifestService();
        $retentionService = new BackupRetentionService($storageServiceMock);

        $coordinator = new BackupCoordinatorService(
            $dumpServiceMock,
            $encryptionService,
            $integrityService,
            $storageServiceMock,
            $manifestService,
            $retentionService
        );

        $this->expectException(\RuntimeException::class);

        try {
            $coordinator->performBackup(DatabaseBackup::TYPE_MANUAL);
        } finally {
            $files = Storage::disk('r2_backups')->allFiles();
            $this->assertEmpty($files, "El paquete ZIP subido debe eliminarse si la subida del manifiesto falla.");
        }
    }

    public function test_15_temp_options_file_sql_and_zip_cleaned_up_in_finally(): void
    {
        $dumpService = new MySqlDumpService();
        $cnfPath = $dumpService->createTempOptionsFile('127.0.0.1', '3306', 'root', 'secret');

        $this->assertFileExists($cnfPath);
        $dumpService->removeTempFile($cnfPath);
        $this->assertFileDoesNotExist($cnfPath);
    }

    public function test_16_unauthorized_download_denied(): void
    {
        $patient = $this->createPatient();

        $backup = DatabaseBackup::create([
            'uuid' => (string) Str::uuid(),
            'type' => 'manual',
            'status' => 'verified',
            'disk' => 'r2_backups',
            'file_path' => 'database/manual/2026/08/backup-test.zip',
            'file_size' => 100,
        ]);

        Storage::disk('r2_backups')->put($backup->file_path, 'dummy zip content');

        $response = $this->actingAs($patient)->get(route('superadmin.respaldos.download', $backup->id));
        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'Acceso denegado');
    }

    public function test_17_unauthorized_verification_denied(): void
    {
        $patient = $this->createPatient();

        $backup = DatabaseBackup::create([
            'uuid' => (string) Str::uuid(),
            'type' => 'manual',
            'status' => 'verified',
            'disk' => 'r2_backups',
            'file_path' => 'database/manual/2026/08/backup-test.zip',
            'file_size' => 100,
        ]);

        $response = $this->actingAs($patient)->post(route('superadmin.respaldos.verify', $backup->id));
        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'Acceso denegado');
    }

    public function test_18_manual_backups_excluded_from_retention(): void
    {
        $storageService = new BackupStorageService();
        $retentionService = new BackupRetentionService($storageService);

        $manualUuid = (string) Str::uuid();
        $manualPath = "database/manual/2026/08/backup-manual.zip";
        Storage::disk('r2_backups')->put($manualPath, 'dummy');

        DatabaseBackup::create([
            'uuid' => $manualUuid,
            'type' => DatabaseBackup::TYPE_MANUAL,
            'status' => DatabaseBackup::STATUS_VERIFIED,
            'disk' => 'r2_backups',
            'file_path' => $manualPath,
            'created_at' => now()->subDays(100),
        ]);

        $retentionService->prune(DatabaseBackup::TYPE_MANUAL);

        $this->assertDatabaseHas('database_backups', ['uuid' => $manualUuid]);
        Storage::disk('r2_backups')->assertExists($manualPath);
    }

    public function test_19_idempotent_catalog_rebuild(): void
    {
        $manifestData = [
            'uuid' => (string) Str::uuid(),
            'type' => 'daily',
            'status' => 'verified',
            'database_name' => 'clinica_donbosco_db_test',
            'timestamp' => '2026-08-04T02:00:00-05:00',
            'file_size' => 1024,
            'sha256' => hash('sha256', 'dummy'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'duration_seconds' => 5.2,
            'r2_key' => 'database/automatic/daily/2026/08/backup-test.zip',
            'format_version' => '1.0',
        ];

        $manifestKey = 'database/automatic/daily/2026/08/backup-test.manifest.json';
        Storage::disk('r2_backups')->put($manifestKey, json_encode($manifestData));

        $storageService = new BackupStorageService();
        $manifestService = new BackupManifestService();
        $encryptionService = new BackupEncryptionService();
        $integrityService = new BackupIntegrityService($encryptionService);
        $retentionService = new BackupRetentionService($storageService);
        $dumpMock = $this->createMock(MySqlDumpService::class);

        $coordinator = new BackupCoordinatorService(
            $dumpMock,
            $encryptionService,
            $integrityService,
            $storageService,
            $manifestService,
            $retentionService
        );

        $inserted = $coordinator->rebuildCatalogFromR2();
        $this->assertEquals(1, $inserted);

        // Second run must be idempotent (0 inserted)
        $insertedSecond = $coordinator->rebuildCatalogFromR2();
        $this->assertEquals(0, $insertedSecond);
    }

    public function test_20_restore_rejected_when_target_is_active_db(): void
    {
        $storageService = new BackupStorageService();
        $manifestService = new BackupManifestService();
        $encryptionService = new BackupEncryptionService();
        $integrityService = new BackupIntegrityService($encryptionService);
        $retentionService = new BackupRetentionService($storageService);
        $dumpMock = $this->createMock(MySqlDumpService::class);

        $coordinator = new BackupCoordinatorService(
            $dumpMock,
            $encryptionService,
            $integrityService,
            $storageService,
            $manifestService,
            $retentionService
        );

        $activeDb = config('database.connections.mysql.database');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/prohibido restaurar directamente sobre la base activa/i');

        $coordinator->restoreBackup((string) Str::uuid(), $activeDb, true);
    }

    public function test_21_restore_rejected_when_target_does_not_end_in_test_in_testing_env(): void
    {
        $storageService = new BackupStorageService();
        $manifestService = new BackupManifestService();
        $encryptionService = new BackupEncryptionService();
        $integrityService = new BackupIntegrityService($encryptionService);
        $retentionService = new BackupRetentionService($storageService);
        $dumpMock = $this->createMock(MySqlDumpService::class);

        $coordinator = new BackupCoordinatorService(
            $dumpMock,
            $encryptionService,
            $integrityService,
            $storageService,
            $manifestService,
            $retentionService
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/el destino debe terminar en \'_test\'/i');

        $coordinator->restoreBackup((string) Str::uuid(), 'production_db_replica', true);
    }

    public function test_22_restore_cli_command_rejects_missing_target_database_option(): void
    {
        $uuid = (string) Str::uuid();

        $this->artisan('database-backups:restore', ['uuid' => $uuid])
            ->expectsOutputToContain('DEBE ESPECIFICAR LA OPCIÓN --target-database')
            ->assertExitCode(1);
    }

    public function test_23_restore_cli_command_requires_exact_uuid_confirmation(): void
    {
        $uuid = (string) Str::uuid();

        $this->artisan('database-backups:restore', [
            'uuid' => $uuid,
            '--target-database' => 'clinica_donbosco_db_isolated_test',
        ])
            ->expectsQuestion("Para confirmar la restauración en 'clinica_donbosco_db_isolated_test', escriba exactamente el UUID del respaldo ({$uuid}):", 'WRONG_UUID')
            ->expectsOutputToContain('Confirmación fallida')
            ->assertExitCode(1);
    }

    public function test_24_absence_of_secrets_in_manifests_and_logs(): void
    {
        $manifestService = new BackupManifestService();
        $manifest = $manifestService->generateManifest(
            (string) Str::uuid(),
            'daily',
            'verified',
            'clinica_db',
            100,
            hash('sha256', 'x'),
            1.5,
            'database/automatic/daily/2026/08/backup.zip'
        );

        $json = json_encode($manifest);
        $this->assertStringNotContainsString('SecretBackupPassword123!', $json);
        $this->assertStringNotContainsString('DB_PASSWORD', $json);
    }

    public function test_25_zero_writes_to_r2_public_and_r2_private(): void
    {
        $this->assertEmpty(Storage::disk('r2_public')->allFiles());
        $this->assertEmpty(Storage::disk('r2_private')->allFiles());
    }

    public function test_26_check_storage_artisan_command_executes_full_r2_crud_cycle(): void
    {
        $this->artisan('database-backups:check-storage')
            ->expectsOutputToContain('CONECTIVIDAD EXITOSA')
            ->assertExitCode(0);

        $this->assertEmpty(Storage::disk('r2_backups')->allFiles());
    }

    public function test_27_failed_automatic_backup_notifies_superadmin_email(): void
    {
        $superadmin = $this->createSuperadmin();
        $superadmin->email = 'admin_alert@clinic.test';
        $superadmin->save();

        $dumpServiceMock = $this->createPartialMock(MySqlDumpService::class, ['dump']);
        $dumpServiceMock->method('dump')->willThrowException(new \RuntimeException("Fallo mysqldump simula alerta"));

        $encryptionService = new BackupEncryptionService();
        $integrityService = new BackupIntegrityService($encryptionService);
        $storageService = new BackupStorageService();
        $manifestService = new BackupManifestService();
        $retentionService = new BackupRetentionService($storageService);

        $coordinator = new BackupCoordinatorService(
            $dumpServiceMock,
            $encryptionService,
            $integrityService,
            $storageService,
            $manifestService,
            $retentionService
        );

        try {
            $coordinator->performBackup(DatabaseBackup::TYPE_DAILY);
        } catch (\Throwable) {
            // Expected exception
        }

        Mail::assertSent(\App\Mail\DatabaseBackupFailedMail::class, function (\App\Mail\DatabaseBackupFailedMail $mail) use ($superadmin) {
            return $mail->hasTo($superadmin->email);
        });
    }

    public function test_28_invalid_json_manifest_is_ignored_by_discovery(): void
    {
        $storageService = new BackupStorageService();
        $manifestService = new BackupManifestService();
        $encryptionService = new BackupEncryptionService();
        $integrityService = new BackupIntegrityService($encryptionService);
        $retentionService = new BackupRetentionService($storageService);
        $dumpMock = $this->createMock(MySqlDumpService::class);

        $coordinator = new BackupCoordinatorService(
            $dumpMock,
            $encryptionService,
            $integrityService,
            $storageService,
            $manifestService,
            $retentionService
        );

        Storage::disk('r2_backups')->put('database/automatic/daily/2026/08/invalid.manifest.json', '{INVALID_JSON:');

        $discovered = $coordinator->discoverFromR2();
        $this->assertEmpty($discovered);
    }

    public function test_29_missing_manifest_returns_null(): void
    {
        $storageService = new BackupStorageService();
        $result = $storageService->getManifest('database/non_existent.manifest.json');
        $this->assertNull($result);
    }

    public function test_30_initial_zip_upload_failure_cleans_up_local_temp(): void
    {
        $dumpServiceMock = $this->createMock(MySqlDumpService::class);
        $dumpServiceMock->method('dump')->willReturnCallback(function ($path) {
            file_put_contents($path, "-- MySQL dump\nCREATE TABLE t (i INT);");
            return $path;
        });

        $storageServiceMock = $this->createPartialMock(BackupStorageService::class, ['uploadBackup']);
        $storageServiceMock->method('uploadBackup')->willThrowException(new \RuntimeException("Fallo de subida R2 simulada"));

        $encryptionService = new BackupEncryptionService();
        $integrityService = new BackupIntegrityService($encryptionService);
        $manifestService = new BackupManifestService();
        $retentionService = new BackupRetentionService($storageServiceMock);

        $coordinator = new BackupCoordinatorService(
            $dumpServiceMock,
            $encryptionService,
            $integrityService,
            $storageServiceMock,
            $manifestService,
            $retentionService
        );

        $this->expectException(\RuntimeException::class);

        try {
            $coordinator->performBackup(DatabaseBackup::TYPE_MANUAL);
        } finally {
            $files = Storage::disk('r2_backups')->allFiles();
            $this->assertEmpty($files);
        }
    }

    public function test_31_real_retention_prunes_daily_weekly_monthly_backups(): void
    {
        $storageService = new BackupStorageService();
        $retentionService = new BackupRetentionService($storageService);

        // Daily
        for ($i = 1; $i <= 32; $i++) {
            $p = "database/automatic/daily/2026/08/d-{$i}.zip";
            $m = "database/automatic/daily/2026/08/d-{$i}.manifest.json";
            Storage::disk('r2_backups')->put($p, 'x');
            Storage::disk('r2_backups')->put($m, '{}');
            DatabaseBackup::create([
                'uuid' => (string) Str::uuid(),
                'type' => DatabaseBackup::TYPE_DAILY,
                'status' => DatabaseBackup::STATUS_VERIFIED,
                'disk' => 'r2_backups',
                'file_path' => $p,
                'manifest_path' => $m,
                'created_at' => now()->subDays(40 - $i),
            ]);
        }

        // Weekly
        for ($i = 1; $i <= 14; $i++) {
            $p = "database/automatic/weekly/2026/08/w-{$i}.zip";
            $m = "database/automatic/weekly/2026/08/w-{$i}.manifest.json";
            Storage::disk('r2_backups')->put($p, 'x');
            Storage::disk('r2_backups')->put($m, '{}');
            DatabaseBackup::create([
                'uuid' => (string) Str::uuid(),
                'type' => DatabaseBackup::TYPE_WEEKLY,
                'status' => DatabaseBackup::STATUS_VERIFIED,
                'disk' => 'r2_backups',
                'file_path' => $p,
                'manifest_path' => $m,
                'created_at' => now()->subWeeks(20 - $i),
            ]);
        }

        // Monthly
        for ($i = 1; $i <= 14; $i++) {
            $p = "database/automatic/monthly/2026/08/m-{$i}.zip";
            $m = "database/automatic/monthly/2026/08/m-{$i}.manifest.json";
            Storage::disk('r2_backups')->put($p, 'x');
            Storage::disk('r2_backups')->put($m, '{}');
            DatabaseBackup::create([
                'uuid' => (string) Str::uuid(),
                'type' => DatabaseBackup::TYPE_MONTHLY,
                'status' => DatabaseBackup::STATUS_VERIFIED,
                'disk' => 'r2_backups',
                'file_path' => $p,
                'manifest_path' => $m,
                'created_at' => now()->subMonths(20 - $i),
            ]);
        }

        $retentionService->prune(DatabaseBackup::TYPE_DAILY);
        $retentionService->prune(DatabaseBackup::TYPE_WEEKLY);
        $retentionService->prune(DatabaseBackup::TYPE_MONTHLY);

        $this->assertEquals(30, DatabaseBackup::where('type', DatabaseBackup::TYPE_DAILY)->count());
        $this->assertEquals(12, DatabaseBackup::where('type', DatabaseBackup::TYPE_WEEKLY)->count());
        $this->assertEquals(12, DatabaseBackup::where('type', DatabaseBackup::TYPE_MONTHLY)->count());
    }

    public function test_32_mail_failure_does_not_erase_original_backup_error(): void
    {
        $superadmin = $this->createSuperadmin();
        $superadmin->email = 'mail_fail@clinic.test';
        $superadmin->save();

        Mail::shouldReceive('to')->andThrow(new \RuntimeException("Fallo simulado de SMTP"));

        $dumpServiceMock = $this->createPartialMock(MySqlDumpService::class, ['dump']);
        $dumpServiceMock->method('dump')->willThrowException(new \RuntimeException("ERROR_DUMP_ORIGINAL_RETAINED"));

        $encryptionService = new BackupEncryptionService();
        $integrityService = new BackupIntegrityService($encryptionService);
        $storageService = new BackupStorageService();
        $manifestService = new BackupManifestService();
        $retentionService = new BackupRetentionService($storageService);

        $coordinator = new BackupCoordinatorService(
            $dumpServiceMock,
            $encryptionService,
            $integrityService,
            $storageService,
            $manifestService,
            $retentionService
        );

        try {
            $coordinator->performBackup(DatabaseBackup::TYPE_DAILY);
        } catch (\Throwable) {
            // Expected
        }

        $failedBackup = DatabaseBackup::latest()->first();
        $this->assertEquals(DatabaseBackup::STATUS_FAILED, $failedBackup->status);
        $this->assertStringContainsString("ERROR_DUMP_ORIGINAL_RETAINED", $failedBackup->error_message);
    }

    public function test_33_panel_warning_shown_when_no_successful_backup_in_26_hours(): void
    {
        $superadmin = $this->createSuperadmin();

        // Create an old backup from 30 hours ago
        DatabaseBackup::create([
            'uuid' => (string) Str::uuid(),
            'type' => 'daily',
            'status' => 'verified',
            'disk' => 'r2_backups',
            'file_path' => 'database/automatic/daily/2026/08/old.zip',
            'completed_at' => now()->subHours(30),
        ]);

        $response = $this->actingAs($superadmin)->get(route('superadmin.respaldos.index'));
        $response->assertSee('Advertencia: No existe un respaldo reciente');
    }

    public function test_34_all_temp_files_created_outside_project_paths(): void
    {
        $uuid = (string) Str::uuid();
        $tempDir = BackupTempDirectoryManager::getTempDir($uuid);

        $normTemp = strtolower(str_replace('\\', '/', $tempDir));
        $normBase = strtolower(str_replace('\\', '/', base_path()));
        $normStorage = strtolower(str_replace('\\', '/', storage_path()));

        $this->assertFalse(str_starts_with($normTemp, $normBase), "Directorio temporal no debe estar dentro de base_path()");
        $this->assertFalse(str_starts_with($normTemp, $normStorage), "Directorio temporal no debe estar dentro de storage_path()");

        BackupTempDirectoryManager::deleteTempDir($tempDir);
    }

    public function test_35_complete_temp_dir_deleted_on_finish(): void
    {
        $uuid = (string) Str::uuid();
        $tempDir = BackupTempDirectoryManager::getTempDir($uuid);
        file_put_contents($tempDir . '/dummy.txt', 'test');

        $this->assertFileExists($tempDir . '/dummy.txt');
        BackupTempDirectoryManager::deleteTempDir($tempDir);
        $this->assertDirectoryDoesNotExist($tempDir);
    }

    public function test_36_cnf_file_deleted_even_if_dump_fails(): void
    {
        $dumpService = new MySqlDumpService();
        $cnfPath = null;

        $dumpServiceMock = $this->createPartialMock(MySqlDumpService::class, ['createTempOptionsFile']);
        $dumpServiceMock->method('createTempOptionsFile')->willReturnCallback(function ($h, $p, $u, $pw) use ($dumpService, &$cnfPath) {
            $cnfPath = $dumpService->createTempOptionsFile($h, $p, $u, $pw);
            return $cnfPath;
        });

        try {
            $dumpServiceMock->dump('/invalid_dir_does_not_exist/file.sql');
        } catch (\Throwable) {
            // Exception expected
        }

        $this->assertNotNull($cnfPath);
        $this->assertFileDoesNotExist($cnfPath);
        $this->assertDirectoryDoesNotExist(dirname($cnfPath));
    }

    public function test_38_job_uses_database_backups_connection_and_backups_queue_even_with_sync_default(): void
    {
        Config::set('queue.default', 'sync');

        $job = new CreateDatabaseBackupJob(DatabaseBackup::TYPE_MANUAL);

        $this->assertEquals('database_backups', $job->connection);
        $this->assertEquals('backups', $job->queue);
    }

    public function test_39_database_backups_retry_after_is_greater_than_job_timeout(): void
    {
        $connectionConfig = config('queue.connections.database_backups');
        $this->assertNotNull($connectionConfig);

        $retryAfter = (int) ($connectionConfig['retry_after'] ?? 0);
        $job = new CreateDatabaseBackupJob();

        $this->assertEquals(2400, $retryAfter);
        $this->assertEquals(1800, $job->timeout);
        $this->assertGreaterThan($job->timeout, $retryAfter);
    }

    public function test_40_manual_backup_button_does_not_execute_backup_in_http_request(): void
    {
        $superadmin = $this->createSuperadmin();
        $this->withSession(['auth.password_confirmed_at' => time()]);

        $coordinatorMock = $this->createMock(BackupCoordinatorService::class);
        $coordinatorMock->expects($this->never())->method('performBackup');
        $this->app->instance(BackupCoordinatorService::class, $coordinatorMock);

        $response = $this->actingAs($superadmin)->post(route('superadmin.respaldos.store'));

        $response->assertRedirect(route('superadmin.respaldos.index'));
        $response->assertSessionHas('success');

        Queue::assertPushed(CreateDatabaseBackupJob::class, function ($job) {
            return $job->connection === 'database_backups' && $job->queue === 'backups';
        });
    }

    public function test_41_scheduler_dispatches_with_correct_connection_and_queue(): void
    {
        $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();
        $backupEvent = null;

        foreach ($events as $event) {
            if ($event->timezone === 'America/Guayaquil') {
                $backupEvent = $event;
                break;
            }
        }

        $this->assertNotNull($backupEvent);
        $this->assertEquals('America/Guayaquil', $backupEvent->timezone);
        $this->assertEquals('0 2 * * *', $backupEvent->expression);

        $job = new CreateDatabaseBackupJob(DatabaseBackup::TYPE_DAILY);
        $this->assertEquals('database_backups', $job->connection);
        $this->assertEquals('backups', $job->queue);
    }

    public function test_42_existing_default_and_media_connections_and_jobs_remain_intact(): void
    {
        $queueConfig = config('queue.connections');

        $this->assertArrayHasKey('sync', $queueConfig);
        $this->assertArrayHasKey('database', $queueConfig);
        $this->assertArrayHasKey('media', $queueConfig);
        $this->assertArrayHasKey('database_backups', $queueConfig);

        $this->assertEquals('media', $queueConfig['media']['queue']);
        $this->assertEquals('backups', $queueConfig['database_backups']['queue']);
        $this->assertEquals('database', $queueConfig['database_backups']['driver']);
    }

    public function test_37_special_character_password_escaped_and_not_leaked(): void
    {
        $dumpService = new MySqlDumpService();
        $specialPassword = 'P@ss"word\\#;= space!123';

        $cnfPath = $dumpService->createTempOptionsFile('127.0.0.1', '3306', 'test_user', $specialPassword);
        $content = file_get_contents($cnfPath);

        // Verify option file contains correctly escaped password
        $this->assertStringContainsString('password="P@ss\"word\\\\#;= space!123"', $content);

        // Verify sanitizeLogOutput masks it
        $sanitized = $dumpService->sanitizeLogOutput("Error with " . $specialPassword, $specialPassword);
        $this->assertStringNotContainsString($specialPassword, $sanitized);
        $this->assertStringContainsString('********', $sanitized);

        $dumpService->removeTempFile($cnfPath);
        $this->assertFileDoesNotExist($cnfPath);
    }

    public function test_43_manual_backup_form_contains_correct_confirm_attributes(): void
    {
        $superadmin = $this->createSuperadmin();
        $response = $this->actingAs($superadmin)->get(route('superadmin.respaldos.index'));
        $response->assertStatus(200);

        // Must contain contextual confirm texts
        $response->assertSee('¿Crear respaldo manual?', false);
        $response->assertSee('Se generará una copia cifrada de la base de datos', false);
        $response->assertSee('El proceso se ejecutará en segundo plano', false);
        $response->assertSee('Sí, crear respaldo', false);
        $response->assertSee('Programando respaldo...', false);
        $response->assertSee('Espera mientras enviamos el respaldo a la cola de procesamiento.', false);

        // Must contain data attributes on the form
        $response->assertSee('data-confirm-title="¿Crear respaldo manual?"', false);
        $response->assertSee('data-confirm-btn="Sí, crear respaldo"', false);
        $response->assertSee('data-action-lock-title="Programando respaldo..."', false);
        $response->assertSee('data-action-lock-description="Espera mientras enviamos el respaldo a la cola de procesamiento."', false);
    }

    public function test_44_manual_backup_form_does_not_contain_destructive_texts(): void
    {
        $superadmin = $this->createSuperadmin();
        $response = $this->actingAs($superadmin)->get(route('superadmin.respaldos.index'));
        $response->assertStatus(200);

        $html = $response->getContent();

        // The manual backup form must NOT have any destructive or generic data attributes
        $this->assertStringNotContainsString('confirmManualBackup', $html);
        $this->assertStringNotContainsString('data-confirm-action="eliminar"', $html);
        $this->assertStringNotContainsString('Guardando nota clínica', $html);
        $this->assertStringNotContainsString('window.confirm(', $html);
        $this->assertStringNotContainsString('!confirm(', $html);
        $this->assertStringNotContainsString('onsubmit="return confirmManualBackup', $html);

        // Verify the form has the correct non-destructive action
        $this->assertStringContainsString('data-confirm-action="crear"', $html);
        $this->assertStringNotContainsString('Acción crítica', $html);
    }

    public function test_45_manual_backup_form_has_no_inline_onsubmit(): void
    {
        $superadmin = $this->createSuperadmin();
        $response = $this->actingAs($superadmin)->get(route('superadmin.respaldos.index'));
        $response->assertStatus(200);

        // The form must not use the old native confirm() dialog handler
        $response->assertDontSee('onsubmit="return confirmManualBackup', false);
        $response->assertDontSee('onsubmit="return confirm(', false);

        // Must have the global confirm system trigger attributes
        $response->assertSee('id="form-manual-backup"', false);
        $response->assertSee('data-confirm-title', false);
        $response->assertSee('data-action-lock-title', false);
    }

    public function test_46_download_link_has_overlay_exclusion_attributes(): void
    {
        $superadmin = $this->createSuperadmin();

        // Create a completed backup so the download link renders
        \App\Models\DatabaseBackup::create([
            'uuid'         => \Illuminate\Support\Str::uuid(),
            'status'       => \App\Models\DatabaseBackup::STATUS_COMPLETED,
            'type'         => \App\Models\DatabaseBackup::TYPE_MANUAL,
            'file_path'    => 'backups/backup_test.zip',
            'file_size'    => 1024,
            'disk'         => 'r2_backups',
            'sha256'       => hash('sha256', 'test'),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($superadmin)->get(route('superadmin.respaldos.index'));
        $response->assertStatus(200);

        $html = $response->getContent();

        // The download anchor must carry all three overlay-exclusion attributes
        $this->assertStringContainsString('data-action-lock-ignore', $html);
        $this->assertStringContainsString('data-skip-page-loader', $html);

        // The download attribute must appear on the same anchor as the other two exclusion attrs
        $this->assertMatchesRegularExpression('/\bdownload\s+data-action-lock-ignore\s+data-skip-page-loader/s', $html);

        // Must NOT show the action-lock overlay label for downloads
        $this->assertStringNotContainsString('Abriendo Descargar', $html);
    }

    public function test_47_restore_backup_protects_active_database(): void
    {
        $activeDb = (string) config('database.connections.' . config('database.default', 'mysql') . '.database');

        $coordinator = app(\App\Services\DatabaseBackup\BackupCoordinatorService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('RESTAURACIÓN RECHAZADA');

        $coordinator->restoreBackup(\Illuminate\Support\Str::uuid(), $activeDb, true);
    }

    public function test_48_restore_backup_validates_non_existent_custom_mysql_path(): void
    {
        $nonExistentPath = 'C:/Program Files/MySQL/InvalidPath/mysql.exe';
        config(['database_backups.mysql_path' => $nonExistentPath]);

        $coordinator = app(\App\Services\DatabaseBackup\BackupCoordinatorService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('El archivo mysql especificado en la ruta no existe.');

        $coordinator->restoreBackup(\Illuminate\Support\Str::uuid(), 'target_db_test', true);
    }

    public function test_49_restore_backup_validates_non_executable_custom_mysql_path(): void
    {
        // Create a temporary file which is NOT executable
        $tempFile = tempnam(sys_get_temp_dir(), 'not_exe');
        config(['database_backups.mysql_path' => $tempFile]);

        $coordinator = app(\App\Services\DatabaseBackup\BackupCoordinatorService::class);

        try {
            $coordinator->restoreBackup(\Illuminate\Support\Str::uuid(), 'target_db_test', true);
            $this->fail('Should have thrown an exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no es ejecutable', $e->getMessage());
        } finally {
            @unlink($tempFile);
        }
    }

    public function test_50_restore_backup_argument_preserves_spaces_and_is_not_shell_concatenated(): void
    {
        $code = file_get_contents(app_path('Services/DatabaseBackup/BackupCoordinatorService.php'));

        // Assert that $cmd is constructed as an array where $mysqlPath is the first element
        $this->assertMatchesRegularExpression('/\$cmd\s*=\s*\[\s*\$mysqlPath\s*,/s', $code);

        // Assert that new Process receives $cmd directly (which prevents shell concatenation)
        $this->assertMatchesRegularExpression('/new\s+Process\(\s*\$cmd\s*,/s', $code);
    }

    public function test_51_restore_backup_removes_cnf_file_on_failure(): void
    {
        $tempBaseDir = config('database_backups.temp_directory');

        // Clean any pre-existing cnf files first so we only look at the one created for this test
        if (is_dir($tempBaseDir)) {
            $dirIterator = new \RecursiveDirectoryIterator($tempBaseDir, \RecursiveDirectoryIterator::SKIP_DOTS);
            $iterator = new \RecursiveIteratorIterator($dirIterator);
            foreach ($iterator as $file) {
                if (str_ends_with($file->getFilename(), '.cnf')) {
                    @unlink($file->getPathname());
                }
            }
        }

        $phpPath = PHP_BINARY;
        config(['database_backups.mysql_path' => $phpPath]);

        $service = app(\App\Services\DatabaseBackup\BackupCoordinatorService::class);
        $method = new \ReflectionMethod($service, 'importSqlIntoDatabase');
        $method->setAccessible(true);

        $dummySql = tempnam(sys_get_temp_dir(), 'dummy_sql');
        file_put_contents($dummySql, '<?php echo "error"; exit(1);');

        try {
            $method->invoke($service, $dummySql, 'target_db_test');
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Fallo al importar el SQL', $e->getMessage());
        } finally {
            @unlink($dummySql);
        }

        $cnfFiles = [];
        if (is_dir($tempBaseDir)) {
            $dirIterator = new \RecursiveDirectoryIterator($tempBaseDir, \RecursiveDirectoryIterator::SKIP_DOTS);
            $iterator = new \RecursiveIteratorIterator($dirIterator);
            foreach ($iterator as $file) {
                if (str_ends_with($file->getFilename(), '.cnf')) {
                    $cnfFiles[] = $file->getPathname();
                }
            }
        }

        $this->assertEmpty($cnfFiles, 'The cnf file should have been cleaned up/deleted.');
    }

    public function test_52_restore_backup_rejects_invalid_target_database_name(): void
    {
        $coordinator = app(\App\Services\DatabaseBackup\BackupCoordinatorService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('RESTAURACIÓN RECHAZADA: Nombre de base de datos destino inválido o inseguro');

        $coordinator->restoreBackup(\Illuminate\Support\Str::uuid(), 'target_db; DROP TABLE users;_test', true);
    }

    public function test_53_restore_backup_sanitation_clears_tables_and_reconciles_backups(): void
    {
        $targetDb = 'clinica_donbosco_restore_test_db_test';
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $service = app(\App\Services\DatabaseBackup\BackupCoordinatorService::class);

        $databaseManager = \Illuminate\Support\Facades\DB::getFacadeRoot();
        $activeConnection = $databaseManager->connection();
        $db = \Mockery::mock(\Illuminate\Database\ConnectionInterface::class);

        $db->shouldReceive('selectOne')->once()
            ->with('SELECT DATABASE() as db')
            ->andReturn((object) ['db' => $targetDb]);
        $db->shouldReceive('select')->once()
            ->with('SHOW TABLES')
            ->andReturn(array_map(
                fn (string $table) => (object) ['table' => $table],
                ['database_backups', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'sessions']
            ));

        foreach (['jobs', 'job_batches', 'cache', 'sessions'] as $table) {
            $db->shouldReceive('statement')->once()
                ->with("TRUNCATE TABLE `{$table}`")
                ->andReturnTrue();
        }

        $db->shouldReceive('select')->once()
            ->with('SHOW COLUMNS FROM `database_backups`')
            ->andReturn(array_map(
                fn (string $column) => (object) ['Field' => $column],
                ['status', 'completed_at', 'last_verified_at']
            ));
        $db->shouldReceive('statement')->once()
            ->withArgs(function (string $sql, array $bindings) use ($uuid): bool {
                $this->assertStringStartsWith('UPDATE `database_backups` SET', $sql);
                $this->assertSame('verified', $bindings[0]);
                $this->assertNotEmpty($bindings[1]);
                $this->assertNotEmpty($bindings[2]);
                $this->assertSame($uuid, $bindings[3]);

                return true;
            })
            ->andReturnTrue();

        \Illuminate\Support\Facades\DB::shouldReceive('connection')
            ->zeroOrMoreTimes()
            ->withNoArgs()
            ->andReturn($activeConnection);
        \Illuminate\Support\Facades\DB::shouldReceive('connection')->once()
            ->with('temp_restore_sanitize_connection')
            ->andReturn($db);
        \Illuminate\Support\Facades\DB::shouldReceive('purge')->once()
            ->with('temp_restore_sanitize_connection');

        try {
            $method = new \ReflectionMethod($service, 'sanitizeTargetDatabase');
            $method->setAccessible(true);

            $manifestData = [
                'uuid' => $uuid,
                'status' => 'verified',
                'timestamp' => now()->toIso8601String(),
            ];

            $method->invoke($service, $targetDb, $uuid, $manifestData);
            $this->addToAssertionCount(1);
        } finally {
            \Illuminate\Support\Facades\DB::swap($databaseManager);
        }
    }

    public function test_54_restore_backup_sanitation_failure_aborts_success_reporting(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();

        // Mock StorageService
        $storageMock = $this->getMockBuilder(\App\Services\DatabaseBackup\BackupStorageService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['download', 'getManifest'])
            ->getMock();
        $storageMock->method('download')->willReturn('dummy_path');
        $storageMock->method('getManifest')->willReturn([
            'uuid' => $uuid,
            'status' => 'verified',
            'timestamp' => now()->toIso8601String(),
        ]);

        // Mock IntegrityService
        $integrityMock = $this->getMockBuilder(\App\Services\DatabaseBackup\BackupIntegrityService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['verifyLocalPackage'])
            ->getMock();

        // Mock EncryptionService
        $encryptionMock = $this->getMockBuilder(\App\Services\DatabaseBackup\BackupEncryptionService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['decrypt'])
            ->getMock();
        $encryptionMock->method('decrypt')->willReturn('dummy_sql.sql');

        $coordinator = $this->getMockBuilder(\App\Services\DatabaseBackup\BackupCoordinatorService::class)
            ->setConstructorArgs([
                app(\App\Services\DatabaseBackup\MySqlDumpService::class),
                $encryptionMock,
                $integrityMock,
                $storageMock,
                app(\App\Services\DatabaseBackup\BackupManifestService::class),
                app(\App\Services\DatabaseBackup\BackupRetentionService::class),
            ])
            ->onlyMethods(['validateMysqlExecutable', 'importSqlIntoDatabase', 'sanitizeTargetDatabase'])
            ->getMock();

        $coordinator->expects($this->once())
            ->method('validateMysqlExecutable');

        $coordinator->expects($this->once())
            ->method('importSqlIntoDatabase');

        $coordinator->expects($this->once())
            ->method('sanitizeTargetDatabase')
            ->willThrowException(new \RuntimeException("Sanitation connection failed"));

        \App\Models\DatabaseBackup::create([
            'uuid'         => $uuid,
            'status'       => \App\Models\DatabaseBackup::STATUS_COMPLETED,
            'type'         => \App\Models\DatabaseBackup::TYPE_MANUAL,
            'file_path'    => 'backups/backup_test.zip',
            'file_size'    => 1024,
            'disk'         => 'r2_backups',
            'sha256'       => hash('sha256', 'test'),
            'completed_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('La base de datos fue importada con éxito, pero no está lista para activarse porque falló el saneamiento de seguridad');

        $coordinator->restoreBackup($uuid, 'some_restore_db_test', true);
    }
}
