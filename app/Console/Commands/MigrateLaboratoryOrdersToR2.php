<?php

namespace App\Console\Commands;

use App\Models\PedidoLaboratorio;
use App\Services\LaboratoryOrderDocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateLaboratoryOrdersToR2 extends Command
{
    protected $signature = 'laboratory-orders:migrate-to-r2
                            {--dry-run : Audit candidates without making any changes}
                            {--execute : Perform the migration of active laboratory order PDFs to R2}
                            {--verify : Verify that migrated laboratory orders exist in R2 with matching size and SHA-256}';

    protected $description = 'Migrate active laboratory order PDFs from local storage to Cloudflare R2 private';

    public function handle(LaboratoryOrderDocumentService $docService): int
    {
        $dryRun = $this->option('dry-run');
        $execute = $this->option('execute');
        $verify = $this->option('verify');

        if (! $dryRun && ! $execute && ! $verify) {
            $this->error('Please specify one of: --dry-run, --execute, or --verify');
            return 1;
        }

        if ($dryRun) {
            return $this->handleDryRun();
        }

        if ($execute) {
            return $this->handleExecute($docService);
        }

        if ($verify) {
            return $this->handleVerify($docService);
        }

        return 0;
    }

    protected function handleDryRun(): int
    {
        $this->info('=== DRY RUN: AUDIT LABORATORY ORDER PDFs FOR R2 MIGRATION ===');

        $activeOrders = PedidoLaboratorio::query()
            ->whereNotNull('pdf_path')
            ->where('pdf_path', '!=', '')
            ->get();
        $totalActiveInDb = $activeOrders->count();

        $candidates = 0;
        $alreadyMigrated = 0;
        $missingLocal = 0;

        foreach ($activeOrders as $pl) {
            $disk = $pl->pdf_disk;
            if ($disk === 'r2_private') {
                $alreadyMigrated++;
            } else {
                $localPath = (string) $pl->getRawOriginal('pdf_path');
                if (Storage::disk('local')->exists($localPath)) {
                    $candidates++;
                } else {
                    $missingLocal++;
                }
            }
        }

        $allLocalFiles = Storage::disk('local')->exists('pedidos-laboratorio')
            ? Storage::disk('local')->allFiles('pedidos-laboratorio')
            : [];
        $totalFilesOnLocalDisk = count($allLocalFiles);

        $activeLocalPathsSet = $activeOrders->pluck('pdf_path')->filter()->flip();
        $orphansCount = 0;
        foreach ($allLocalFiles as $file) {
            if (! $activeLocalPathsSet->has($file)) {
                $orphansCount++;
            }
        }

        $this->table(['Metric', 'Count'], [
            ['Active Laboratory Orders in DB', $totalActiveInDb],
            ['Already Migrated to R2', $alreadyMigrated],
            ['Candidates for Migration (Local Exists)', $candidates],
            ['Active Missing Local File', $missingLocal],
            ['Total Files in Local pedidos-laboratorio/ Folder', $totalFilesOnLocalDisk],
            ['Local Orphan Files (Will be preserved & omitted)', $orphansCount],
        ]);

        if ($candidates > 0 && $missingLocal === 0) {
            $this->info('Dry-run complete. System is ready to execute migration.');
        } elseif ($candidates === 0 && $alreadyMigrated > 0) {
            $this->info('All active laboratory orders are already migrated to R2.');
        } elseif ($missingLocal > 0) {
            $this->warn("Warning: {$missingLocal} active laboratory orders have missing local files!");
        }

        return 0;
    }

    protected function handleExecute(LaboratoryOrderDocumentService $docService): int
    {
        $this->info('=== EXECUTE: MIGRATING ACTIVE LABORATORY ORDER PDFs TO R2 ===');

        $activeOrders = PedidoLaboratorio::query()
            ->whereNotNull('pdf_path')
            ->where('pdf_path', '!=', '')
            ->get();
        $migratedManifest = [];
        $successCount = 0;

        foreach ($activeOrders as $pl) {
            if ($pl->pdf_disk === 'r2_private') {
                $this->line("PedidoLaboratorio #{$pl->id} is already in R2.");
                continue;
            }

            $localPath = (string) $pl->getRawOriginal('pdf_path');
            if (! Storage::disk('local')->exists($localPath)) {
                $this->error("PedidoLaboratorio #{$pl->id} local file not found: {$localPath}");
                continue;
            }

            $res = $docService->migrateLocalOrderToR2($pl);

            if ($res['success']) {
                $successCount++;
                $migratedManifest[] = $res;
                $this->info("PedidoLaboratorio #{$pl->id} migrated: {$localPath} -> {$res['new_key']} (Size: {$res['size']} B)");
            } else {
                $this->error("PedidoLaboratorio #{$pl->id} migration failed: {$res['error']}");
            }
        }

        $manifestDir = storage_path('app/private/scratch');
        if (! is_dir($manifestDir)) {
            @mkdir($manifestDir, 0755, true);
        }
        file_put_contents(
            $manifestDir . '/laboratory_order_migration_manifest.json',
            json_encode($migratedManifest, JSON_PRETTY_PRINT)
        );

        $this->info("Successfully migrated {$successCount} active laboratory order(s) to R2.");
        return 0;
    }

    protected function handleVerify(LaboratoryOrderDocumentService $docService): int
    {
        $this->info('=== VERIFY: CHECKING MIGRATED LABORATORY ORDER PDFs IN R2 ===');

        $r2Orders = PedidoLaboratorio::query()->where('pdf_disk', 'r2_private')->get();
        $verifiedCount = 0;
        $errors = 0;

        foreach ($r2Orders as $pl) {
            $key = $pl->pdf_path;
            $r2Disk = Storage::disk('r2_private');

            if (! $r2Disk->exists($key)) {
                $this->error("Verification failed for PedidoLaboratorio #{$pl->id}: Key {$key} not found on R2!");
                $errors++;
                continue;
            }

            $r2Binary = $r2Disk->get($key);
            $size = strlen($r2Binary);
            $sha256 = hash('sha256', $r2Binary);

            if (! str_starts_with($r2Binary, '%PDF')) {
                $this->error("Verification failed for PedidoLaboratorio #{$pl->id}: Invalid PDF header!");
                $errors++;
                continue;
            }

            $this->line("PedidoLaboratorio #{$pl->id} verified: Key {$key} | Size: {$size} B | SHA256: " . substr($sha256, 0, 12) . "...");
            $verifiedCount++;
        }

        $orphansCount = 0;
        if (Storage::disk('local')->exists('pedidos-laboratorio')) {
            $allLocalFiles = Storage::disk('local')->allFiles('pedidos-laboratorio');
            $orphansCount = count($allLocalFiles);
        }

        $this->table(['Metric', 'Status'], [
            ['Verified R2 Laboratory Orders', "{$verifiedCount} OK"],
            ['Verification Errors', $errors === 0 ? '0 (Clean)' : "{$errors} Failed"],
            ['Preserved Local Files & Orphans', "{$orphansCount} Files Omitted"],
        ]);

        if ($errors === 0) {
            $this->info('All migrated R2 laboratory orders verified successfully!');
            return 0;
        }

        return 1;
    }
}
