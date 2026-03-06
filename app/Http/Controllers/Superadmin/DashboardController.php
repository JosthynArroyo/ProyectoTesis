<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\FeatureAccessRequest;
use App\Models\User;
use App\Services\SiteSettingsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(SiteSettingsService $settings)
    {
        $totalUsuarios = User::count();
        $totalPacientes = User::whereHas('roles', fn ($q) => $q->where('name', 'paciente'))->count();
        $totalDoctores = User::whereHas('roles', fn ($q) => $q->where('name', 'doctor'))->count();
        $totalAdmins = User::whereHas('roles', fn ($q) => $q->where('name', 'administrador'))->count();
        $totalLabs = User::whereHas('roles', fn ($q) => $q->where('name', 'laboratorio'))->count();

        $totalCitas = Cita::count();
        $citasHoy = Cita::whereDate('created_at', now()->toDateString())->count();
        $pendientes = Cita::where('estado', 'pendiente')->count();

        $pendientesPersonalizacion = FeatureAccessRequest::forFeature('personalizacion')->pending()->count();
        $maintenanceEnabled = $settings->getBool('maintenance.enabled', false);

        $activity = collect(range(6, 0))->map(function (int $offset) {
            $day = now()->subDays($offset);
            return [
                'label' => $day->format('d/m'),
                'citas' => Cita::whereDate('created_at', $day->toDateString())->count(),
                'usuarios' => User::whereDate('created_at', $day->toDateString())->count(),
            ];
        })->values();

        return view('superadmin.dashboard', compact(
            'totalUsuarios',
            'totalPacientes',
            'totalDoctores',
            'totalAdmins',
            'totalLabs',
            'totalCitas',
            'citasHoy',
            'pendientes',
            'pendientesPersonalizacion',
            'maintenanceEnabled',
            'activity'
        ));
    }

    public function users(Request $request)
    {
        $role = strtolower(trim((string) $request->query('role', 'all')));
        $allowedRoles = ['all', 'paciente', 'doctor', 'laboratorio', 'administrador'];
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'all';
        }

        $search = trim((string) $request->query('buscar', ''));

        $users = User::query()
            ->with('roles')
            ->when($role !== 'all', function ($query) use ($role) {
                $query->whereHas('roles', fn ($q) => $q->where('name', $role));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('dni', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.users.index', [
            'users' => $users,
            'role' => $role,
            'search' => $search,
        ]);
    }
}
