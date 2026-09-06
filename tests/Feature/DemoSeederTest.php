<?php

namespace Tests\Feature;

use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\ClinicalRecord;
use App\Models\Dependiente;
use App\Models\Horario;
use App\Models\NotaSoap;
use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use App\Models\Receta;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_demo_seeder_throws_exception_when_app_mode_is_production(): void
    {
        config(['app.mode' => 'production']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DemoSeeder solo puede ejecutarse cuando APP_MODE=demo');

        $seeder = new DemoSeeder;
        $seeder->run();
    }

    public function test_production_seeder_in_production_mode_does_not_create_demo_users_or_citas(): void
    {
        config(['app.mode' => 'production']);

        $seeder = new ProductionSeeder;
        $seeder->run();

        $this->assertDatabaseMissing('users', ['email' => 'superadmin@demo-clinigest.test']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@demo-clinigest.test']);
        $this->assertDatabaseMissing('users', ['email' => 'paciente@demo-clinigest.test']);
        $this->assertDatabaseMissing('citas_medicas', ['motivo_consulta' => 'Control de hipertensión arterial y ajuste de tratamiento.']);
    }

    public function test_demo_seeder_populates_complete_coherent_dataset_in_demo_mode(): void
    {
        config(['app.mode' => 'demo']);

        $seeder = new DemoSeeder;
        $seeder->run();

        // 1. Verificar identidad de clínica demo
        $this->assertSame('Clínica Josthyn Arroyo', SiteSetting::where('key', 'branding.name')->value('value'));
        $this->assertSame('contacto@demo-clinigest.test', SiteSetting::where('key', 'contact.email')->value('value'));

        // 2. Verificar usuarios de los 5 roles
        $superadmin = User::where('email', 'superadmin@demo-clinigest.test')->first();
        $admin = User::where('email', 'admin@demo-clinigest.test')->first();
        $lab = User::where('email', 'laboratorio@demo-clinigest.test')->first();
        $pacienteDemo = User::where('email', 'paciente@demo-clinigest.test')->first();

        $this->assertNotNull($superadmin, 'Superadmin demo debe existir');
        $this->assertTrue($superadmin->hasRole('superadmin'));

        $this->assertNotNull($admin, 'Admin demo debe existir');
        $this->assertTrue($admin->hasRole('administrador'));

        $this->assertNotNull($lab, 'Laboratorio demo debe existir');
        $this->assertTrue($lab->hasRole('laboratorio'));

        $this->assertNotNull($pacienteDemo, 'Paciente demo debe existir');
        $this->assertTrue($pacienteDemo->hasRole('paciente'));

        // 3. Verificar dependientes del paciente demo
        $this->assertGreaterThanOrEqual(2, Dependiente::where('user_id', $pacienteDemo->id)->count());

        // 4. Verificar médicos (exactamente 3) con especialidades y horarios
        $doctors = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'doctor'))->get();
        $this->assertCount(3, $doctors, 'El dataset demo debe poseer exactamente 3 doctores.');

        foreach ($doctors as $doc) {
            $this->assertGreaterThanOrEqual(1, $doc->especialidades()->count());
            $this->assertGreaterThanOrEqual(10, Horario::where('doctor_id', $doc->id)->count());
        }

        // 5. Verificar pacientes (exactamente 3)
        $patientsCount = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'paciente'))->count();
        $this->assertSame(3, $patientsCount, 'El dataset demo debe poseer exactamente 3 pacientes.');

        // 6. Verificar citas distribuidas (al menos 15)
        $citasCount = Cita::count();
        $this->assertGreaterThanOrEqual(15, $citasCount);

        // Estados representados
        $this->assertGreaterThanOrEqual(1, Cita::where('estado', Cita::ESTADO_REALIZADA)->count());
        $this->assertGreaterThanOrEqual(1, Cita::where('estado', Cita::ESTADO_CONFIRMADA)->count());
        $this->assertGreaterThanOrEqual(1, Cita::where('estado', Cita::ESTADO_PENDIENTE)->count());
        $this->assertGreaterThanOrEqual(1, Cita::where('estado', Cita::ESTADO_CANCELADA)->count());
        $this->assertGreaterThanOrEqual(1, Cita::where('estado', Cita::ESTADO_NO_SE_PRESENTO)->count());

        // Cita futura
        $this->assertTrue(Cita::where('fecha', '>', now()->format('Y-m-d'))->exists());

        // 7. Verificar historias clínicas, notas SOAP y diagnósticos
        $this->assertGreaterThanOrEqual(3, ClinicalRecord::count());
        $this->assertGreaterThanOrEqual(9, NotaSoap::where('estado', NotaSoap::ESTADO_FIRMADA)->count());

        // 8. Verificar recetas y certificados médicos
        $this->assertGreaterThanOrEqual(5, Receta::count());
        $this->assertGreaterThanOrEqual(2, CertificadoMedico::where('estado_version', CertificadoMedico::ESTADO_VIGENTE)->count());

        // 9. Verificar órdenes y resultados de laboratorio
        $this->assertGreaterThanOrEqual(8, PedidoLaboratorio::count());
        $this->assertGreaterThanOrEqual(5, PedidoLaboratorioResultado::where('estado', PedidoLaboratorioResultado::ESTADO_PUBLICADO)->count());

        // 10. Verificar cobros y recibos
        $this->assertGreaterThanOrEqual(10, Pago::where('estado', Pago::ESTADO_PAGADO)->count());
        $this->assertGreaterThanOrEqual(1, Pago::where('estado', Pago::ESTADO_EN_VERIFICACION)->count());
        $this->assertGreaterThanOrEqual(1, Pago::where('estado', Pago::ESTADO_PENDIENTE)->count());
        $this->assertGreaterThanOrEqual(10, PaymentReceipt::count());

        // 11. Integridad relacional (sin huérfanos)
        $this->assertFalse(Cita::whereDoesntHave('doctor')->exists());
        $this->assertFalse(Cita::whereDoesntHave('paciente')->exists());
        $this->assertFalse(NotaSoap::whereDoesntHave('cita')->exists());
        $this->assertFalse(Pago::whereDoesntHave('cita')->exists());
    }

    public function test_real_dashboards_query_seeded_dataset_successfully(): void
    {
        config(['app.mode' => 'demo']);

        $seeder = new DemoSeeder;
        $seeder->run();

        $admin = User::where('email', 'admin@demo-clinigest.test')->first();
        $doctor = User::where('email', 'doctor.medicina@demo-clinigest.test')->first();
        $paciente = User::where('email', 'paciente@demo-clinigest.test')->first();
        $lab = User::where('email', 'laboratorio@demo-clinigest.test')->first();

        // 1. Admin dashboard
        $adminResponse = $this->actingAs($admin)->get('/admin/dashboard');
        $adminResponse->assertStatus(200);

        // 2. Doctor dashboard
        $doctorResponse = $this->actingAs($doctor)->get('/doctor/dashboard');
        $doctorResponse->assertStatus(200);

        // 3. Paciente dashboard
        $pacienteResponse = $this->actingAs($paciente)->get('/paciente/dashboard');
        $pacienteResponse->assertStatus(200);

        // 4. Laboratorio dashboard
        $labResponse = $this->actingAs($lab)->get('/laboratorio/dashboard');
        $labResponse->assertStatus(200);
    }
}
