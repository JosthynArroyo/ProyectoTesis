<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\FeatureAccessRequest;
use App\Models\Horario;
use App\Models\LaboratorioOrden;
use App\Models\Pago;
use App\Models\Role;
use App\Models\User;
use App\Services\SiteSettingsService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditFunctionalFlowTest extends TestCase
{
    private array $results = [];

    private array $context = [];

    protected function setUp(): void
    {
        parent::setUp();

        $dbPath = env('AUDIT_DB_PATH');
        if ($dbPath) {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $dbPath,
                'mail.default' => 'array',
                'mail.mailers.smtp.transport' => 'array',
                'queue.default' => 'sync',
                'session.driver' => 'array',
                'cache.default' => 'array',

                'mail.contact_to' => 'qa@example.test',
            ]);

            DB::purge('sqlite');
            DB::reconnect('sqlite');
        } else {
            $this->prepareIsolatedAuditFixture();
        }

        Mail::fake();
        Notification::fake();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function prepareIsolatedAuditFixture(): void
    {
        Artisan::call('migrate:fresh', [
            '--database' => config('database.default'),
            '--seed' => true,
            '--force' => true,
        ]);

        $this->seedAuditRoleUsers();
    }

    private function seedAuditRoleUsers(): void
    {
        $doctorSpecialty = Especialidad::query()
            ->orderBy('id')
            ->get()
            ->first(fn (Especialidad $especialidad) => ! $especialidad->isLaboratorioClinico());

        $labSpecialtyId = Especialidad::laboratorioClinicoId();

        if (! $doctorSpecialty || ! $labSpecialtyId) {
            $this->fail('La auditoría funcional requiere especialidades base sembradas.');
        }

        $this->upsertAuditUser(
            roleName: 'administrador',
            attributes: [
                'name' => 'Admin QA',
                'email' => 'admin.qa@clinic.test',
                'password' => 'admin1234*',
                'telefono' => '0990000001',
                'dni' => '1000000001',
                'direccion' => 'Calle Admin QA 123',
                'fecha_nacimiento' => '1990-01-10',
                'sexo' => 'Masculino',
            ]
        );

        $this->upsertAuditUser(
            roleName: 'doctor',
            attributes: [
                'name' => 'Doctor QA',
                'email' => 'doctor.qa@clinic.test',
                'password' => 'admin1234*',
                'telefono' => '0990000002',
                'dni' => '1000000002',
                'direccion' => 'Consultorio QA 101',
                'fecha_nacimiento' => '1988-04-12',
                'sexo' => 'Masculino',
                'precio_consulta' => '35.50',
            ],
            specialtyIds: [$doctorSpecialty->id]
        );

        $this->upsertAuditUser(
            roleName: 'paciente',
            attributes: [
                'name' => 'Paciente QA',
                'email' => 'paciente.qa@clinic.test',
                'password' => 'admin1234*',
                'telefono' => '0990000003',
                'dni' => '1000000003',
                'direccion' => 'Av. Paciente QA 456',
                'fecha_nacimiento' => '1995-06-08',
                'sexo' => 'Femenino',
            ],
            patientFlags: [
                'adulto_mayor' => false,
                'embarazo' => false,
                'discapacidad' => false,
                'cronico' => true,
            ]
        );

        $this->upsertAuditUser(
            roleName: 'laboratorio',
            attributes: [
                'name' => 'Laboratorio QA',
                'email' => 'laboratorio.qa@clinic.test',
                'password' => 'admin1234*',
                'telefono' => '0990000004',
                'dni' => '1000000004',
                'direccion' => 'Av. Laboratorio QA 789',
                'fecha_nacimiento' => '1992-09-14',
                'sexo' => 'Otro',
                'precio_consulta' => '18.00',
            ],
            specialtyIds: [$labSpecialtyId]
        );
    }

    private function upsertAuditUser(
        string $roleName,
        array $attributes,
        array $specialtyIds = [],
        ?array $patientFlags = null
    ): User {
        $roleId = Role::query()->where('name', $roleName)->value('id');

        if (! $roleId) {
            $this->fail(sprintf('No existe el rol base requerido para la auditoría: %s.', $roleName));
        }

        $user = User::query()->updateOrCreate(
            ['email' => $attributes['email']],
            array_merge($attributes, [
                'status' => User::STATUS_ACTIVE,
                'active' => true,
                'moneda' => 'USD',
            ])
        );

        $user->roles()->sync([$roleId]);
        $user->especialidades()->sync($specialtyIds);

        if ($patientFlags !== null) {
            $user->patientFlag()->updateOrCreate(
                ['user_id' => $user->id],
                $patientFlags
            );
        }

        return $user;
    }

    public function test_full_functional_audit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-08 10:00:00', 'America/Guayaquil'));
        try {
            $roles = $this->loadRoleUsers();
        $this->context['roles_detectados'] = array_keys($roles);
        $this->context['credenciales'] = [
            'superadmin' => ['email' => $roles['superadmin']->email, 'password' => 'superadmin1234'],
            'administrador' => ['email' => $roles['administrador']->email, 'password' => 'admin1234*'],
            'doctor' => ['email' => $roles['doctor']->email, 'password' => 'admin1234*'],
            'paciente' => ['email' => $roles['paciente']->email, 'password' => 'admin1234*'],
            'laboratorio' => ['email' => $roles['laboratorio']->email, 'password' => 'admin1234*'],
        ];

        $doctor = $roles['doctor']->load('especialidades');
        $patient = $roles['paciente'];
        $lab = $roles['laboratorio']->load('especialidades');
        $superadmin = $roles['superadmin'];

        $doctorSpecialty = $doctor->especialidades->first() ?: Especialidad::query()->where('nombre', 'like', '%General%')->firstOrFail();
        $doctorDate = Carbon::now('America/Guayaquil')->addDays(5);
        $doctorDate2 = Carbon::now('America/Guayaquil')->addDays(6);
        $doctorDate3 = Carbon::now('America/Guayaquil')->addDays(7);
        $labDate = Carbon::now('America/Guayaquil')->addDays(8);
        $labDate2 = Carbon::now('America/Guayaquil')->addDays(9);

        $this->testRoleLogins($roles);
        $this->testAccessRestrictions($roles);
        $tempAdmin = $this->testSuperadminCrudAndGetWorkingAdmin($superadmin);
        $this->testSuperadminGlobalViews($superadmin);
        $this->testAdminFeatureAccessAndCrud($tempAdmin, $superadmin, $doctorSpecialty);
        $this->testAdminScheduleCrud($tempAdmin, $doctor, $doctorDate, $doctorDate2);

        $appointments = $this->testPatientBookingFlows($patient, $doctor, $doctorSpecialty, $doctorDate);


        $followupSchedule = $this->testDoctorHorarioCrud($doctor, $doctorDate3);
        $this->testDoctorCoreFlows($doctor, $appointments, $patient, $followupSchedule);

        $this->testLaboratorioHorarioCrud($lab, $labDate, $labDate2);
        $labOrder = LaboratorioOrden::create([
            'cita_id' => $appointments['principal']->id,
            'clinical_record_id' => null,
            'solicitante_id' => $doctor->id,
            'origen' => 'doctor',
            'prioridad' => 'normal',
            'tipo_examen' => 'Hemograma completo',
            'indicaciones' => 'Traer orden y documento.',
            'preparacion' => 'Ayuno de 8 horas.',
            'estado' => LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
        ]);
        $this->testLaboratorioOrderFlow($lab, $labOrder);
        $this->testPatientSeesLabResults($patient, $labOrder);

        $payments = $this->testPaymentFlows($tempAdmin, $patient, $doctor, $appointments['principal'], $doctorDate3);
        $this->testAdminAuditViews($tempAdmin, $patient);
        $this->testSuperadminDeleteTempAdmin($superadmin, $tempAdmin);

        $this->context['pagos_finales'] = [
            'aprobado' => $payments['approved']->estado,
            'anulado' => $payments['annulled']->estado,
        ];

        $this->writeReport();
        $this->assertTrue(true);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function loadRoleUsers(): array
    {
        $roles = ['superadmin', 'administrador', 'doctor', 'paciente', 'laboratorio'];
        $map = [];

        foreach ($roles as $role) {
            $map[$role] = User::query()
                ->whereHas('roles', fn ($q) => $q->where('name', $role))
                ->firstOrFail();
        }

        return $map;
    }

    private function errorKeys(): array
    {
        $errors = session('errors');
        if (! $errors) {
            return [];
        }

        return array_keys($errors->getBag('default')->messages());
    }

    private function record(
        string $role,
        string $module,
        string $function,
        string $route,
        array $data,
        array $steps,
        string $expected,
        string $actual,
        string $status,
        array $evidence = [],
        ?string $cause = null,
        ?string $recommendation = null
    ): void {
        $this->results[] = [
            'Rol' => $role,
            'Módulo' => $module,
            'Función' => $function,
            'Ruta/Vista/Controlador' => $route,
            'Datos usados en la prueba' => $data,
            'Pasos ejecutados' => $steps,
            'Resultado esperado' => $expected,
            'Resultado real' => $actual,
            'Estado' => $status,
            'Evidencia técnica' => $evidence,
            'Posible causa del problema' => $cause,
            'Recomendación concreta' => $recommendation,
        ];
    }

    private function testRoleLogins(array $roles): void
    {
        $expected = [
            'superadmin' => '/superadmin/dashboard',
            'administrador' => '/admin/dashboard',
            'doctor' => '/doctor/dashboard',
            'paciente' => '/paciente/dashboard',
            'laboratorio' => '/laboratorio/dashboard',
        ];

        $passwords = [
            'superadmin' => 'superadmin1234',
            'administrador' => 'admin1234*',
            'doctor' => 'admin1234*',
            'paciente' => 'admin1234*',
            'laboratorio' => 'admin1234*',
        ];

        foreach ($roles as $role => $user) {
            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => $passwords[$role],
                'remember' => '1',
            ]);

            $location = $response->headers->get('Location');
            $ok = $response->getStatusCode() === 302 && str_contains((string) $location, $expected[$role]);

            $this->record(
                role: $role,
                module: 'Autenticación',
                function: 'Login por rol y redirección al dashboard',
                route: 'POST /login',
                data: ['email' => $user->email],
                steps: ['Enviar credenciales verificadas en DB', 'Inspeccionar status y Location'],
                expected: 'Redirección al dashboard correspondiente.',
                actual: sprintf(
                    'HTTP %s con Location=%s; active=%s status=%s',
                    $response->getStatusCode(),
                    $location ?: 'sin Location',
                    (string) $user->active,
                    (string) $user->status
                ),
                status: $ok ? 'EXITOSA' : 'FALLIDA',
                evidence: ['route' => 'routes/web.php', 'controller' => 'app/Http/Controllers/Auth/LoginController.php']
            );

            $this->post('/salir');
        }

        $lab = $roles['laboratorio']->fresh();
        if (! $lab->active && $lab->status === 'active') {
            $this->record(
                role: 'laboratorio',
                module: 'Autenticación',
                function: 'Coherencia de banderas active/status',
                route: 'POST /login',
                data: ['email' => $lab->email, 'active' => $lab->active, 'status' => $lab->status],
                steps: ['Contrastar flags de usuario con el login exitoso'],
                expected: 'Una cuenta marcada como inactiva no debería autenticarse, o la bandera no debería existir.',
                actual: 'El login fue exitoso con `active=0`; solo se respeta `status`.',
                status: 'FALLIDA',
                evidence: ['controller' => 'app/Http/Controllers/Auth/LoginController.php', 'model' => 'app/Models/User.php'],
                cause: '`User::isActive()` ignora la columna `active`.',
                recommendation: 'Eliminar `active` o integrarla en la lógica de acceso.'
            );
        }
    }

    private function testAccessRestrictions(array $roles): void
    {
        $patient = $roles['paciente'];
        $doctor = $roles['doctor'];

        $patientResponse = $this->actingAs($patient)->get('/admin/dashboard');
        $doctorResponse = $this->actingAs($doctor)->get('/laboratorio/dashboard');

        $this->record(
            role: 'paciente',
            module: 'Autorización',
            function: 'Restricción de acceso al panel admin',
            route: 'GET /admin/dashboard',
            data: ['actor' => $patient->email],
            steps: ['Autenticar paciente', 'Solicitar panel admin'],
            expected: 'Redirección fuera del panel.',
            actual: sprintf('HTTP %s; Location=%s', $patientResponse->getStatusCode(), $patientResponse->headers->get('Location')),
            status: $patientResponse->getStatusCode() === 302 ? 'EXITOSA' : 'FALLIDA',
            evidence: ['middleware' => 'app/Http/Middleware/EnsureUserRole.php']
        );

        $this->record(
            role: 'doctor',
            module: 'Autorización',
            function: 'Restricción de acceso al panel laboratorio',
            route: 'GET /laboratorio/dashboard',
            data: ['actor' => $doctor->email],
            steps: ['Autenticar doctor', 'Solicitar panel laboratorio'],
            expected: 'Redirección fuera del panel.',
            actual: sprintf('HTTP %s; Location=%s', $doctorResponse->getStatusCode(), $doctorResponse->headers->get('Location')),
            status: $doctorResponse->getStatusCode() === 302 ? 'EXITOSA' : 'FALLIDA',
            evidence: ['middleware' => 'app/Http/Middleware/EnsureUserRole.php']
        );

        $this->post('/salir');
    }

    private function testSuperadminCrudAndGetWorkingAdmin(User $superadmin): User
    {
        $this->actingAs($superadmin);

        $this->from('/superadmin/admins/crear')->post('/superadmin/admins', []);
        $this->record(
            role: 'superadmin',
            module: 'Administradores',
            function: 'Validación de creación de administrador',
            route: 'POST /superadmin/admins',
            data: [],
            steps: ['Enviar formulario vacío'],
            expected: 'Errores de validación de campos requeridos.',
            actual: 'Errores detectados: '.implode(', ', $this->errorKeys()),
            status: ! empty($this->errorKeys()) ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Superadmin/AdminsController.php']
        );

        $payload = [
            'name' => 'Audit Admin',
            'email' => 'audit.admin@clinic.test',
            'password' => 'AdminAudit123*',
            'password_confirmation' => 'AdminAudit123*',
            'telefono' => '0991112233',
            'dni' => '0912345678',
            'direccion' => 'Calle QA 123',
            'fecha_nacimiento' => '1990-01-10',
            'sexo' => 'Masculino',
        ];

        $create = $this->post('/superadmin/admins', $payload);
        $admin = User::query()->where('email', $payload['email'])->firstOrFail();

        $this->record(
            role: 'superadmin',
            module: 'Administradores',
            function: 'Crear administrador',
            route: 'POST /superadmin/admins',
            data: ['email' => $payload['email'], 'dni' => $payload['dni']],
            steps: ['Enviar payload válido', 'Verificar users y role_user'],
            expected: 'Administrador persistido con rol administrador.',
            actual: sprintf('HTTP %s; admin_id=%s; role=%s', $create->getStatusCode(), $admin->id, $admin->roles()->value('name')),
            status: $admin->hasRole('administrador') ? 'EXITOSA' : 'FALLIDA',
            evidence: ['table' => 'users/role_user']
        );

        $this->put('/superadmin/admins/'.$admin->id, array_merge($payload, [
            'name' => 'Audit Admin Updated',
            'email' => 'audit.admin.updated@clinic.test',
            'password' => '',
            'password_confirmation' => '',
        ]));
        $admin->refresh();

        $this->record(
            role: 'superadmin',
            module: 'Administradores',
            function: 'Editar administrador',
            route: 'PUT /superadmin/admins/{admin}',
            data: ['admin_id' => $admin->id],
            steps: ['Actualizar nombre y correo', 'Recargar modelo'],
            expected: 'Datos actualizados.',
            actual: sprintf('name=%s; email=%s', $admin->name, $admin->email),
            status: $admin->email === 'audit.admin.updated@clinic.test' ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Superadmin/AdminsController.php']
        );

        $this->patch('/superadmin/admins/'.$admin->id.'/block', ['reason' => 'Prueba QA']);
        $admin->refresh();
        $blockedLogin = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'AdminAudit123*',
            'remember' => '1',
        ]);
        $this->record(
            role: 'administrador',
            module: 'Autenticación',
            function: 'Login denegado por bloqueo',
            route: 'POST /login',
            data: ['email' => $admin->email],
            steps: ['Bloquear admin temporal', 'Intentar login'],
            expected: 'Cuenta bloqueada no autenticable.',
            actual: sprintf('status=%s; login_http=%s', $admin->status, $blockedLogin->getStatusCode()),
            status: $admin->status === 'blocked' && $blockedLogin->getStatusCode() === 302 ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Superadmin/AdminsController.php', 'controller2' => 'app/Http/Controllers/Auth/LoginController.php']
        );

        $this->actingAs($superadmin)->patch('/superadmin/admins/'.$admin->id.'/activate');
        $this->actingAs($superadmin)->patch('/superadmin/admins/'.$admin->id.'/suspend', [
            'admin_id' => $admin->id,
            'until' => now()->addDay()->format('Y-m-d H:i:s'),
            'reason' => 'Suspensión QA',
        ]);
        $admin->refresh();
        $this->record(
            role: 'superadmin',
            module: 'Administradores',
            function: 'Suspender administrador',
            route: 'PATCH /superadmin/admins/{admin}/suspend',
            data: ['admin_id' => $admin->id],
            steps: ['Suspender', 'Verificar suspended_until'],
            expected: 'Cuenta suspendida temporalmente.',
            actual: 'suspended_until='.(string) optional($admin->suspended_until)->format('Y-m-d H:i:s'),
            status: ! is_null($admin->suspended_until) ? 'EXITOSA' : 'FALLIDA',
            evidence: ['table' => 'users']
        );

        $this->actingAs($superadmin)->patch('/superadmin/admins/'.$admin->id.'/activate');
        $this->actingAs($superadmin)->patch('/superadmin/admins/'.$admin->id.'/deactivate', ['reason' => 'QA']);
        $admin->refresh();
        $this->record(
            role: 'superadmin',
            module: 'Administradores',
            function: 'Desactivar administrador',
            route: 'PATCH /superadmin/admins/{admin}/deactivate',
            data: ['admin_id' => $admin->id],
            steps: ['Desactivar', 'Verificar status'],
            expected: 'status=inactive',
            actual: 'status='.$admin->status,
            status: $admin->status === 'inactive' ? 'EXITOSA' : 'FALLIDA',
            evidence: ['table' => 'users']
        );

        $this->actingAs($superadmin)->patch('/superadmin/admins/'.$admin->id.'/activate');
        $admin->refresh();

        return $admin;
    }

    private function testSuperadminGlobalViews(User $superadmin): void
    {
        $this->actingAs($superadmin);

        $dashboard = $this->get('/superadmin/dashboard');
        $users = $this->get('/superadmin/usuarios?role=doctor&buscar=doctor.qa');
        $admins = $this->get('/superadmin/admins?buscar=audit');
        $this->put('/superadmin/mantenimiento', [
            'maintenance_enabled' => '0',
            'maintenance_message' => 'Ventana QA',
            'maintenance_until' => now()->addDay()->toDateString(),
            'maintenance_allow_ips' => '127.0.0.1',
        ]);
        $settings = app(SiteSettingsService::class);

        $this->record(
            role: 'superadmin',
            module: 'Dashboard',
            function: 'Carga de dashboard y listados globales',
            route: 'GET /superadmin/dashboard, /superadmin/usuarios, /superadmin/admins',
            data: ['role_filter' => 'doctor', 'buscar' => 'doctor.qa'],
            steps: ['Abrir dashboard', 'Filtrar usuarios', 'Buscar admins'],
            expected: 'Respuestas 200.',
            actual: sprintf('dashboard=%s users=%s admins=%s', $dashboard->getStatusCode(), $users->getStatusCode(), $admins->getStatusCode()),
            status: $dashboard->getStatusCode() === 200 && $users->getStatusCode() === 200 && $admins->getStatusCode() === 200 ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Superadmin/DashboardController.php']
        );

        $this->record(
            role: 'superadmin',
            module: 'Mantenimiento',
            function: 'Guardar configuración de mantenimiento',
            route: 'PUT /superadmin/mantenimiento',
            data: ['message' => 'Ventana QA'],
            steps: ['Actualizar message y allow_ips', 'Consultar SiteSettingsService'],
            expected: 'Persistencia de maintenance.message.',
            actual: 'maintenance.message='.$settings->get('maintenance.message'),
            status: $settings->get('maintenance.message') === 'Ventana QA' ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Superadmin/MaintenanceController.php']
        );
    }

    private function testAdminFeatureAccessAndCrud(User $admin, User $superadmin, Especialidad $doctorSpecialty): void
    {
        $this->actingAs($admin);

        $dashboard = $this->get('/admin/dashboard');
        $resumen = $this->get('/admin/dashboard/resumen', ['X-Requested-With' => 'XMLHttpRequest']);
        $checkEmail = $this->get('/admin/usuarios/check-email?email='.$admin->email);

        $this->record(
            role: 'administrador',
            module: 'Dashboard',
            function: 'Carga de dashboard y resumen AJAX',
            route: 'GET /admin/dashboard, /admin/dashboard/resumen',
            data: [],
            steps: ['Abrir dashboard', 'Consultar resumen AJAX'],
            expected: 'Dashboard 200 y JSON operativo.',
            actual: sprintf('dashboard=%s resumen=%s', $dashboard->getStatusCode(), $resumen->getStatusCode()),
            status: $dashboard->getStatusCode() === 200 && $resumen->getStatusCode() === 200 ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Admin/AdminController.php']
        );

        $this->record(
            role: 'administrador',
            module: 'Usuarios',
            function: 'Check email con correo ocupado',
            route: 'GET /admin/usuarios/check-email',
            data: ['email' => $admin->email],
            steps: ['Consultar disponibilidad de correo existente'],
            expected: 'available=false',
            actual: json_encode($checkEmail->json(), JSON_UNESCAPED_UNICODE),
            status: $checkEmail->json('available') === false ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Admin/AdminController.php']
        );

        $blocked = $this->get('/admin/personalizacion/bienvenida');
        $this->post('/admin/personalizacion/solicitar', ['redirect_to' => '/admin/personalizacion/bienvenida']);
        $accessRequest = FeatureAccessRequest::query()
            ->where('user_id', $admin->id)
            ->where('feature', 'personalizacion')
            ->latest('id')
            ->firstOrFail();

        $this->record(
            role: 'administrador',
            module: 'Personalización',
            function: 'Solicitar acceso a personalización',
            route: 'GET/POST /admin/personalizacion*',
            data: ['admin_id' => $admin->id],
            steps: ['Intentar entrar sin permiso', 'Solicitar acceso'],
            expected: 'FeatureAccessRequest pending.',
            actual: sprintf('bloqueo=%s request_id=%s status=%s', $blocked->getStatusCode(), $accessRequest->id, $accessRequest->status),
            status: $accessRequest->status === 'pending' ? 'EXITOSA' : 'FALLIDA',
            evidence: ['middleware' => 'app/Http/Middleware/EnsureFeatureAccess.php', 'table' => 'feature_access_requests']
        );

        $this->actingAs($superadmin)->patch('/superadmin/solicitudes/personalizacion/'.$accessRequest->id.'/aprobar', [
            'req_id' => $accessRequest->id,
            'duration_hours' => 24,
            'no_expire' => '0',
        ]);
        $accessRequest->refresh();

        $this->actingAs($admin);
        $allowed = $this->get('/admin/personalizacion/bienvenida');
        $contactPayload = [
            'contact_info_badge' => 'QA',
            'contact_title' => 'Contacto QA',
            'contact_subtitle' => 'Subtitulo QA',
            'contact_address_label' => 'Direccion',
            'contact_address' => 'Av. QA 123',
            'contact_phone_label' => 'Telefono',
            'contact_phone' => '0999999999',
            'contact_hours_label' => 'Horario',
            'contact_hours' => 'Lun a Vie',
            'contact_map_title' => 'Mapa QA',
            'contact_map_embed' => 'https://maps.example.test/embed',
            'contact_form_section_badge' => 'Formulario',
            'contact_form_title' => 'Escribenos',
            'contact_form_badge' => 'QA',
            'contact_form_submit_text' => 'Enviar',
            'contact_form_name_label' => 'Nombre',
            'contact_form_email_label' => 'Correo',
            'contact_form_phone_label' => 'Telefono',
            'contact_form_subject_label' => 'Asunto',
            'contact_form_subject_placeholder' => 'Asunto QA',
            'contact_form_message_label' => 'Mensaje',
            'contact_form_message_placeholder' => 'Mensaje QA',
            'contact_form_message_help' => 'Ayuda QA',
        ];
        $this->put('/admin/personalizacion/contacto', $contactPayload);
        $settings = app(SiteSettingsService::class);

        $this->record(
            role: 'administrador',
            module: 'Personalización',
            function: 'Aprobar acceso y guardar contacto',
            route: 'PATCH /superadmin/solicitudes/personalizacion/{id}/aprobar + PUT /admin/personalizacion/contacto',
            data: ['request_id' => $accessRequest->id],
            steps: ['Aprobar solicitud', 'Abrir vista', 'Guardar sección contacto'],
            expected: 'Acceso aprobado y setting persistido.',
            actual: sprintf('status=%s vista=%s contact.title=%s', $accessRequest->status, $allowed->getStatusCode(), $settings->get('contact.title')),
            status: $accessRequest->status === 'approved' && $allowed->getStatusCode() === 200 && $settings->get('contact.title') === 'Contacto QA' ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Admin/PersonalizacionController.php']
        );

        $this->from('/admin/usuarios/crear')->post('/admin/usuarios', ['name' => '', 'email' => '']);
        $this->record(
            role: 'administrador',
            module: 'Usuarios',
            function: 'Validación de alta rápida',
            route: 'POST /admin/usuarios',
            data: [],
            steps: ['Enviar payload incompleto'],
            expected: 'Errores de validación.',
            actual: 'Errores detectados: '.implode(', ', $this->errorKeys()),
            status: ! empty($this->errorKeys()) ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Admin/AdminController.php']
        );

        $patientRoleId = Role::query()->where('name', 'paciente')->value('id');
        $doctorRoleId = Role::query()->where('name', 'doctor')->value('id');
        $payload = [
            'name' => 'Audit User',
            'email' => 'audit.user@clinic.test',
            'password' => 'AuditUser123*',
            'password_confirmation' => 'AuditUser123*',
            'telefono' => '0987654321',
            'dni' => '0934567890',
            'direccion' => 'Dirección Audit',
            'fecha_nacimiento' => '1995-02-14',
            'sexo' => 'Femenino',
            'role_id' => $patientRoleId,
            'adulto_mayor' => '0',
            'embarazo' => '0',
            'discapacidad' => '0',
            'cronico' => '0',
        ];
        $this->post('/admin/usuarios', $payload);
        $user = User::query()->where('email', $payload['email'])->firstOrFail();

        $this->record(
            role: 'administrador',
            module: 'Usuarios',
            function: 'Crear usuario paciente',
            route: 'POST /admin/usuarios',
            data: ['email' => $payload['email']],
            steps: ['Crear usuario', 'Verificar rol'],
            expected: 'Usuario persistido como paciente.',
            actual: sprintf('user_id=%s role=%s', $user->id, $user->roles()->value('name')),
            status: $user->hasRole('paciente') ? 'EXITOSA' : 'FALLIDA',
            evidence: ['table' => 'users/role_user']
        );

        $this->put('/admin/usuarios/'.$user->id, [
            'name' => 'Audit Doctor Converted',
            'email' => 'audit.user@clinic.test',
            'telefono' => '0987654321',
            'dni' => '0934567890',
            'direccion' => 'Dirección Editada',
            'fecha_nacimiento' => '1995-02-14',
            'sexo' => 'Femenino',
            'role_id' => $doctorRoleId,
            'especialidad_id' => $doctorSpecialty->id,
            'precio_consulta' => '35.50',
            'adulto_mayor' => '0',
            'embarazo' => '0',
            'discapacidad' => '0',
            'cronico' => '0',
        ]);
        $user->refresh();
        $user->load('roles', 'especialidades');

        $this->record(
            role: 'administrador',
            module: 'Usuarios',
            function: 'Editar usuario y cambiar rol a doctor',
            route: 'PUT /admin/usuarios/{user}',
            data: ['user_id' => $user->id],
            steps: ['Cambiar rol a doctor', 'Asignar especialidad y tarifa'],
            expected: 'Rol doctor y especialidad sincronizada.',
            actual: sprintf('role=%s especialidad=%s precio=%s', $user->roles->first()?->name, $user->especialidades->first()?->nombre, (string) $user->precio_consulta),
            status: $user->hasRole('doctor') && $user->especialidades->isNotEmpty() ? 'EXITOSA' : 'FALLIDA',
            evidence: ['table' => 'doctor_especialidad']
        );

        $this->patch('/admin/usuarios/'.$user->id.'/block', ['reason' => 'QA']);
        $this->patch('/admin/usuarios/'.$user->id.'/activate');
        $this->patch('/admin/usuarios/'.$user->id.'/deactivate', ['reason' => 'QA']);
        $this->patch('/admin/usuarios/'.$user->id.'/activate');
        $user->refresh();

        $this->record(
            role: 'administrador',
            module: 'Usuarios',
            function: 'Cambios de estado de usuario',
            route: 'PATCH /admin/usuarios/{user}/block|activate|deactivate',
            data: ['user_id' => $user->id],
            steps: ['Bloquear', 'Reactivar', 'Desactivar', 'Reactivar'],
            expected: 'Estado final active.',
            actual: 'status='.$user->status,
            status: $user->status === 'active' ? 'EXITOSA' : 'FALLIDA',
            evidence: ['route' => 'routes/web.php']
        );

        $this->delete('/admin/usuarios/'.$user->id);
        $exists = User::query()->whereKey($user->id)->exists();

        $this->record(
            role: 'administrador',
            module: 'Usuarios',
            function: 'Eliminar usuario',
            route: 'DELETE /admin/usuarios/{user}',
            data: ['user_id' => $user->id],
            steps: ['Eliminar usuario temporal', 'Verificar DB'],
            expected: 'Usuario eliminado.',
            actual: 'exists='.($exists ? 'true' : 'false'),
            status: ! $exists ? 'EXITOSA' : 'FALLIDA',
            evidence: ['table' => 'users']
        );
    }

    private function testAdminScheduleCrud(User $admin, User $doctor, Carbon $date, Carbon $date2): void
    {
        $this->actingAs($admin);

        $this->from('/admin/horarios/crear')->post('/admin/horarios', []);
        $this->record(
            role: 'administrador',
            module: 'Horarios',
            function: 'Validación de creación de horario',
            route: 'POST /admin/horarios',
            data: [],
            steps: ['Enviar payload vacío'],
            expected: 'Errores de validación.',
            actual: 'Errores detectados: '.implode(', ', $this->errorKeys()),
            status: ! empty($this->errorKeys()) ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Admin/HorarioController.php']
        );

        $this->post('/admin/horarios', [
            'doctor_id' => $doctor->id,
            'fecha_inicio' => $date->toDateString(),
            'fecha_fin' => $date->toDateString(),
            'dias' => [$date->isoWeekday()],
            'misma_franja' => '1',
            'hora_inicio' => '09:00',
            'hora_fin' => '12:00',
        ]);
        $created = Horario::query()->where('doctor_id', $doctor->id)->whereDate('fecha', $date->toDateString())->where('hora_inicio', '09:00')->firstOrFail();

        $this->post('/admin/horarios', [
            'doctor_id' => $doctor->id,
            'fecha_inicio' => $date2->toDateString(),
            'fecha_fin' => $date2->toDateString(),
            'dias' => [$date2->isoWeekday()],
            'misma_franja' => '1',
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
        ]);
        $extra = Horario::query()->where('doctor_id', $doctor->id)->whereDate('fecha', $date2->toDateString())->where('hora_inicio', '10:00')->firstOrFail();
        $this->put('/admin/horarios/'.$extra->id, [
            'doctor_id' => $doctor->id,
            'fecha' => $date2->toDateString(),
            'hora_inicio' => '11:00',
            'hora_fin' => '12:00',
        ]);
        $this->delete('/admin/horarios/'.$extra->id);

        $this->record(
            role: 'administrador',
            module: 'Horarios',
            function: 'Crear, editar y eliminar horario',
            route: 'POST/PUT/DELETE /admin/horarios',
            data: ['doctor_id' => $doctor->id],
            steps: ['Crear horario primario', 'Crear secundario', 'Editar secundario', 'Eliminar secundario'],
            expected: 'Horarios persistidos y CRUD operativo.',
            actual: sprintf('created=%s extra_deleted=%s', $created->id, Horario::query()->whereKey($extra->id)->exists() ? 'false' : 'true'),
            status: $created && ! Horario::query()->whereKey($extra->id)->exists() ? 'EXITOSA' : 'FALLIDA',
            evidence: ['table' => 'horarios']
        );
    }

    private function testPatientBookingFlows(User $patient, User $doctor, Especialidad $specialty, Carbon $date): array
    {
        $this->actingAs($patient);

        $dashboard = $this->get('/paciente/dashboard');
        $createPage = $this->get('/paciente/crear-cita');
        $labPage = $this->get('/paciente/laboratorio');

        $this->record(
            role: 'paciente',
            module: 'Dashboard',
            function: 'Carga de panel y módulos base',
            route: 'GET /paciente/dashboard, /paciente/crear-cita, /paciente/laboratorio',
            data: [],
            steps: ['Abrir dashboard', 'Abrir agenda', 'Abrir laboratorio'],
            expected: 'Vistas accesibles.',
            actual: sprintf('dashboard=%s crear=%s laboratorio=%s', $dashboard->getStatusCode(), $createPage->getStatusCode(), $labPage->getStatusCode()),
            status: $dashboard->getStatusCode() === 200 && $createPage->getStatusCode() === 200 && $labPage->getStatusCode() === 200 ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Paciente/AdminController.php']
        );

        $this->from('/paciente/crear-cita')->post('/paciente/crear-cita', []);
        $this->record(
            role: 'paciente',
            module: 'Citas médicas',
            function: 'Validación al crear cita',
            route: 'POST /paciente/crear-cita',
            data: [],
            steps: ['Enviar formulario vacío'],
            expected: 'Errores de validación.',
            actual: 'Errores detectados: '.implode(', ', $this->errorKeys()),
            status: ! empty($this->errorKeys()) ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/CitaController.php']
        );

        $this->post('/paciente/crear-cita', [
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => $date->toDateString(),
            'hora' => '09:00',
            'motivo_consulta' => 'Dolor de cabeza intenso',
        ]);
        $principal = Cita::query()->where('paciente_id', $patient->id)->whereDate('fecha', $date->toDateString())->where('hora', '09:00:00')->latest('id')->firstOrFail();

        $this->record(
            role: 'paciente',
            module: 'Citas médicas',
            function: 'Crear cita principal',
            route: 'POST /paciente/crear-cita',
            data: ['doctor_id' => $doctor->id, 'fecha' => $date->toDateString(), 'hora' => '09:00'],
            steps: ['Crear cita válida', 'Verificar DB'],
            expected: 'Cita pendiente persistida.',
            actual: sprintf('cita_id=%s estado=%s', $principal->id, $principal->estado),
            status: $principal->estado === 'pendiente' ? 'EXITOSA' : 'FALLIDA',
            evidence: ['table' => 'citas_medicas']
        );

        $this->from('/paciente/crear-cita')->post('/paciente/crear-cita', [
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => $date->toDateString(),
            'hora' => '09:00',
            'motivo_consulta' => 'Intento duplicado',
        ]);
        $this->record(
            role: 'paciente',
            module: 'Citas médicas',
            function: 'Detectar conflicto de horario',
            route: 'POST /paciente/crear-cita',
            data: ['fecha' => $date->toDateString(), 'hora' => '09:00'],
            steps: ['Intentar segunda cita en mismo slot'],
            expected: 'Mensaje de conflicto.',
            actual: 'Errores detectados: '.implode(', ', $this->errorKeys()),
            status: ! empty($this->errorKeys()) ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/CitaController.php']
        );

        $this->put('/paciente/editar-cita/'.$principal->id, [
            'fecha' => $date->toDateString(),
            'hora' => '09:30',
            'motivo_consulta' => 'Dolor reprogramado',
        ]);
        $principal->refresh();
        $this->record(
            role: 'paciente',
            module: 'Citas médicas',
            function: 'Reagendar cita',
            route: 'PUT /paciente/editar-cita/{id}',
            data: ['cita_id' => $principal->id],
            steps: ['Mover a 09:30', 'Recargar modelo'],
            expected: 'Hora actualizada.',
            actual: 'hora='.$principal->hora,
            status: $principal->hora === '09:30:00' ? 'EXITOSA' : 'FALLIDA',
            evidence: ['table' => 'citas_medicas']
        );

        $this->post('/paciente/crear-cita', [
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => $date->toDateString(),
            'hora' => '10:00',
            'motivo_consulta' => 'Cita para cancelar',
        ]);
        $cancel = Cita::query()->where('paciente_id', $patient->id)->whereDate('fecha', $date->toDateString())->where('hora', '10:00:00')->latest('id')->firstOrFail();
        $this->post('/paciente/citas/'.$cancel->id.'/cancelar');
        $cancel->refresh();
        $this->record(
            role: 'paciente',
            module: 'Citas médicas',
            function: 'Cancelar cita',
            route: 'POST /paciente/citas/{id}/cancelar',
            data: ['cita_id' => $cancel->id],
            steps: ['Crear cita secundaria', 'Cancelar'],
            expected: 'Estado cancelada.',
            actual: sprintf('estado=%s activo=%s', $cancel->estado, $cancel->activo ? '1' : '0'),
            status: $cancel->estado === 'cancelada' && ! $cancel->activo ? 'EXITOSA' : 'FALLIDA',
            evidence: ['table' => 'citas_medicas']
        );

        $this->post('/paciente/crear-cita', [
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => $date->toDateString(),
            'hora' => '10:30',
            'motivo_consulta' => 'Cita para rechazo doctor',
        ]);
        $reject = Cita::query()->where('paciente_id', $patient->id)->whereDate('fecha', $date->toDateString())->where('hora', '10:30:00')->latest('id')->firstOrFail();

        return ['principal' => $principal, 'cancelada' => $cancel, 'rechazo' => $reject];
    }

    private function testDoctorHorarioCrud(User $doctor, Carbon $date): Horario
    {
        $this->actingAs($doctor);

        $this->post('/doctor/horario', [
            'fecha' => $date->toDateString(),
            'hora_inicio' => '11:00',
            'hora_fin' => '13:00',
            'intervalo_minutos' => '30',
        ]);
        $primary = Horario::query()->where('doctor_id', $doctor->id)->whereDate('fecha', $date->toDateString())->where('hora_inicio', '11:00')->latest('id')->firstOrFail();

        $extraDate = $date->copy()->addDay();
        $this->post('/doctor/horario', [
            'fecha' => $extraDate->toDateString(),
            'hora_inicio' => '08:00',
            'hora_fin' => '09:00',
            'intervalo_minutos' => '30',
        ]);
        $extra = Horario::query()->where('doctor_id', $doctor->id)->whereDate('fecha', $extraDate->toDateString())->where('hora_inicio', '08:00')->latest('id')->firstOrFail();
        $this->put('/doctor/horario/'.$extra->id, [
            'fecha' => $extraDate->toDateString(),
            'hora_inicio' => '08:30',
            'hora_fin' => '09:30',
            'intervalo_minutos' => '30',
        ]);
        $this->delete('/doctor/horario/'.$extra->id);

        $this->record(
            role: 'doctor',
            module: 'Horario',
            function: 'CRUD de horario propio',
            route: 'POST/PUT/DELETE /doctor/horario',
            data: ['doctor_id' => $doctor->id],
            steps: ['Crear horario primario', 'Crear secundario', 'Editar secundario', 'Eliminar secundario'],
            expected: 'CRUD operativo.',
            actual: sprintf('primary=%s deleted_secondary=%s', $primary->id, Horario::query()->whereKey($extra->id)->exists() ? 'false' : 'true'),
            status: ! Horario::query()->whereKey($extra->id)->exists() ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Doctor/HorarioController.php']
        );

        return $primary;
    }

    private function testDoctorCoreFlows(User $doctor, array $appointments, User $patient, Horario $followupSchedule): void
    {
        $this->actingAs($doctor);

        $dashboard = $this->get('/doctor/dashboard');
        $data = $this->get('/doctor/dashboard/data');
        $agenda = $this->get('/doctor/agenda');
        $citas = $this->get('/doctor/citas?estado=pendiente');

        $this->record(
            role: 'doctor',
            module: 'Dashboard',
            function: 'Carga de dashboard y citas',
            route: 'GET /doctor/dashboard, /doctor/dashboard/data, /doctor/agenda, /doctor/citas',
            data: [],
            steps: ['Abrir panel', 'Consultar JSON', 'Abrir agenda y citas'],
            expected: 'Respuestas 200.',
            actual: sprintf('dashboard=%s data=%s agenda=%s citas=%s', $dashboard->getStatusCode(), $data->getStatusCode(), $agenda->getStatusCode(), $citas->getStatusCode()),
            status: $dashboard->getStatusCode() === 200 && $data->getStatusCode() === 200 && $agenda->getStatusCode() === 200 && $citas->getStatusCode() === 200 ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Doctor/AdminController.php']
        );

        $reject = $appointments['rechazo'];
        $this->post('/doctor/citas/'.$reject->id.'/rechazar');
        $reject->refresh();
        $this->record(
            role: 'doctor',
            module: 'Citas médicas',
            function: 'Rechazar cita pendiente',
            route: 'POST /doctor/citas/{id}/rechazar',
            data: ['cita_id' => $reject->id],
            steps: ['Rechazar cita de prueba'],
            expected: 'Estado cancelada.',
            actual: 'estado='.$reject->estado,
            status: $reject->estado === 'cancelada' ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/CitaController.php']
        );

        $principal = $appointments['principal']->fresh();
        $this->post('/doctor/citas/'.$principal->id.'/aceptar');
        $principal->refresh();
        $this->patch('/doctor/citas/'.$principal->id.'/prioridad', [
            'prioridad_nivel' => 'ALTA',
            'prioridad_comentario' => '',
        ]);
        $this->patch('/doctor/citas/'.$principal->id.'/prioridad', [
            'prioridad_nivel' => 'BAJA',
            'prioridad_comentario' => '',
        ]);
        $priorityErrors = $this->errorKeys();
        $this->patch('/doctor/citas/'.$principal->id.'/prioridad', [
            'prioridad_nivel' => 'BAJA',
            'prioridad_comentario' => 'QA reduce prioridad',
            'ignorar_red_flag' => '1',
        ]);
        $principal->refresh();

        $this->post('/doctor/citas/'.$principal->id.'/historial-clinico', [
            'subjetivo_motivo' => 'Cefalea persistente',
            'subjetivo_hpi' => 'Paciente refiere dolor de 3 días',
            'sv_ta' => '120/80',
        ]);
        $soap = $principal->notaSoap()->first();

        $this->post('/doctor/citas/'.$principal->id.'/historial-clinico/firmar', [
            'subjetivo_motivo' => 'Cefalea persistente',
            'subjetivo_hpi' => 'Dolor de 3 días',
            'subjetivo_ros' => 'Sin otros síntomas',
            'subjetivo_notas' => 'Sin alergias conocidas',
            'examen_fisico' => 'Normal',
            'notas_objetivas' => 'Paciente estable',
            'assessment' => 'Cefalea tensional',
            'plan_general' => 'Paracetamol',
            'plan_seguimiento' => 'Control',
            'plan_notas' => 'Hidratación',
            'sv_ta' => '120/80',
            'sv_fc' => '80',
            'sv_fr' => '18',
            'sv_temp' => '36.8',
            'sv_spo2' => '98',
            'sv_peso' => '70',
            'sv_talla' => '170',
            'diagnosticos' => [['tipo' => 'principal', 'texto' => '', 'cie10' => 'R51']],
        ]);
        $signErrors = $this->errorKeys();
        $this->post('/doctor/citas/'.$principal->id.'/historial-clinico/firmar', [
            'subjetivo_motivo' => 'Cefalea persistente',
            'subjetivo_hpi' => 'Dolor de 3 días, mejora parcial',
            'subjetivo_ros' => 'Sin fiebre ni vómitos',
            'subjetivo_notas' => 'Sin alergias',
            'examen_fisico' => 'Neurológico normal',
            'notas_objetivas' => 'Sin signos meníngeos',
            'assessment' => 'Cefalea tensional',
            'plan_general' => 'Paracetamol 500 mg',
            'plan_seguimiento' => 'Seguimiento en consulta',
            'plan_notas' => 'Reposo',
            'sv_ta' => '120/80',
            'sv_fc' => '80',
            'sv_fr' => '18',
            'sv_temp' => '36.8',
            'sv_spo2' => '98',
            'sv_peso' => '70',
            'sv_talla' => '170',
            'alergias_no_conocidas' => '1',
            'diagnosticos' => [['tipo' => 'principal', 'texto' => 'Cefalea tensional', 'cie10' => 'R51']],
        ]);
        $principal->refresh();
        $soap = $principal->notaSoap()->first();
        $this->post('/doctor/citas/'.$principal->id.'/realizar');
        $principal->refresh();
        $payment = $principal->pago()->first();

        $this->record(
            role: 'doctor',
            module: 'Consulta / Historial',
            function: 'Prioridad, SOAP y cierre de cita',
            route: 'PATCH /doctor/citas/{cita}/prioridad + POST /doctor/citas/{cita}/historial-clinico* + POST /doctor/citas/{id}/realizar',
            data: ['cita_id' => $principal->id],
            steps: ['Aceptar cita', 'Probar prioridad', 'Guardar borrador', 'Firmar SOAP', 'Realizar cita'],
            expected: 'SOAP firmada y pago generado.',
            actual: sprintf('priority_errors=%s sign_errors=%s soap=%s cita=%s pago_id=%s', implode(', ', $priorityErrors), implode(', ', $signErrors), $soap?->estado, $principal->estado, $payment?->id ?: 'null'),
            status: ! empty($priorityErrors) && ! empty($signErrors) && $soap && $soap->estado === 'signed' && $principal->estado === 'realizada' && (bool) $payment ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Doctor/SoapController.php', 'controller2' => 'app/Http/Controllers/CitaPrioridadController.php']
        );

        $recipeForm = $this->get('/doctor/recetas/crear/'.$principal->id);
        $response = $this->post('/doctor/recetas', [
            'cita_id' => $principal->id,
            'diagnostico' => 'Cefalea tensional',
            'medicamentos' => 'Paracetamol 500mg',
            'indicaciones' => 'Tomar con alimentos',
        ]);
        $this->assertTrue(
            $response->isSuccessful() || $response->isRedirect(),
            $response->exception?->getMessage() ?? 'La creacion de la receta devolvio una respuesta inesperada.'
        );

        $recipe = $principal->receta()->firstOrFail();
        $this->post('/doctor/recetas/actualizar', [
            'cita_id' => $principal->id,
            'diagnostico' => 'Cefalea tensional mejorando',
            'medicamentos' => 'Paracetamol 500mg cada 12h',
            'indicaciones' => 'Control si persiste',
            'regenerar_pdf' => '1',
            'reenviar' => '0',
        ]);
        $download = $this->get('/doctor/recetas/descargar/'.$principal->id);

        $this->record(
            role: 'doctor',
            module: 'Recetas',
            function: 'Crear, actualizar y descargar receta',
            route: 'GET/POST /doctor/recetas*',
            data: ['cita_id' => $principal->id],
            steps: ['Abrir formulario', 'Crear receta', 'Actualizarla', 'Descargar PDF'],
            expected: 'Receta persistida y PDF disponible.',
            actual: sprintf('form=%s receta_id=%s download=%s', $recipeForm->getStatusCode(), $recipe->id, $download->headers->get('content-type')),
            status: $recipe && str_contains((string) $download->headers->get('content-type'), 'application/pdf') ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Doctor/RecetaController.php']
        );

        $followup = $this->postJson('/doctor/citas/'.$principal->id.'/proxima/planificar', [
            'fecha' => Carbon::parse($followupSchedule->fecha)->toDateString(),
            'hora' => '11:00',
        ]);
        $next = Cita::query()->where('paciente_id', $patient->id)->where('doctor_id', $doctor->id)->whereDate('fecha', Carbon::parse($followupSchedule->fecha)->toDateString())->where('hora', '11:00:00')->latest('id')->firstOrFail();
        $history = $this->get('/doctor/pacientes/'.$patient->id.'/historial');

        $this->record(
            role: 'doctor',
            module: 'Consulta / Historial',
            function: 'Planificar próxima cita y ver historial del paciente',
            route: 'POST /doctor/citas/{cita}/proxima/planificar + GET /doctor/pacientes/{paciente}/historial',
            data: ['cita_id' => $principal->id, 'paciente_id' => $patient->id],
            steps: ['Crear cita de seguimiento', 'Abrir historial del paciente'],
            expected: 'Nueva cita pendiente e historial accesible.',
            actual: sprintf('followup=%s next_id=%s history=%s', $followup->getStatusCode(), $next->id, $history->getStatusCode()),
            status: $followup->getStatusCode() === 200 && $history->getStatusCode() === 200 ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/CitaController.php', 'controller2' => 'app/Http/Controllers/Doctor/HistorialController.php']
        );
    }

    private function testLaboratorioHorarioCrud(User $lab, Carbon $date, Carbon $date2): void
    {
        $this->actingAs($lab);

        $dashboard = $this->get('/laboratorio/dashboard');
        $this->post('/laboratorio/horario', ['fecha' => $date->toDateString(), 'hora_inicio' => '09:00', 'hora_fin' => '11:00', 'intervalo_minutos' => '30']);
        $primary = Horario::query()->where('doctor_id', $lab->id)->whereDate('fecha', $date->toDateString())->where('hora_inicio', '09:00')->latest('id')->firstOrFail();
        $this->post('/laboratorio/horario', ['fecha' => $date2->toDateString(), 'hora_inicio' => '08:00', 'hora_fin' => '09:00', 'intervalo_minutos' => '30']);
        $extra = Horario::query()->where('doctor_id', $lab->id)->whereDate('fecha', $date2->toDateString())->where('hora_inicio', '08:00')->latest('id')->firstOrFail();
        $this->put('/laboratorio/horario/'.$extra->id, ['fecha' => $date2->toDateString(), 'hora_inicio' => '08:30', 'hora_fin' => '09:30', 'intervalo_minutos' => '30']);
        $this->delete('/laboratorio/horario/'.$extra->id);

        $this->record(
            role: 'laboratorio',
            module: 'Horario',
            function: 'Dashboard y CRUD de horarios',
            route: 'GET /laboratorio/dashboard + POST/PUT/DELETE /laboratorio/horario',
            data: ['laboratorio_id' => $lab->id],
            steps: ['Abrir dashboard', 'Crear primario', 'Crear/editar/eliminar secundario'],
            expected: 'Panel operativo y CRUD funcional.',
            actual: sprintf('dashboard=%s primary=%s extra_deleted=%s', $dashboard->getStatusCode(), $primary->id, Horario::query()->whereKey($extra->id)->exists() ? 'false' : 'true'),
            status: $dashboard->getStatusCode() === 200 && ! Horario::query()->whereKey($extra->id)->exists() ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Laboratorio/HorarioController.php']
        );
    }

    private function testLaboratorioOrderFlow(User $lab, LaboratorioOrden $order): void
    {
        $this->actingAs($lab);
        $index = $this->get('/laboratorio/ordenes?estado=cita_programada');
        $this->post('/laboratorio/ordenes/'.$order->id.'/muestra');
        $order->refresh();
        $this->post('/laboratorio/ordenes/'.$order->id.'/resultado', ['resultado_resumen' => 'Valores dentro de rango']);
        $invalidErrors = $this->errorKeys();
        $this->post('/laboratorio/ordenes/'.$order->id.'/resultado', [
            'resultado_resumen' => 'Hemograma sin alteraciones',
            'resultado_pdf' => UploadedFile::fake()->create('resultado.pdf', 200, 'application/pdf'),
        ]);
        $order->refresh();
        $download = $this->get('/laboratorio/ordenes/'.$order->id.'/download');

        $this->record(
            role: 'laboratorio',
            module: 'Órdenes / Resultados',
            function: 'Filtrar, marcar muestra, subir resultado y descargar',
            route: 'GET/POST /laboratorio/ordenes*',
            data: ['orden_id' => $order->id],
            steps: ['Abrir index', 'Marcar muestra', 'Intentar upload inválido', 'Subir PDF', 'Descargar'],
            expected: 'Estado resultado_disponible y PDF descargable.',
            actual: sprintf('index=%s estado=%s invalid_errors=%s download=%s', $index->getStatusCode(), $order->estado, implode(', ', $invalidErrors), $download->headers->get('content-type')),
            status: $index->getStatusCode() === 200 && $order->estado === 'resultado_disponible' && str_contains((string) $download->headers->get('content-type'), 'application/pdf') ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Laboratorio/OrdenController.php']
        );
    }

    private function testPatientSeesLabResults(User $patient, LaboratorioOrden $order): void
    {
        $this->actingAs($patient);
        $index = $this->get('/paciente/laboratorio');
        $download = $this->get('/paciente/laboratorio/'.$order->id.'/descargar');

        $this->record(
            role: 'paciente',
            module: 'Laboratorio',
            function: 'Ver y descargar resultado publicado',
            route: 'GET /paciente/laboratorio + /paciente/laboratorio/{orden}/descargar',
            data: ['orden_id' => $order->id],
            steps: ['Abrir timeline', 'Descargar PDF'],
            expected: 'Resultado visible y descargable.',
            actual: sprintf('index=%s download=%s', $index->getStatusCode(), $download->headers->get('content-type')),
            status: $index->getStatusCode() === 200 && str_contains((string) $download->headers->get('content-type'), 'application/pdf') ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Paciente/LaboratorioController.php']
        );
    }

    private function testPaymentFlows(User $admin, User $patient, User $doctor, Cita $principal, Carbon $followupDate): array
    {
        $approvedPayment = $principal->fresh()->pago()->firstOrFail();

        $this->actingAs($patient);
        $index = $this->get('/paciente/pagos');
        $orderPdf = $this->get('/paciente/pagos/'.$approvedPayment->id.'/orden.pdf');
        $this->post('/paciente/pagos/'.$approvedPayment->id.'/enviar', [
            'metodo_pago' => 'transferencia',
            'referencia_transaccion' => 'QA-REF-001',
        ]);
        $invalidErrors = $this->errorKeys();
        $this->post('/paciente/pagos/'.$approvedPayment->id.'/enviar', [
            'metodo_pago' => 'transferencia',
            'referencia_transaccion' => 'QA-REF-001',
            'comprobante' => UploadedFile::fake()->create('comprobante.pdf', 200, 'application/pdf'),
        ]);
        $approvedPayment->refresh();

        $this->record(
            role: 'paciente',
            module: 'Pagos',
            function: 'Ver orden y enviar comprobante',
            route: 'GET /paciente/pagos, /paciente/pagos/{pago}/orden.pdf, POST /paciente/pagos/{pago}/enviar',
            data: ['pago_id' => $approvedPayment->id],
            steps: ['Abrir pagos', 'Abrir orden PDF', 'Enviar transferencia sin y con archivo'],
            expected: 'Sin archivo debe fallar; con archivo pasa a en_verificacion.',
            actual: sprintf('index=%s order=%s invalid_errors=%s estado=%s', $index->getStatusCode(), $orderPdf->headers->get('content-type'), implode(', ', $invalidErrors), $approvedPayment->estado),
            status: $index->getStatusCode() === 200 && ! empty($invalidErrors) && $approvedPayment->estado === 'en_verificacion' ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Paciente/PagoController.php']
        );

        $this->actingAs($admin);
        $adminIndex = $this->get('/admin/pagos?q='.$patient->email);
        $adminShow = $this->get('/admin/pagos/'.$approvedPayment->id);
        $comprobante = $this->get('/admin/pagos/'.$approvedPayment->id.'/comprobante');
        $this->post('/admin/pagos/'.$approvedPayment->id.'/aprobar', ['observacion_admin' => 'QA aprobado']);
        $approvedPayment->refresh();
        $receipt = $approvedPayment->receipt()->first();
        $recibo = $this->get('/admin/pagos/'.$approvedPayment->id.'/recibo.pdf');

        $this->record(
            role: 'administrador',
            module: 'Pagos',
            function: 'Aprobar pago y emitir recibo',
            route: 'GET /admin/pagos* + POST /admin/pagos/{pago}/aprobar + GET /admin/pagos/{pago}/recibo.pdf',
            data: ['pago_id' => $approvedPayment->id],
            steps: ['Buscar pago', 'Abrir detalle', 'Ver comprobante', 'Aprobar', 'Emitir recibo'],
            expected: 'Pago pagado y recibo generado.',
            actual: sprintf('index=%s show=%s comprobante=%s estado=%s recibo=%s', $adminIndex->getStatusCode(), $adminShow->getStatusCode(), $comprobante->getStatusCode(), $approvedPayment->estado, $recibo->headers->get('content-type')),
            status: $approvedPayment->estado === 'pagado' && $receipt && str_contains((string) $recibo->headers->get('content-type'), 'application/pdf') ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Admin/PagoController.php', 'service' => 'app/Services/PagoService.php']
        );

        $this->actingAs($patient);
        $patientReceipt = $this->get('/paciente/pagos/'.$approvedPayment->id.'/recibo.pdf');
        $this->record(
            role: 'paciente',
            module: 'Pagos',
            function: 'Descargar recibo aprobado',
            route: 'GET /paciente/pagos/{pago}/recibo.pdf',
            data: ['pago_id' => $approvedPayment->id],
            steps: ['Descargar recibo desde panel paciente'],
            expected: 'PDF accesible.',
            actual: 'content-type='.$patientReceipt->headers->get('content-type'),
            status: str_contains((string) $patientReceipt->headers->get('content-type'), 'application/pdf') ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Paciente/PagoController.php']
        );

        $this->actingAs($doctor);
        $followup = Cita::query()->where('paciente_id', $patient->id)->where('doctor_id', $doctor->id)->whereDate('fecha', $followupDate->toDateString())->where('hora', '11:00:00')->latest('id')->firstOrFail();
        $this->post('/doctor/citas/'.$followup->id.'/aceptar');
        $this->post('/doctor/citas/'.$followup->id.'/historial-clinico/firmar', [
            'subjetivo_motivo' => 'Seguimiento',
            'subjetivo_hpi' => 'Seguimiento favorable',
            'subjetivo_ros' => 'Sin síntomas de alarma',
            'subjetivo_notas' => 'Sin alergias',
            'examen_fisico' => 'Paciente estable',
            'notas_objetivas' => 'Control satisfactorio',
            'assessment' => 'Evolución favorable',
            'plan_general' => 'Continuar manejo',
            'plan_seguimiento' => 'Control en una semana',
            'plan_notas' => 'Mantener hidratación',
            'sv_ta' => '118/76',
            'sv_fc' => '74',
            'sv_fr' => '18',
            'sv_temp' => '36.6',
            'sv_spo2' => '99',
            'sv_peso' => '70',
            'sv_talla' => '170',
            'alergias_no_conocidas' => '1',
            'diagnosticos' => [['tipo' => 'principal', 'texto' => 'Control de cefalea', 'cie10' => 'R51']],
        ]);
        $this->post('/doctor/citas/'.$followup->id.'/realizar');
        $annulledPayment = $followup->fresh()->pago()->firstOrFail();

        $this->actingAs($patient);
        $this->post('/paciente/pagos/'.$annulledPayment->id.'/enviar', [
            'metodo_pago' => 'transferencia',
            'referencia_transaccion' => 'QA-REF-002',
            'comprobante' => UploadedFile::fake()->create('comprobante-2.pdf', 120, 'application/pdf'),
        ]);
        $annulledPayment->refresh();

        $this->actingAs($admin);
        $this->post('/admin/pagos/'.$annulledPayment->id.'/rechazar', ['observacion_admin' => 'QA rechazo']);
        $this->post('/admin/pagos/'.$annulledPayment->id.'/anular', ['observacion_admin' => 'QA anulación']);
        $annulledPayment->refresh();

        $this->record(
            role: 'administrador',
            module: 'Pagos',
            function: 'Rechazar y anular pago',
            route: 'POST /admin/pagos/{pago}/rechazar + /admin/pagos/{pago}/anular',
            data: ['pago_id' => $annulledPayment->id],
            steps: ['Crear segundo pago', 'Enviar comprobante', 'Rechazar', 'Anular'],
            expected: 'Estado final anulado.',
            actual: 'estado='.$annulledPayment->estado,
            status: $annulledPayment->estado === 'anulado' ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Admin/PagoController.php']
        );

        return ['approved' => $approvedPayment, 'annulled' => $annulledPayment];
    }

    private function testAdminAuditViews(User $admin, User $patient): void
    {
        $this->actingAs($admin);
        $changes = $this->get('/admin/cambios-citas');
        $historyIndex = $this->get('/admin/historial');
        $historyPatient = $this->get('/admin/historial/paciente/'.$patient->id);

        $this->record(
            role: 'administrador',
            module: 'Auditoría e historial',
            function: 'Ver cambios de citas e historial clínico',
            route: 'GET /admin/cambios-citas + /admin/historial*',
            data: ['paciente_id' => $patient->id],
            steps: ['Abrir auditoría', 'Abrir historial global', 'Abrir historial por paciente'],
            expected: 'Pantallas accesibles.',
            actual: sprintf('changes=%s historial=%s historial_paciente=%s', $changes->getStatusCode(), $historyIndex->getStatusCode(), $historyPatient->getStatusCode()),
            status: $changes->getStatusCode() === 200 && $historyIndex->getStatusCode() === 200 && $historyPatient->getStatusCode() === 200 ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Admin/CitaEventosController.php', 'controller2' => 'app/Http/Controllers/Admin/HistorialController.php']
        );
    }

    private function testSuperadminDeleteTempAdmin(User $superadmin, User $admin): void
    {
        $this->actingAs($superadmin);
        $this->delete('/superadmin/admins/'.$admin->id);
        $exists = User::query()->whereKey($admin->id)->exists();

        $this->record(
            role: 'superadmin',
            module: 'Administradores',
            function: 'Eliminar administrador temporal',
            route: 'DELETE /superadmin/admins/{admin}',
            data: ['admin_id' => $admin->id],
            steps: ['Eliminar admin temporal al final de la auditoría'],
            expected: 'Administrador removido.',
            actual: 'exists='.($exists ? 'true' : 'false'),
            status: ! $exists ? 'EXITOSA' : 'FALLIDA',
            evidence: ['controller' => 'app/Http/Controllers/Superadmin/AdminsController.php']
        );
    }

    private function writeReport(): void
    {
        $payload = [
            'contexto' => $this->context,
            'resultados' => $this->results,
        ];

        file_put_contents(
            base_path('tmp/audit-functional-report.json'),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
