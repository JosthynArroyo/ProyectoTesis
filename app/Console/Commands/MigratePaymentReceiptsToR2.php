<?php

namespace App\Console\Commands;

use App\Models\PaymentReceipt;
use App\Services\PaymentReceiptDocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigratePaymentReceiptsToR2 extends Command
{
    protected $signature = 'payment-receipts:migrate-to-r2
                            {--dry-run : Audit candidates without making any changes}
                            {--execute : Perform the migration of active payment receipt PDFs to R2}
                            {--verify : Verify that migrated payment receipt PDFs exist in R2 with matching size and %PDF header}';

    protected $description = 'Migrate active payment receipt PDFs from local storage to Cloudflare R2 private';

    public function handle(PaymentReceiptDocumentService $documentService): int
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

    protected function handleDryRun(PaymentReceiptDocumentService $documentService): int
    {
        $this->info('=== DRY RUN: AUDIT PAYMENT RECEIPT PDFs FOR R2 MIGRATION ===');

        $activeReceipts = PaymentReceipt::query()->whereNotNull('pdf_path')->where('pdf_path', '!=', '')->get();
        $totalActiveInDb = $activeReceipts->count();

        $candidates = 0;
        $alreadyMigrated = 0;
        $missingFiles = 0;

        foreach ($activeReceipts as $receipt) {
            if ($receipt->pdf_disk === PaymentReceiptDocumentService::DISK) {
                $alreadyMigrated++;
            } else {
                $resolved = $documentService->resolveStorage($receipt->pdf_path, $receipt->pdf_disk);
                if ($resolved) {
                    $candidates++;
                } else {
                    $missingFiles++;
                }
            }
        }

        $activeLocalPathsSet = $activeReceipts->pluck('pdf_path')->filter()->flip();

        $allLocalFiles = Storage::disk('local')->exists('pagos/recibos')
            ? Storage::disk('local')->allFiles('pagos/recibos')
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
            ['Total Receipts in DB', PaymentReceipt::count()],
            ['Active Receipts with pdf_path in DB', $totalActiveInDb],
            ['Already Migrated to R2', $alreadyMigrated],
            ['Candidates for Migration (Local/Public Exists)', $candidates],
            ['Active Missing PDF File', $missingFiles],
            ['Total Files in Local pagos/recibos/ Folder', $totalLocalFiles],
            ['Linked Local PDF Files', $candidates],
            ['Local Orphan Files (Will be preserved & omitted)', $orphansCount],
            ['Total Bytes in Local pagos/recibos/', number_format($totalBytes) . ' B (' . round($totalBytes / 1024, 2) . ' KB)'],
        ]);

        if ($candidates > 0 && $missingFiles === 0) {
            $this->info('Dry-run complete. System is ready to execute migration.');
        } elseif ($candidates === 0) {
            $this->info('Zero active candidates to migrate. System is ready or already migrated.');
        } elseif ($missingFiles > 0) {
            $this->warn("Warning: {$missingFiles} active receipts have missing PDF files!");
        }

        return 0;
    }

    protected function handleExecute(PaymentReceiptDocumentService $documentService): int
    {
        $this->info('=== EXECUTE: MIGRATING ACTIVE PAYMENT RECEIPT PDFs TO R2 ===');

        $activeReceipts = PaymentReceipt::query()->whereNotNull('pdf_path')->where('pdf_path', '!=', '')->get();
        $successCount = 0;

        foreach ($activeReceipts as $receipt) {
            if ($receipt->pdf_disk === PaymentReceiptDocumentService::DISK) {
                $this->line("Receipt #{$receipt->id} ({$receipt->folio_recibo}) is already in R2.");
                continue;
            }

            $resolved = $documentService->resolveStorage($receipt->pdf_path, $receipt->pdf_disk);
            if (! $resolved) {
                $this->error("Receipt #{$receipt->id} file not found: {$receipt->pdf_path}");
                continue;
            }

            $sourceDisk = $resolved['disk'];
            $sourcePath = $resolved['path'];

            $content = Storage::disk($sourceDisk)->get($sourcePath);
            if (! $content || ! str_starts_with($content, '%PDF')) {
                $this->error("Receipt #{$receipt->id} content invalid or not starting with %PDF");
                continue;
            }

            $uuid = Str::uuid()->toString();
            $newKey = "documents/payment-receipts/{$receipt->id}/{$uuid}.pdf";

            $putSuccess = Storage::disk(PaymentReceiptDocumentService::DISK)->put($newKey, $content);
            if (! $putSuccess || ! Storage::disk(PaymentReceiptDocumentService::DISK)->exists($newKey)) {
                $this->error("Receipt #{$receipt->id} failed to upload to R2 key: {$newKey}");
                continue;
            }

            $r2Size = Storage::disk(PaymentReceiptDocumentService::DISK)->size($newKey);
            $sourceSize = Storage::disk($sourceDisk)->size($sourcePath);

            if ($r2Size <= 0 || $r2Size !== $sourceSize) {
                Storage::disk(PaymentReceiptDocumentService::DISK)->delete($newKey);
                $this->error("Receipt #{$receipt->id} size mismatch (R2: {$r2Size}, Source: {$sourceSize})");
                continue;
            }

            DB::transaction(function () use ($receipt, $newKey) {
                $receipt->pdf_path = $newKey;
                $receipt->pdf_disk = PaymentReceiptDocumentService::DISK;
                $receipt->save();
            });

            $successCount++;
            $this->info("Receipt #{$receipt->id} migrated: {$sourcePath} -> {$newKey} (Size: {$r2Size} B)");
        }

        $this->info("Successfully migrated {$successCount} active payment receipt PDF(s) to R2.");

        return 0;
    }

    protected function handleVerify(PaymentReceiptDocumentService $documentService): int
    {
        $this->info('=== VERIFY: CHECKING MIGRATED PAYMENT RECEIPT PDFs IN R2 ===');

        $r2Receipts = PaymentReceipt::query()->where('pdf_disk', PaymentReceiptDocumentService::DISK)->get();
        $verifiedCount = 0;
        $errors = 0;

        foreach ($r2Receipts as $receipt) {
            $key = $receipt->pdf_path;
            $r2Disk = Storage::disk(PaymentReceiptDocumentService::DISK);

            if (! $key || ! $r2Disk->exists($key)) {
                $this->error("Verification FAILED: Receipt #{$receipt->id} key not found in R2: {$key}");
                $errors++;

                continue;
            }

            $size = $r2Disk->size($key);
            if ($size <= 0) {
                $this->error("Verification FAILED: Receipt #{$receipt->id} key is empty in R2: {$key}");
                $errors++;

                continue;
            }

            $header = $r2Disk->get($key);
            if (! str_starts_with((string) $header, '%PDF')) {
                $this->error("Verification FAILED: Receipt #{$receipt->id} does not start with %PDF header");
                $errors++;

                continue;
            }

            $verifiedCount++;
            $this->line("Receipt #{$receipt->id} verified in R2: {$key} (Size: {$size} B, Header: %PDF)");
        }

        $this->info("Verification complete. Verified: {$verifiedCount}, Errors: {$errors}");

        return $errors > 0 ? 1 : 0;
    }
}
