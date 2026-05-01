<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\MaintenanceAccessService;
use App\Services\SiteSettingsService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    private const MAINTENANCE_LOGIN_MESSAGE = 'El sistema está en mantenimiento. Intenta nuevamente más tarde.';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    public function showLoginForm()
    {
        return redirect(url('/').'?login=1');
    }

    protected function validateLogin(Request $request)
    {
        $request->validate(
            [
                $this->username() => 'required|email',
                'password' => 'required|string',
                'remember' => 'required|boolean',
            ],
            [
                $this->username().'.required' => 'Ingrese su correo electrónico.',
                $this->username().'.email' => 'Ingrese un correo electrónico válido.',
                'password.required' => 'Ingrese su contraseña.',
            ],
            [
                $this->username() => 'correo electrónico',
                'password' => 'contraseña',
            ]
        );
    }

    protected function attemptLogin(Request $request): bool
    {
        return $this->guard()->attempt(
            $this->credentials($request),
            $request->boolean('remember')
        );
    }

    protected function authenticated(Request $request, $user)
    {
        $settings = app(SiteSettingsService::class);
        $maintenanceEnabled = $settings->getBool('maintenance.enabled', false);
        $maintenanceAccess = app(MaintenanceAccessService::class);

        if ($maintenanceEnabled && (! $user->isActive() || ! $maintenanceAccess->userOrIpCanBypass($request, $user))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect(url()->previous() ?: url('/'))
                ->withErrors(['email' => self::MAINTENANCE_LOGIN_MESSAGE])
                ->with('auth_error', self::MAINTENANCE_LOGIN_MESSAGE);
        }

        if (! $user->isActive()) {
            Auth::logout();

            $target = url('/').'?login=1';

            return redirect($target)
                ->withErrors(['email' => 'Tu cuenta está deshabilitada o suspendida.'])
                ->with('auth_error', 'Tu cuenta está deshabilitada o suspendida.');
        }

        return redirect($user->dashboardPath());
    }

    protected function redirectTo()
    {
        $user = Auth::user();
        if ($user && ! $user->isActive()) {
            return url('/').'?login=1';
        }

        return $user?->dashboardPath() ?? '/';
    }

    protected function loggedOut(Request $request)
    {
        return redirect('/');
    }

    protected function sendFailedLoginResponse(Request $request)
    {
        throw ValidationException::withMessages([
            $this->username() => ['Las credenciales no coinciden con nuestros registros.'],
        ]);
    }

    protected function sendLockoutResponse(Request $request)
    {
        $seconds = $this->limiter()->availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            $this->username() => ['Demasiados intentos. Inténtelo nuevamente en '.$seconds.' segundos.'],
        ])->status(429);
    }
}
