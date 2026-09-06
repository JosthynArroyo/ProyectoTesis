<?php

namespace Database\Seeders;

use App\Models\DatabaseBackup;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDatabaseBackupSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::where('email', 'superadmin@demo-clinigest.test')->first();

        // 1. Respaldo Automático Diario (Reciente, Verificado y Al Día)
        DatabaseBackup::updateOrCreate(
            [
                'uuid' => '9c8b7a61-5e4d-4c3b-8a21-1f0e9d8c7b6a',
            ],
            [
                'type' => DatabaseBackup::TYPE_DAILY,
                'status' => DatabaseBackup::STATUS_VERIFIED,
                'disk' => 'r2_backups',
                'file_path' => 'database/automatic/daily/2026/08/20260831-030000/backup-daily-9c8b7a61.zip',
                'manifest_path' => 'database/automatic/daily/2026/08/20260831-030000/manifest.json',
                'file_size' => 2516582,
                'sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
                'started_at' => now()->subHours(4)->subMinutes(15),
                'completed_at' => now()->subHours(4),
                'duration_seconds' => 14.2,
                'user_id' => null,
                'error_message' => null,
                'attempts' => 1,
                'last_verified_at' => now()->subHours(1),
                'created_at' => now()->subHours(4)->subMinutes(15),
                'updated_at' => now()->subHours(1),
            ]
        );

        // 2. Respaldo Manual Solicitado por el Superadministrador (Verificado)
        DatabaseBackup::updateOrCreate(
            [
                'uuid' => '8b7a615e-4d3c-4b2a-1f0e-9d8c7b6a5e4d',
            ],
            [
                'type' => DatabaseBackup::TYPE_MANUAL,
                'status' => DatabaseBackup::STATUS_VERIFIED,
                'disk' => 'r2_backups',
                'file_path' => 'database/manual/2026/08/20260830-160000/backup-manual-8b7a615e.zip',
                'manifest_path' => 'database/manual/2026/08/20260830-160000/manifest.json',
                'file_size' => 2411724,
                'sha256' => 'a591a6d40bf420404a011733cfb7b190d62c65bf0bcda32b57b277d9ad9f146e',
                'started_at' => now()->subDays(1)->subMinutes(12),
                'completed_at' => now()->subDays(1),
                'duration_seconds' => 12.8,
                'user_id' => $superadmin?->id,
                'error_message' => null,
                'attempts' => 1,
                'last_verified_at' => now()->subDays(1),
                'created_at' => now()->subDays(1)->subMinutes(12),
                'updated_at' => now()->subDays(1),
            ]
        );

        // 3. Respaldo Automático Semanal (Completado)
        DatabaseBackup::updateOrCreate(
            [
                'uuid' => '7a615e4d-3c2b-4a1f-0e9d-8c7b6a5e4d3c',
            ],
            [
                'type' => DatabaseBackup::TYPE_WEEKLY,
                'status' => DatabaseBackup::STATUS_COMPLETED,
                'disk' => 'r2_backups',
                'file_path' => 'database/automatic/weekly/2026/08/20260824-020000/backup-weekly-7a615e4d.zip',
                'manifest_path' => 'database/automatic/weekly/2026/08/20260824-020000/manifest.json',
                'file_size' => 2202009,
                'sha256' => 'c7567e8b39e2426e385159ef5196bd18edd68bc78c583f3fb966279c2a273660',
                'started_at' => now()->subDays(7)->subMinutes(11),
                'completed_at' => now()->subDays(7),
                'duration_seconds' => 11.5,
                'user_id' => null,
                'error_message' => null,
                'attempts' => 1,
                'last_verified_at' => now()->subDays(7),
                'created_at' => now()->subDays(7)->subMinutes(11),
                'updated_at' => now()->subDays(7),
            ]
        );
    }
}
