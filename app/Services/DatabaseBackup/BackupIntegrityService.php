<?php

namespace App\Services\DatabaseBackup;

use RuntimeException;

class BackupIntegrityService
{
    public function __construct(
        private readonly BackupEncryptionService $encryptionService
    ) {}

    /**
     * Compute SHA-256 hash of a local file.
     */
    public function computeSha256(string $filePath): string
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException("El archivo para calcular SHA-256 no existe.");
        }

        $hash = hash_file('sha256', $filePath);
        if ($hash === false) {
            throw new RuntimeException("Fallo al calcular el hash SHA-256.");
        }

        return $hash;
    }

    /**
     * Verify package integrity: checks existence, size, decryptability, and SQL header.
     */
    public function verifyLocalPackage(string $zipPath, ?string $expectedSha256 = null): bool
    {
        if (! file_exists($zipPath) || filesize($zipPath) === 0) {
            throw new RuntimeException("El archivo ZIP no existe o tiene tamaño 0.");
        }

        if ($expectedSha256 !== null) {
            $computed = $this->computeSha256($zipPath);
            if (! hash_equals(strtolower($expectedSha256), strtolower($computed))) {
                throw new RuntimeException("El hash SHA-256 del archivo no coincide con el valor esperado.");
            }
        }

        $tempExtractDir = BackupTempDirectoryManager::getTempDir('verify_' . uniqid('', true));

        try {
            $extractedSql = $this->encryptionService->decrypt($zipPath, $tempExtractDir);
            $this->validateSqlContent($extractedSql);
            return true;
        } finally {
            BackupTempDirectoryManager::deleteTempDir($tempExtractDir);
        }
    }

    /**
     * Inspect SQL file to confirm it contains standard MySQL structure.
     */
    public function validateSqlContent(string $sqlFilePath): bool
    {
        if (! file_exists($sqlFilePath) || filesize($sqlFilePath) === 0) {
            throw new RuntimeException("El archivo SQL extraído está vacío.");
        }

        $handle = fopen($sqlFilePath, 'rb');
        if (! $handle) {
            throw new RuntimeException("No se pudo leer el archivo SQL extraído.");
        }

        // Read first 8KB to check signature
        $header = fread($handle, 8192);
        fclose($handle);

        $hasMySqlHeader = str_contains($header, 'MySQL dump') ||
                           str_contains($header, 'CREATE TABLE') ||
                           str_contains($header, 'INSERT INTO') ||
                           str_contains($header, 'DROP TABLE') ||
                           str_contains($header, '/*!40101') ||
                           str_contains($header, 'Database:');

        if (! $hasMySqlHeader) {
            throw new RuntimeException("El contenido del archivo no corresponde a un respaldo SQL de MySQL válido.");
        }

        return true;
    }
}
