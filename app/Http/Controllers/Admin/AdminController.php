<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Role;
use App\Models\User;
use App\Services\CitaNoShowService;
use App\Services\CitaRecordatorioService;
use App\Services\DashboardAnalyticsService;
use App\Services\ProfileAvatarService;
use App\Support\DateField;
use App\Support\ImageUrl;
use App\Support\ValidationRules;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AdminController extends Controller
{
    // ===== Dashboard =====
    public function dashboard(Request $request, DashboardAnalyticsService $analytics)
    {
        app(CitaNoShowService::class)->marcarVencidas();
        $recordatorios = app(CitaRecordatorioService::class);
        $recordatorioStats = $recordatorios->pendingDueStats();
        $user = Auth::user();
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

        return view('admin.dashboard', compact(
            'user',
            'citas',
            'recordatoriosPendientes',
            'recordatoriosSinTelefono',
            'prioridad',
            'dashboard',
            'metrics'
        ))->with([
            'totalCitas' => $metrics['appointments_period']['value'] ?? 0,
            'totalCitasPendientes' => $metrics['appointments_pending']['value'] ?? 0,
            'totalCitasPendientesAlta' => 0,
            'totalCitasRealizadas' => $metrics['appointments_completed']['value'] ?? 0,
            'totalCitasCanceladas' => $metrics['appointments_cancelled']['value'] ?? 0,
            'totalPacientes' => $metrics['patients_total']['value'] ?? 0,
            'totalDoctores' => $metrics['doctors_active']['value'] ?? 0,
            'usuariosActivosHoy' => User::whereDate('last_login_at', now()->toDateString())->count(),
        ]);
    }

    public function resumenGlobal(Request $request)
    {
        if (! $request->ajax()) {
            return redirect()->route('admin.dashboard');
        }

        app(CitaNoShowService::class)->marcarVencidas();

        return response()->json([
            'agendadas' => Cita::count(),
            'completadas' => Cita::where('estado', 'realizada')->count(),
            'canceladas' => Cita::where('estado', 'cancelada')->count(),
        ]);
    }

    public function dashboardData(Request $request, DashboardAnalyticsService $analytics)
    {
        app(CitaNoShowService::class)->marcarVencidas();
        $payload = $analytics->buildAdminDashboard($request->user(), $request->all());

        return response()->json($payload);
    }

    public function exportPdf(Request $request)
    {
        app(CitaNoShowService::class)->marcarVencidas();
        $recordatorios = app(CitaRecordatorioService::class);
        $recordatorioStats = $recordatorios->pendingDueStats();
        
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
            'pdfCss' => $this->loadPdfCss('admin/dashboard-pdf.css'),
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


    // ===== Perfil =====
    public function editarPerfil()
    {
        $user = Auth::user();

        return view('admin.perfil', compact('user'));
    }

    public function actualizarPerfil(Request $request, ProfileAvatarService $profileAvatars)
    {
        $user = Auth::user();
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
            $imageFolder = $this->resolveAvatarFolder($user);
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
                return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.'])->withInput();
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

        return redirect()->route('admin.perfil.edit')->with('success', 'Perfil actualizado correctamente.');
    }

    // ===== Usuarios (listado + filtros) =====
    public function usuarios(Request $request)
    {
        $buscar = $this->normalizeSearchTerm($request->get('buscar', ''));
        $perPage = $request->has('per_page')
            ? $this->normalizePerPage($request->get('per_page', 15))
            : 12;
        $role = $this->normalizeUserRoleFilter($request->string('role')->lower()->value());

        $allColumns = ['usuario', 'contacto', 'rol', 'estado', 'especialidades', 'acciones'];
        $cols = $request->has('cols')
            ? array_values(array_intersect($allColumns, (array) $request->get('cols')))
            : ($request->session()->get('usuarios.cols') ?: $allColumns);
        if (empty($cols)) {
            $cols = $allColumns;
        }
        $request->session()->put('usuarios.cols', $cols);

        $usersQ = User::with([
            'roles:id,name',
            'especialidades:id,nombre',
            'patientFlag',
            'dependientes' => function ($query) {
                $query->select([
                    'id',
                    'user_id',
                    'nombre',
                    'dni',
                    'fecha_nacimiento',
                    'sexo',
                    'parentesco',
                    'activo',
                ])->orderBy('nombre');
            },
        ])->withCount('dependientes')
            ->orderBy('created_at', 'desc')
            ->search($buscar)
            ->role($role)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['administrador', 'superadmin']));

        $users = $usersQ->paginate($perPage)->appends($request->query());
        $roles = Role::whereNotIn('name', ['administrador', 'superadmin'])->orderBy('name', 'asc')->get();

        return view('admin.usuarios', compact('users', 'roles', 'buscar', 'cols', 'allColumns', 'perPage'))
            ->with('role', $role);
    }

    public function checkEmail(Request $request)
    {
        $email = mb_strtolower(trim((string) $request->query('email', '')));
        if ($email === '') {
            return response()->json([
                'ok' => false,
                'available' => false,
                'message' => 'Ingresa un correo electrónico.',
            ], 422);
        }

        $validator = Validator::make(['email' => $email], [
            'email' => ValidationRules::emailUnique(),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => true,
                'available' => false,
                'message' => $validator->errors()->first('email'),
            ]);
        }

        return response()->json([
            'ok' => true,
            'available' => true,
            'message' => 'Correo disponible.',
        ]);
    }

    // ===== CRUD usuario rápido =====
    public function usuariosCreate()
    {
        $roles = Role::whereNotIn('name', ['administrador', 'superadmin'])->orderBy('name')->get();
        $especialidades = Especialidad::orderBy('nombre')->get();

        return view('admin.users.create', compact('roles', 'especialidades'));
    }

    public function usuariosStore(Request $request)
    {
        DateField::mergeIntoRequest($request, 'fecha_nacimiento');
        $roles = Role::pluck('name', 'id');
        $roleName = $roles[(int) $request->input('role_id')] ?? null;

        $baseRules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ValidationRules::emailUnique(),
            'password' => ValidationRules::passwordRequired(),
            'telefono' => ValidationRules::telefono(),
            'tipo_documento' => ['nullable', 'in:cedula,pasaporte'],
            'nacionalidad' => ['required_if:tipo_documento,pasaporte', 'nullable', 'string', function ($attribute, $value, $fail) use ($request) {
                if ($request->input('tipo_documento') === 'pasaporte' && (! $value || ! \App\Support\CountryCatalog::isValidCode($value))) {
                    $fail('La nacionalidad es obligatoria cuando el documento es pasaporte.');
                }
            }],
            'dni' => ValidationRules::cedulaUnique(),
            'direccion' => ['required', 'string', 'max:255'],
            'fecha_nacimiento' => ValidationRules::birthDate(),
            'sexo' => ['required', 'in:Masculino,Femenino,Otro'],
            'role_id' => ['required', 'exists:roles,id'],
            'especialidad_id' => ['nullable', Rule::requiredIf($roleName === 'doctor'), 'integer', 'exists:especialidades,id'],
            'precio_consulta' => ['nullable', Rule::requiredIf(in_array($roleName, ['doctor', 'laboratorio'], true)), 'numeric', 'min:0', 'max:99999999.99'],
            'adulto_mayor' => ['required', 'boolean'],
            'embarazo' => ['required', 'boolean'],
            'discapacidad' => ['required', 'boolean'],
            'cronico' => ['required', 'boolean'],
        ];

        $data = $request->validate($baseRules);

        $roleName = $roles[(int) $data['role_id']] ?? null;
        if ($this->isPrivilegedRole($roleName)) {
            return back()->withErrors(['role_id' => 'No puedes asignar roles Administrador o Superadmin.'])->withInput();
        }
        $isDoctor = $roleName === 'doctor';
        $isLaboratorio = $roleName === 'laboratorio';
        $labId = null;

        if ($isDoctor) {
            $request->validate([
                'especialidad_id' => ['required', 'integer', 'exists:especialidades,id'],
            ], [
                'especialidad_id.required' => 'La especialidad es obligatoria para este rol.',
            ]);
        } elseif ($isLaboratorio) {
            $labId = Especialidad::laboratorioClinicoId();
            if (! $labId) {
                return back()->withErrors(['especialidad_id' => 'No existe la especialidad Laboratorio Clínico.'])->withInput();
            }
        }

        $u = new User;
        $u->name = $data['name'];
        $u->email = $data['email'];
        $u->password = Hash::make($data['password']);
        $u->telefono = $data['telefono'] ?? null;
        $u->dni = $data['dni'];
        $u->direccion = $data['direccion'] ?? null;
        $u->fecha_nacimiento = $data['fecha_nacimiento'] ?? null;
        $u->sexo = $data['sexo'] ?? null;
        $u->precio_consulta = ($isDoctor || $isLaboratorio) ? ($data['precio_consulta'] ?? null) : null;
        $u->moneda = 'USD';
        $u->status = User::STATUS_ACTIVE;
        $u->save();

        $u->roles()->sync([(int) $data['role_id']]);

        if ($isDoctor || $isLaboratorio) {
            $espId = $isLaboratorio ? (int) $labId : (int) $request->input('especialidad_id');
            $u->especialidades()->sync([$espId]);
        }

        if ($roleName === 'paciente') {
            $u->patientFlag()->updateOrCreate(
                ['user_id' => $u->id],
                $this->extractPatientFlags($request)
            );
        }

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    public function usuariosShow(User $user)
    {
        if ($this->isPrivilegedAccount($user)) {
            return redirect()->route('admin.usuarios.index')
                ->withErrors(['No puedes administrar cuentas Administrador o Superadmin.']);
        }
        $user->load(['roles', 'especialidades']);

        return view('admin.users.show', compact('user'));
    }

    public function usuariosEdit(User $user)
    {
        if ($this->isPrivilegedAccount($user)) {
            return redirect()->route('admin.usuarios.index')
                ->withErrors(['No puedes administrar cuentas Administrador o Superadmin.']);
        }
        $user->load(['roles', 'especialidades']);
        $roles = Role::whereNotIn('name', ['administrador', 'superadmin'])->orderBy('name')->get();
        $especialidades = Especialidad::orderBy('nombre')->get();

        return view('admin.users.edit', compact('user', 'roles', 'especialidades'));
    }

    public function usuariosUpdate(Request $request, User $user)
    {
        if ($this->isPrivilegedAccount($user)) {
            return redirect()->route('admin.usuarios.index')
                ->withErrors(['No puedes administrar cuentas Administrador o Superadmin.']);
        }
        DateField::mergeIntoRequest($request, 'fecha_nacimiento');
        $request->merge(['email' => strtolower($request->input('email'))]);
        $roles = Role::pluck('name', 'id');
        $roleNameRequest = $roles[(int) $request->input('role_id')] ?? null;

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
            'role_id' => ['required', 'exists:roles,id'],
            'especialidad_id' => ['nullable', Rule::requiredIf($roleNameRequest === 'doctor'), 'integer', 'exists:especialidades,id'],
            'precio_consulta' => ['nullable', Rule::requiredIf(in_array($roleNameRequest, ['doctor', 'laboratorio'], true)), 'numeric', 'min:0', 'max:99999999.99'],
            'adulto_mayor' => ['required', 'boolean'],
            'embarazo' => ['required', 'boolean'],
            'discapacidad' => ['required', 'boolean'],
            'cronico' => ['required', 'boolean'],
        ];
        $data = $request->validate($rules);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->telefono = $data['telefono'] ?? null;
        $user->tipo_documento = $data['tipo_documento'] ?? 'cedula';
        $user->nacionalidad = ($data['tipo_documento'] ?? 'cedula') === 'pasaporte' ? ($data['nacionalidad'] ?? null) : null;
        $user->dni = $data['dni'] ?? null;
        $user->direccion = $data['direccion'] ?? null;
        $user->fecha_nacimiento = $data['fecha_nacimiento'] ?? null;
        $user->sexo = $data['sexo'] ?? null;

        $roleId = (int) $data['role_id'];
        $roleName = optional(Role::find($roleId))->name;
        if ($this->isPrivilegedRole($roleName)) {
            return back()->withErrors(['role_id' => 'No puedes asignar roles Administrador o Superadmin.'])->withInput();
        }
        $isDoctor = $roleName === 'doctor';
        $isLaboratorio = $roleName === 'laboratorio';
        $labId = null;

        if ($isDoctor && empty($data['especialidad_id'])) {
            return back()->withErrors(['especialidad_id' => 'La especialidad es obligatoria para este rol.'])->withInput();
        }
        if ($isLaboratorio) {
            $labId = Especialidad::laboratorioClinicoId();
            if (! $labId) {
                return back()->withErrors(['especialidad_id' => 'No existe la especialidad Laboratorio Clínico.'])->withInput();
            }
        }

        if ($isDoctor) {
            $user->precio_consulta = $data['precio_consulta'] ?? $user->precio_consulta;
            $user->moneda = $user->moneda ?: 'USD';
            if (! empty($data['especialidad_id'])) {
                $user->especialidades()->sync([(int) $data['especialidad_id']]);
            } else {
                $user->especialidades()->sync([]);
            }
        } elseif ($isLaboratorio) {
            $user->precio_consulta = $data['precio_consulta'] ?? $user->precio_consulta;
            $user->moneda = $user->moneda ?: 'USD';
            $user->especialidades()->sync([(int) $labId]);
        } else {
            $user->precio_consulta = null;
            $user->especialidades()->sync([]);
            $user->moneda = $user->moneda ?: 'USD';
        }

        $user->save();
        $user->roles()->sync([$roleId]);

        if ($roleName === 'paciente') {
            $user->patientFlag()->updateOrCreate(
                ['user_id' => $user->id],
                $this->extractPatientFlags($request)
            );
        }

        return redirect()
            ->route('admin.usuarios.edit', $user)
            ->with('success', 'Usuario actualizado correctamente.');
    }

    private function extractPatientFlags(Request $request): array
    {
        return [
            'adulto_mayor' => $request->boolean('adulto_mayor'),
            'embarazo' => $request->boolean('embarazo'),
            'discapacidad' => $request->boolean('discapacidad'),
            'cronico' => $request->boolean('cronico'),
        ];
    }

    private function resolveAvatarFolder(User $user): string
    {
        if ($user->hasRole('doctor') || $user->hasRole('laboratorio')) {
            return 'doctors';
        }

        if ($user->hasRole('paciente')) {
            return 'patients';
        }

        return 'users';
    }

    private function isPrivilegedRole(string $roleName): bool
    {
        return in_array($roleName, ['administrador', 'superadmin'], true);
    }

    private function isPrivilegedAccount(User $user): bool
    {
        return ! auth()->user()->can('manage', $user);
    }

    private function isClinicalProfessionalAccount(User $user): bool
    {
        return $user->hasRole('doctor') || $user->hasRole('laboratorio');
    }

    public function usuariosDestroy(User $user)
    {
        if (Auth::id() === $user->id) {
            return back()->withErrors(['No puedes eliminar tu propio usuario.']);
        }
        if ($this->isPrivilegedAccount($user)) {
            return back()->withErrors(['No puedes eliminar cuentas con rol Administrador o Superadmin.']);
        }

        if ($this->userHasClinicalOrAdministrativeRecords($user)) {
            return back()->withErrors([
                'No es posible eliminar esta cuenta porque posee información clínica o administrativa que debe conservarse. Puedes desactivarla para impedir su acceso.'
            ]);
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
                $user->delete();
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors([$e->getMessage()]);
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->withErrors([
                'No es posible eliminar esta cuenta porque posee información clínica o administrativa que debe conservarse. Puedes desactivarla para impedir su acceso.'
            ]);
        }

        return back()->with('success', 'Usuario eliminado.');
    }

    private function userHasClinicalOrAdministrativeRecords(User $user): bool
    {
        $userId = $user->id;

        if (\App\Models\Cita::where('doctor_id', $userId)->orWhere('paciente_id', $userId)->exists()) {
            return true;
        }

        $certQuery = \App\Models\CertificadoMedico::where('doctor_id', $userId)->orWhere('paciente_id', $userId);
        if (\Illuminate\Support\Facades\Schema::hasColumn('certificados_medicos', 'corregido_por')) {
            $certQuery->orWhere('corregido_por', $userId);
        }
        if ($certQuery->exists()) {
            return true;
        }

        if (\App\Models\ClinicalRecord::where('patient_id', $userId)
            ->orWhere('created_by', $userId)
            ->orWhere('updated_by', $userId)
            ->exists()) {
            return true;
        }

        if (\App\Models\NotaSoap::where('signed_by', $userId)->exists()) {
            return true;
        }

        if (\App\Models\LaboratorioOrden::where('solicitante_id', $userId)->exists()) {
            return true;
        }

        if (\App\Models\LabOrder::where('doctor_id', $userId)
            ->orWhere('patient_id', $userId)
            ->orWhere('laboratorio_id', $userId)
            ->exists()) {
            return true;
        }

        if (\App\Models\PedidoLaboratorio::where('doctor_id', $userId)
            ->orWhere('paciente_id', $userId)
            ->exists()) {
            return true;
        }

        if (\App\Models\MedicalOrder::where('doctor_id', $userId)
            ->orWhere('patient_id', $userId)
            ->exists()) {
            return true;
        }

        if (\App\Models\PedidoLaboratorioResultado::where('laboratorio_id', $userId)->exists()) {
            return true;
        }

        if (\App\Models\Pago::where('paciente_id', $userId)
            ->orWhere('aprobado_por', $userId)
            ->orWhere('creado_por', $userId)
            ->exists()) {
            return true;
        }

        if (\App\Models\Factura::where('paciente_id', $userId)
            ->orWhere('doctor_id', $userId)
            ->exists()) {
            return true;
        }

        if (\App\Models\PaymentReceipt::where('emitido_por', $userId)->exists()) {
            return true;
        }

        if (\App\Models\Dependiente::where('user_id', $userId)->exists()) {
            return true;
        }

        if (\App\Models\Horario::where('doctor_id', $userId)->exists()) {
            return true;
        }

        return false;
    }

    public function doctoresPorEspecialidad(Especialidad $especialidad, ImageUrl $imageUrl)
    {
        $rol = $especialidad->isLaboratorioClinico()
            ? 'laboratorio'
            : 'doctor';

        $doctores = $especialidad->doctores()
            ->with('especialidades:id,nombre')
            ->whereHas('roles', fn ($q) => $q->where('name', $rol))
            ->onlyActive()
            ->orderBy('name')
            ->get([
                'users.id',
                'users.name',
                'users.telefono',
                'users.direccion',
                'users.avatar',
                'users.precio_consulta',
                'users.moneda',
                'users.sexo',
            ])
            ->map(function (User $doctor) use ($imageUrl) {
                $avatar = $imageUrl->variants($doctor->avatar, 'doctors', 'doctor');
                $moneda = $doctor->moneda ?: 'USD';
                $precio = $doctor->precio_consulta;

                return [
                    'id' => $doctor->id,
                    'name' => $doctor->name,
                    'telefono' => $doctor->telefono,
                    'direccion' => $doctor->direccion,
                    'sexo' => $doctor->sexo,
                    'avatar_url' => $avatar['medium'] ?? $avatar['src'],
                    'avatar_thumb' => $avatar['thumb'] ?? $avatar['src'],
                    'avatar_srcset' => $avatar['srcset'],
                    'especialidades' => $doctor->especialidades
                        ->pluck('nombre')
                        ->values(),
                    'precio_consulta' => is_null($precio) ? null : (float) $precio,
                    'moneda' => $moneda,
                    'precio_format' => is_null($precio)
                        ? null
                        : '$'.number_format((float) $precio, 2).' '.$moneda,
                ];
            })
            ->values();

        return response()->json($doctores);
    }

    // ===== Exportes (respetan buscar + role) =====
    public function usuariosExportExcel(Request $request)
    {
        $buscar = $this->normalizeSearchTerm($request->get('buscar', ''));
        $role = $this->normalizeUserRoleFilter($request->string('role')->lower()->value());
        if (in_array($role, ['administrador', 'superadmin'], true)) {
            return back()->withErrors(['No puedes exportar cuentas Administrador o Superadmin.']);
        }

        $users = User::with(['roles', 'especialidades'])
            ->orderBy('id', 'desc')
            ->search($buscar)
            ->role($role)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['administrador', 'superadmin']))
            ->get();

        $sheetData = [];
        $sheetData[] = ['ID', 'Nombre', 'Email', 'Teléfono', 'Cédula', 'Rol', 'Estado', 'Suspendido Hasta', 'Último acceso', 'Creado', 'Especialidades'];

        foreach ($users as $u) {
            $rol = optional($u->roles->first())->name;
            $esp = $u->especialidades ? $u->especialidades->pluck('nombre')->implode(', ') : '';
            $sheetData[] = [
                $u->id,
                $u->name,
                $u->email,
                $u->telefono,
                $u->dni,
                $rol,
                $u->status ?: User::STATUS_ACTIVE,
                optional($u->suspended_until)->format('Y-m-d H:i'),
                optional($u->last_login_at)->format('Y-m-d H:i'),
                optional($u->created_at)->format('Y-m-d H:i'),
                $esp,
            ];
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($sheetData, null, 'A1', true);
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->setTitle('Usuarios');

        $writer = new Xlsx($spreadsheet);
        $filename = 'usuarios'.($role ? '-'.$role : '').'_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function usuariosExportPdf(Request $request)
    {
        $buscar = $this->normalizeSearchTerm($request->get('buscar', ''));
        $role = $this->normalizeUserRoleFilter($request->string('role')->lower()->value());
        if (in_array($role, ['administrador', 'superadmin'], true)) {
            return back()->withErrors(['No puedes exportar cuentas Administrador o Superadmin.']);
        }

        $users = User::with(['roles', 'especialidades'])
            ->orderBy('id', 'desc')
            ->search($buscar)
            ->role($role)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['administrador', 'superadmin']))
            ->get();

        $html = view('admin.users.usuarios-pdf', [
            'users' => $users,
            'role' => $role,
            'pdfCss' => $this->loadPdfCss('admin/usuarios-pdf.css'),
            'statusLabels' => [
                User::STATUS_ACTIVE => 'Activo',
                User::STATUS_INACTIVE => 'Inactivo',
                User::STATUS_BLOCKED => 'Bloqueado',
            ],
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'usuarios'.($role ? '-'.$role : '').'_'.now()->format('Ymd_His').'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function loadPdfCss(string $relativePath): string
    {
        $path = resource_path('css/'.$relativePath);

        return is_file($path) ? (file_get_contents($path) ?: '') : '';
    }

    private function normalizeSearchTerm(mixed $value, int $maxLength = 100): string
    {
        return trim(mb_substr((string) $value, 0, $maxLength));
    }

    private function normalizePerPage(mixed $value, int $default = 15): int
    {
        $perPage = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $allowed = [10, 15, 25, 50];

        return in_array($perPage, $allowed, true) ? $perPage : $default;
    }

    private function normalizeUserRoleFilter(string $role): string
    {
        $role = trim(strtolower($role));
        if ($role === 'all') {
            return '';
        }

        return in_array($role, ['', 'paciente', 'doctor', 'laboratorio', 'administrador', 'superadmin'], true) ? $role : '';
    }
}
