<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cita;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
            'telefono'          => ['nullable','string','max:50'],
            'dni'               => ['required','digits:10', Rule::unique('users','dni')->ignore($user->id)],
            'direccion'         => ['nullable','string','max:255'],
            'fecha_nacimiento'  => ['required','date','before:today'],
            'sexo'              => ['nullable','in:Masculino,Femenino,Otro'],
            'avatar'            => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
        ], [
            'name.required'                 => 'El nombre es obligatorio.',
            'email.required'                => 'El correo es obligatorio.',
            'email.email'                   => 'Formato de correo inválido.',
            'email.unique'                  => 'Este correo ya está registrado.',
            'dni.required'                  => 'El número de cédula es obligatorio.',
            'dni.digits'                    => 'El número de cédula debe tener exactamente 10 dígitos.',
            'dni.unique'                    => 'Este número de cédula ya está registrado.',
            'fecha_nacimiento.required'     => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento.before'       => 'La fecha de nacimiento debe ser anterior a hoy.',
            'avatar.image'                  => 'El archivo debe ser una imagen.',
            'avatar.mimes'                  => 'Formatos permitidos: JPG, JPEG, PNG o WEBP.',
            'avatar.max'                    => 'La imagen no debe exceder 2 MB.',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        }

        $user->fill([
            'name'             => $request->name,
            'email'            => $request->email,
            'telefono'         => $request->telefono,
            'dni'              => $request->dni,
            'direccion'        => $request->direccion,
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'sexo'             => $request->sexo,
        ])->save();

        return redirect()->route('paciente.perfil.edit')->with('success', 'Perfil actualizado.');
    }
}
