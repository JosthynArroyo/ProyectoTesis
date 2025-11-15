<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cita;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


class AdminController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();

        $citas = Cita::where('paciente_id', $user->id)
            ->orderBy('fecha', 'asc')
            ->get();

        $totalCitas = $citas->count();
        $totalCitasPendientes = $citas->where('estado', 'pendiente')->count();
        $totalCitasRealizadas = $citas->where('estado', 'realizada')->count();
        $totalCitasCanceladas = $citas->where('estado', 'cancelada')->count();

        $citasAgendadas2h = $citas->where('created_at', '>=', now()->subHours(2))->count();
        $citasCompletadas2h = $citas->where('estado', 'realizada')->where('updated_at', '>=', now()->subHours(2))->count();
        $citasCanceladas2h = $citas->where('estado', 'cancelada')->where('updated_at', '>=', now()->subHours(2))->count();

        return view('paciente.dashboard', compact(
            'user',
            'citas',
            'totalCitas',
            'totalCitasPendientes',
            'totalCitasRealizadas',
            'totalCitasCanceladas',
            'citasAgendadas2h',
            'citasCompletadas2h',
            'citasCanceladas2h'
        ));
    }

    public function editarPerfil()
    {
        $user = Auth::user();
        return view('paciente.perfil', compact('user'));
    }

    public function actualizarPerfil(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name'              => ['required','string','max:255'],
            'email'             => ['required','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'telefono'          => ['nullable','regex:/^\d{10}$/'],
            'dni'               => ['required','digits:10', Rule::unique('users','dni')->ignore($user->id)],
            'direccion'         => ['nullable','string','max:255'],
            'fecha_nacimiento'  => ['required','date','before:today'],
            'sexo'              => ['nullable','in:Masculino,Femenino,Otro'],
            'avatar'            => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'current_password'  => ['nullable','string'],
            'password'          => [
                'nullable','string','min:8','confirmed','different:current_password',
                'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/'
            ],
        ], [
            'telefono.regex' => 'Teléfono: exactamente 10 dígitos.',
            'password.regex' => 'La contraseña debe incluir letras, números y al menos un carácter especial.',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->fill([
            'name'             => $request->name,
            'email'            => $request->email,
            'telefono'         => $request->telefono,
            'dni'              => $request->dni,
            'direccion'        => $request->direccion,
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'sexo'             => $request->sexo,
        ]);

        $passwordChanged = false;
        if ($request->filled('password')) {
            if (!$request->filled('current_password') || !Hash::check($request->input('current_password'), $user->password)) {
                return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.'])->withInput();
            }
            $user->password = Hash::make($request->input('password'));
            $passwordChanged = true;
        }

        $user->save();

        if ($passwordChanged) {
            $user->setRememberToken(Str::random(60));
            $user->save();
            \Auth::logoutOtherDevices($request->input('password'));
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            \Auth::login($user);
            $request->session()->regenerate();
        }

        return redirect()->route('paciente.perfil.edit')->with('success', 'Perfil actualizado.');
    }
}
