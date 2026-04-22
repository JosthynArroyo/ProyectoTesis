<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserStatusController extends Controller
{
    public function block(Request $request, User $user): RedirectResponse
    {
        if ($response = $this->guardPrivilegedUser($user, 'bloquear')) {
            return $response;
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update([
            'status' => User::STATUS_BLOCKED,
            'suspended_until' => null,
            'deactivation_reason' => $data['reason'] ?? null,
        ]);

        return back()->with('success', 'Usuario bloqueado.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        if ($response = $this->guardPrivilegedUser($user, 'suspender')) {
            return $response;
        }

        $data = $request->validate([
            'until' => ['required', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update([
            'status' => User::STATUS_ACTIVE,
            'suspended_until' => $data['until'],
            'deactivation_reason' => $data['reason'] ?? null,
        ]);

        return back()->with('success', 'Usuario suspendido temporalmente.');
    }

    public function activate(User $user): RedirectResponse
    {
        if ($response = $this->guardPrivilegedUser($user, 'reactivar')) {
            return $response;
        }

        $user->update([
            'status' => User::STATUS_ACTIVE,
            'suspended_until' => null,
            'deactivation_reason' => null,
        ]);

        return back()->with('success', $this->statusMessage($user, 'reactivado'));
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        if ($response = $this->guardPrivilegedUser($user, 'desactivar')) {
            return $response;
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update([
            'status' => User::STATUS_INACTIVE,
            'suspended_until' => null,
            'deactivation_reason' => $data['reason'] ?? null,
        ]);

        return back()->with('success', $this->statusMessage($user, 'desactivado'));
    }

    private function guardPrivilegedUser(User $user, string $action): ?RedirectResponse
    {
        if (! $user->hasRole('administrador') && ! $user->hasRole('superadmin')) {
            return null;
        }

        return back()->withErrors(["No puedes {$action} cuentas Administrador o Superadmin."]);
    }

    private function statusMessage(User $user, string $action): string
    {
        if ($user->hasRole('doctor')) {
            return "Doctor {$action}. Sus citas, historiales, recetas y especialidades se conservaron.";
        }

        if ($user->hasRole('laboratorio')) {
            return "Usuario de laboratorio {$action}. Sus ordenes y citas relacionadas se conservaron.";
        }

        return "Usuario {$action}.";
    }
}
