<?php

namespace App\Console\Commands;

use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Services\PaymentProofStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigratePaymentProofsToR2 extends Command
{
    protected $signature = 'payment-proofs:migrate-to-r2
                            {--dry-run : Audit candidates without making any changes}
                            {--execute : Perform the migration of active payment proofs to R2}
                            {--verify : Verify that migrated payment proofs exist in R2 with matching size}';

    protected $description = 'Migrate active payment proofs from local/public storage to Cloudflare R2 private';

    public function handle(PaymentProofStorageService $storageService): int
    {
        $dryRun = $this->option('dry-run');
        $execute = $this->option('execute');
        $verify = $this->option('verify');

        if (! $dryRun && ! $execute && ! $verify) {
            $this->error('Please specify one of: --dry-run, --execute, or --verify');

            return 1;
        }

        if ($dryRun) {
            return $this->handleDryRun($storageService);
        }

        if ($execute) {
            return $this->handleExecute($storageService);
        }

        if ($verify) {
            return $this->handleVerify($storageService);
        }

        return 0;
    }

    protected function handleDryRun(PaymentProofStorageService $storageService): int
    {
        $this->info('=== DRY RUN: AUDIT PAYMENT PROOFS FOR R2 MIGRATION ===');

        $activePagos = Pago::query()->whereNotNull('comprobante_path')->where('comprobante_path', '!=', '')->get();
        $totalActivePagos = $activePagos->count();

        $candidates = 0;
        $alreadyMigrated = 0;
        $missingLocal = 0;

        foreach ($activePagos as $pago) {
            if ($pago->comprobante_disk === PaymentProofStorageService::DISK) {
                $alreadyMigrated++;
            } else {
                $resolved = $storageService->resolveStorage($pago->comprobante_path, $pago->comprobante_disk);
                if ($resolved) {
                    $candidates++;
                } else {
                    $missingLocal++;
                }
            }
        }

        $activePathsSet = $activePagos->pluck('comprobante_path')->filter()->map(fn ($p) => ltrim(str_replace('\\', '/', $p), '/'))->flip();
        $receiptPaths = PaymentReceipt::query()->whereNotNull('comprobante_path')->where('comprobante_path', '!=', '')->pluck('comprobante_path');
        foreach ($receiptPaths as $rp) {
            $activePathsSet->put(ltrim(str_replace('\\', '/', $rp), '/'), true);
        }

        $allLocalFiles = [];
        foreach (['public', 'local'] as $d) {
            $diskInst = Storage::disk($d);
            foreach (['payment-proofs', 'images/payment-proofs', 'pagos/comprobantes'] as $folder) {
                if ($diskInst->exists($folder)) {
                    foreach ($diskInst->allFiles($folder) as $f) {
                        $allLocalFiles[] = $f;
                    }
                }
            }
        }

        $orphansCount = 0;
        foreach ($allLocalFiles as $file) {
            $normalized = ltrim(str_replace('\\', '/', $file), '/');
            if (! $activePathsSet->has($normalized) && ! $activePathsSet->has('storage/' . $normalized)) {
                $orphansCount++;
            }
        }

        $this->table(['Metric', 'Count'], [
            ['Active Payments with Proof in DB', $totalActivePagos],
            ['Already Migrated to R2', $alreadyMigrated],
            ['Candidates for Migration (Local/Public Exists)', $candidates],
            ['Active Missing File', $missingLocal],
            ['Total Local Proof Files Scanned', count($allLocalFiles)],
            ['Local Orphan Files (Will be preserved & omitted)', $orphansCount],
        ]);

        if ($candidates > 0 && $missingLocal === 0) {
            $this->info('Dry-run complete. System is ready to execute migration.');
        } elseif ($candidates === 0) {
            $this->info('Zero active candidates to migrate. System is ready or already migrated.');
        } elseif ($missingLocal > 0) {
            $this->warn("Warning: {$missingLocal} active payments have missing files!");
        }

        return 0;
    }

    protected function handleExecute(PaymentProofStorageService $storageService): int
    {
        $this->info('=== EXECUTE: MIGRATING ACTIVE PAYMENT PROOFS TO R2 ===');

        $activePagos = Pago::query()->whereNotNull('comprobante_path')->where('comprobante_path', '!=', '')->get();
        $successCount = 0;

        foreach ($activePagos as $pago) {
            if ($pago->comprobante_disk === PaymentProofStorageService::DISK) {
                $this->line("Pago #{$pago->id} is already in R2.");
                continue;
            }

            $resolved = $storageService->resolveStorage($pago->comprobante_path, $pago->comprobante_disk);
            if (! $resolved) {
                $this->error("Pago #{$pago->id} file not found: {$pago->comprobante_path}");
                continue;
            }

            $sourceDisk = $resolved['disk'];
            $sourcePath = $resolved['path'];

            $content = Storage::disk($sourceDisk)->get($sourcePath);
            if (! $content) {
                $this->error("Pago #{$pago->id} could not read content from {$sourcePath}");
                continue;
            }

            $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) ?: 'jpg';
            $uuid = Str::uuid()->toString();
            $newKey = "documents/payment-proofs/{$pago->id}/{$uuid}.{$ext}";

            $putSuccess = Storage::disk(PaymentProofStorageService::DISK)->put($newKey, $content);
            if (! $putSuccess || ! Storage::disk(PaymentProofStorageService::DISK)->exists($newKey)) {
                $this->error("Pago #{$pago->id} failed to upload to R2 key: {$newKey}");
                continue;
            }

            $r2Size = Storage::disk(PaymentProofStorageService::DISK)->size($newKey);
            $sourceSize = Storage::disk($sourceDisk)->size($sourcePath);

            if ($r2Size <= 0 || $r2Size !== $sourceSize) {
                Storage::disk(PaymentProofStorageService::DISK)->delete($newKey);
                $this->error("Pago #{$pago->id} size mismatch (R2: {$r2Size}, Source: {$sourceSize})");
                continue;
            }

            $oldPath = $pago->comprobante_path;

            DB::transaction(function () use ($pago, $newKey, $oldPath) {
                $pago->comprobante_path = $newKey;
                $pago->comprobante_disk = PaymentProofStorageService::DISK;
                $pago->save();

                PaymentReceipt::query()
                    ->where('pago_id', $pago->id)
                    ->where('comprobante_path', $oldPath)
                    ->update([
                        'comprobante_path' => $newKey,
                        'comprobante_disk' => PaymentProofStorageService::DISK,
                    ]);
            });

            $successCount++;
            $this->info("Pago #{$pago->id} migrated: {$sourcePath} -> {$newKey} (Size: {$r2Size} B)");
        }

        $this->info("Successfully migrated {$successCount} active payment proof(s) to R2.");

        return 0;
    }

    protected function handleVerify(PaymentProofStorageService $storageService): int
    {
        $this->info('=== VERIFY: CHECKING MIGRATED PAYMENT PROOFS IN R2 ===');

        $r2Pagos = Pago::query()->where('comprobante_disk', PaymentProofStorageService::DISK)->get();
        $verifiedCount = 0;
        $errors = 0;

        foreach ($r2Pagos as $pago) {
            $key = $pago->comprobante_path;
            $r2Disk = Storage::disk(PaymentProofStorageService::DISK);

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

            $receipts = PaymentReceipt::query()->where('pago_id', $pago->id)->get();
            foreach ($receipts as $receipt) {
                if ($receipt->comprobante_path && ($receipt->comprobante_disk !== PaymentProofStorageService::DISK || $receipt->comprobante_path !== $key)) {
                    $this->warn("Receipt #{$receipt->id} for Pago #{$pago->id} has mismatched snapshot");
                }
            }

            $verifiedCount++;
            $this->line("Pago #{$pago->id} verified in R2: {$key} (Size: {$size} B)");
        }

        $this->info("Verification complete. Verified: {$verifiedCount}, Errors: {$errors}");

        return $errors > 0 ? 1 : 0;
    }
}
