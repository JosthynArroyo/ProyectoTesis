<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use App\Models\Cita;
use App\Models\User;
use App\Models\Role;
use App\Models\Especialidad;
use App\Support\ValidationRules;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use App\Services\CitaNoShowService;
use App\Services\ImageOptimizer;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    // ===== Dashboard =====
    public function dashboard(Request $request)
    {
        app(CitaNoShowService::class)->marcarVencidas();
        $user = Auth::user();
        $prioridad = strtoupper(trim((string) $request->get('prioridad', '')));
        if ($prioridad === 'ALL' || !in_array($prioridad, Cita::PRIORIDAD_NIVELES, true)) {
            $prioridad = '';
        }

        $citas = Cita::with(['paciente', 'doctor'])
            ->when($prioridad !== '', fn ($query) => $query->where('prioridad_nivel', $prioridad))
            ->orderByRaw(Cita::prioridadOrderSql())
            ->orderBy('fecha', 'asc')
            ->orderBy('hora', 'asc')
            ->limit(50)
            ->get();

        $totalCitas           = Cita::count();
        $totalCitasPendientes = Cita::where('estado', 'pendiente')->count();
        $totalCitasRealizadas = Cita::where('estado', 'realizada')->count();
        $totalCitasCanceladas = Cita::where('estado', 'cancelada')->count();
        $totalCitasPendientesAlta = Cita::where('estado', 'pendiente')
            ->where('activo', true)
            ->where('prioridad_nivel', Cita::PRIORIDAD_ALTA)
            ->count();
        $totalPacientes       = User::whereHas('roles', fn($q) => $q->where('name', 'paciente'))->count();
        $totalDoctores        = User::whereHas('roles', fn($q) => $q->where('name', 'doctor'))->count();
        $usuariosActivosHoy   = User::whereDate('last_login_at', now()->toDateString())->count();

        return view('admin.dashboard', compact(
            'user',
            'citas',
            'totalCitas',
            'totalCitasPendientes',
            'totalCitasPendientesAlta',
            'totalCitasRealizadas',
            'totalCitasCanceladas',
            'totalPacientes',
            'totalDoctores',
            'usuariosActivosHoy',
            'prioridad'
        ));
    }

    public function resumenGlobal(Request $request)
    {
        if (!$request->ajax()) {
            return redirect()->route('admin.dashboard');
        }

        app(CitaNoShowService::class)->marcarVencidas();
        return response()->json([
            'agendadas'   => Cita::count(),
            'completadas' => Cita::where('estado', 'realizada')->count(),
            'canceladas'  => Cita::where('estado', 'cancelada')->count(),
        ]);
    }

    // ===== Perfil =====
    public function editarPerfil()
    {
        $user = Auth::user();
        return view('admin.perfil', compact('user'));
    }

    public function actualizarPerfil(Request $request, ImageOptimizer $imageOptimizer)
    {
        $user = Auth::user();

        $rules = [
            'name'              => ['required','string','max:255'],
            'email'             => ValidationRules::emailUnique('users', $user->id),
            'telefono'          => ValidationRules::telefono(),
            'dni'               => ValidationRules::cedulaUnique('users', $user->id),
            'direccion'         => ['required','string','max:255'],
            'fecha_nacimiento'  => ['required','date','before:today'],
            'sexo'              => ['required','in:Masculino,Femenino,Otro'],
            'avatar'            => ['nullable','image','mimes:jpg,jpeg,png,webp,svg','max:2048'],
            'current_password'  => ['nullable','string'],
            'password'          => array_merge(
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
            if ($user->avatar) {
                $imageOptimizer->deleteByStoredPath($user->avatar, $imageFolder);
            }
            $data['avatar'] = $imageOptimizer->optimizeAndStore($request->file('avatar'), $imageFolder);
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->telefono = $data['telefono'] ?? null;
        $user->dni = $data['dni'];
        $user->direccion = $data['direccion'] ?? null;
        $user->fecha_nacimiento = $data['fecha_nacimiento'] ?? null;
        $user->sexo = $data['sexo'] ?? null;
        if (isset($data['avatar'])) $user->avatar = $data['avatar'];

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

        return redirect()->route('admin.perfil.edit')->with('success','Perfil actualizado correctamente.');
    }

    // ===== Usuarios (listado + filtros) =====
    public function usuarios(Request $request)
    {
        $buscar  = trim((string) $request->get('buscar', ''));
        $perPage = (int) ($request->get('per_page', 12));
        $role    = $request->string('role')->lower()->value();
        if ($role === 'all') {
            $role = '';
        }

        $allColumns = ['usuario','contacto','rol','estado','especialidades','acciones'];
        $cols = $request->has('cols')
            ? array_values(array_intersect($allColumns, (array) $request->get('cols')))
            : ($request->session()->get('usuarios.cols') ?: $allColumns);
        if (empty($cols)) $cols = $allColumns;
        $request->session()->put('usuarios.cols', $cols);

        $usersQ = User::with(['roles', 'especialidades', 'patientFlag'])
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
        return view('admin.users.create', compact('roles','especialidades'));
    }

    public function usuariosStore(Request $request)
    {
        $roles = Role::pluck('name','id');
        $roleName = $roles[(int) $request->input('role_id')] ?? null;

        $baseRules = [
            'name'     => ['required','string','max:255'],
            'email'    => ValidationRules::emailUnique(),
            'password' => ValidationRules::passwordRequired(),
            'telefono' => ValidationRules::telefono(),
            'dni'      => ValidationRules::cedulaUnique(),
            'direccion'=> ['required','string','max:255'],
            'fecha_nacimiento' => ['required','date','before:today'],
            'sexo'     => ['required','in:Masculino,Femenino,Otro'],
            'role_id'  => ['required','exists:roles,id'],
            'especialidad_id' => ['nullable', Rule::requiredIf($roleName === 'doctor'), 'integer', 'exists:especialidades,id'],
            'precio_consulta' => ['nullable', Rule::requiredIf(in_array($roleName, ['doctor','laboratorio'], true)), 'numeric', 'min:0', 'max:99999999.99'],
            'adulto_mayor' => ['required','boolean'],
            'embarazo' => ['required','boolean'],
            'discapacidad' => ['required','boolean'],
            'cronico' => ['required','boolean'],
        ];

        $data = $request->validate($baseRules);

        $roleName = $roles[(int)$data['role_id']] ?? null;
        if ($this->isPrivilegedRole($roleName)) {
            return back()->withErrors(['role_id' => 'No puedes asignar roles Administrador o Superadmin.'])->withInput();
        }
        $isDoctor = $roleName === 'doctor';
        $isLaboratorio = $roleName === 'laboratorio';
        $labId = null;

        if ($isDoctor) {
            $request->validate([
                'especialidad_id' => ['required','integer','exists:especialidades,id'],
            ], [
                'especialidad_id.required' => 'La especialidad es obligatoria para este rol.',
            ]);
        } elseif ($isLaboratorio) {
            $labId = Especialidad::where('nombre', 'Laboratorio Clinico')->value('id');
            if (!$labId) {
                return back()->withErrors(['especialidad_id' => 'No existe la especialidad Laboratorio Clínico.'])->withInput();
            }
        }

        $u = new User();
        $u->name             = $data['name'];
        $u->email            = $data['email'];
        $u->password         = Hash::make($data['password']);
        $u->active           = true;
        $u->telefono         = $data['telefono'] ?? null;
        $u->dni              = $data['dni'];
        $u->direccion        = $data['direccion'] ?? null;
        $u->fecha_nacimiento = $data['fecha_nacimiento'] ?? null;
        $u->sexo             = $data['sexo'] ?? null;
        $u->precio_consulta  = ($isDoctor || $isLaboratorio) ? ($data['precio_consulta'] ?? null) : null;
        $u->moneda           = 'USD';
        $u->status           = 'active';
        $u->save();

        $u->roles()->sync([(int)$data['role_id']]);

        if ($isDoctor || $isLaboratorio) {
            $espId = $isLaboratorio ? (int)$labId : (int)$request->input('especialidad_id');
            $u->especialidades()->sync([$espId]);
        }

        if ($roleName === 'paciente') {
            $u->patientFlag()->updateOrCreate(
                ['user_id' => $u->id],
                $this->extractPatientFlags($request)
            );
        }

        return redirect()->route('admin.usuarios.index')->with('success','Usuario creado correctamente.');
    }

    public function usuariosShow(User $user)
    {
        if ($this->isPrivilegedAccount($user)) {
            return redirect()->route('admin.usuarios.index')
                ->withErrors(['No puedes administrar cuentas Administrador o Superadmin.']);
        }
        $user->load(['roles','especialidades']);
        return view('admin.users.show', compact('user'));
    }

    public function usuariosEdit(User $user)
    {
        if ($this->isPrivilegedAccount($user)) {
            return redirect()->route('admin.usuarios.index')
                ->withErrors(['No puedes administrar cuentas Administrador o Superadmin.']);
        }
        $user->load(['roles','especialidades']);
        $roles = Role::whereNotIn('name', ['administrador', 'superadmin'])->orderBy('name')->get();
        $especialidades = Especialidad::orderBy('nombre')->get();
        return view('admin.users.edit', compact('user','roles','especialidades'));
    }

    public function usuariosUpdate(Request $request, User $user)
    {
        if ($this->isPrivilegedAccount($user)) {
            return redirect()->route('admin.usuarios.index')
                ->withErrors(['No puedes administrar cuentas Administrador o Superadmin.']);
        }
        $request->merge(['email' => strtolower($request->input('email'))]);
        $roles = Role::pluck('name','id');
        $roleNameRequest = $roles[(int) $request->input('role_id')] ?? null;

        $rules = [
            'name'             => ['required','string','max:255'],
            'email'            => ValidationRules::emailUnique('users', $user->id),
            'telefono'         => ValidationRules::telefono(),
            'dni'              => ValidationRules::cedulaUnique('users', $user->id),
            'direccion'        => ['required','string','max:255'],
            'fecha_nacimiento' => ['required','date','before:today'],
            'sexo'             => ['required','in:Masculino,Femenino,Otro'],
            'role_id'          => ['required','exists:roles,id'],
            'especialidad_id'  => ['nullable', Rule::requiredIf($roleNameRequest === 'doctor'), 'integer', 'exists:especialidades,id'],
            'precio_consulta'  => ['nullable', Rule::requiredIf(in_array($roleNameRequest, ['doctor','laboratorio'], true)), 'numeric', 'min:0', 'max:99999999.99'],
            'adulto_mayor' => ['required','boolean'],
            'embarazo' => ['required','boolean'],
            'discapacidad' => ['required','boolean'],
            'cronico' => ['required','boolean'],
        ];
        $data = $request->validate($rules);

        $user->name             = $data['name'];
        $user->email            = $data['email'];
        $user->telefono         = $data['telefono'] ?? null;
        $user->dni              = $data['dni'] ?? null;
        $user->direccion        = $data['direccion'] ?? null;
        $user->fecha_nacimiento = $data['fecha_nacimiento'] ?? null;
        $user->sexo             = $data['sexo'] ?? null;

        $roleId   = (int)$data['role_id'];
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
            $labId = Especialidad::where('nombre', 'Laboratorio Clinico')->value('id');
            if (!$labId) {
                return back()->withErrors(['especialidad_id' => 'No existe la especialidad Laboratorio Clínico.'])->withInput();
            }
        }

        if ($isDoctor) {
            $user->precio_consulta = $data['precio_consulta'] ?? $user->precio_consulta;
            $user->moneda = $user->moneda ?: 'USD';
            if (!empty($data['especialidad_id'])) {
                $user->especialidades()->sync([(int)$data['especialidad_id']]);
            } else {
                $user->especialidades()->sync([]);
            }
        } elseif ($isLaboratorio) {
            $user->precio_consulta = $data['precio_consulta'] ?? $user->precio_consulta;
            $user->moneda = $user->moneda ?: 'USD';
            $user->especialidades()->sync([(int)$labId]);
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
        return $user->hasRole('administrador') || $user->hasRole('superadmin');
    }

    public function usuariosDestroy(User $user)
    {
        if (Auth::id() === $user->id) {
            return back()->withErrors(['No puedes eliminar tu propio usuario.']);
        }
        if ($this->isPrivilegedAccount($user)) {
            return back()->withErrors(['No puedes eliminar cuentas con rol Administrador o Superadmin.']);
        }

        $user->roles()->detach();
        $user->delete();

        return back()->with('success', 'Usuario eliminado.');
    }

    // ===== Alta rápida Doctor (atajo legado) =====
    public function crearDoctor()
    {
        $especialidades = Especialidad::orderBy('nombre')->get();
        return view('admin.doctor-create', compact('especialidades'));
    }

    public function storeDoctor(Request $request, ImageOptimizer $imageOptimizer)
    {
        return $this->guardarDoctor($request, $imageOptimizer);
    }

    public function guardarDoctor(Request $request, ImageOptimizer $imageOptimizer)
    {
        $rules = [
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ValidationRules::emailUnique(),
            'password'         => ValidationRules::passwordRequired(),
            'telefono'         => ValidationRules::telefono(),
            'dni'              => ValidationRules::cedulaUnique(),
            'direccion'        => ['required', 'string', 'max:255'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'sexo'             => ['required', 'in:Masculino,Femenino,Otro'],
            'avatar'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'especialidad_id'  => ['required', 'integer', 'exists:especialidades,id'],
            'precio_consulta'  => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'moneda'           => ['required', 'in:USD'],
        ];

        $validated = $request->validate($rules);

        $user = new User();
        $user->name             = $validated['name'];
        $user->email            = $validated['email'];
        $user->password         = Hash::make($validated['password']);
        $user->active           = true;
        $user->telefono         = $validated['telefono'] ?? null;
        $user->dni              = $validated['dni'] ?? null;
        $user->direccion        = $validated['direccion'] ?? null;
        $user->fecha_nacimiento = $validated['fecha_nacimiento'] ?? null;
        $user->sexo             = $validated['sexo'] ?? null;
        $user->precio_consulta  = $validated['precio_consulta'] ?? null;
        $user->moneda           = 'USD';

        if ($request->hasFile('avatar')) {
            $user->avatar = $imageOptimizer->optimizeAndStore($request->file('avatar'), 'doctors');
        }

        $user->save();

        $role = Role::where('name', 'doctor')->firstOrFail();
        $user->roles()->sync([$role->id]);
        $user->especialidades()->sync([$validated['especialidad_id']]);

        return redirect()->route('admin.usuarios.index')->with('success', 'Doctor creado correctamente.');
    }

    public function doctoresPorEspecialidad(Especialidad $especialidad)
    {
        $rol = mb_strtolower($especialidad->nombre) === 'laboratorio clinico'
            ? 'laboratorio'
            : 'doctor';

        $doctores = $especialidad->doctores()
            ->whereHas('roles', fn($q) => $q->where('name', $rol))
            ->onlyActive()
            ->orderBy('name')
            ->get(['users.id', 'users.name']);

        return response()->json($doctores);
    }

    public function crearPaciente()
    {
        return view('admin.paciente-create');
    }

    public function storePaciente(Request $request)
    {
        $rules = [
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ValidationRules::emailUnique(),
            'password'         => ValidationRules::passwordRequired(),
            'telefono'         => ValidationRules::telefono(),
            'dni'              => ValidationRules::cedulaUnique(),
            'direccion'        => ['required', 'string', 'max:255'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'sexo'             => ['required', 'in:Masculino,Femenino,Otro'],
        ];

        $data = $request->validate($rules);

        $user = new User();
        $user->name             = $data['name'];
        $user->email            = $data['email'];
        $user->password         = Hash::make($data['password']);
        $user->active           = true;
        $user->telefono         = $data['telefono'] ?? null;
        $user->dni              = $data['dni'];
        $user->direccion        = $data['direccion'] ?? null;
        $user->fecha_nacimiento = $data['fecha_nacimiento'] ?? null;
        $user->sexo             = $data['sexo'] ?? null;
        $user->status           = 'active';
        $user->save();

        $role = Role::where('name', 'paciente')->firstOrFail();
        $user->roles()->sync([$role->id]);

        return redirect()->route('admin.usuarios.index')->with('success', 'Paciente creado correctamente.');
    }

    // ===== Exportes (respetan buscar + role) =====
    public function usuariosExportExcel(Request $request)
    {
        $buscar = trim((string)$request->get('buscar',''));
        $role   = $request->string('role')->lower()->value();
        if (in_array($role, ['administrador', 'superadmin'], true)) {
            return back()->withErrors(['No puedes exportar cuentas Administrador o Superadmin.']);
        }

        $users = User::with(['roles','especialidades'])
            ->orderBy('id','desc')
            ->search($buscar)
            ->role($role)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['administrador', 'superadmin']))
            ->get();

        $sheetData = [];
        $sheetData[] = ['ID','Nombre','Email','Teléfono','Cédula','Rol','Estado','Suspendido Hasta','Último acceso','Creado','Especialidades'];

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
                $u->status ? 'active' : 'inactive',
                optional($u->suspended_until)->format('Y-m-d H:i'),
                optional($u->last_login_at)->format('Y-m-d H:i'),
                optional($u->created_at)->format('Y-m-d H:i'),
                $esp,
            ];
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($sheetData, null, 'A1', true);
        foreach (range('A','L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->setTitle('Usuarios');

        $writer = new Xlsx($spreadsheet);
        $filename = 'usuarios'.($role ? '-'.$role : '').'_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function usuariosExportPdf(Request $request)
    {
        $buscar = trim((string)$request->get('buscar',''));
        $role   = $request->string('role')->lower()->value();
        if (in_array($role, ['administrador', 'superadmin'], true)) {
            return back()->withErrors(['No puedes exportar cuentas Administrador o Superadmin.']);
        }

        $users = User::with(['roles','especialidades'])
            ->orderBy('id','desc')
            ->search($buscar)
            ->role($role)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['administrador', 'superadmin']))
            ->get();

        $html = view('admin.users.usuarios-pdf', compact('users','role'))->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4','portrait');
        $dompdf->render();

        $filename = 'usuarios'.($role ? '-'.$role : '').'_'.now()->format('Ymd_His').'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
