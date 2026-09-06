<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ApplicationModeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DemoAccessController extends Controller
{
    private const DEMO_ROLES = [
        'superadmin' => [
            'email' => 'superadmin@demo-clinigest.test',
            'role_name' => 'superadmin',
            'title' => 'Superadministrador',
            'description' => 'Configuración institucional, administradores y personalización del sistema.',
            'icon' => 'ri-shield-star-line',
            'badge' => 'Control Total',
        ],
        'administrador' => [
            'email' => 'admin@demo-clinigest.test',
            'role_name' => 'administrador',
            'title' => 'Administrador',
            'description' => 'Gestión general de citas, usuarios, horarios de atención y cobros de caja.',
            'icon' => 'ri-hospital-line',
            'badge' => 'Operativo & Caja',
        ],
        'admin' => [
            'email' => 'admin@demo-clinigest.test',
            'role_name' => 'administrador',
            'title' => 'Administrador',
            'description' => 'Gestión general de citas, usuarios, horarios de atención y cobros de caja.',
            'icon' => 'ri-hospital-line',
            'badge' => 'Operativo & Caja',
        ],
        'doctor' => [
            'email' => 'doctor.medicina@demo-clinigest.test',
            'role_name' => 'doctor',
            'title' => 'Médico Especialista',
            'description' => 'Agenda médica, expedientes clínicos, consultas SOAP, recetas y certificados.',
            'icon' => 'ri-stethoscope-line',
            'badge' => 'Atención Clínica',
        ],
        'paciente' => [
            'email' => 'paciente@demo-clinigest.test',
            'role_name' => 'paciente',
            'title' => 'Paciente',
            'description' => 'Portal de citas, descarga de recetas, certificados y órdenes de laboratorio.',
            'icon' => 'ri-user-heart-line',
            'badge' => 'Portal Paciente',
        ],
        'laboratorio' => [
            'email' => 'laboratorio@demo-clinigest.test',
            'role_name' => 'laboratorio',
            'title' => 'Laboratorio Clínico',
            'description' => 'Recepción de muestras, procesamiento técnico y publicación de resultados.',
            'icon' => 'ri-test-tube-line',
            'badge' => 'Muestras & Resultados',
        ],
    ];

    public function __construct(
        private readonly ApplicationModeService $applicationMode
    ) {}

    /**
     * Display the demo profile selector.
     */
    public function selector(Request $request): View|Response
    {
        if (! $this->applicationMode->isDemo()) {
            abort(404);
        }

        $roles = [
            'superadmin' => self::DEMO_ROLES['superadmin'],
            'administrador' => self::DEMO_ROLES['administrador'],
            'doctor' => self::DEMO_ROLES['doctor'],
            'paciente' => self::DEMO_ROLES['paciente'],
            'laboratorio' => self::DEMO_ROLES['laboratorio'],
        ];

        return view('demo.selector', compact('roles'));
    }

    /**
     * Authenticate as the canonical demo account for the given role and redirect to real dashboard.
     */
    public function loginAsRole(Request $request, string $role): RedirectResponse
    {
        if (! $this->applicationMode->isDemo()) {
            abort(404);
        }

        $roleKey = strtolower(trim($role));

        if (! array_key_exists($roleKey, self::DEMO_ROLES)) {
            abort(404);
        }

        $roleConfig = self::DEMO_ROLES[$roleKey];

        // 1. Resolve canonical demo user from DemoSeeder
        $user = User::where('email', $roleConfig['email'])->first();

        // 2. Fallback: find any active user with that role
        if (! $user) {
            $user = User::whereHas('roles', fn ($q) => $q->where('roles.name', $roleConfig['role_name']))
                ->where('status', User::STATUS_ACTIVE)
                ->first();
        }

        if (! $user) {
            abort(404, 'No se encontró la cuenta de demostración requerida. Asegúrese de ejecutar DemoSeeder.');
        }

        // 3. Real Laravel Authentication & session regeneration
        if (Auth::check()) {
            Auth::logout();
        }
        Auth::login($user);
        $request->session()->regenerate();

        // 4. Redirect to REAL dashboard path
        return redirect()->to($user->dashboardPath());
    }
}
