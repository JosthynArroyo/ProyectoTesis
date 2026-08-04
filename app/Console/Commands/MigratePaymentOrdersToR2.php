<?php

namespace App\Console\Commands;

use App\Models\Pago;
use App\Services\PaymentOrderDocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigratePaymentOrdersToR2 extends Command
{
    protected $signature = 'payment-orders:migrate-to-r2
                            {--dry-run : Audit candidates without making any changes}
                            {--execute : Perform the migration of active payment order PDFs to R2}
                            {--verify : Verify that migrated payment order PDFs exist in R2 with matching size and %PDF header}';

    protected $description = 'Migrate active payment order PDFs from local storage to Cloudflare R2 private';

    public function handle(PaymentOrderDocumentService $documentService): int
    {
        $dryRun = $this->option('dry-run');
        $execute = $this->option('execute');
        $verify = $this->option('verify');

        if (! $dryRun && ! $execute && ! $verify) {
            $this->error('Please specify one of: --dry-run, --execute, or --verify');

            return 1;
        }

        if ($dryRun) {
            return $this->handleDryRun($documentService);
        }

        if ($execute) {
            return $this->handleExecute($documentService);
        }

        if ($verify) {
            return $this->handleVerify($documentService);
        }

        return 0;
    }

    protected function handleDryRun(PaymentOrderDocumentService $documentService): int
    {
        $this->info('=== DRY RUN: AUDIT PAYMENT ORDER PDFs FOR R2 MIGRATION ===');

        $activeOrders = Pago::query()->whereNotNull('orden_pdf_path')->where('orden_pdf_path', '!=', '')->get();
        $totalActiveInDb = $activeOrders->count();
        $totalPagosInDb = Pago::count();
        $sinOrdenPdfInDb = Pago::query()->whereNull('orden_pdf_path')->orWhere('orden_pdf_path', '')->count();

        $candidates = 0;
        $alreadyMigrated = 0;
        $missingFiles = 0;

        foreach ($activeOrders as $pago) {
            if ($pago->orden_pdf_disk === PaymentOrderDocumentService::DISK) {
                $alreadyMigrated++;
            } else {
                $resolved = $documentService->resolveStorage($pago->orden_pdf_path, $pago->orden_pdf_disk);
                if ($resolved) {
                    $candidates++;
                } else {
                    $missingFiles++;
                }
            }
        }

        $activeLocalPathsSet = $activeOrders->pluck('orden_pdf_path')->filter()->flip();

        $allLocalFiles = Storage::disk('local')->exists('pagos/ordenes')
            ? Storage::disk('local')->allFiles('pagos/ordenes')
            : [];
        $totalLocalFiles = count($allLocalFiles);

        $orphansCount = 0;
        $totalBytes = 0;

        foreach ($allLocalFiles as $file) {
            $size = Storage::disk('local')->size($file);
            $totalBytes += $size;
            if (! isset($activeLocalPathsSet[$file])) {
                $orphansCount++;
            }
        }

        $this->table(['Metric', 'Count'], [
            ['Total Pagos in DB', $totalPagosInDb],
            ['Pagos with orden_pdf_path in DB', $totalActiveInDb],
            ['Pagos without orden_pdf_path in DB', $sinOrdenPdfInDb],
            ['Already Migrated to R2', $alreadyMigrated],
            ['Candidates for Migration (Local/Public Exists)', $candidates],
            ['Active Missing PDF File', $missingFiles],
            ['Total Files in Local pagos/ordenes/ Folder', $totalLocalFiles],
            ['Linked Local PDF Files', $candidates],
            ['Local Orphan Files (Will be preserved & omitted)', $orphansCount],
            ['Total Bytes in Local pagos/ordenes/', number_format($totalBytes) . ' B (' . round($totalBytes / 1024, 2) . ' KB)'],
        ]);

        if ($candidates > 0 && $missingFiles === 0) {
            $this->info('Dry-run complete. System is ready to execute migration.');
        } elseif ($candidates === 0) {
            $this->info('Zero active candidates to migrate. System is ready or already migrated.');
        } elseif ($missingFiles > 0) {
            $this->warn("Warning: {$missingFiles} active payment orders have missing PDF files!");
        }

        return 0;
    }

    protected function handleExecute(PaymentOrderDocumentService $documentService): int
    {
        $this->info('=== EXECUTE: MIGRATING ACTIVE PAYMENT ORDER PDFs TO R2 ===');

        $activeOrders = Pago::query()->whereNotNull('orden_pdf_path')->where('orden_pdf_path', '!=', '')->get();
        $successCount = 0;

        foreach ($activeOrders as $pago) {
            if ($pago->orden_pdf_disk === PaymentOrderDocumentService::DISK) {
                $this->line("Pago #{$pago->id} ({$pago->folio_unico}) is already in R2.");
                continue;
            }

            $resolved = $documentService->resolveStorage($pago->orden_pdf_path, $pago->orden_pdf_disk);
            if (! $resolved) {
                $this->error("Pago #{$pago->id} file not found: {$pago->orden_pdf_path}");
                continue;
            }

            $sourceDisk = $resolved['disk'];
            $sourcePath = $resolved['path'];

            $content = Storage::disk($sourceDisk)->get($sourcePath);
            if (! $content || ! str_starts_with($content, '%PDF')) {
                $this->error("Pago #{$pago->id} content invalid or not starting with %PDF");
                continue;
            }

            $uuid = Str::uuid()->toString();
            $newKey = "documents/payment-orders/{$pago->id}/{$uuid}.pdf";

            $putSuccess = Storage::disk(PaymentOrderDocumentService::DISK)->put($newKey, $content);
            if (! $putSuccess || ! Storage::disk(PaymentOrderDocumentService::DISK)->exists($newKey)) {
                $this->error("Pago #{$pago->id} failed to upload to R2 key: {$newKey}");
                continue;
            }

            $r2Size = Storage::disk(PaymentOrderDocumentService::DISK)->size($newKey);
            $sourceSize = Storage::disk($sourceDisk)->size($sourcePath);

            if ($r2Size <= 0 || $r2Size !== $sourceSize) {
                Storage::disk(PaymentOrderDocumentService::DISK)->delete($newKey);
                $this->error("Pago #{$pago->id} size mismatch (R2: {$r2Size}, Source: {$sourceSize})");
                continue;
            }

            DB::transaction(function () use ($pago, $newKey) {
                $pago->orden_pdf_path = $newKey;
                $pago->orden_pdf_disk = PaymentOrderDocumentService::DISK;
                $pago->save();
            });

            $successCount++;
            $this->info("Pago #{$pago->id} migrated: {$sourcePath} -> {$newKey} (Size: {$r2Size} B)");
        }

        $this->info("Successfully migrated {$successCount} active payment order PDF(s) to R2.");

        return 0;
    }

    protected function handleVerify(PaymentOrderDocumentService $documentService): int
    {
        $this->info('=== VERIFY: CHECKING MIGRATED PAYMENT ORDER PDFs IN R2 ===');

        $r2Orders = Pago::query()->where('orden_pdf_disk', PaymentOrderDocumentService::DISK)->get();
        $verifiedCount = 0;
        $errors = 0;

        foreach ($r2Orders as $pago) {
            $key = $pago->orden_pdf_path;
            $r2Disk = Storage::disk(PaymentOrderDocumentService::DISK);

            if (! $key || ! $r2Disk->exists($key)) {
                $this->error("Verification FAILED: Pago #{$pago->id} key not found in R2: {$key}");
                $errors++;

                continue;
            }

            $size = $r2Disk->size($key);
            if ($size <= 0) {
                $this->error("Verification FAILED: Pago #{$pago->id} key is empty in R2: {$key}");
                $errors++;

                continue;
            }

            $header = $r2Disk->get($key);
            if (! str_starts_with((string) $header, '%PDF')) {
                $this->error("Verification FAILED: Pago #{$pago->id} does not start with %PDF header");
                $errors++;

                continue;
            }

            $verifiedCount++;
            $this->line("Pago #{$pago->id} verified in R2: {$key} (Size: {$size} B, Header: %PDF)");
        }

        $this->info("Verification complete. Verified: {$verifiedCount}, Errors: {$errors}");

        return $errors > 0 ? 1 : 0;
    }
}
