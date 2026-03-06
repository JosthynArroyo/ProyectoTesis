<?php

namespace App\Console\Commands;

use App\Models\LandingWelcomeDoctor;
use App\Models\LandingWelcomeSlide;
use App\Models\User;
use App\Services\ImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Throwable;

class OptimizeExistingImages extends Command
{
    protected $signature = 'images:optimize-existing
        {--dry-run : Solo mostrar que se procesaria}
        {--folder= : Filtrar por carpeta objetivo (users, doctors, patients, banners, uploads)}
        {--limit= : Limitar la cantidad de imagenes a procesar}';

    protected $description = 'Genera variantes WEBP (thumb, medium, large) para imagenes existentes sin perder referencias actuales.';

    public function handle(ImageOptimizer $optimizer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $folderFilter = $this->normalizeFolderOption((string) $this->option('folder'));
        $limit = $this->parseLimit($this->option('limit'));

        $references = $this->collectReferences();
        if (empty($references)) {
            $this->warn('No se encontraron imagenes para procesar.');
            return self::SUCCESS;
        }

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($references as $reference) {
            if ($folderFilter && $reference['folder'] !== $folderFilter) {
                $skipped++;
                continue;
            }

            if ($limit !== null && $processed >= $limit) {
                break;
            }

            $source = $this->resolveSource($optimizer, $reference['path']);
            if (! $source) {
                $skipped++;
                continue;
            }

            if (Str::startsWith($source['normalized'], 'images/')) {
                $skipped++;
                continue;
            }

            $baseName = pathinfo($source['normalized'], PATHINFO_FILENAME);
            $message = sprintf(
                '%s [%s] %s -> images/%s/{thumb,medium,large}/%s.webp',
                $dryRun ? 'DRY-RUN' : 'PROCESAR',
                $reference['origin'],
                $source['normalized'],
                $reference['folder'],
                $optimizer->normalizeBaseName($baseName)
            );
            $this->line($message);

            if ($dryRun) {
                $processed++;
                continue;
            }

            try {
                if ($source['type'] === 'disk_public') {
                    $result = $optimizer->optimizeExistingPublicPath(
                        publicPath: $source['normalized'],
                        folder: $reference['folder'],
                        baseName: $baseName
                    );
                } else {
                    $result = $optimizer->optimizeAbsolutePath(
                        absolutePath: $source['absolute'],
                        folder: $reference['folder'],
                        baseName: $baseName
                    );
                }

                if (! $result) {
                    $failed++;
                    $this->warn('  - No se pudo optimizar: '.$source['normalized']);
                    continue;
                }
            } catch (Throwable $exception) {
                $failed++;
                $this->warn('  - Error: '.$exception->getMessage());
                continue;
            }

            $processed++;
        }

        $this->newLine();
        $this->info(sprintf(
            'Finalizado. Procesadas: %d, Omitidas: %d, Fallidas: %d%s',
            $processed,
            $skipped,
            $failed,
            $dryRun ? ' (modo dry-run)' : ''
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array{path:string,folder:string,origin:string}>
     */
    private function collectReferences(): array
    {
        $items = [];
        $seen = [];

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'avatar')) {
            $hasRoleTables = Schema::hasTable('roles') && Schema::hasTable('role_user');
            $userQuery = User::query()->whereNotNull('avatar');
            if ($hasRoleTables) {
                $userQuery->with('roles:id,name');
            }

            $userQuery->chunkById(200, function ($users) use (&$items, &$seen) {
                foreach ($users as $user) {
                    $path = trim((string) $user->avatar);
                    if ($path === '') {
                        continue;
                    }

                    $folder = $this->resolveUserFolder($user);
                    $key = $folder.'|'.$path;
                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                    $items[] = [
                        'path' => $path,
                        'folder' => $folder,
                        'origin' => 'users.avatar#'.$user->id,
                    ];
                }
            });
        }

        if (Schema::hasTable('landing_welcome_slides') && Schema::hasColumn('landing_welcome_slides', 'image_path')) {
            LandingWelcomeSlide::query()
                ->whereNotNull('image_path')
                ->chunkById(200, function ($slides) use (&$items, &$seen) {
                    foreach ($slides as $slide) {
                        $path = trim((string) $slide->image_path);
                        if ($path === '') {
                            continue;
                        }

                        $key = 'banners|'.$path;
                        if (isset($seen[$key])) {
                            continue;
                        }
                        $seen[$key] = true;

                        $items[] = [
                            'path' => $path,
                            'folder' => 'banners',
                            'origin' => 'landing_welcome_slides#'.$slide->id,
                        ];
                    }
                });
        }

        if (Schema::hasTable('landing_welcome_doctors') && Schema::hasColumn('landing_welcome_doctors', 'photo_path')) {
            LandingWelcomeDoctor::query()
                ->whereNotNull('photo_path')
                ->chunkById(200, function ($doctors) use (&$items, &$seen) {
                    foreach ($doctors as $doctor) {
                        $path = trim((string) $doctor->photo_path);
                        if ($path === '') {
                            continue;
                        }

                        $key = 'doctors|'.$path;
                        if (isset($seen[$key])) {
                            continue;
                        }
                        $seen[$key] = true;

                        $items[] = [
                            'path' => $path,
                            'folder' => 'doctors',
                            'origin' => 'landing_welcome_doctors#'.$doctor->id,
                        ];
                    }
                });
        }

        foreach ($this->collectUploadsFromDirectory(storage_path('app/public/uploads'), 'storage/uploads') as $path) {
            $key = 'uploads|'.$path;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items[] = [
                'path' => $path,
                'folder' => 'uploads',
                'origin' => 'storage/uploads',
            ];
        }

        foreach ($this->collectUploadsFromDirectory(public_path('uploads'), 'public/uploads') as $path) {
            $key = 'uploads|'.$path;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items[] = [
                'path' => $path,
                'folder' => 'uploads',
                'origin' => 'public/uploads',
            ];
        }

        return $items;
    }

    /**
     * @return array<int, string>
     */
    private function collectUploadsFromDirectory(string $absoluteDirectory, string $origin): array
    {
        if (! is_dir($absoluteDirectory)) {
            return [];
        }

        $finder = (new Finder())
            ->files()
            ->in($absoluteDirectory)
            ->name('/\.(jpg|jpeg|png|webp|svg)$/i');

        $paths = [];
        foreach ($finder as $file) {
            $absolutePath = $file->getRealPath();
            if (! $absolutePath) {
                continue;
            }

            if ($origin === 'storage/uploads') {
                $relative = ltrim(str_replace('\\', '/', Str::after($absolutePath, storage_path('app/public'))), '/');
            } else {
                $relative = ltrim(str_replace('\\', '/', Str::after($absolutePath, public_path())), '/');
            }

            if ($relative !== '') {
                $paths[] = $relative;
            }
        }

        return $paths;
    }

    /**
     * @return array{type:string,normalized:string,absolute:string}|null
     */
    private function resolveSource(ImageOptimizer $optimizer, string $path): ?array
    {
        $normalized = $optimizer->normalizeStoredPath($path);
        if (! $normalized || Str::startsWith($normalized, ['http://', 'https://', 'data:'])) {
            return null;
        }

        if (Storage::disk('public')->exists($normalized)) {
            return [
                'type' => 'disk_public',
                'normalized' => $normalized,
                'absolute' => Storage::disk('public')->path($normalized),
            ];
        }

        $absolutePublic = public_path($normalized);
        if (is_file($absolutePublic)) {
            return [
                'type' => 'public_path',
                'normalized' => $normalized,
                'absolute' => $absolutePublic,
            ];
        }

        return null;
    }

    private function resolveUserFolder(User $user): string
    {
        $roles = $user->relationLoaded('roles')
            ? $user->roles->pluck('name')->map(fn ($name) => strtolower((string) $name))->all()
            : [];

        if (in_array('doctor', $roles, true) || in_array('laboratorio', $roles, true)) {
            return 'doctors';
        }

        if (in_array('paciente', $roles, true)) {
            return 'patients';
        }

        return 'users';
    }

    private function parseLimit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $limit = max(0, (int) $value);
        return $limit > 0 ? $limit : null;
    }

    private function normalizeFolderOption(string $folder): ?string
    {
        $folder = trim(strtolower($folder));
        if ($folder === '') {
            return null;
        }

        return preg_replace('#[^a-z0-9/_-]+#', '', $folder) ?: null;
    }
}
