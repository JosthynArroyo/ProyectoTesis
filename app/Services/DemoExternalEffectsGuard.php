<?php

namespace App\Services;

use App\Support\DemoGuardedStorageAdapter;
use Illuminate\Filesystem\FilesystemAdapter as LaravelFilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;

class DemoExternalEffectsGuard
{
    private static bool $applied = false;

    public function __construct(
        private readonly ApplicationModeService $applicationMode,
    ) {}

    public function apply(): void
    {
        if (! $this->applicationMode->isDemo()) {
            return;
        }

        // 1. Mail Isolation: Divert all outgoing email to the in-memory array mailer
        config(['mail.default' => 'array']);

        // 2. Queue Isolation: Neutralize background jobs dispatched from web requests
        config([
            'queue.default' => 'null',
            'queue.media_connection' => 'null',
            'queue.backup_queue_connection' => 'null',
        ]);

        // 3. Storage & R2 Isolation: Guard all filesystem disks against permanent mutations
        $this->guardStorageDisks();
    }

    /**
     * Wrap configured storage disks with the non-destructive DemoGuardedStorageAdapter.
     */
    public function guardStorageDisks(): void
    {
        $disks = array_keys((array) config('filesystems.disks', []));

        foreach ($disks as $diskName) {
            $diskConfig = (array) config("filesystems.disks.{$diskName}", []);
            if (empty($diskConfig)) {
                continue;
            }

            try {
                $realDisk = Storage::disk($diskName);
                if ($realDisk instanceof LaravelFilesystemAdapter) {
                    $innerAdapter = $realDisk->getAdapter();
                    if (! ($innerAdapter instanceof DemoGuardedStorageAdapter)) {
                        $isTestRuntime = defined('PHPUNIT_TESTSUITE')
                            || class_exists(\PHPUnit\Framework\TestCase::class, false)
                            || app()->runningUnitTests();
                        // Los recibos privados sembrados deben sobrevivir al proceso que construye el dataset demo.
                        $persistentPrefixes = $diskName === 'local'
                            && ($diskConfig['driver'] ?? null) === 'local'
                            && config('private_documents.disk') === 'local'
                            && (
                                ! $isTestRuntime
                                || config('private_documents.persist_demo_receipts_in_tests', false)
                            )
                                ? ['documents/payment-receipts']
                                : [];
                        $guardedAdapter = new DemoGuardedStorageAdapter($innerAdapter, $diskConfig, $persistentPrefixes);
                        $flysystem = new Filesystem($guardedAdapter);
                        $wrapped = new LaravelFilesystemAdapter($flysystem, $guardedAdapter, $diskConfig);
                        Storage::set($diskName, $wrapped);
                    }
                }
            } catch (\Throwable) {
                // If disk credentials are not configured in current environment, skip gracefully
            }
        }
    }

    /**
     * Reset guard state (useful for testing transitions between demo and production).
     */
    public static function reset(): void
    {
        self::$applied = false;
    }
}
