<?php

namespace App\Services;

use App\Models\Dependiente;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Throwable;

class ProfileAvatarService
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/webp'];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    private const MAX_FILE_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB
    private const MIN_DIMENSION = 50;
    private const MAX_DIMENSION = 6000;

    public function disk(): string
    {
        return (string) config('image_optimization.avatar_disk', 'local');
    }

    public function replace(User $user, UploadedFile $file, ?string $folder = null): string
    {
        $this->validateUploadedFile($file);

        $previousAvatar = (string) $user->avatar;
        $uuid = Str::uuid()->toString();
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        $baseKey = "avatars/{$user->id}/{$uuid}";
        $originalKey = "{$baseKey}/original.{$ext}";
        $thumbKey = "{$baseKey}/thumb.webp";
        $mediumKey = "{$baseKey}/medium.webp";

        $uploadedKeys = [];

        try {
            $realPath = (string) $file->getRealPath();
            $manager = new ImageManager(new GdDriver());
            $img = $manager->read($realPath)->orient();

            // 1. Original (oriented and normalized)
            $originalBytes = match ($ext) {
                'png' => (string) $img->toPng(),
                'webp' => (string) $img->toWebp(85),
                default => (string) $img->toJpeg(85),
            };

            // 2. Thumb (128x128 center crop)
            $thumbImg = (clone $img)->cover(128, 128);
            $thumbBytes = (string) $thumbImg->toWebp(83);

            // 3. Medium (512x512 center crop)
            $mediumImg = (clone $img)->cover(512, 512);
            $mediumBytes = (string) $mediumImg->toWebp(83);

            $disk = Storage::disk($this->disk());

            // Upload all 3 variants to r2_private
            $disk->put($originalKey, $originalBytes);
            $uploadedKeys[] = $originalKey;

            $disk->put($thumbKey, $thumbBytes);
            $uploadedKeys[] = $thumbKey;

            $disk->put($mediumKey, $mediumBytes);
            $uploadedKeys[] = $mediumKey;

            // Update user in DB atomically
            $user->avatar = $originalKey;
            $user->save();

            // After successful DB update: delete previous R2 variants if previous avatar was in R2 format
            if (
                $previousAvatar !== ''
                && $previousAvatar !== $originalKey
                && preg_match('#^avatars/\d+/[a-f0-9\-]{36}/original\.[a-z0-9]+$#i', $previousAvatar)
            ) {
                $this->deletePreviousR2Avatar($previousAvatar);
            }

            return $originalKey;
        } catch (Throwable $e) {
            // Rollback: Clean up newly uploaded objects on failure
            if (! empty($uploadedKeys)) {
                try {
                    Storage::disk($this->disk())->delete($uploadedKeys);
                } catch (Throwable $cleanupError) {
                    Log::warning('Failed to clean up partial avatar upload', [
                        'user_id' => $user->id,
                        'keys' => $uploadedKeys,
                        'error' => $cleanupError->getMessage(),
                    ]);
                }
            }

            throw $e;
        }
    }

    public function replaceForDependent(Dependiente $dependiente, UploadedFile $file): string
    {
        $this->validateUploadedFile($file);

        $previousAvatar = (string) $dependiente->getRawOriginal('avatar');
        $uuid = Str::uuid()->toString();
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        $baseKey = "avatars/dependents/{$dependiente->id}/{$uuid}";
        $originalKey = "{$baseKey}/original.{$ext}";
        $thumbKey = "{$baseKey}/thumb.webp";
        $mediumKey = "{$baseKey}/medium.webp";

        $uploadedKeys = [];

        try {
            $realPath = (string) $file->getRealPath();
            $manager = new ImageManager(new GdDriver());
            $img = $manager->read($realPath)->orient();

            $originalBytes = match ($ext) {
                'png' => (string) $img->toPng(),
                'webp' => (string) $img->toWebp(85),
                default => (string) $img->toJpeg(85),
            };

            $thumbImg = (clone $img)->cover(128, 128);
            $thumbBytes = (string) $thumbImg->toWebp(83);

            $mediumImg = (clone $img)->cover(512, 512);
            $mediumBytes = (string) $mediumImg->toWebp(83);

            $disk = Storage::disk($this->disk());

            $disk->put($originalKey, $originalBytes);
            $uploadedKeys[] = $originalKey;

            $disk->put($thumbKey, $thumbBytes);
            $uploadedKeys[] = $thumbKey;

            $disk->put($mediumKey, $mediumBytes);
            $uploadedKeys[] = $mediumKey;

            $dependiente->avatar = $originalKey;
            $dependiente->save();

            if (
                $previousAvatar !== ''
                && $previousAvatar !== $originalKey
                && preg_match('#^avatars/dependents/\d+/[a-f0-9\-]{36}/original\.[a-z0-9]+$#i', $previousAvatar)
            ) {
                $this->deleteDependentR2Avatar($previousAvatar);
            }

            return $originalKey;
        } catch (Throwable $e) {
            if (! empty($uploadedKeys)) {
                try {
                    Storage::disk($this->disk())->delete($uploadedKeys);
                } catch (Throwable $cleanupError) {
                    Log::warning('Failed to clean up partial dependent avatar upload', [
                        'dependent_id' => $dependiente->id,
                        'keys' => $uploadedKeys,
                        'error' => $cleanupError->getMessage(),
                    ]);
                }
            }

            throw $e;
        }
    }

    public function deleteDependentR2Avatar(string $previousPath): void
    {
        if (preg_match('#^avatars/dependents/([^/]+)/([^/]+)/original\.([a-z0-9]+)$#i', $previousPath, $matches)) {
            $depId = $matches[1];
            $uuid = $matches[2];
            $ext = strtolower($matches[3]);

            $keysToDelete = [
                "avatars/dependents/{$depId}/{$uuid}/original.{$ext}",
                "avatars/dependents/{$depId}/{$uuid}/thumb.webp",
                "avatars/dependents/{$depId}/{$uuid}/medium.webp",
            ];

            try {
                Storage::disk($this->disk())->delete($keysToDelete);
            } catch (Throwable $e) {
                Log::warning('Failed to delete previous dependent R2 avatar variants', [
                    'previous_path' => $previousPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function deletePreviousR2Avatar(string $previousPath): void
    {
        if (preg_match('#^avatars/([^/]+)/([^/]+)/original\.([a-z0-9]+)$#i', $previousPath, $matches)) {
            $userId = $matches[1];
            $uuid = $matches[2];
            $ext = strtolower($matches[3]);

            $keysToDelete = [
                "avatars/{$userId}/{$uuid}/original.{$ext}",
                "avatars/{$userId}/{$uuid}/thumb.webp",
                "avatars/{$userId}/{$uuid}/medium.webp",
            ];

            try {
                Storage::disk($this->disk())->delete($keysToDelete);
            } catch (Throwable $e) {
                Log::warning('Failed to delete previous R2 avatar variants', [
                    'previous_path' => $previousPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function validateUploadedFile(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('El archivo de avatar no es válido o falló la carga.');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw new InvalidArgumentException('El avatar excede el tamaño máximo permitido de 5 MB.');
        }

        $mime = strtolower((string) $file->getMimeType());
        $ext = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($mime, self::ALLOWED_MIMES, true) || ! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('Formato de imagen no permitido. Solo se aceptan JPEG, PNG y WebP.');
        }

        $realPath = (string) $file->getRealPath();
        $imageInfo = @getimagesize($realPath);
        if (! $imageInfo || empty($imageInfo[0]) || empty($imageInfo[1])) {
            throw new InvalidArgumentException('El archivo no puede decodificarse realmente como una imagen.');
        }

        [$width, $height] = $imageInfo;
        if ($width < self::MIN_DIMENSION || $height < self::MIN_DIMENSION) {
            throw new InvalidArgumentException('La imagen debe tener dimensiones mínimas de 50x50 píxeles.');
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new InvalidArgumentException('La imagen excede las dimensiones máximas permitidas de 6000x6000 píxeles.');
        }
    }

    public function avatarUrl(?User $user, string $variant = 'thumb'): string
    {
        if (! $user || ! $user->avatar) {
            return $this->fallbackUrl($user);
        }

        $avatar = trim($user->avatar);
        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
            return $avatar;
        }

        $variant = in_array($variant, ['thumb', 'medium', 'original'], true) ? $variant : 'thumb';
        $version = substr(md5($avatar), 0, 8);

        return route('media.avatars.show', [
            'user' => $user->id,
            'variant' => $variant,
            'v' => $version,
        ]);
    }

    public function dependentAvatarUrl(?Dependiente $dependiente, string $variant = 'thumb'): string
    {
        if (! $dependiente || ! $dependiente->avatar) {
            return '';
        }

        $avatar = trim($dependiente->avatar);
        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
            return $avatar;
        }

        $variant = in_array($variant, ['thumb', 'medium', 'original'], true) ? $variant : 'thumb';
        $version = substr(md5($avatar), 0, 8);

        return route('media.dependent-avatars.show', [
            'dependiente' => $dependiente->id,
            'variant' => $variant,
            'v' => $version,
        ]);
    }

    public function fallbackUrl(?User $user): string
    {
        $role = strtolower((string) ($user?->role ?? 'user'));
        $path = match ($role) {
            'doctor' => 'img/placeholders/doctor.svg',
            'paciente' => 'img/placeholders/patient.svg',
            default => 'img/placeholders/user.svg',
        };

        return asset($path);
    }
}
