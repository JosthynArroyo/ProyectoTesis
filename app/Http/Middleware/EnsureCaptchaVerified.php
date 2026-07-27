<?php

namespace App\Http\Middleware;

use App\Support\ChatbotSessionKeys;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCaptchaVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $verified = $request->session()->get(ChatbotSessionKeys::SESSION_VERIFIED);
        $verifiedAt = $request->session()->get(ChatbotSessionKeys::SESSION_VERIFIED_AT);

        // Valid for 5 minutes (300 seconds)
        if (!$verified || !$verifiedAt || (now()->timestamp - $verifiedAt) > 300) {
            $request->session()->forget([
                ChatbotSessionKeys::SESSION_VERIFIED,
                ChatbotSessionKeys::SESSION_VERIFIED_AT,
                ChatbotSessionKeys::SESSION_CHALLENGE_ID,
                ChatbotSessionKeys::SESSION_CHALLENGE_TOKEN,
            ]);

            if ($request->expectsJson() || $request->is('chatbot/*')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Debes completar la verificación CAPTCHA para continuar.',
                    'error' => 'captcha_required',
                ], 403);
            }

            abort(403, 'Verificación CAPTCHA requerida o expirada.');
        }

        return $next($request);
    }
}
