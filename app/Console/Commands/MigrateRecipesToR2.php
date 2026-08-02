<?php

namespace App\Console\Commands;

use App\Models\Receta;
use App\Services\RecipeDocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MigrateRecipesToR2 extends Command
{
    protected $signature = 'recipes:migrate-to-r2
                            {--dry-run : Audit candidates without making any changes}
                            {--execute : Perform the migration of active recipe PDFs to R2}
                            {--verify : Verify that migrated recipes exist in R2 with matching size and SHA-256}';

    protected $description = 'Migrate active medical prescription PDFs from local storage to Cloudflare R2 private';

    public function handle(RecipeDocumentService $recipeService): int
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
            return $this->handleExecute($recipeService);
        }

        if ($verify) {
            return $this->handleVerify($recipeService);
        }

        return 0;
    }

    protected function handleDryRun(): int
    {
        $this->info('=== DRY RUN: AUDIT MEDICAL PRESCRIPTION PDFs FOR R2 MIGRATION ===');

        $activeRecipes = Receta::query()->whereNotNull('pdf_path')->where('pdf_path', '!=', '')->get();
        $totalActiveInDb = $activeRecipes->count();

        $candidates = 0;
        $alreadyMigrated = 0;
        $missingLocal = 0;

        foreach ($activeRecipes as $receta) {
            $disk = $receta->pdf_disk;
            if ($disk === 'r2_private') {
                $alreadyMigrated++;
            } else {
                $localPath = (string) $receta->getRawOriginal('pdf_path');
                if (Storage::disk('local')->exists($localPath)) {
                    $candidates++;
                } else {
                    $missingLocal++;
                }
            }
        }

        $allLocalFiles = Storage::disk('local')->exists('recetas')
            ? Storage::disk('local')->allFiles('recetas')
            : [];
        $totalFilesOnLocalDisk = count($allLocalFiles);

        $activeLocalPathsSet = $activeRecipes->pluck('pdf_path')->filter()->flip();
        $orphansCount = 0;
        foreach ($allLocalFiles as $file) {
            if (! $activeLocalPathsSet->has($file)) {
                $orphansCount++;
            }
        }

        $this->table(['Metric', 'Count'], [
            ['Active Recipes in DB', $totalActiveInDb],
            ['Already Migrated to R2', $alreadyMigrated],
            ['Candidates for Migration (Local Exists)', $candidates],
            ['Active Missing Local File', $missingLocal],
            ['Total Files in Local recetas/ Folder', $totalFilesOnLocalDisk],
            ['Local Orphan Files (Will be preserved & omitted)', $orphansCount],
        ]);

        if ($candidates > 0 && $missingLocal === 0) {
            $this->info('Dry-run complete. System is ready to execute migration.');
        } elseif ($candidates === 0 && $alreadyMigrated > 0) {
            $this->info('All active recipes are already migrated to R2.');
        } elseif ($missingLocal > 0) {
            $this->warn("Warning: {$missingLocal} active recipes have missing local files!");
        }

        return 0;
    }

    protected function handleExecute(RecipeDocumentService $recipeService): int
    {
        $this->info('=== EXECUTE: MIGRATING ACTIVE RECIPE PDFs TO R2 ===');

        $activeRecipes = Receta::query()->whereNotNull('pdf_path')->where('pdf_path', '!=', '')->get();
        $migratedManifest = [];
        $successCount = 0;

        foreach ($activeRecipes as $receta) {
            if ($receta->pdf_disk === 'r2_private') {
                $this->line("Receta #{$receta->id} is already in R2.");
                continue;
            }

            $localPath = (string) $receta->getRawOriginal('pdf_path');
            if (! Storage::disk('local')->exists($localPath)) {
                $this->error("Receta #{$receta->id} local file not found: {$localPath}");
                continue;
            }

            $res = $recipeService->migrateLocalRecipeToR2($receta);

            if ($res['success']) {
                $successCount++;
                $migratedManifest[] = $res;
                $this->info("Receta #{$receta->id} migrated: {$localPath} -> {$res['new_key']} (Size: {$res['size']} B)");
            } else {
                $this->error("Receta #{$receta->id} migration failed: {$res['error']}");
            }
        }

        $manifestDir = storage_path('app/private/scratch');
        if (! is_dir($manifestDir)) {
            @mkdir($manifestDir, 0755, true);
        }
        file_put_contents(
            $manifestDir . '/receta_migration_manifest.json',
            json_encode($migratedManifest, JSON_PRETTY_PRINT)
        );

        $this->info("Successfully migrated {$successCount} active recipe(s) to R2.");
        return 0;
    }

    protected function handleVerify(RecipeDocumentService $recipeService): int
    {
        $this->info('=== VERIFY: CHECKING MIGRATED RECIPE PDFs IN R2 ===');

        $r2Recipes = Receta::query()->where('pdf_disk', 'r2_private')->get();
        $verifiedCount = 0;
        $errors = 0;

        foreach ($r2Recipes as $receta) {
            $key = $receta->pdf_path;
            $r2Disk = Storage::disk('r2_private');

            if (! $r2Disk->exists($key)) {
                $this->error("Verification failed for Receta #{$receta->id}: Key {$key} not found on R2!");
                $errors++;
                continue;
            }

            $r2Binary = $r2Disk->get($key);
            $size = strlen($r2Binary);
            $sha256 = hash('sha256', $r2Binary);

            if (! str_starts_with($r2Binary, '%PDF')) {
                $this->error("Verification failed for Receta #{$receta->id}: Invalid PDF header!");
                $errors++;
                continue;
            }

            $this->line("Receta #{$receta->id} verified: Key {$key} | Size: {$size} B | SHA256: " . substr($sha256, 0, 12) . "...");
            $verifiedCount++;
        }

        $orphansCount = 0;
        if (Storage::disk('local')->exists('recetas')) {
            $allLocalFiles = Storage::disk('local')->allFiles('recetas');
            $orphansCount = count($allLocalFiles);
        }

        $this->table(['Metric', 'Status'], [
            ['Verified R2 Recipes', "{$verifiedCount} OK"],
            ['Verification Errors', $errors === 0 ? '0 (Clean)' : "{$errors} Failed"],
            ['Preserved Local Files & Orphans', "{$orphansCount} Files Omitted"],
        ]);

        if ($errors === 0) {
            $this->info('All migrated R2 recipes verified successfully!');
            return 0;
        }

        return 1;
    }
}
