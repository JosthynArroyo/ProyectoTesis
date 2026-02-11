<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\ValidationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminsController extends Controller
{
    public function index(Request $request)
    {
        $buscar = trim((string) $request->get('buscar', ''));
        $perPage = (int) ($request->get('per_page', 12));

        $admins = User::with('roles')
            ->orderBy('created_at', 'desc')
            ->search($buscar)
            ->whereHas('roles', fn ($q) => $q->where('name', 'administrador'))
            ->paginate($perPage)
            ->appends($request->query());

        return view('superadmin.admins.index', compact('admins', 'buscar', 'perPage'));
    }

    public function create()
    {
        return view('superadmin.admins.create');
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->telefono = $data['telefono'] ?? null;
        $user->dni = $data['dni'];
        $user->direccion = $data['direccion'] ?? null;
        $user->fecha_nacimiento = $data['fecha_nacimiento'] ?? null;
        $user->sexo = $data['sexo'] ?? null;
        $user->active = true;
        $user->status = 'active';
        $user->save();

        $adminRole = Role::where('name', 'administrador')->firstOrFail();
        $user->roles()->sync([$adminRole->id]);

        return redirect()->route('superadmin.admins.edit', $user)->with('success', 'Administrador creado correctamente.');
    }

    public function edit(User $admin)
    {
        if (! $admin->hasRole('administrador')) {
            return redirect()->route('superadmin.admins.index')
                ->withErrors(['Solo puedes editar cuentas con rol Administrador.']);
        }

        return view('superadmin.admins.edit', compact('admin'));
    }

    public function update(Request $request, User $admin)
    {
        if (! $admin->hasRole('administrador')) {
            return redirect()->route('superadmin.admins.index')
                ->withErrors(['Solo puedes editar cuentas con rol Administrador.']);
        }

        $data = $this->validatePayload($request, $admin->id, true);

        $admin->name = $data['name'];
        $admin->email = $data['email'];
        $admin->telefono = $data['telefono'] ?? null;
        $admin->dni = $data['dni'];
        $admin->direccion = $data['direccion'] ?? null;
        $admin->fecha_nacimiento = $data['fecha_nacimiento'] ?? null;
        $admin->sexo = $data['sexo'] ?? null;

        if (! empty($data['password'])) {
            $admin->password = Hash::make($data['password']);
        }

        $admin->save();

        return redirect()->route('superadmin.admins.edit', $admin)->with('success', 'Administrador actualizado correctamente.');
    }

    public function destroy(User $admin)
    {
        if ($admin->id === auth()->id()) {
            return back()->withErrors(['No puedes eliminar tu propio usuario.']);
        }

        if (! $admin->hasRole('administrador')) {
            return back()->withErrors(['Solo puedes eliminar cuentas con rol Administrador.']);
        }

        $admin->roles()->detach();
        $admin->delete();

        return back()->with('success', 'Administrador eliminado.');
    }

    public function block(User $admin)
    {
        if (! $this->canManage($admin)) {
            return back()->withErrors(['No puedes bloquear esta cuenta.']);
        }

        $admin->update([
            'status' => 'blocked',
            'suspended_until' => null,
            'deactivation_reason' => request('reason'),
        ]);

        return back()->with('success', 'Administrador bloqueado.');
    }

    public function suspend(Request $request, User $admin)
    {
        if (! $this->canManage($admin)) {
            return back()->withErrors(['No puedes suspender esta cuenta.']);
        }

        $request->validate([
            'admin_id' => ['required', 'integer', Rule::in([$admin->id])],
            'until' => ['required', 'date', 'after:now'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $admin->update([
            'status' => 'active',
            'suspended_until' => $request->input('until'),
            'deactivation_reason' => $request->input('reason'),
        ]);

        return back()->with('success', 'Administrador suspendido temporalmente.');
    }

    public function activate(User $admin)
    {
        if (! $this->canManage($admin)) {
            return back()->withErrors(['No puedes reactivar esta cuenta.']);
        }

        $admin->update([
            'status' => 'active',
            'suspended_until' => null,
            'deactivation_reason' => null,
        ]);

        return back()->with('success', 'Administrador reactivado.');
    }

    public function deactivate(User $admin)
    {
        if (! $this->canManage($admin)) {
            return back()->withErrors(['No puedes desactivar esta cuenta.']);
        }

        $admin->update([
            'status' => 'inactive',
            'suspended_until' => null,
            'deactivation_reason' => request('reason'),
        ]);

        return back()->with('success', 'Administrador marcado como inactivo.');
    }

    private function validatePayload(Request $request, int $ignoreId = null, bool $requirePassword = true): array
    {
        $passwordRule = $requirePassword
            ? ValidationRules::passwordRequired()
            : ValidationRules::passwordOptional();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ValidationRules::emailUnique('users', $ignoreId),
            'password' => $passwordRule,
            'telefono' => ValidationRules::telefono(),
            'dni' => ValidationRules::cedulaUnique('users', $ignoreId),
            'direccion' => ['required', 'string', 'max:255'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'sexo' => ['required', 'in:Masculino,Femenino,Otro'],
        ]);
    }

    private function canManage(User $admin): bool
    {
        if ($admin->id === auth()->id()) {
            return false;
        }

        return $admin->hasRole('administrador');
    }
}
