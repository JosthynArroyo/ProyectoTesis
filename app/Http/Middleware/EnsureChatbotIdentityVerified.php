<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ChatbotSessionKeys;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChatbotIdentityVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->session()->get(ChatbotSessionKeys::SESSION_CHATBOT_USER_ID);
        $verified = (bool) $request->session()->get(ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED, false);

        if (! $userId || ! $verified) {
            $this->clearChatbotIdentity($request);

            if ($request->expectsJson() || $request->is('chatbot/*')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Sesión de chatbot no identificada. Por favor, verifica tu identidad.',
                    'error' => 'identity_required',
                ], 401);
            }

            abort(401, 'Identificación OTP requerida.');
        }

        $user = User::query()->find($userId);

        if (! $user || ! $user->hasRole('paciente') || ! $user->isActive()) {
            $this->clearChatbotIdentity($request);

            if ($request->expectsJson() || $request->is('chatbot/*')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Tu cuenta de paciente se encuentra inactiva o bloqueada.',
                    'error' => 'user_inactive',
                ], 403);
            }

            abort(403, 'Usuario inactivo o sin permisos.');
        }

        return $next($request);
    }

    private function clearChatbotIdentity(Request $request): void
    {
        $request->session()->forget([
            ChatbotSessionKeys::SESSION_CHATBOT_USER_ID,
            ChatbotSessionKeys::SESSION_CHATBOT_OTP_VERIFIED,
        ]);
    }
}
