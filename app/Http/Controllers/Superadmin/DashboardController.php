<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\FeatureAccessRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Services\SiteSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Dompdf\Dompdf;
use Dompdf\Options;

class DashboardController extends Controller
{
    public function index(Request $request, SiteSettingsService $settings, DashboardAnalyticsService $analytics)
    {
        $pendientesPersonalizacion = FeatureAccessRequest::forFeature('personalizacion')->pending()->count();
        $maintenanceEnabled = $settings->getBool('maintenance.enabled', false);
        $dashboard = $analytics->buildSuperadminDashboard($request->user(), $request->all());

        return view('superadmin.dashboard', compact(
            'pendientesPersonalizacion',
            'maintenanceEnabled',
            'dashboard'
        ));
    }

    public function users(Request $request)
    {
        $role = strtolower(trim((string) $request->query('role', 'all')));
        $allowedRoles = ['all', 'paciente', 'doctor', 'laboratorio', 'administrador'];
        if (! in_array($role, $allowedRoles, true)) {
            $role = 'all';
        }

        $search = $this->normalizeSearchTerm($request->query('buscar', ''));

        $users = User::query()
            ->with('roles')
            ->when($role !== 'all', function ($query) use ($role) {
                $query->whereHas('roles', fn ($q) => $q->where('name', $role));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('dni', 'like', '%'.$search.'%');
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

    private function normalizeSearchTerm(mixed $value, int $maxLength = 100): string
    {
        return trim(mb_substr((string) $value, 0, $maxLength));
    }

    public function exportPdf(SiteSettingsService $settings)
    {
        $totalUsuarios = User::count();
        $usuariosPorRol = Role::query()
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('roles.name', ['paciente', 'doctor', 'administrador', 'laboratorio'])
            ->select('roles.name', DB::raw('COUNT(DISTINCT role_user.user_id) as total'))
            ->groupBy('roles.name')
            ->pluck('total', 'roles.name');
        $totalPacientes = (int) ($usuariosPorRol['paciente'] ?? 0);
        $totalDoctores = (int) ($usuariosPorRol['doctor'] ?? 0);
        $totalAdmins = (int) ($usuariosPorRol['administrador'] ?? 0);
        $totalLabs = (int) ($usuariosPorRol['laboratorio'] ?? 0);

        $totalCitas = Cita::count();
        $citasHoy = Cita::whereDate('created_at', now()->toDateString())->count();
        $pendientes = Cita::where('estado', 'pendiente')->count();

        $start = now()->subDays(6)->startOfDay();
        $end = now()->endOfDay();
        $citasPorDia = Cita::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');
        $usuariosPorDia = User::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $activity = collect(range(6, 0))->map(function (int $offset) use ($citasPorDia, $usuariosPorDia) {
            $day = now()->subDays($offset);
            $key = $day->toDateString();

            return [
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('d/m'),
                'citas' => (int) ($citasPorDia[$key] ?? 0),
                'usuarios' => (int) ($usuariosPorDia[$key] ?? 0),
            ];
        })->values();

        $kpis = [
            'Usuarios Registrados' => $totalUsuarios,
            'Pacientes' => $totalPacientes,
            'Doctores' => $totalDoctores,
            'Administradores' => $totalAdmins,
            'Laboratorios' => $totalLabs,
            'Citas Totales' => $totalCitas,
            'Citas Hoy' => $citasHoy,
            'Citas Pendientes' => $pendientes,
        ];

        $html = view('pdf.dashboard-report', [
            'title' => 'Reporte de Actividad del Sistema (Superadmin)',
            'role' => 'Superadmin',
            'kpis' => $kpis,
            'activity' => $activity,
            'pdfCss' => $this->loadPdfCss('admin/dashboard-pdf.css'),
        ])->render();

        $opt = new Options;
        $opt->set('isRemoteEnabled', true);
        $opt->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($opt);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $filename = 'reporte_superadmin_'.now()->format('Ymd_His').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function dashboardData(Request $request, DashboardAnalyticsService $analytics)
    {
        return response()->json($analytics->buildSuperadminDashboard($request->user(), $request->all()));
    }

    private function loadPdfCss(string $relativePath): string
    {
        $path = resource_path('css/'.$relativePath);

        return is_file($path) ? (file_get_contents($path) ?: '') : '';
    }
}
