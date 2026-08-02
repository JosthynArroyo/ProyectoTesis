<?php

namespace App\Console\Commands;

use App\Models\CertificadoMedico;
use App\Services\MedicalCertificateDocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateCertificatesToR2 extends Command
{
    protected $signature = 'certificates:migrate-to-r2
                            {--dry-run : Audit candidates without making any changes}
                            {--execute : Perform the migration of active medical certificate PDFs to R2}
                            {--verify : Verify that migrated certificates exist in R2 with matching size and SHA-256}';

    protected $description = 'Migrate active medical certificate PDFs from local storage to Cloudflare R2 private';

    public function handle(MedicalCertificateDocumentService $docService): int
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
        $this->info('=== DRY RUN: AUDIT MEDICAL CERTIFICATE PDFs FOR R2 MIGRATION ===');

        $activeCertificates = CertificadoMedico::query()
            ->whereNotNull('pdf_path')
            ->where('pdf_path', '!=', '')
            ->get();
        $totalActiveInDb = $activeCertificates->count();

        $candidates = 0;
        $alreadyMigrated = 0;
        $missingLocal = 0;

        foreach ($activeCertificates as $cm) {
            $disk = $cm->pdf_disk;
            if ($disk === 'r2_private') {
                $alreadyMigrated++;
            } else {
                $localPath = (string) $cm->getRawOriginal('pdf_path');
                if (Storage::disk('local')->exists($localPath)) {
                    $candidates++;
                } else {
                    $missingLocal++;
                }
            }
        }

        $allLocalFiles = Storage::disk('local')->exists('certificados-medicos')
            ? Storage::disk('local')->allFiles('certificados-medicos')
            : [];
        $totalFilesOnLocalDisk = count($allLocalFiles);

        $activeLocalPathsSet = $activeCertificates->pluck('pdf_path')->filter()->flip();
        $orphansCount = 0;
        foreach ($allLocalFiles as $file) {
            if (! $activeLocalPathsSet->has($file)) {
                $orphansCount++;
            }
        }

        $this->table(['Metric', 'Count'], [
            ['Active Medical Certificates in DB', $totalActiveInDb],
            ['Already Migrated to R2', $alreadyMigrated],
            ['Candidates for Migration (Local Exists)', $candidates],
            ['Active Missing Local File', $missingLocal],
            ['Total Files in Local certificados-medicos/ Folder', $totalFilesOnLocalDisk],
            ['Local Orphan Files (Will be preserved & omitted)', $orphansCount],
        ]);

        if ($candidates > 0 && $missingLocal === 0) {
            $this->info('Dry-run complete. System is ready to execute migration.');
        } elseif ($candidates === 0 && $alreadyMigrated > 0) {
            $this->info('All active medical certificates are already migrated to R2.');
        } elseif ($missingLocal > 0) {
            $this->warn("Warning: {$missingLocal} active medical certificates have missing local files!");
        }

        return 0;
    }

    protected function handleExecute(MedicalCertificateDocumentService $docService): int
    {
        $this->info('=== EXECUTE: MIGRATING ACTIVE MEDICAL CERTIFICATE PDFs TO R2 ===');

        $activeCertificates = CertificadoMedico::query()
            ->whereNotNull('pdf_path')
            ->where('pdf_path', '!=', '')
            ->get();
        $migratedManifest = [];
        $successCount = 0;

        foreach ($activeCertificates as $cm) {
            if ($cm->pdf_disk === 'r2_private') {
                $this->line("CertificadoMedico #{$cm->id} is already in R2.");
                continue;
            }

            $localPath = (string) $cm->getRawOriginal('pdf_path');
            if (! Storage::disk('local')->exists($localPath)) {
                $this->error("CertificadoMedico #{$cm->id} local file not found: {$localPath}");
                continue;
            }

            $res = $docService->migrateLocalCertificateToR2($cm);

            if ($res['success']) {
                $successCount++;
                $migratedManifest[] = $res;
                $this->info("CertificadoMedico #{$cm->id} migrated: {$localPath} -> {$res['new_key']} (Size: {$res['size']} B)");
            } else {
                $this->error("CertificadoMedico #{$cm->id} migration failed: {$res['error']}");
            }
        }

        $manifestDir = storage_path('app/private/scratch');
        if (! is_dir($manifestDir)) {
            @mkdir($manifestDir, 0755, true);
        }
        file_put_contents(
            $manifestDir . '/certificate_migration_manifest.json',
            json_encode($migratedManifest, JSON_PRETTY_PRINT)
        );

        $this->info("Successfully migrated {$successCount} active medical certificate(s) to R2.");
        return 0;
    }

    protected function handleVerify(MedicalCertificateDocumentService $docService): int
    {
        $this->info('=== VERIFY: CHECKING MIGRATED MEDICAL CERTIFICATE PDFs IN R2 ===');

        $r2Certificates = CertificadoMedico::query()->where('pdf_disk', 'r2_private')->get();
        $verifiedCount = 0;
        $errors = 0;

        foreach ($r2Certificates as $cm) {
            $key = $cm->pdf_path;
            $r2Disk = Storage::disk('r2_private');

            if (! $r2Disk->exists($key)) {
                $this->error("Verification failed for CertificadoMedico #{$cm->id}: Key {$key} not found on R2!");
                $errors++;
                continue;
            }

            $r2Binary = $r2Disk->get($key);
            $size = strlen($r2Binary);
            $sha256 = hash('sha256', $r2Binary);

            if (! str_starts_with($r2Binary, '%PDF')) {
                $this->error("Verification failed for CertificadoMedico #{$cm->id}: Invalid PDF header!");
                $errors++;
                continue;
            }

            $this->line("CertificadoMedico #{$cm->id} verified: Key {$key} | Size: {$size} B | SHA256: " . substr($sha256, 0, 12) . "...");
            $verifiedCount++;
        }

        $orphansCount = 0;
        if (Storage::disk('local')->exists('certificados-medicos')) {
            $allLocalFiles = Storage::disk('local')->allFiles('certificados-medicos');
            $orphansCount = count($allLocalFiles);
        }

        $this->table(['Metric', 'Status'], [
            ['Verified R2 Certificates', "{$verifiedCount} OK"],
            ['Verification Errors', $errors === 0 ? '0 (Clean)' : "{$errors} Failed"],
            ['Preserved Local Files & Orphans', "{$orphansCount} Files Omitted"],
        ]);

        if ($errors === 0) {
            $this->info('All migrated R2 medical certificates verified successfully!');
            return 0;
        }

        return 1;
    }
}
