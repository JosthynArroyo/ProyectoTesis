<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\User;
use App\Services\ClinicalRecordService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DependentAvatarMediaController extends Controller
{
    public function __invoke(Request $request, Dependiente $dependiente, string $variant = 'thumb'): Response
    {
        $currentUser = $request->user();
        if (! $currentUser) {
            abort(401, 'No autenticado.');
        }

        $variant = strtolower(trim($variant));
        if (! in_array($variant, ['thumb', 'medium', 'original'], true) || str_contains($variant, '..') || str_contains($variant, '/')) {
            abort(404, 'Variante no válida.');
        }

        if (! $this->authorizeAvatarAccess($currentUser, $dependiente)) {
            abort(403, 'Acceso denegado al avatar del dependiente.');
        }

        $avatarPath = (string) $dependiente->getRawOriginal('avatar');
        if ($avatarPath === '' || str_contains($avatarPath, '..')) {
            return $this->fallbackResponse();
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
            Log::warning('Error reading dependent avatar from r2_private disk', [
                'dependentId' => $dependiente->id,
                'targetPath' => $targetPath,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->fallbackResponse();
    }

    private function authorizeAvatarAccess(User $currentUser, Dependiente $dependiente): bool
    {
        // 1. Owner Titular
        if ((int) $currentUser->id === (int) $dependiente->user_id) {
            return true;
        }

        // 2. Superadmin & Administrador
        if ($currentUser->hasRole('superadmin') || $currentUser->hasRole('administrador')) {
            return true;
        }

        // 3. Doctor role: allowed if doctor has appointments or clinical record relationship
        if ($currentUser->hasRole('doctor')) {
            $hasAppointment = Cita::query()
                ->where('doctor_id', $currentUser->id)
                ->where('dependiente_id', $dependiente->id)
                ->where('activo', true)
                ->exists();

            if ($hasAppointment) {
                return true;
            }

            if ($dependiente->responsable && app(ClinicalRecordService::class)->canView($currentUser, $dependiente->responsable)) {
                return true;
            }
        }

        return false;
    }

    private function resolveVariantPath(string $avatarPath, string $variant): string
    {
        if (preg_match('#^avatars/dependents/([^/]+)/([^/]+)/original\.([a-z0-9]+)$#i', $avatarPath, $matches)) {
            $depId = $matches[1];
            $uuid = $matches[2];
            $ext = strtolower($matches[3]);

            return match ($variant) {
                'thumb' => "avatars/dependents/{$depId}/{$uuid}/thumb.webp",
                'medium' => "avatars/dependents/{$depId}/{$uuid}/medium.webp",
                default => "avatars/dependents/{$depId}/{$uuid}/original.{$ext}",
            };
        }

        return $avatarPath;
    }

    private function fallbackResponse(): Response
    {
        $path = public_path('img/placeholders/patient.svg');
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
