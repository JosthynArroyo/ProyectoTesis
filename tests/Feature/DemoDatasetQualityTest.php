<?php

namespace Tests\Feature;

use App\Http\Controllers\DemoAccessController;
use App\Models\Cita;
use App\Models\ClinicalRecord;
use App\Models\ContactMessage;
use App\Models\DatabaseBackup;
use App\Models\Dependiente;
use App\Models\FeatureAccessRequest;
use App\Models\NotaSoap;
use App\Models\Pago;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use App\Models\Receta;
use App\Models\Role;
use App\Models\User;
use App\Services\PagoService;
use App\Services\ProfessionalScheduleService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoDatasetQualityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.mode' => 'demo']);
    }

    // =========================================================
    // GRUPO ESTÁTICO — sin DemoSeeder (no dependen de la DB demo)
    // =========================================================

    /**
     * INVARIANTE 2: DemoAccessController debe mapear los roles canónicos exclusivamente al dominio reservado.
     * — No necesita seed; verifica constante en código productivo.
     */
    public function test_demo_access_controller_resolves_canonical_accounts_with_reserved_domain(): void
    {
        $reflection = new \ReflectionClass(DemoAccessController::class);
        $demoRoles = $reflection->getConstant('DEMO_ROLES');

        $this->assertIsArray($demoRoles);
        foreach ($demoRoles as $roleKey => $config) {
            $email = $config['email'];
            $this->assertStringEndsWith(
                '@demo-clinigest.test',
                $email,
                "El rol [{$roleKey}] en DemoAccessController debe usar '@demo-clinigest.test', actual: [{$email}]."
            );
            $this->assertStringNotContainsString('@gmail.com', $email);
            $this->assertStringNotContainsString('@hotmail.com', $email);
            $this->assertStringNotContainsString('@clinica.demo', $email);
        }
    }

    /**
     * INVARIANTE 3: Ningún archivo seeder de demo contiene datos personales ni dominios comerciales entregables.
     * — No necesita seed; verifica contenido estático de archivos.
     */
    public function test_no_personal_or_developer_pii_in_demo_seeders(): void
    {
        $seederFiles = glob(database_path('seeders/Demo*.php'));

        foreach ($seederFiles as $file) {
            $content = file_get_contents($file);
            $basename = basename($file);

            if ($basename !== 'DemoClinicSeeder.php') {
                $this->assertStringNotContainsStringIgnoringCase('josthyn', $content, "El seeder [{$basename}] no debe contener referencias personales de usuarios.");
                $this->assertStringNotContainsStringIgnoringCase('arroyo', $content, "El seeder [{$basename}] no debe contener referencias personales de usuarios.");
            }

            $this->assertStringNotContainsString('@gmail.com', $content, "El seeder [{$basename}] no debe contener emails de gmail.");
            $this->assertStringNotContainsString('@hotmail.com', $content, "El seeder [{$basename}] no debe contener emails de hotmail.");
            $this->assertStringNotContainsString('@clinica.demo', $content, "El seeder [{$basename}] debe usar el dominio normalizado @demo-clinigest.test.");
        }
    }

    // =========================================================
    // GRUPO A — Estructura core del dataset, usuarios y login
    // INVARIANTES: 1, 4, 5, 14
    // SEEDER RUNS: 1 (reducido de 4)
    // =========================================================

    /**
     * Grupo A: estructura de usuarios canónicos, coherencia relacional, login de los 5 roles,
     * límite de 9 cuentas y ausencia de registros huérfanos.
     *
     * Invariantes cubiertas:
     *   [INV-1]  Las 5 cuentas canónicas demo usan @demo-clinigest.test
     *   [INV-4]  Coherencia relacional: médicos, paciente, SOAP, recetas, labs, pagos
     *   [INV-5]  Los 5 roles pueden autenticarse con sus dashboards
     *   [INV-14] Dataset limitado a 9 cuentas sin huérfanos
     */
    public function test_dataset_core_structure_users_and_login(): void
    {
        $this->seed(DemoSeeder::class);

        // [INV-1] Cuentas canónicas con dominio reservado
        $canonicalEmails = [
            'superadmin'    => 'superadmin@demo-clinigest.test',
            'administrador' => 'admin@demo-clinigest.test',
            'doctor'        => 'doctor.medicina@demo-clinigest.test',
            'paciente'      => 'paciente@demo-clinigest.test',
            'laboratorio'   => 'laboratorio@demo-clinigest.test',
        ];

        foreach ($canonicalEmails as $role => $expectedEmail) {
            $user = User::where('email', $expectedEmail)->first();
            $this->assertNotNull($user, "[INV-1] El usuario demo canónico para el rol [{$role}] con email [{$expectedEmail}] debe existir.");
            $this->assertStringEndsWith('@demo-clinigest.test', $user->email);
            $this->assertTrue($user->hasRole($role), "[INV-1] El usuario [{$expectedEmail}] debe poseer el rol [{$role}].");
        }

        // [INV-4] Coherencia relacional del dataset demo
        $doctors = User::whereHas('roles', fn ($q) => $q->where('name', 'doctor'))->get();
        $this->assertCount(3, $doctors, '[INV-4] El dataset demo debe contener exactamente 3 médicos con especialidades diferenciadas.');
        foreach ($doctors as $doctor) {
            $this->assertGreaterThan(0, $doctor->especialidades()->count(), "[INV-4] El doctor {$doctor->email} debe tener especialidades.");
            $this->assertGreaterThan(0, \App\Models\Horario::where('doctor_id', $doctor->id)->count(), "[INV-4] El doctor {$doctor->email} debe tener horarios.");
        }

        $paciente = User::where('email', 'paciente@demo-clinigest.test')->first();
        $this->assertNotNull($paciente, '[INV-4] Paciente canónico debe existir.');
        $this->assertGreaterThan(0, $paciente->dependientes()->count(), '[INV-4] Paciente canónico debe tener dependientes.');

        $completedCitas = Cita::where('estado', Cita::ESTADO_REALIZADA)->get();
        $this->assertGreaterThanOrEqual(9, $completedCitas->count(), '[INV-4] Deben existir al menos 9 citas realizadas.');
        $this->assertGreaterThanOrEqual(9, NotaSoap::count(), '[INV-4] Deben existir al menos 9 notas SOAP.');
        $this->assertGreaterThanOrEqual(5, Receta::count(), '[INV-4] Deben existir al menos 5 recetas.');
        $this->assertGreaterThanOrEqual(5, PedidoLaboratorio::count(), '[INV-4] Deben existir al menos 5 pedidos de laboratorio.');
        $this->assertGreaterThanOrEqual(15, Pago::count(), '[INV-4] Deben existir al menos 15 pagos.');

        // [INV-5] Los 5 roles pueden autenticarse
        $roles = [
            'superadmin'    => '/superadmin/dashboard',
            'administrador' => '/admin/dashboard',
            'doctor'        => '/doctor/dashboard',
            'paciente'      => '/paciente/dashboard',
            'laboratorio'   => '/laboratorio/dashboard',
        ];

        foreach ($roles as $role => $expectedRedirect) {
            $response = $this->get(route('demo.access.role', ['role' => $role]));
            $response->assertRedirect($expectedRedirect);
            $this->assertTrue(Auth::check(), "[INV-5] El rol [{$role}] debe estar autenticado.");
            $this->assertTrue(Auth::user()->hasRole($role), "[INV-5] El usuario autenticado debe poseer el rol [{$role}].");
        }

        // [INV-14] Exactamente 9 cuentas sin huérfanos
        $demoUsers = User::where('email', 'like', '%@demo-clinigest.test')->get();
        $this->assertCount(9, $demoUsers, '[INV-14] El dataset demo debe contener exactamente 9 cuentas principales.');

        $expectedEmails = [
            'superadmin@demo-clinigest.test',
            'admin@demo-clinigest.test',
            'laboratorio@demo-clinigest.test',
            'doctor.medicina@demo-clinigest.test',
            'doctor.pediatria@demo-clinigest.test',
            'doctor.ginecologia@demo-clinigest.test',
            'paciente@demo-clinigest.test',
            'paciente01@demo-clinigest.test',
            'paciente02@demo-clinigest.test',
        ];

        foreach ($expectedEmails as $email) {
            $this->assertTrue($demoUsers->contains('email', $email), "[INV-14] Debe existir la cuenta demo [{$email}].");
        }

        $orphanDependientes = Dependiente::whereNotIn('user_id', $demoUsers->pluck('id'))->count();
        $this->assertSame(0, $orphanDependientes, '[INV-14] No deben existir dependientes huérfanos en el dataset demo.');

        $orphanRecords = ClinicalRecord::whereNotIn('patient_id', $demoUsers->pluck('id'))->count();
        $this->assertSame(0, $orphanRecords, '[INV-14] No deben existir historias clínicas huérfanas en el dataset demo.');
    }

    // =========================================================
    // GRUPO B — Documentos clínicos: recetas y laboratorio
    // INVARIANTES: 9, 10
    // SEEDER RUNS: 1 (reducido de 2)
    // =========================================================

    /**
     * Grupo B: recetas médicas y pedidos de laboratorio visibles y descargables para el Doctor canónico.
     *
     * Invariantes cubiertas:
     *   [INV-9]  Recetas visibles y descargables por Doctor canónico
     *   [INV-10] Pedidos de laboratorio con resultados funcionales para Doctor canónico
     */
    public function test_dataset_clinical_documents_prescriptions_and_lab(): void
    {
        $this->seed(DemoSeeder::class);

        $doctor = User::where('email', 'doctor.medicina@demo-clinigest.test')->firstOrFail();

        // [INV-9] Recetas
        $recetas = \App\Models\Receta::whereHas('cita', fn ($q) => $q->where('doctor_id', $doctor->id))->get();
        $this->assertGreaterThanOrEqual(2, $recetas->count(), '[INV-9] El Dr. Fernando Alvarado debe tener al menos 2 recetas.');

        $recetaJavier = $recetas->first(fn ($r) => $r->cita->paciente->email === 'paciente@demo-clinigest.test');
        $this->assertNotNull($recetaJavier, '[INV-9] Debe existir una receta asociada al paciente Javier Espinoza.');

        $response = $this->actingAs($doctor)->get('/doctor/recetas');
        $response->assertStatus(200);
        $response->assertSee('Historial de recetas');
        $response->assertSee('Descargar');
        $response->assertSee('Javier Espinoza');
        $response->assertDontSee('Aún no hay recetas.');

        $downloadResponse = $this->actingAs($doctor)->get(route('doctor.recetas.download', $recetaJavier->cita_id));
        $downloadResponse->assertStatus(200);
        $this->assertTrue(str_contains($downloadResponse->headers->get('content-type'), 'application/pdf'), '[INV-9] Download de receta debe retornar PDF.');

        // [INV-10] Pedidos de laboratorio
        $pedidos = \App\Models\PedidoLaboratorio::with(['resultados', 'paciente'])
            ->where('doctor_id', $doctor->id)
            ->get();
        $this->assertGreaterThanOrEqual(1, $pedidos->count(), '[INV-10] El Dr. Fernando Alvarado debe tener al menos 1 pedido de laboratorio.');

        $pedidoPublicado = $pedidos->firstWhere('estado', \App\Models\PedidoLaboratorio::ESTADO_RESULTADO_LISTO);
        $this->assertNotNull($pedidoPublicado, '[INV-10] Debe existir un pedido en estado resultado_listo.');
        $this->assertNotEmpty($pedidoPublicado->pdf_path, '[INV-10] El pedido debe tener pdf_path configurado.');
        $this->assertNotEmpty($pedidoPublicado->resultado_path, '[INV-10] El pedido debe tener resultado_path configurado.');

        $resultado = $pedidoPublicado->resultados->firstWhere('estado', PedidoLaboratorioResultado::ESTADO_PUBLICADO);
        $this->assertNotNull($resultado, '[INV-10] Debe existir un resultado publicado asociado al pedido.');
        $this->assertNotEmpty($resultado->pdf_path, '[INV-10] El resultado debe tener pdf_path configurado.');

        $response = $this->actingAs($doctor)->get(route('doctor.pedidos-laboratorio.index'));
        $response->assertStatus(200);
        $response->assertSee('Pedidos de laboratorio');
        $response->assertSee('Resultados publicados');
        $response->assertSee('Ver informe');
        $response->assertSee('Descargar informe');
        $response->assertDontSee('El informe aún no está publicado.');

        $downloadResponse = $this->actingAs($doctor)->get(route('doctor.pedidos-laboratorio.resultado.download', [
            'pedido'      => $pedidoPublicado->id,
            'disposition' => 'attachment',
        ]));
        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('Content-Type', 'application/pdf');
    }

    // =========================================================
    // GRUPO C — Finanzas, horarios y disponibilidad de agendamiento
    // INVARIANTES: 11, 12, 13
    // SEEDER RUNS: 1 (reducido de 3)
    // =========================================================

    /**
     * Grupo C: historial financiero limpio del paciente, horarios de laboratorio activos
     * y disponibilidad médica futura completa para agendamiento.
     *
     * Invariantes cubiertas:
     *   [INV-11] Javier Espinoza sin bloqueo financiero y accede a agendar citas
     *   [INV-12] Carlos Morales tiene horarios activos y visibles en laboratorio
     *   [INV-13] Dr. Fernando Alvarado tiene disponibilidad médica futura
     */
    public function test_dataset_financial_schedules_and_booking_readiness(): void
    {
        $this->seed(DemoSeeder::class);

        $doctor  = User::where('email', 'doctor.medicina@demo-clinigest.test')->firstOrFail();
        $paciente = User::where('email', 'paciente@demo-clinigest.test')->firstOrFail();
        $labUser = User::where('email', 'laboratorio@demo-clinigest.test')->firstOrFail();

        // [INV-11] Historial financiero limpio de Javier Espinoza
        $pagos = Pago::where('paciente_id', $paciente->id)->get();
        $this->assertGreaterThanOrEqual(2, $pagos->count(), '[INV-11] Javier Espinoza debe tener al menos 2 pagos en su historial.');

        $pagoService = app(PagoService::class);
        $this->assertFalse(
            $pagoService->pacienteTieneBloqueo($paciente->id),
            '[INV-11] Javier Espinoza no debe tener bloqueo financiero activo en modo demo.'
        );

        $response = $this->actingAs($paciente)->get(route('paciente.crear-cita'));
        $response->assertStatus(200);
        $response->assertSee('Agendar');
        $response->assertDontSee(PagoService::MENSAJE_BLOQUEO);

        $otrosPagos = Pago::where('paciente_id', '!=', $paciente->id)->get();
        $this->assertGreaterThan(0, $otrosPagos->where('estado', Pago::ESTADO_EN_VERIFICACION)->count(), '[INV-11] Debe haber pagos en verificación.');
        $this->assertGreaterThan(0, $otrosPagos->where('estado', Pago::ESTADO_PENDIENTE)->count(), '[INV-11] Debe haber pagos pendientes.');
        $this->assertGreaterThan(0, $otrosPagos->where('estado', Pago::ESTADO_RECHAZADO)->count(), '[INV-11] Debe haber pagos rechazados.');

        // [INV-12] Horarios activos del usuario de laboratorio
        $horarios = \App\Models\Horario::where('doctor_id', $labUser->id)->get();
        $this->assertGreaterThanOrEqual(10, $horarios->count(), '[INV-12] Lic. Carlos Morales debe tener al menos 10 bloques de atención.');

        $startOfWeek = now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        $endOfWeek   = now()->endOfWeek(\Carbon\Carbon::SUNDAY)->toDateString();
        $currentWeekHorarios = $horarios->whereBetween('fecha', [$startOfWeek, $endOfWeek]);
        $this->assertGreaterThan(0, $currentWeekHorarios->count(), '[INV-12] Lic. Carlos Morales debe tener horarios en la semana actual.');

        $response = $this->actingAs($labUser)->get(route('laboratorio.horario.index'));
        $response->assertStatus(200);
        $response->assertSee('Mi horario');
        $response->assertSee('Recepción activa');
        $response->assertDontSee('Sin actividad semanal');
        $response->assertDontSee('Sin horarios en el rango.');

        // [INV-13] Disponibilidad médica futura
        $docHorarios = \App\Models\Horario::where('doctor_id', $doctor->id)->count();
        $this->assertGreaterThanOrEqual(100, $docHorarios, '[INV-13] Dr. Fernando Alvarado debe tener al menos 100 bloques de horario.');

        $targetDate = now()->addDays(30);
        while (! $targetDate->isWeekday()) {
            $targetDate->addDay();
        }
        $targetDateStr = $targetDate->format('Y-m-d');

        $scheduleService = app(ProfessionalScheduleService::class);
        $slots = $scheduleService->buildSlotsForDate($doctor->id, $targetDateStr);
        $this->assertNotEmpty($slots, '[INV-13] Dr. Fernando Alvarado debe tener slots disponibles en fecha futura.');

        $freeSlots = array_filter($slots, fn ($s) => ($s['estado'] ?? '') === 'libre');
        $this->assertNotEmpty($freeSlots, '[INV-13] Debe haber slots libres disponibles para agendamiento.');
    }

    // =========================================================
    // GRUPO D — Visibilidad administrativa y mensajes
    // INVARIANTES: 6, 7, 8
    // SEEDER RUNS: 1 (reducido de 3)
    // =========================================================

    /**
     * Grupo D: solicitudes de personalización, respaldos de base de datos y mensajes de contacto
     * visibles desde los paneles de administración canónicos.
     *
     * Invariantes cubiertas:
     *   [INV-6]  Solicitudes de personalización visibles en Superadmin
     *   [INV-7]  Registros de respaldos visibles en Superadmin
     *   [INV-8]  Mensajes de contacto visibles en Admin
     */
    public function test_dataset_admin_visibility_personalizacion_backups_and_messages(): void
    {
        $this->seed(DemoSeeder::class);

        $superadmin = User::where('email', 'superadmin@demo-clinigest.test')->firstOrFail();
        $admin      = User::where('email', 'admin@demo-clinigest.test')->firstOrFail();

        // [INV-6] Solicitudes de personalización
        $requestsCount = FeatureAccessRequest::forFeature('personalizacion')->count();
        $this->assertGreaterThanOrEqual(1, $requestsCount, '[INV-6] El dataset demo debe incluir solicitudes de personalización.');

        $pendingRequest = FeatureAccessRequest::forFeature('personalizacion')
            ->where('user_id', $admin->id)
            ->where('status', 'pending')
            ->first();
        $this->assertNotNull($pendingRequest, '[INV-6] Debe existir una solicitud de personalización pendiente asociada a la Administradora demo.');

        $response = $this->actingAs($superadmin)->get('/superadmin/solicitudes/personalizacion');
        $response->assertStatus(200);
        $response->assertSee('Dra. Valeria Mendoza');
        $response->assertSee('admin@demo-clinigest.test');
        $response->assertDontSee('No hay solicitudes registradas.');

        // [INV-7] Respaldos de base de datos
        $backupsCount = DatabaseBackup::count();
        $this->assertGreaterThanOrEqual(1, $backupsCount, '[INV-7] El dataset demo debe incluir registros de respaldos.');

        $recentVerified = DatabaseBackup::where('status', DatabaseBackup::STATUS_VERIFIED)->first();
        $this->assertNotNull($recentVerified, '[INV-7] Debe existir un respaldo verificado.');

        $response = $this->actingAs($superadmin)->get('/superadmin/respaldos');
        $response->assertStatus(200);
        $response->assertSee('Respaldos de base de datos');
        $response->assertSee('Al día');
        $response->assertSee('Manual');
        $response->assertSee('Daily');
        $response->assertDontSee('No se encontraron respaldos registrados.');

        // [INV-8] Mensajes de contacto
        $messagesCount = ContactMessage::count();
        $this->assertGreaterThanOrEqual(3, $messagesCount, '[INV-8] El dataset demo debe incluir al menos 3 mensajes de contacto.');
        $this->assertLessThanOrEqual(5, $messagesCount, '[INV-8] El dataset demo no debe exceder 5 mensajes de contacto.');

        $response = $this->actingAs($admin)->get('/admin/contacto/mensajes');
        $response->assertStatus(200);
        $response->assertSee('Mensajes de contacto');
        $response->assertSee('María Fernanda Cevallos');
        $response->assertDontSee('No hay mensajes de contacto registrados.');
    }
}
