<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LaboratoryResultStorageService
{
    public const DISK = 'r2_private';

    public function disk(): string
    {
        return (string) config('private_documents.disk', self::DISK);
    }

    public function storeUploadedPdf(UploadedFile $file, string $scope, int $ownerId): string
    {
        $scope = Str::slug($scope);
        $key = "documents/laboratory-results/{$scope}/{$ownerId}/".Str::uuid().'.pdf';
        $stream = fopen($file->getRealPath(), 'rb');

        if (! is_resource($stream)) {
            throw new \RuntimeException('No se pudo abrir el resultado PDF para subirlo.');
        }

        $diskName = $this->disk();
        try {
            $uploaded = Storage::disk($diskName)->put($key, $stream, [
                'ContentType' => 'application/pdf',
                'visibility' => 'private',
            ]);
        } finally {
            fclose($stream);
        }

        $disk = Storage::disk($diskName);
        if (! $uploaded || ! $disk->exists($key) || $disk->size($key) <= 0) {
            if ($disk->exists($key)) {
                $disk->delete($key);
            }

            throw new \RuntimeException('No se pudo guardar el resultado PDF.');
        }

        return $key;
    }

    /** @return array{disk:string,path:string}|null */
    public function resolve(?string $path, ?string $preferredDisk = null): ?array
    {
        $path = ltrim(str_replace('\\', '/', trim((string) $path)), '/');
        if ($path === '') {
            return null;
        }

        $disks = array_values(array_unique(array_filter([
            in_array($preferredDisk, [self::DISK, 'local'], true) ? $preferredDisk : null,
            $this->disk(),
            self::DISK,
            'local',
        ])));

        foreach ($disks as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return ['disk' => $disk, 'path' => $path];
            }
        }

        return null;
    }

    public function download(?string $path, string $filename, ?string $preferredDisk = null)
    {
        $stored = $this->resolve($path, $preferredDisk);
        if (! $stored) {
            return null;
        }

        return Storage::disk($stored['disk'])->download($stored['path'], $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /** @param array{disk:string,path:string} $stored */
    public function contents(array $stored): string
    {
        return Storage::disk($stored['disk'])->get($stored['path']);
    }

    public function deleteNew(string $path): void
    {
        $disk = Storage::disk($this->disk());
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
