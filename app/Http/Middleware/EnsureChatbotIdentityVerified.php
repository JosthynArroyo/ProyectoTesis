<?php

namespace App\Http\Middleware;

use App\Support\ChatbotSessionKeys;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChatbotIdentityVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->session()->get(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID);
        $verified = $request->session()->get(ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED);

        if (!$userId || !$verified) {
            if ($request->expectsJson() || $request->is('chatbot/*')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Sesión de chatbot no identificada. Por favor, verifica tu identidad.',
                    'error' => 'identity_required',
                ], 401);
            }

            abort(401, 'Identificación OTP requerida.');
        }

        return $next($request);
    }
}
