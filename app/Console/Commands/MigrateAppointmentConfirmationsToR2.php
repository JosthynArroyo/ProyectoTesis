<?php

namespace App\Console\Commands;

use App\Models\Cita;
use App\Services\AppointmentConfirmationDocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigrateAppointmentConfirmationsToR2 extends Command
{
    protected $signature = 'appointment-confirmations:migrate-to-r2 {--dry-run} {--execute} {--verify}';

    protected $description = 'Migra comprobantes de citas desde el almacenamiento local a Cloudflare R2 privado (r2_private)';

    public function handle(AppointmentConfirmationDocumentService $confirmationDocumentService): int
    {
        $isDryRun = $this->option('dry-run');
        $isExecute = $this->option('execute');
        $isVerify = $this->option('verify');

        if (! $isDryRun && ! $isExecute && ! $isVerify) {
            $this->error("Debes especificar una de las opciones: --dry-run, --execute o --verify.");
            return self::FAILURE;
        }

        if ($isDryRun) {
            return $this->runDryRun();
        }

        if ($isExecute) {
            return $this->runExecute($confirmationDocumentService);
        }

        if ($isVerify) {
            return $this->runVerify($confirmationDocumentService);
        }

        return self::SUCCESS;
    }

    private function runDryRun(): int
    {
        $this->info("=== DRY RUN: AUDIT APPOINTMENT CONFIRMATION PDFs FOR R2 MIGRATION ===");

        $totalCitas = Cita::count();
        $conRuta = Cita::whereNotNull('comprobante_pdf_path')->where('comprobante_pdf_path', '!=', '')->count();
        $sinRuta = Cita::where(function ($q) {
            $q->whereNull('comprobante_pdf_path')->orWhere('comprobante_pdf_path', '');
        })->count();

        $alreadyR2 = Cita::where('comprobante_pdf_disk', AppointmentConfirmationDocumentService::DISK)->count();

        $candidatos = Cita::whereNotNull('comprobante_pdf_path')
            ->where('comprobante_pdf_path', '!=', '')
            ->where(function ($q) {
                $q->whereNull('comprobante_pdf_disk')
                  ->orWhere('comprobante_pdf_disk', 'local')
                  ->orWhere('comprobante_pdf_disk', '!=', AppointmentConfirmationDocumentService::DISK);
            })
            ->get();

        $candidatosMigrables = 0;
        $missingFiles = 0;

        foreach ($candidatos as $cita) {
            $normalized = ltrim((string) $cita->comprobante_pdf_path, '/');
            if (Storage::disk('local')->exists($normalized) || Storage::disk('public')->exists($normalized)) {
                $candidatosMigrables++;
            } else {
                $missingFiles++;
            }
        }

        $localFolder = 'citas/comprobantes';
        $localFiles = Storage::disk('local')->exists($localFolder)
            ? Storage::disk('local')->files($localFolder)
            : [];

        $totalFiles = count($localFiles);
        $linkedPaths = Cita::whereNotNull('comprobante_pdf_path')
            ->where('comprobante_pdf_path', '!=', '')
            ->pluck('comprobante_pdf_path')
            ->map(fn ($p) => ltrim((string) $p, '/'))
            ->toArray();

        $linkedCount = 0;
        $orphanCount = 0;
        $totalBytes = 0;

        foreach ($localFiles as $file) {
            $bytes = Storage::disk('local')->size($file);
            $totalBytes += $bytes;

            if (in_array(ltrim($file, '/'), $linkedPaths, true)) {
                $linkedCount++;
            } else {
                $orphanCount++;
            }
        }

        $formattedBytes = number_format($totalBytes) . ' B (' . round($totalBytes / 1024, 2) . ' KB)';

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Citas in DB', $totalCitas],
                ['Citas with comprobante_pdf_path in DB', $conRuta],
                ['Citas without comprobante_pdf_path in DB', $sinRuta],
                ['Already Migrated to R2', $alreadyR2],
                ['Candidates for Migration (Local/Public Exists)', $candidatosMigrables],
                ['Active Missing PDF File', $missingFiles],
                ['Total Files in Local citas/comprobantes/ Folder', $totalFiles],
                ['Linked Local PDF Files', $linkedCount],
                ['Local Orphan Files (Will be preserved & omitted)', $orphanCount],
                ['Total Bytes in Local citas/comprobantes/', $formattedBytes],
            ]
        );

        $this->info("Dry-run complete. System is ready to execute migration.");
        return self::SUCCESS;
    }

    private function runExecute(AppointmentConfirmationDocumentService $confirmationDocumentService): int
    {
        $this->info("=== EXECUTE: MIGRATING ACTIVE APPOINTMENT CONFIRMATION PDFs TO R2 ===");

        $citas = Cita::whereNotNull('comprobante_pdf_path')
            ->where('comprobante_pdf_path', '!=', '')
            ->where(function ($q) {
                $q->whereNull('comprobante_pdf_disk')
                  ->orWhere('comprobante_pdf_disk', 'local')
                  ->orWhere('comprobante_pdf_disk', '!=', AppointmentConfirmationDocumentService::DISK);
            })
            ->get();

        $migrated = 0;
        $r2Disk = Storage::disk(AppointmentConfirmationDocumentService::DISK);

        foreach ($citas as $cita) {
            $normalizedPath = ltrim((string) $cita->comprobante_pdf_path, '/');
            $sourceDisk = null;

            if (Storage::disk('local')->exists($normalizedPath)) {
                $sourceDisk = 'local';
            } elseif (Storage::disk('public')->exists($normalizedPath)) {
                $sourceDisk = 'public';
            }

            if (! $sourceDisk) {
                $this->warn("Cita #{$cita->id}: Archivo local '{$normalizedPath}' no existe. Omitiendo.");
                continue;
            }

            $bytes = Storage::disk($sourceDisk)->get($normalizedPath);
            if (! $confirmationDocumentService->verifyPdfContent($bytes)) {
                $this->error("Cita #{$cita->id}: Contenido PDF corrupto o inválido en el almacenamiento local. Omitiendo.");
                continue;
            }

            $uuid = (string) Str::uuid();
            $r2Key = "documents/appointment-confirmations/{$cita->id}/{$uuid}.pdf";

            $r2Disk->put($r2Key, $bytes);

            if (! $r2Disk->exists($r2Key) || ! $confirmationDocumentService->verifyPdfContent($r2Disk->get($r2Key))) {
                $r2Disk->delete($r2Key);
                $this->error("Cita #{$cita->id}: Falló la verificación post-subida a R2 para '{$r2Key}'. Omitiendo.");
                continue;
            }

            try {
                DB::transaction(function () use ($cita, $r2Key) {
                    $cita->forceFill([
                        'comprobante_pdf_path' => $r2Key,
                        'comprobante_pdf_disk' => AppointmentConfirmationDocumentService::DISK,
                    ])->saveQuietly();
                });

                $migrated++;
                $this->info("Cita #{$cita->id} migrada: {$normalizedPath} -> {$r2Key} (Size: " . strlen($bytes) . " B)");
            } catch (\Throwable $e) {
                $r2Disk->delete($r2Key);
                $this->error("Cita #{$cita->id}: Error actualizando la base de datos: {$e->getMessage()}");
            }
        }

        $this->info("Successfully migrated {$migrated} active appointment confirmation PDF(s) to R2.");
        return self::SUCCESS;
    }

    private function runVerify(AppointmentConfirmationDocumentService $confirmationDocumentService): int
    {
        $this->info("=== VERIFY: CHECKING MIGRATED APPOINTMENT CONFIRMATION PDFs IN R2 ===");

        $citas = Cita::where('comprobante_pdf_disk', AppointmentConfirmationDocumentService::DISK)->get();
        $verified = 0;
        $errors = 0;
        $r2Disk = Storage::disk(AppointmentConfirmationDocumentService::DISK);

        foreach ($citas as $cita) {
            $key = (string) $cita->comprobante_pdf_path;
            if (empty($key) || ! $r2Disk->exists($key)) {
                $this->error("Cita #{$cita->id} error: Clave '{$key}' no existe en R2.");
                $errors++;
                continue;
            }

            $content = $r2Disk->get($key);
            if (! $confirmationDocumentService->verifyPdfContent($content)) {
                $this->error("Cita #{$cita->id} error: Clave '{$key}' en R2 no tiene cabecera %PDF o tamaño válido.");
                $errors++;
                continue;
            }

            $size = strlen($content);
            $this->info("Cita #{$cita->id} verificada en R2: {$key} (Size: {$size} B, Header: %PDF)");
            $verified++;
        }

        $this->info("Verification complete. Verified: {$verified}, Errors: {$errors}");
        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
