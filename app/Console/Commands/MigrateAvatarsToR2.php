<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ProfileAvatarService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Throwable;

class MigrateAvatarsToR2 extends Command
{
    protected $signature = 'avatars:migrate-to-r2 {--dry-run} {--execute} {--verify}';

    protected $description = 'Migrates user profile avatars from local storage to r2_private disk';

    public function handle(ProfileAvatarService $avatarService): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isExecute = (bool) $this->option('execute');
        $isVerify = (bool) $this->option('verify');

        if (! $isDryRun && ! $isExecute && ! $isVerify) {
            $this->info('Please specify one of: --dry-run, --execute, or --verify.');
            return self::INVALID;
        }

        $r2DiskName = $avatarService->disk();
        $r2Disk = Storage::disk($r2DiskName);
        $publicDisk = Storage::disk('public');

        if ($isVerify) {
            return $this->runVerification($r2Disk, $r2DiskName);
        }

        $this->info("Starting avatar migration scan (Mode: " . ($isExecute ? 'EXECUTE' : 'DRY RUN') . ")...");

        $users = User::whereNotNull('avatar')->where('avatar', '!=', '')->get();
        $localAvatarFiles = $publicDisk->exists('avatars') ? $publicDisk->allFiles('avatars') : [];

        $manifestData = [
            'timestamp' => now()->toIso8601String(),
            'mode' => $isExecute ? 'execute' : 'dry-run',
            'disk' => $r2DiskName,
            'total_users_checked' => $users->count(),
            'total_local_files_found' => count($localAvatarFiles),
            'processed' => [],
            'migrated_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
        ];

        foreach ($users as $user) {
            $currentPath = (string) $user->avatar;

            // Skip if already migrated to r2_private format
            if (preg_match('#^avatars/\d+/[a-f0-9\-]{36}/original\.[a-z0-9]+$#i', $currentPath)) {
                if ($r2Disk->exists($currentPath)) {
                    $manifestData['skipped_count']++;
                    $manifestData['processed'][] = [
                        'user_id' => $user->id,
                        'previous' => $currentPath,
                        'new' => $currentPath,
                        'status' => 'skipped_already_migrated',
                    ];
                    $this->line("User #{$user->id}: Already migrated to R2 ({$currentPath}). Skipping.");
                    continue;
                }
            }

            // Find local source file
            $localRelative = ltrim(str_replace('/storage/', '', $currentPath), '/');
            $sourceFullPath = null;

            if ($publicDisk->exists($localRelative)) {
                $sourceFullPath = $publicDisk->path($localRelative);
            } elseif (File::exists(public_path('storage/' . $localRelative))) {
                $sourceFullPath = public_path('storage/' . $localRelative);
            }

            if (! $sourceFullPath || ! File::exists($sourceFullPath)) {
                $manifestData['failed_count']++;
                $manifestData['processed'][] = [
                    'user_id' => $user->id,
                    'previous' => $currentPath,
                    'new' => null,
                    'status' => 'failed_source_not_found',
                    'error' => 'Source avatar file not found locally.',
                ];
                $this->warn("User #{$user->id}: Local source file not found for path '{$currentPath}'.");
                continue;
            }

            // Generate R2 target keys
            $uuid = Str::uuid()->toString();
            $ext = strtolower(pathinfo($sourceFullPath, PATHINFO_EXTENSION));
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }

            $baseKey = "avatars/{$user->id}/{$uuid}";
            $originalKey = "{$baseKey}/original.{$ext}";
            $thumbKey = "{$baseKey}/thumb.webp";
            $mediumKey = "{$baseKey}/medium.webp";

            if ($isDryRun) {
                $manifestData['migrated_count']++;
                $manifestData['processed'][] = [
                    'user_id' => $user->id,
                    'previous' => $currentPath,
                    'new' => $originalKey,
                    'status' => 'dry_run_success',
                    'variants' => [$originalKey, $thumbKey, $mediumKey],
                ];
                $this->info("[DRY RUN] User #{$user->id}: Would migrate {$currentPath} -> {$originalKey}");
                continue;
            }

            // EXECUTE MODE: Process image and upload to r2_private
            try {
                $manager = new ImageManager(new GdDriver());
                $img = $manager->read($sourceFullPath)->orient();

                $originalBytes = match ($ext) {
                    'png' => (string) $img->toPng(),
                    'webp' => (string) $img->toWebp(85),
                    default => (string) $img->toJpeg(85),
                };

                $thumbImg = (clone $img)->cover(128, 128);
                $thumbBytes = (string) $thumbImg->toWebp(83);

                $mediumImg = (clone $img)->cover(512, 512);
                $mediumBytes = (string) $mediumImg->toWebp(83);

                $r2Disk->put($originalKey, $originalBytes);
                $r2Disk->put($thumbKey, $thumbBytes);
                $r2Disk->put($mediumKey, $mediumBytes);

                // Update DB record
                $user->avatar = $originalKey;
                $user->save();

                $manifestData['migrated_count']++;
                $manifestData['processed'][] = [
                    'user_id' => $user->id,
                    'previous' => $currentPath,
                    'new' => $originalKey,
                    'status' => 'success',
                    'variants' => [$originalKey, $thumbKey, $mediumKey],
                ];
                $this->info("User #{$user->id}: Successfully migrated to {$originalKey}");
            } catch (Throwable $e) {
                $manifestData['failed_count']++;
                $manifestData['processed'][] = [
                    'user_id' => $user->id,
                    'previous' => $currentPath,
                    'new' => null,
                    'status' => 'failed_exception',
                    'error' => $e->getMessage(),
                ];
                $this->error("User #{$user->id}: Error migrating avatar: {$e->getMessage()}");
            }
        }

        $this->saveManifest($manifestData);
        $this->info("Migration completed. Total Migrated: {$manifestData['migrated_count']}, Skipped: {$manifestData['skipped_count']}, Failed: {$manifestData['failed_count']}");

        return $manifestData['failed_count'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function runVerification($r2Disk, string $r2DiskName): int
    {
        $this->info("Running verification scan on {$r2DiskName}...");
        $users = User::whereNotNull('avatar')->where('avatar', '!=', '')->get();
        $verified = 0;
        $failed = 0;

        foreach ($users as $user) {
            $path = (string) $user->avatar;
            if (preg_match('#^avatars/\d+/[a-f0-9\-]{36}/original\.[a-z0-9]+$#i', $path)) {
                if ($r2Disk->exists($path)) {
                    $verified++;
                    $this->line("User #{$user->id}: Verified on R2 ({$path})");
                } else {
                    $failed++;
                    $this->error("User #{$user->id}: DB path '{$path}' NOT found on R2 disk!");
                }
            } else {
                $this->warn("User #{$user->id}: DB path '{$path}' is in legacy format.");
            }
        }

        $this->info("Verification summary: {$verified} verified on R2, {$failed} missing.");
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function saveManifest(array $data): void
    {
        $dir = storage_path('app/private/r2-migration-manifests');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        $file = $dir . '/avatars-' . date('Ymd-His') . '.json';
        File::put($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info("Manifest saved to: {$file}");
    }
}
