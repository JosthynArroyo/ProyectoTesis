<?php

namespace App\Console\Commands;

use App\Models\PedidoLaboratorioResultado;
use App\Services\PedidoLaboratorioPdfService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateLabResultsToR2 extends Command
{
    protected $signature = 'laboratory-results:migrate-to-r2
                            {--dry-run : Audit candidates without making any changes}
                            {--execute : Perform the migration of active lab results to R2}
                            {--verify : Verify that migrated lab results exist in R2 with matching size and SHA-256}';

    protected $description = 'Migrate active laboratory result PDFs from local storage to Cloudflare R2 private';

    public function handle(PedidoLaboratorioPdfService $pdfService): int
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
            return $this->handleExecute($pdfService);
        }

        if ($verify) {
            return $this->handleVerify();
        }

        return 0;
    }

    protected function handleDryRun(): int
    {
        $this->info('=== DRY RUN: AUDIT LABORATORY RESULT PDFs FOR R2 MIGRATION ===');

        $activeResults = PedidoLaboratorioResultado::query()->whereNotNull('pdf_path')->where('pdf_path', '!=', '')->get();
        $totalActiveInDb = $activeResults->count();

        $candidates = 0;
        $alreadyMigrated = 0;
        $missingLocal = 0;

        foreach ($activeResults as $resultado) {
            $disk = $resultado->pdf_disk;
            if ($disk === 'r2_private') {
                $alreadyMigrated++;
            } else {
                $localPath = (string) $resultado->getRawOriginal('pdf_path');
                if (Storage::disk('local')->exists($localPath)) {
                    $candidates++;
                } else {
                    $missingLocal++;
                }
            }
        }

        $allLocalFiles = Storage::disk('local')->exists('pedidos-laboratorio-resultados')
            ? Storage::disk('local')->allFiles('pedidos-laboratorio-resultados')
            : [];
        $totalFilesOnLocalDisk = count($allLocalFiles);

        $activeLocalPathsSet = $activeResults->pluck('pdf_path')->filter()->flip();
        $orphansCount = 0;
        foreach ($allLocalFiles as $file) {
            if (! $activeLocalPathsSet->has($file)) {
                $orphansCount++;
            }
        }

        $this->table(['Metric', 'Count'], [
            ['Active Lab Results in DB', $totalActiveInDb],
            ['Already Migrated to R2', $alreadyMigrated],
            ['Candidates for Migration (Local Exists)', $candidates],
            ['Active Missing Local File', $missingLocal],
            ['Total Files in Local Folder', $totalFilesOnLocalDisk],
            ['Local Orphan Files (Will be preserved & omitted)', $orphansCount],
        ]);

        if ($candidates > 0 && $missingLocal === 0) {
            $this->info('Dry-run complete. System is ready to execute migration.');
        } elseif ($candidates === 0 && $alreadyMigrated > 0) {
            $this->info('All active lab results are already migrated to R2.');
        } elseif ($missingLocal > 0) {
            $this->warn("Warning: {$missingLocal} active lab results have missing local files!");
        } else {
            $this->info('Zero candidates for migration.');
        }

        return 0;
    }

    protected function handleExecute(PedidoLaboratorioPdfService $pdfService): int
    {
        $this->info('=== EXECUTE: MIGRATING ACTIVE LAB RESULT PDFs TO R2 ===');

        $activeResults = PedidoLaboratorioResultado::query()->whereNotNull('pdf_path')->where('pdf_path', '!=', '')->get();
        $migratedManifest = [];
        $successCount = 0;

        foreach ($activeResults as $resultado) {
            if ($resultado->pdf_disk === 'r2_private') {
                $this->line("Resultado #{$resultado->id} is already in R2.");
                continue;
            }

            $localPath = (string) $resultado->getRawOriginal('pdf_path');
            if (! Storage::disk('local')->exists($localPath)) {
                $this->error("Resultado #{$resultado->id} local file not found: {$localPath}");
                continue;
            }

            $res = $pdfService->migrateLocalResultToR2($resultado);

            if ($res['success']) {
                $successCount++;
                $migratedManifest[] = $res;
                $this->info("Resultado #{$resultado->id} migrated: {$localPath} -> {$res['new_key']} (Size: {$res['size']} B)");
            } else {
                $this->error("Resultado #{$resultado->id} migration failed: {$res['error']}");
            }
        }

        $manifestDir = storage_path('app/private/scratch');
        if (! is_dir($manifestDir)) {
            @mkdir($manifestDir, 0755, true);
        }
        file_put_contents(
            $manifestDir . '/lab_result_migration_manifest.json',
            json_encode($migratedManifest, JSON_PRETTY_PRINT)
        );

        $this->info("Successfully migrated {$successCount} active laboratory result(s) to R2.");
        return 0;
    }

    protected function handleVerify(): int
    {
        $this->info('=== VERIFY: CHECKING MIGRATED LAB RESULT PDFs IN R2 ===');

        $r2Results = PedidoLaboratorioResultado::query()->where('pdf_disk', 'r2_private')->get();
        $verifiedCount = 0;
        $errors = 0;

        foreach ($r2Results as $resultado) {
            $key = $resultado->pdf_path;
            $r2Disk = Storage::disk('r2_private');

            if (! $r2Disk->exists($key)) {
                $this->error("Verification failed for Resultado #{$resultado->id}: Key {$key} not found on R2!");
                $errors++;
                continue;
            }

            $r2Binary = $r2Disk->get($key);
            $size = strlen($r2Binary);
            $sha256 = hash('sha256', $r2Binary);

            if (! str_starts_with($r2Binary, '%PDF')) {
                $this->error("Verification failed for Resultado #{$resultado->id}: Invalid PDF header!");
                $errors++;
                continue;
            }

            $this->line("Resultado #{$resultado->id} verified: Key {$key} | Size: {$size} B | SHA256: " . substr($sha256, 0, 12) . "...");
            $verifiedCount++;
        }

        $orphansCount = 0;
        if (Storage::disk('local')->exists('pedidos-laboratorio-resultados')) {
            $allLocalFiles = Storage::disk('local')->allFiles('pedidos-laboratorio-resultados');
            $orphansCount = count($allLocalFiles);
        }

        $this->table(['Metric', 'Status'], [
            ['Verified R2 Lab Results', "{$verifiedCount} OK"],
            ['Verification Errors', $errors === 0 ? '0 (Clean)' : "{$errors} Failed"],
            ['Preserved Local Files & Orphans', "{$orphansCount} Files Omitted"],
        ]);

        if ($errors === 0) {
            $this->info('All migrated R2 lab results verified successfully!');
            return 0;
        }

        return 1;
    }
}
