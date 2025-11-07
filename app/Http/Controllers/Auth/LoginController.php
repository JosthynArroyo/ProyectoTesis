<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    // Evitar ver la pantalla /login y usar el modal del home
    protected function showLoginForm()
    {
        return redirect('/?login=1');
    }

    protected function validateLogin(Request $request)
    {
        $request->validate(
            [
                $this->username() => 'required|email',
                'password' => 'required|string',
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

    protected function authenticated(Request $request, $user)
    {
        if (!$user->isActive()) {
            \Auth::logout();
            return redirect()->route('login')
                ->withErrors(['email' => 'Tu cuenta está deshabilitada o suspendida.'])
                ->with('auth_error', 'Tu cuenta está deshabilitada o suspendida.');
        }

        if ($user->hasRole('administrador')) return redirect()->intended('admin/dashboard');
        if ($user->hasRole('paciente'))      return redirect()->intended('paciente/dashboard');
        if ($user->hasRole('doctor'))        return redirect()->intended('doctor/dashboard');
        return redirect('/');
    }


    protected function redirectTo()
    {
        $user = Auth::user();

        if ($user && !$user->isActive()) {
            return route('login');
        }

        if ($user && $user->hasRole('administrador')) {
            return 'admin/dashboard';
        } elseif ($user && $user->hasRole('paciente')) {
            return 'paciente/dashboard';
        } elseif ($user && $user->hasRole('doctor')) {
            return 'doctor/dashboard';
        }

        return '/';
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
