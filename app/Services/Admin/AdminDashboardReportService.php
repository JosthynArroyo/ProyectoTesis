<?php

namespace App\Services\Admin;

use App\Models\Cita;
use App\Models\Role;
use App\Models\User;
use App\Services\CitaNoShowService;
use App\Services\CitaRecordatorioService;
use App\Services\DashboardAnalyticsService;
use App\Services\ProfileAvatarService;
use App\Support\DateField;
use App\Support\ValidationRules;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminDashboardReportService
{
    public function __construct(
        protected CitaNoShowService $noShowService,
        protected CitaRecordatorioService $recordatorioService,
        protected AdminUserManagementService $userManagementService
    ) {}

    public function dashboard(Request $request, User $user, DashboardAnalyticsService $analytics): array
    {
        $this->noShowService->marcarVencidas();
        $recordatorioStats = $this->recordatorioService->pendingDueStats();
        $prioridad = strtoupper(trim((string) $request->get('prioridad', '')));
        if ($prioridad === 'ALL' || ! in_array($prioridad, Cita::PRIORIDAD_NIVELES, true)) {
            $prioridad = '';
        }

        $citas = Cita::query()
            ->with(['paciente:id,name', 'doctor:id,name'])
            ->when($prioridad !== '', fn ($query) => $query->where('prioridad_nivel', $prioridad))
            ->orderByRaw(Cita::prioridadOrderSql())
            ->orderBy('fecha', 'asc')
            ->orderBy('hora', 'asc')
            ->limit(50)
            ->get([
                'id',
                'paciente_id',
                'doctor_id',
                'estado',
                'fecha',
                'hora',
                'prioridad_nivel',
                'prioridad_red_flag',
            ]);

        $recordatoriosPendientes = $recordatorioStats['pendientes'];
        $recordatoriosSinTelefono = $recordatorioStats['sin_telefono'];
        $dashboard = $analytics->buildAdminDashboard($user, $request->all());
        $metrics = $dashboard['metrics'];

        return [
            'viewData' => compact(
                'user',
                'citas',
                'recordatoriosPendientes',
                'recordatoriosSinTelefono',
                'prioridad',
                'dashboard',
                'metrics'
            ),
            'withData' => [
                'totalCitas' => $metrics['appointments_period']['value'] ?? 0,
                'totalCitasPendientes' => $metrics['appointments_pending']['value'] ?? 0,
                'totalCitasPendientesAlta' => 0,
                'totalCitasRealizadas' => $metrics['appointments_completed']['value'] ?? 0,
                'totalCitasCanceladas' => $metrics['appointments_cancelled']['value'] ?? 0,
                'totalPacientes' => $metrics['patients_total']['value'] ?? 0,
                'totalDoctores' => $metrics['doctors_active']['value'] ?? 0,
                'usuariosActivosHoy' => User::whereDate('last_login_at', now()->toDateString())->count(),
            ],
        ];
    }

    public function resumenGlobal(Request $request): JsonResponse|array
    {
        if (! $request->ajax()) {
            return ['redirect' => route('admin.dashboard')];
        }

        return response()->json([
            'agendadas' => Cita::count(),
            'completadas' => Cita::where('estado', 'realizada')->count(),
            'canceladas' => Cita::where('estado', 'cancelada')->count(),
        ]);
    }

    public function dashboardData(Request $request, DashboardAnalyticsService $analytics): JsonResponse
    {
        $this->noShowService->marcarVencidas();
        $payload = $analytics->buildAdminDashboard($request->user(), $request->all());

        return response()->json($payload);
    }

    public function exportPdf(Request $request): Response
    {
        $this->noShowService->marcarVencidas();
        $recordatorioStats = $this->recordatorioService->pendingDueStats();

        $citasPorEstado = Cita::query()
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $totalCitas = (int) $citasPorEstado->sum();
        $totalCitasPendientes = (int) ($citasPorEstado[Cita::ESTADO_PENDIENTE] ?? 0);
        $totalCitasRealizadas = (int) ($citasPorEstado[Cita::ESTADO_REALIZADA] ?? 0);
        $totalCitasCanceladas = (int) ($citasPorEstado[Cita::ESTADO_CANCELADA] ?? 0);
        $totalCitasPendientesAlta = Cita::where('estado', Cita::ESTADO_PENDIENTE)
            ->where('activo', true)
            ->where('prioridad_nivel', Cita::PRIORIDAD_ALTA)
            ->count();

        $usuariosPorRol = Role::query()
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('roles.name', ['paciente', 'doctor'])
            ->select('roles.name', DB::raw('COUNT(DISTINCT role_user.user_id) as total'))
            ->groupBy('roles.name')
            ->pluck('total', 'roles.name');
        $totalPacientes = (int) ($usuariosPorRol['paciente'] ?? 0);
        $totalDoctores = (int) ($usuariosPorRol['doctor'] ?? 0);
        $usuariosActivosHoy = User::whereDate('last_login_at', now()->toDateString())->count();
        $recordatoriosPendientes = $recordatorioStats['pendientes'];

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
            'Total Citas' => $totalCitas,
            'Citas Pendientes' => $totalCitasPendientes,
            'Citas de Alta Prioridad' => $totalCitasPendientesAlta,
            'Citas Realizadas' => $totalCitasRealizadas,
            'Citas Canceladas' => $totalCitasCanceladas,
            'Pacientes Registrados' => $totalPacientes,
            'Doctores Registrados' => $totalDoctores,
            'Usuarios Activos Hoy' => $usuariosActivosHoy,
            'Recordatorios Pendientes' => $recordatoriosPendientes,
        ];

        $html = view('pdf.dashboard-report', [
            'title' => 'Reporte Operativo y Estadísticas (Administrador)',
            'role' => 'Administrador',
            'kpis' => $kpis,
            'activity' => $activity,
            'pdfCss' => $this->userManagementService->loadPdfCss('admin/dashboard-pdf.css'),
        ])->render();

        $opt = new Options;
        $opt->set('isRemoteEnabled', true);
        $opt->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($opt);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $filename = 'reporte_administrador_'.now()->format('Ymd_His').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function editarPerfil(User $user): array
    {
        return compact('user');
    }

    public function actualizarPerfil(Request $request, User $user, ProfileAvatarService $profileAvatars): array
    {
        DateField::mergeIntoRequest($request, 'fecha_nacimiento');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ValidationRules::emailUnique('users', $user->id),
            'telefono' => ValidationRules::telefono(),
            'tipo_documento' => ['nullable', 'in:cedula,pasaporte'],
            'nacionalidad' => ['required_if:tipo_documento,pasaporte', 'nullable', 'string', function ($attribute, $value, $fail) use ($request) {
                if ($request->input('tipo_documento') === 'pasaporte' && (! $value || ! \App\Support\CountryCatalog::isValidCode($value))) {
                    $fail('La nacionalidad es obligatoria cuando el documento es pasaporte.');
                }
            }],
            'dni' => ValidationRules::cedulaUnique('users', $user->id),
            'direccion' => ['required', 'string', 'max:255'],
            'fecha_nacimiento' => ValidationRules::birthDate(),
            'sexo' => ['required', 'in:Masculino,Femenino,Otro'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'current_password' => ['nullable', 'string'],
            'password' => array_merge(
                ValidationRules::passwordOptional(),
                ['different:current_password']
            ),
        ];

        $messages = [
            'password.regex' => 'La contraseña debe incluir letras, números y al menos un carácter especial.',
        ];

        $data = $request->validate($rules, $messages);

        if ($request->hasFile('avatar')) {
            $imageFolder = $this->userManagementService->resolveAvatarFolder($user);
            $data['avatar'] = $profileAvatars->replace($user, $request->file('avatar'), $imageFolder);
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->telefono = $data['telefono'] ?? null;
        $user->dni = $data['dni'];
        $user->direccion = $data['direccion'] ?? null;
        $user->fecha_nacimiento = $data['fecha_nacimiento'] ?? null;
        $user->sexo = $data['sexo'] ?? null;
        if (isset($data['avatar'])) {
            $user->avatar = $data['avatar'];
        }

        $passwordChanged = false;
        if ($request->filled('password')) {
            if (! $request->filled('current_password') || ! Hash::check($request->input('current_password'), $user->password)) {
                return ['ok' => false, 'errors' => ['current_password' => 'La contraseña actual no es correcta.']];
            }
            $user->password = Hash::make($request->input('password'));
            $passwordChanged = true;
        }

        $user->save();

        if ($passwordChanged) {
            $user->setRememberToken(Str::random(60));
            $user->save();
            Auth::logoutOtherDevices($request->input('password'));
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            Auth::login($user);
            $request->session()->regenerate();
        }

        return ['ok' => true, 'user' => $user];
    }
}
