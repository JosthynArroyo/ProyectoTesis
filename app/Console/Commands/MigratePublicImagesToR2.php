<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MigratePublicImagesToR2 extends Command
{
    protected $signature = 'app:migrate-public-images-to-r2
        {--execute : Ejecutar la migración real (por defecto solo realiza simulación dry-run)}';

    protected $description = 'Migra imágenes públicas locales desde el disco public hacia r2_public preservando claves relativas.';

    public const ALLOWED_ROOTS = [
        'images/banners',
        'images/doctors',
        'images/branding',
        'images/services',
    ];

    public const EXCLUDED_PREFIXES = [
        'images/avatars',
        'comprobantes',
        'laboratorio_resultados',
        'certificados_medicos',
        'comprobantes_citas',
        'ai',
        'dataset',
        'vision',
        'img',
        'build',
    ];

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $isDryRun = ! $execute;

        $this->info('=== Migración de Imágenes Públicas a Cloudflare R2 ===');
        $this->info('Origen: disco [public]');
        $this->info('Destino: disco [r2_public]');
        $this->info('Raíces permitidas: '.implode(', ', self::ALLOWED_ROOTS));
        $this->info('Modo: '.($isDryRun ? 'SIMULACIÓN (dry-run)' : 'EJECUCIÓN REAL'));

        if ($execute && ! $this->option('no-interaction')) {
            if (! $this->confirm('¿Desea proceder con la copia de imágenes hacia r2_public?', false)) {
                $this->warn('Operación cancelada por el usuario.');

                return self::SUCCESS;
            }
        }

        $files = $this->collectTargetFiles();

        if (empty($files)) {
            $this->info('No se encontraron imágenes en el disco public bajo las raíces permitidas.');
            if ($isDryRun) {
                $this->comment('SIMULACIÓN: no se modificó ningún archivo.');
            }

            return self::SUCCESS;
        }

        $this->info(sprintf('Archivos detectados para evaluar: %d', count($files)));

        $stats = [
            'total_found' => count($files),
            'total_copied' => 0,
            'total_identical' => 0,
            'total_conflicts' => 0,
            'total_errors' => 0,
            'total_bytes' => 0,
        ];

        $manifestItems = [];

        foreach ($files as $relativePath) {
            $itemResult = $this->processFile($relativePath, $isDryRun, $stats);
            $manifestItems[] = $itemResult;
        }

        $this->renderSummary($stats, $isDryRun);

        $manifestPath = $this->writeManifest($stats, $manifestItems, $isDryRun);
        if ($manifestPath) {
            $this->info(sprintf('Manifiesto guardado en: %s', $manifestPath));
        }

        if ($isDryRun) {
            $this->comment('SIMULACIÓN: no se modificó ningún archivo');
        }

        if ($stats['total_conflicts'] > 0 || $stats['total_errors'] > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function collectTargetFiles(): array
    {
        $allFiles = [];

        foreach (self::ALLOWED_ROOTS as $root) {
            try {
                if (Storage::disk('public')->exists($root)) {
                    $found = Storage::disk('public')->allFiles($root);
                    foreach ($found as $file) {
                        $normalized = $this->normalizeKey($file);
                        if ($normalized && $this->isKeyAllowed($normalized)) {
                            $allFiles[] = $normalized;
                        }
                    }
                }
            } catch (Throwable $e) {
                $this->error(sprintf('Error al listar la raíz [%s]: %s', $root, $e->getMessage()));
            }
        }

        return array_values(array_unique($allFiles));
    }

    public function normalizeKey(string $key): ?string
    {
        $key = str_replace('\\', '/', trim($key));
        $key = ltrim($key, '/');

        if ($key === '' || str_contains($key, '..')) {
            return null;
        }

        return $key;
    }

    public function isKeyAllowed(string $key): bool
    {
        $key = ltrim(str_replace('\\', '/', trim($key)), '/');

        foreach (self::EXCLUDED_PREFIXES as $excluded) {
            if (Str::startsWith($key, $excluded)) {
                return false;
            }
        }

        foreach (self::ALLOWED_ROOTS as $allowed) {
            if (Str::startsWith($key, $allowed)) {
                return true;
            }
        }

        return false;
    }

    private function processFile(string $relativePath, bool $isDryRun, array &$stats): array
    {
        $item = [
            'key' => $relativePath,
            'status' => 'pending',
            'size' => 0,
            'sha256' => null,
            'error' => null,
        ];

        try {
            $sourceStream = Storage::disk('public')->readStream($relativePath);
            if (! $sourceStream) {
                $item['status'] = 'error';
                $item['error'] = 'No se pudo abrir el stream de origen.';
                $stats['total_errors']++;

                return $item;
            }

            $sourceInfo = $this->calculateStreamInfo($sourceStream);
            if (is_resource($sourceStream)) {
                fclose($sourceStream);
            }

            $item['size'] = $sourceInfo['size'];
            $item['sha256'] = $sourceInfo['hash'];
            $stats['total_bytes'] += $sourceInfo['size'];

            $destExists = Storage::disk('r2_public')->exists($relativePath);

            if ($destExists) {
                $destStream = Storage::disk('r2_public')->readStream($relativePath);
                $destInfo = $destStream ? $this->calculateStreamInfo($destStream) : ['size' => -1, 'hash' => ''];
                if (is_resource($destStream)) {
                    fclose($destStream);
                }

                if ($sourceInfo['size'] === $destInfo['size'] && $sourceInfo['hash'] === $destInfo['hash']) {
                    $item['status'] = 'already_exists_identical';
                    $stats['total_identical']++;
                    $this->line(sprintf('  [OMITIDO - IDÉNTICO] %s', $relativePath));

                    return $item;
                }

                $item['status'] = 'conflict_different_content';
                $item['error'] = sprintf('Conflicto: tamaño u hash difiere en destino (origen=%d vs dest=%d).', $sourceInfo['size'], $destInfo['size']);
                $stats['total_conflicts']++;
                $this->error(sprintf('  [CONFLICTO] %s - El archivo existe en R2 con contenido diferente.', $relativePath));

                return $item;
            }

            if ($isDryRun) {
                $item['status'] = 'would_copy';
                $stats['total_copied']++;
                $this->line(sprintf('  [SIMULACIÓN COPIA] %s (%d bytes)', $relativePath, $sourceInfo['size']));

                return $item;
            }

            // Real Execution
            $writeStream = Storage::disk('public')->readStream($relativePath);
            $mime = Storage::disk('public')->mimeType($relativePath);
            $options = [];
            if ($mime) {
                $options['ContentType'] = $mime;
            }

            $written = Storage::disk('r2_public')->writeStream($relativePath, $writeStream, $options);
            if (is_resource($writeStream)) {
                fclose($writeStream);
            }

            if (! $written) {
                $item['status'] = 'error';
                $item['error'] = 'Falló el método writeStream en r2_public.';
                $stats['total_errors']++;
                $this->error(sprintf('  [ERROR ESCRITURA] %s', $relativePath));

                return $item;
            }

            // Verification
            $verifyStream = Storage::disk('r2_public')->readStream($relativePath);
            $verifyInfo = $verifyStream ? $this->calculateStreamInfo($verifyStream) : ['size' => -1, 'hash' => ''];
            if (is_resource($verifyStream)) {
                fclose($verifyStream);
            }

            if ($sourceInfo['size'] === $verifyInfo['size'] && $sourceInfo['hash'] === $verifyInfo['hash']) {
                $item['status'] = 'copied_and_verified';
                $stats['total_copied']++;
                $this->info(sprintf('  [COPIADO Y VERIFICADO] %s', $relativePath));

                return $item;
            }

            // Verification Failed - remove created destination object safely
            Storage::disk('r2_public')->delete($relativePath);
            $item['status'] = 'error';
            $item['error'] = 'Falló la verificación del hash/tamaño tras la copia.';
            $stats['total_errors']++;
            $this->error(sprintf('  [ERROR VERIFICACIÓN] %s - El objeto copiado no coincidió y fue removido de R2.', $relativePath));

            return $item;
        } catch (Throwable $e) {
            $item['status'] = 'error';
            $item['error'] = $e->getMessage();
            $stats['total_errors']++;
            $this->error(sprintf('  [EXCEPCIÓN] %s: %s', $relativePath, $e->getMessage()));

            return $item;
        }
    }

    public function calculateStreamInfo($stream): array
    {
        if (! is_resource($stream)) {
            return ['size' => 0, 'hash' => ''];
        }

        $context = hash_init('sha256');
        $size = 0;

        while (! feof($stream)) {
            $chunk = fread($stream, 8192);
            if ($chunk === false) {
                break;
            }
            $size += strlen($chunk);
            hash_update($context, $chunk);
        }

        return [
            'size' => $size,
            'hash' => hash_final($context),
        ];
    }

    private function renderSummary(array $stats, bool $isDryRun): void
    {
        $this->newLine();
        $this->info('=== Resumen de la Operación ===');
        $this->line(sprintf('Modo: %s', $isDryRun ? 'Simulación (dry-run)' : 'Ejecución real'));
        $this->line(sprintf('Archivos evaluados: %d', $stats['total_found']));
        $this->line(sprintf('Archivos copiable(s) / copiado(s): %d', $stats['total_copied']));
        $this->line(sprintf('Archivos idénticos (omitidos): %d', $stats['total_identical']));
        $this->line(sprintf('Conflictos detectados: %d', $stats['total_conflicts']));
        $this->line(sprintf('Errores: %d', $stats['total_errors']));
        $this->line(sprintf('Tamaño total evaluado: %.2f MB', $stats['total_bytes'] / (1024 * 1024)));
        $this->newLine();
    }

    private function writeManifest(array $stats, array $items, bool $isDryRun): ?string
    {
        try {
            $directory = 'r2-migration-manifests';
            if (! Storage::disk('local')->exists($directory)) {
                Storage::disk('local')->makeDirectory($directory);
            }

            $filename = sprintf('%s/public-images-%s.json', $directory, date('Ymd-His'));

            $manifestData = [
                'timestamp' => date('c'),
                'mode' => $isDryRun ? 'dry-run' : 'execute',
                'source_disk' => 'public',
                'target_disk' => 'r2_public',
                'allowed_roots' => self::ALLOWED_ROOTS,
                'stats' => $stats,
                'items' => $items,
            ];

            $json = json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                Storage::disk('local')->put($filename, $json);

                return Storage::disk('local')->path($filename);
            }
        } catch (Throwable $e) {
            $this->warn(sprintf('No se pudo guardar el manifiesto local: %s', $e->getMessage()));
        }

        return null;
    }
}
