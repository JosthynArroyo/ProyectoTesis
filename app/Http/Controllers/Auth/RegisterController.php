<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    use RegistersUsers;

    public function __construct()
    {
        $this->middleware('guest');
    }

    protected function validator(array $data)
    {
        return Validator::make(
            $data,
            [
                'name'     => ['required', 'string', 'max:255'],
                'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[a-zA-Z])(?=.*\d).+$/', 'confirmed'],
            ],
            [
                'name.required' => 'Ingrese su nombre.',
                'email.required' => 'Ingrese su correo electrónico.',
                'email.email' => 'Ingrese un correo electrónico válido.',
                'email.unique' => 'Este correo ya está registrado.',
                'password.required' => 'Ingrese una contraseña.',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
                'password.regex' => 'La contraseña debe contener al menos una letra y un número.',
                'password.confirmed' => 'La confirmación de contraseña no coincide.',
            ],
            [
                'name' => 'nombre',
                'email' => 'correo electrónico',
                'password' => 'contraseña',
                'password_confirmation' => 'confirmación de contraseña',
            ]
        );
    }

    protected function create(array $data)
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $role = Role::where('name', 'paciente')->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    protected function registered(Request $request, $user)
    {
        if ($user->hasRole('administrador')) {
            return redirect('admin/dashboard');
        } elseif ($user->hasRole('paciente')) {
            return redirect('paciente/dashboard');
        } elseif ($user->hasRole('doctor')) {
            return redirect('doctor/dashboard');
        }
        return redirect('/');
    }

    protected function redirectTo()
    {
        $user = auth()->user();
        if (!$user) return '/';
        if ($user->hasRole('administrador')) {
            return 'admin/dashboard';
        } elseif ($user->hasRole('paciente')) {
            return 'paciente/dashboard';
        } elseif ($user->hasRole('doctor')) {
            return 'doctor/dashboard';
        }
        return '/';
    }
}
