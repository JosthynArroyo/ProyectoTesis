<?php
// app/Http/Controllers/Admin/AdminController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use App\Models\Cita;
use App\Models\User;
use App\Models\Role;
use App\Models\Especialidad;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;
use Dompdf\Options;

class AdminController extends Controller
{
    // ===== Dashboard =====
    public function dashboard()
    {
        $user = Auth::user();

        $citas = Cita::with(['paciente', 'doctor'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $totalCitas           = Cita::count();
        $totalCitasPendientes = Cita::where('estado', 'pendiente')->count();
        $totalCitasRealizadas = Cita::where('estado', 'realizada')->count();
        $totalCitasCanceladas = Cita::where('estado', 'cancelada')->count();

        return view('admin.dashboard', compact(
            'user',
            'citas',
            'totalCitas',
            'totalCitasPendientes',
            'totalCitasRealizadas',
            'totalCitasCanceladas'
        ));
    }

    public function resumenGlobal(Request $request)
    {
        if (!$request->ajax()) {
            return redirect()->route('admin.dashboard');
        }

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

    public function actualizarPerfil(Request $request)
    {
        $user = Auth::user();

        $rules = [
            'name'              => ['required', 'string', 'max:255'],
            'email'             => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'telefono'          => ['nullable', 'digits:10'],
            'dni'               => ['required', 'digits:10', Rule::unique('users', 'dni')->ignore($user->id)],
            'direccion'         => ['nullable', 'string', 'max:255'],
            'fecha_nacimiento'  => ['nullable', 'date', 'before:today'],
            'sexo'              => ['nullable', 'in:Masculino,Femenino,Otro'],
            'avatar'            => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        $messages = [
            'name.required'           => 'El nombre es obligatorio.',
            'email.required'          => 'El correo es obligatorio.',
            'email.email'             => 'Formato de correo inválido.',
            'email.unique'            => 'Este correo ya está registrado.',
            'telefono.digits'         => 'El teléfono debe tener exactamente 10 dígitos.',
            'dni.required'            => 'El número de cédula es obligatorio.',
            'dni.digits'              => 'El número de cédula debe tener exactamente 10 dígitos.',
            'dni.unique'              => 'Este número de cédula ya está registrado.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'sexo.in'                 => 'Seleccione un sexo válido.',
            'avatar.image'            => 'La foto debe ser una imagen.',
            'avatar.mimes'            => 'Formato permitido: jpg, jpeg, png o webp.',
            'avatar.max'              => 'La imagen no debe superar 2 MB.',
        ];

        $data = $request->validate($rules, $messages);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        return redirect()->route('admin.perfil.edit')->with('success', 'Perfil actualizado correctamente.');
    }

    // ===== Usuarios (listado + filtros) =====
    public function usuarios(Request $request)
    {
        $buscar  = trim((string) $request->get('buscar', ''));
        $perPage = (int) ($request->get('per_page', 12));
        $role    = $request->string('role')->lower()->value();

        $allColumns = ['usuario','contacto','rol','estado','especialidades','acciones'];
        $cols = $request->has('cols')
            ? array_values(array_intersect($allColumns, (array)$request->get('cols')))
            : ($request->session()->get('usuarios.cols') ?: $allColumns);
        if (empty($cols)) $cols = $allColumns;
        $request->session()->put('usuarios.cols', $cols);

        $usersQ = User::with(['roles', 'especialidades'])
            ->orderBy('created_at', 'desc')
            ->search($buscar)
            ->role($role);

        $users = $usersQ->paginate($perPage)->appends($request->query());
        $roles = Role::orderBy('name', 'asc')->get();

        return view('admin.usuarios', compact('users', 'roles', 'buscar', 'cols', 'allColumns', 'perPage'))
            ->with('role', $role);
    }

    // ===== CRUD usuario rápido =====
    public function usuariosCreate()
    {
        $roles = Role::orderBy('name')->get();
        $especialidades = Especialidad::orderBy('nombre')->get();
        return view('admin.users.create', compact('roles','especialidades'));
    }

    public function usuariosStore(Request $request)
    {
        $roles = Role::pluck('name','id');

        $baseRules = [
            'name'     => ['required','string','max:255'],
            'email'    => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:8','confirmed','regex:/^(?=.*[A-Za-z])(?=.*\d).+$/'],
            'telefono' => ['nullable','digits:10'],
            'dni'      => ['required','digits:10','unique:users,dni'],
            'direccion'=> ['nullable','string','max:255'],
            'fecha_nacimiento' => ['nullable','date','before:today'],
            'sexo'     => ['nullable','in:Masculino,Femenino,Otro'],
            'role_id'  => ['required','exists:roles,id'],
            'especialidad_id' => ['nullable','integer','exists:especialidades,id'],
            'precio_consulta' => ['nullable','numeric','min:0','max:99999999.99'],
        ];

        $data = $request->validate($baseRules);

        $roleName = $roles[(int)$data['role_id']] ?? null;
        $isDoctor = $roleName === 'doctor';

        if ($isDoctor) {
            $request->validate([
                'especialidad_id' => ['required','integer','exists:especialidades,id'],
            ], [
                'especialidad_id.required' => 'La especialidad es obligatoria para el rol Doctor.',
            ]);
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
        $u->precio_consulta  = $isDoctor ? ($data['precio_consulta'] ?? null) : null;
        $u->moneda           = 'USD';
        $u->status           = 'active';
        $u->save();

        $u->roles()->sync([(int)$data['role_id']]);

        if ($isDoctor) {
            $u->especialidades()->sync([(int)$request->input('especialidad_id')]);
        }

        return redirect()->route('admin.usuarios.index')->with('success','Usuario creado correctamente.');
    }

    public function usuariosShow(User $user)
    {
        $user->load(['roles','especialidades']);
        return view('admin.users.show', compact('user'));
    }

    public function usuariosEdit(User $user)
    {
        $user->load(['roles','especialidades']);
        $roles = Role::orderBy('name')->get();
        $especialidades = Especialidad::orderBy('nombre')->get();
        return view('admin.users.edit', compact('user','roles','especialidades'));
    }

    public function usuariosUpdate(Request $request, User $user)
    {
        $request->merge(['email' => strtolower($request->input('email'))]);

        $rules = [
            'name'             => ['required','string','max:255'],
            'email'            => ['required','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'telefono'         => ['nullable','digits:10'],
            'dni'              => ['required','digits:10', Rule::unique('users','dni')->ignore($user->id)],
            'direccion'        => ['nullable','string','max:255'],
            'fecha_nacimiento' => ['nullable','date','before:today'],
            'sexo'             => ['nullable','in:Masculino,Femenino,Otro'],
            'role_id'          => ['required','exists:roles,id'],
            'especialidad_id'  => ['nullable','integer','exists:especialidades,id'],
            'precio_consulta'  => ['nullable','numeric','min:0','max:99999999.99'],
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
        $isDoctor = $roleName === 'doctor';

        if ($isDoctor) {
            $user->precio_consulta = $data['precio_consulta'] ?? $user->precio_consulta;
            $user->moneda = $user->moneda ?: 'USD';
            if (!empty($data['especialidad_id'])) {
                $user->especialidades()->sync([(int)$data['especialidad_id']]);
            } else {
                $user->especialidades()->sync([]);
            }
        } else {
            $user->precio_consulta = null;
            $user->especialidades()->sync([]);
            $user->moneda = $user->moneda ?: 'USD';
        }

        $user->save();
        $user->roles()->sync([$roleId]);

        return redirect()
            ->route('admin.usuarios.edit', $user)
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function usuariosDestroy(User $user)
    {
        if (Auth::id() === $user->id) {
            return back()->withErrors(['No puedes eliminar tu propio usuario.']);
        }
        if ($user->hasRole('administrador')) {
            return back()->withErrors(['No puedes eliminar cuentas con rol Administrador.']);
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

    public function storeDoctor(Request $request)
    {
        return $this->guardarDoctor($request);
    }

    public function guardarDoctor(Request $request)
    {
        $rules = [
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'         => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/'],
            'telefono'         => ['nullable', 'digits:10'],
            'dni'              => ['required', 'digits:10', 'unique:users,dni'],
            'direccion'        => ['nullable', 'string', 'max:255'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'sexo'             => ['nullable', 'in:Masculino,Femenino,Otro'],
            'avatar'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'especialidad_id'  => ['required', 'integer', 'exists:especialidades,id'],
            'precio_consulta'  => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'moneda'           => ['nullable', 'in:USD'],
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
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();

        $role = Role::where('name', 'doctor')->firstOrFail();
        $user->roles()->sync([$role->id]);
        $user->especialidades()->sync([$validated['especialidad_id']]);

        return redirect()->route('admin.usuarios.index')->with('success', 'Doctor creado correctamente.');
    }

    public function doctoresPorEspecialidad(Especialidad $especialidad)
    {
        $doctores = $especialidad->doctores()
            ->whereHas('roles', fn($q) => $q->where('name', 'doctor'))
            ->where('active', true)
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
            'email'            => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'         => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/'],
            'telefono'         => ['nullable', 'digits:10'],
            'dni'              => ['required', 'digits:10', 'unique:users,dni'],
            'direccion'        => ['nullable', 'string', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'sexo'             => ['nullable', 'in:Masculino,Femenino,Otro'],
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

        $users = User::with(['roles','especialidades'])
            ->orderBy('id','desc')
            ->search($buscar)
            ->role($role)
            ->get();

        $sheetData = [];
        $sheetData[] = ['ID','Nombre','Email','Teléfono','Cédula','Rol','Estado','Suspendido Hasta','Último acceso','Creado','Especialidades'];

        foreach ($users as $u) {
            $rol = optional($u->roles->first())->name;
            $esp = ($u->especialidades ?? collect())->pluck('nombre')->implode(', ');
            $sheetData[] = [
                $u->id,
                $u->name,
                $u->email,
                $u->telefono,
                $u->dni,
                $rol,
                $u->status ?? 'active',
                optional($u->suspended_until)?->format('Y-m-d H:i'),
                optional($u->last_login_at)?->format('Y-m-d H:i'),
                optional($u->created_at)?->format('Y-m-d H:i'),
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
        $filename = 'usuarios'.($role?'-'.$role:'').'_'.now()->format('Ymd_His').'.xlsx';

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

        $users = User::with(['roles','especialidades'])
            ->orderBy('id','desc')
            ->search($buscar)
            ->role($role)
            ->get();

        $html = view('admin.users.usuarios-pdf', compact('users','role'))->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4','portrait');
        $dompdf->render();

        $filename = 'usuarios'.($role?'-'.$role:'').'_'.now()->format('Ymd_His').'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
