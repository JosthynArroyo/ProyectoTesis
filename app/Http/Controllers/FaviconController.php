<?php

namespace App\Http\Controllers;

use App\Services\ClinicIdentityService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class FaviconController extends Controller
{
    private const NO_CACHE_HEADERS = [
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ];

    public function __invoke(ClinicIdentityService $identity): RedirectResponse|BinaryFileResponse
    {
        try {
            $path = $identity->faviconPath();
            if ($path !== null && $path !== '') {
                $url = $identity->faviconUrl();
                if (is_string($url) && $url !== '' && ! str_contains($url, 'placeholders/default.svg')) {
                    return redirect()->away($url, 302, self::NO_CACHE_HEADERS);
                }
            }
        } catch (Throwable) {
            // Fallback gracefully on any exception without throwing
        }

        return $this->fallbackResponse();
    }

    private function fallbackResponse(): BinaryFileResponse
    {
        $defaultIco = public_path('images/default-favicon.ico');
        if (is_file($defaultIco)) {
            return response()->file($defaultIco, array_merge(self::NO_CACHE_HEADERS, [
                'Content-Type' => 'image/x-icon',
            ]));
        }

        $defaultSvg = public_path('img/placeholders/default.svg');
        if (is_file($defaultSvg)) {
            return response()->file($defaultSvg, array_merge(self::NO_CACHE_HEADERS, [
                'Content-Type' => 'image/svg+xml',
            ]));
        }

        abort(404);
    }
}
