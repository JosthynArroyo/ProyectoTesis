<?php

namespace App\Services\DatabaseBackup;

use RuntimeException;
use ZipArchive;

class BackupEncryptionService
{
    /**
     * Check if encryption mechanism and required password are available.
     */
    public function isAvailable(): bool
    {
        $password = config('database_backups.archive_password');
        if (empty($password)) {
            return false;
        }

        if (! extension_loaded('zip') || ! class_exists(ZipArchive::class)) {
            return false;
        }

        if (! defined('ZipArchive::EM_AES_256')) {
            return false;
        }

        return true;
    }

    /**
     * Compress and encrypt the SQL file using AES-256 into a zip package.
     */
    public function encrypt(string $sqlFilePath, string $zipOutputPath): string
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException("El mecanismo de cifrado AES-256 no está disponible o falta la clave BACKUP_ARCHIVE_PASSWORD en la configuración.");
        }

        $password = (string) config('database_backups.archive_password');
        $zip = new ZipArchive();

        if ($zip->open($zipOutputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("No se pudo crear el archivo ZIP de respaldo.");
        }

        $zip->setPassword($password);
        $entryName = basename($sqlFilePath);

        if (! $zip->addFile($sqlFilePath, $entryName)) {
            $zip->close();
            throw new RuntimeException("No se pudo agregar el archivo dump al paquete ZIP.");
        }

        $setEncResult = $zip->setEncryptionName($entryName, ZipArchive::EM_AES_256);
        if ($setEncResult !== true) {
            $zip->close();
            throw new RuntimeException("Fallo al aplicar el cifrado AES-256 al archivo dump.");
        }

        if (! $zip->close()) {
            throw new RuntimeException("Fallo al cerrar y guardar el archivo ZIP cifrado.");
        }

        if (! file_exists($zipOutputPath) || filesize($zipOutputPath) === 0) {
            throw new RuntimeException("El paquete cifrado ZIP no fue generado correctamente.");
        }

        // Re-open ZIP with password to verify extractability before declaring success
        $this->verifyEncryptedPackageCanBeOpened($zipOutputPath, $entryName, $password);

        return $zipOutputPath;
    }

    /**
     * Verify that the encrypted ZIP can be opened and stream read with password.
     */
    public function verifyEncryptedPackageCanBeOpened(string $zipPath, string $expectedEntryName, string $password): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("No se pudo abrir el archivo ZIP recién creado para verificación de cifrado.");
        }

        $zip->setPassword($password);
        $stream = $zip->getStream($expectedEntryName);
        if (! $stream) {
            $zip->close();
            throw new RuntimeException("Verificación de cifrado fallida: No se pudo abrir el stream del contenido con la contraseña.");
        }

        fclose($stream);
        $zip->close();
        return true;
    }

    /**
     * Decrypt and extract the ZIP archive using the configured password.
     */
    public function decrypt(string $zipPath, string $extractDir, ?string $expectedEntryName = null): string
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException("Mecanismo de cifrado no disponible o contraseña BACKUP_ARCHIVE_PASSWORD no configurada.");
        }

        $password = (string) config('database_backups.archive_password');
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("No se pudo abrir el paquete ZIP cifrado.");
        }

        $zip->setPassword($password);

        if (! is_dir($extractDir)) {
            mkdir($extractDir, 0755, true);
        }

        $targetName = $expectedEntryName ?: $zip->getNameIndex(0);
        if (! $targetName) {
            $zip->close();
            throw new RuntimeException("El archivo ZIP cifrado está vacío.");
        }

        $extractedPath = rtrim($extractDir, '/\\') . DIRECTORY_SEPARATOR . basename($targetName);
        $stream = $zip->getStream($targetName);

        if (! $stream) {
            $zip->close();
            throw new RuntimeException("No se pudo descifrar el archivo ZIP. Verifique la contraseña configurada.");
        }

        $outStream = fopen($extractedPath, 'wb');
        if (! $outStream) {
            fclose($stream);
            $zip->close();
            throw new RuntimeException("No se pudo escribir el archivo descifrado temporal.");
        }

        while (! feof($stream)) {
            fwrite($outStream, fread($stream, 8192));
        }

        fclose($stream);
        fclose($outStream);
        $zip->close();

        if (! file_exists($extractedPath) || filesize($extractedPath) === 0) {
            throw new RuntimeException("El archivo SQL descifrado está vacío.");
        }

        return $extractedPath;
    }
}
