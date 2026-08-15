<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigratePublicImagesToR2CommandTest extends TestCase
{
    public function test_historical_migration_commands_are_fail_closed_and_do_not_touch_storage(): void
    {
        $this->assertSame('r2_private', config('filesystems.default'));
        $this->assertSame('r2_public', config('image_optimization.disk'));
        $this->assertTrue((bool) config('filesystems.disks.r2_private.throw'));
        $this->assertTrue((bool) config('filesystems.disks.r2_public.throw'));

        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('r2_private');
        Storage::fake('r2_public');

        $this->assertSame('clinica_donbosco_db_test', DB::connection()->getDatabaseName());

        $mutatingQueries = [];
        DB::listen(static function (QueryExecuted $query) use (&$mutatingQueries): void {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop|truncate|rename)\b/i', $query->sql) === 1) {
                $mutatingQueries[] = $query->sql;
            }
        });

        $allModes = [
            [],
            ['--dry-run' => true],
            ['--execute' => true],
            ['--verify' => true],
        ];

        $commands = [
            'appointment-confirmations:migrate-to-r2' => $allModes,
            'avatars:migrate-to-r2' => $allModes,
            'certificates:migrate-to-r2' => $allModes,
            'laboratory-orders:migrate-to-r2' => $allModes,
            'laboratory-results:migrate-to-r2' => $allModes,
            'payment-orders:migrate-to-r2' => $allModes,
            'payment-proofs:migrate-to-r2' => $allModes,
            'payment-receipts:migrate-to-r2' => $allModes,
            'recipes:migrate-to-r2' => $allModes,
            'app:migrate-public-images-to-r2' => [[], ['--execute' => true]],
        ];

        foreach ($commands as $name => $modes) {
            foreach ($modes as $arguments) {
                $context = $name.' '.json_encode($arguments, JSON_THROW_ON_ERROR);
                $this->assertSame(Command::FAILURE, Artisan::call($name, $arguments), $context);
                $this->assertStringContainsString('Comando deshabilitado', Artisan::output(), $context);
            }
        }

        $this->assertSame([], $mutatingQueries, 'Los comandos deshabilitados no deben modificar la DB.');
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('r2_private')->allFiles());
        $this->assertSame([], Storage::disk('r2_public')->allFiles());
        $this->assertDirectoryDoesNotExist(storage_path('app/private/r2-migration-manifests'));
        $this->assertDirectoryDoesNotExist(storage_path('app/private/scratch'));
    }
}
