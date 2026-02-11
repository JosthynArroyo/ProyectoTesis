<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\FeatureAccessRequest;
use App\Models\User;
use App\Services\SiteSettingsService;

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
            'maintenanceEnabled'
        ));
    }
}
