<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class MustChangePasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the password change form.
     */
    public function show()
    {
        $user = Auth::user();
        if (!$user->must_change_password) {
            return redirect($user->dashboardPath());
        }

        return view('auth.must-change-password');
    }

    /**
     * Update the password.
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user->must_change_password) {
            return redirect($user->dashboardPath());
        }

        $email = $user->email;

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
                function ($attribute, $value, $fail) use ($email, $user) {
                    if ($value === $email) {
                        $fail('La nueva contraseña no puede ser igual al correo electrónico.');
                    }
                    if (Hash::check($value, $user->password)) {
                        $fail('La nueva contraseña no puede ser igual a la contraseña actual.');
                    }
                }
            ],
        ], [
            'current_password.required' => 'La contraseña actual es obligatoria.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.min' => 'La nueva contraseña debe tener al menos 12 caracteres.',
            'password.confirmed' => 'La confirmación de la nueva contraseña no coincide.',
            'password.regex' => 'La nueva contraseña debe contener al menos una letra mayúscula, una minúscula, un número y un símbolo especial.',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            $validator->errors()->add('current_password', 'La contraseña actual es incorrecta.');
            return redirect()->back()->withErrors($validator)->withInput();
        }

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Save password
        $user->password = $request->password; // casts hashed handles the hash
        $user->must_change_password = false;
        
        if (Schema::hasColumn('users', 'password_changed_at')) {
            $user->password_changed_at = now();
        }

        $user->save();

        // Audit log
        Log::info('AUDIT: User changed password on initial login', [
            'action' => 'user_initial_password_change',
            'timestamp' => now()->toIso8601String(),
            'channel' => 'web',
            'actor' => $user->id,
            'user_id' => $user->id,
        ]);

        // Regenerate session
        $request->session()->regenerate();

        return redirect($user->dashboardPath())->with('success', 'Contraseña actualizada correctamente.');
    }
}
