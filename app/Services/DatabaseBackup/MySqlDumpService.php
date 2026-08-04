<?php

namespace App\Services\DatabaseBackup;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class MySqlDumpService
{
    /**
     * Generate a full MySQL dump safely using a temporary credentials options file in OS temp dir.
     *
     * @param string $outputFilePath Path where the SQL file should be created.
     * @param string|null $connection Database connection name.
     * @return string Path to the generated SQL dump file.
     */
    public function dump(string $outputFilePath, ?string $connection = null): string
    {
        $connectionName = $connection ?: config('database.default', 'mysql');
        $dbConfig = config("database.connections.{$connectionName}");

        if (! is_array($dbConfig)) {
            throw new RuntimeException("Configuración de base de datos no encontrada para la conexión [{$connectionName}].");
        }

        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = (string) ($dbConfig['port'] ?? 3306);
        $database = $dbConfig['database'] ?? '';
        $username = $dbConfig['username'] ?? '';
        $password = (string) ($dbConfig['password'] ?? '');
        $mysqldumpPath = (string) config('database_backups.mysqldump_path', 'mysqldump');

        if (empty($database)) {
            throw new RuntimeException("El nombre de la base de datos no está configurado.");
        }

        // Create temporary MySQL options file in system temp dir
        $cnfPath = $this->createTempOptionsFile($host, $port, $username, $password);

        try {
            // MySQL requires --defaults-extra-file to be the FIRST option
            $cmd = [
                $mysqldumpPath,
                "--defaults-extra-file={$cnfPath}",
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--events',
                '--hex-blob',
                '--set-gtid-purged=OFF',
                '--result-file=' . $outputFilePath,
                $database,
            ];

            $process = new Process($cmd, null, null, null, 1800);
            $process->run();

            if (! $process->isSuccessful()) {
                $rawError = $process->getErrorOutput() ?: $process->getOutput();
                $sanitizedError = $this->sanitizeLogOutput($rawError, $password);
                throw new RuntimeException("Error ejecutando mysqldump: " . $sanitizedError);
            }

            if (! file_exists($outputFilePath) || filesize($outputFilePath) === 0) {
                throw new RuntimeException("El archivo dump generado está vacío o no fue creado.");
            }

            return $outputFilePath;
        } finally {
            // Guarantee removal of temporary credentials file and its parent folder
            $this->removeTempFile($cnfPath);
        }
    }

    /**
     * Create a restricted temporary MySQL client configuration file in OS temp directory.
     */
    public function createTempOptionsFile(string $host, string $port, string $user, string $password): string
    {
        $uuid = 'cnf_' . Str::random(16);
        $dir = BackupTempDirectoryManager::getTempDir($uuid);

        $cnfPath = $dir . DIRECTORY_SEPARATOR . 'client_' . Str::random(8) . '.cnf';

        $escapedHost = $this->escapeCnfValue($host);
        $escapedPort = $this->escapeCnfValue($port);
        $escapedUser = $this->escapeCnfValue($user);
        $escapedPass = $this->escapeCnfValue($password);

        $content = "[client]\n"
            . "host=\"{$escapedHost}\"\n"
            . "port=\"{$escapedPort}\"\n"
            . "user=\"{$escapedUser}\"\n"
            . "password=\"{$escapedPass}\"\n";

        if (file_put_contents($cnfPath, $content) === false) {
            throw new RuntimeException("No se pudo crear el archivo temporal de credenciales MySQL.");
        }

        @chmod($cnfPath, 0600);

        return $cnfPath;
    }

    /**
     * Safely escape values for MySQL configuration option files.
     */
    public function escapeCnfValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\"'], $value);
    }

    /**
     * Safely delete a temporary file and its parent folder if empty.
     */
    public function removeTempFile(?string $filePath): void
    {
        if ($filePath && file_exists($filePath)) {
            $dir = dirname($filePath);
            @unlink($filePath);
            BackupTempDirectoryManager::deleteTempDir($dir);
        }
    }

    /**
     * Sanitize output strings to remove passwords or sensitive paths/info.
     */
    public function sanitizeLogOutput(string $input, ?string $password = null): string
    {
        $sanitized = $input;

        if (! empty($password)) {
            $sanitized = str_replace($password, '********', $sanitized);
        }

        $sanitized = preg_replace('/--defaults-extra-file=[^\s]+/', '--defaults-extra-file=********', $sanitized);
        $sanitized = preg_replace('/-p[^\s]+/', '-p********', $sanitized);
        $sanitized = preg_replace('/--password=[^\s]+/', '--password=********', $sanitized);

        return (string) $sanitized;
    }
}
