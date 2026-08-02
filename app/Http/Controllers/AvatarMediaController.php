<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AvatarMediaController extends Controller
{
    public function __invoke(Request $request, User $user, string $variant = 'thumb'): Response
    {
        $currentUser = $request->user();
        if (! $currentUser) {
            abort(401, 'No autenticado.');
        }

        $variant = strtolower(trim($variant));
        if (! in_array($variant, ['thumb', 'medium', 'original'], true) || str_contains($variant, '..') || str_contains($variant, '/')) {
            abort(404, 'Variante no válida.');
        }

        if (! $this->authorizeAvatarAccess($currentUser, $user)) {
            abort(403, 'Acceso denegado al avatar.');
        }

        $avatarPath = (string) $user->getRawOriginal('avatar');
        if ($avatarPath === '' || str_contains($avatarPath, '..')) {
            return $this->fallbackResponse($user);
        }

        $targetPath = $this->resolveVariantPath($avatarPath, $variant);

        try {
            $disk = Storage::disk(config('image_optimization.avatar_disk', 'r2_private'));

            if ($disk->exists($targetPath)) {
                $contents = $disk->get($targetPath);
                $mime = $this->guessMimeTypeFromExtension($targetPath);

                return response($contents, 200, [
                    'Content-Type' => $mime,
                    'Content-Disposition' => 'inline',
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control' => 'private, max-age=86400, must-revalidate',
                    'Content-Length' => strlen($contents),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Error reading avatar from r2_private disk', [
                'userId' => $user->id,
                'targetPath' => $targetPath,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback for local legacy unmigrated avatars
        try {
            $publicDisk = Storage::disk('public');
            if ($publicDisk->exists($avatarPath)) {
                $contents = $publicDisk->get($avatarPath);
                $mime = $this->guessMimeTypeFromExtension($avatarPath);

                return response($contents, 200, [
                    'Content-Type' => $mime,
                    'Content-Disposition' => 'inline',
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control' => 'private, max-age=86400, must-revalidate',
                    'Content-Length' => strlen($contents),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Error reading legacy avatar from public disk', [
                'userId' => $user->id,
                'avatarPath' => $avatarPath,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->fallbackResponse($user);
    }

    private function authorizeAvatarAccess(User $currentUser, User $targetUser): bool
    {
        // 1. Owner
        if ($currentUser->id === $targetUser->id) {
            return true;
        }

        // 2. Superadmin & Administrador
        if ($currentUser->hasRole('superadmin') || $currentUser->hasRole('administrador')) {
            return true;
        }

        // 3. Doctor role: allowed to view doctors or patients
        if ($currentUser->hasRole('doctor')) {
            return $targetUser->hasRole('doctor') || $targetUser->hasRole('paciente');
        }

        // 4. Paciente role: allowed to view active doctors/laboratorios offered for appointments or own family dependents
        if ($currentUser->hasRole('paciente')) {
            if ($targetUser->isActive() && ($targetUser->hasRole('doctor') || $targetUser->hasRole('laboratorio'))) {
                return true;
            }

            if ($targetUser->hasRole('paciente')) {
                $myTitularId = $currentUser->titular_id ?? $currentUser->id;
                $targetTitularId = $targetUser->titular_id ?? $targetUser->id;

                return (int) $myTitularId === (int) $targetTitularId;
            }

            return false;
        }

        // 5. Laboratorio role: allowed to view doctors or patients
        if ($currentUser->hasRole('laboratorio')) {
            return $targetUser->hasRole('doctor') || $targetUser->hasRole('paciente');
        }

        return false;
    }

    private function resolveVariantPath(string $avatarPath, string $variant): string
    {
        if (preg_match('#^avatars/([^/]+)/([^/]+)/original\.([a-z0-9]+)$#i', $avatarPath, $matches)) {
            $userId = $matches[1];
            $uuid = $matches[2];
            $ext = strtolower($matches[3]);

            return match ($variant) {
                'thumb' => "avatars/{$userId}/{$uuid}/thumb.webp",
                'medium' => "avatars/{$userId}/{$uuid}/medium.webp",
                default => "avatars/{$userId}/{$uuid}/original.{$ext}",
            };
        }

        return $avatarPath;
    }

    private function fallbackResponse(User $user): Response
    {
        $role = 'user';
        if ($user->hasRole('doctor')) {
            $role = 'doctor';
        } elseif ($user->hasRole('paciente')) {
            $role = 'paciente';
        }

        $fileName = match ($role) {
            'doctor' => 'doctor.svg',
            'paciente' => 'patient.svg',
            default => 'user.svg',
        };

        $path = public_path("img/placeholders/{$fileName}");
        if (! is_file($path)) {
            $path = public_path('img/placeholders/default.svg');
        }

        $contents = is_file($path) ? file_get_contents($path) : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="50" fill="#e5e7eb"/></svg>';

        return response($contents, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400, must-revalidate',
            'Content-Length' => strlen((string) $contents),
        ]);
    }

    private function guessMimeTypeFromExtension(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'webp' => 'image/webp',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
