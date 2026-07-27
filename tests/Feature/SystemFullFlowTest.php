<?php

namespace Tests\Feature;

use App\Mail\ResultadoLaboratorioMail;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\LaboratorioOrden;
use App\Models\NotaSoap;
use App\Models\Pago;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemFullFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-03-09 09:00:00', 'America/Guayaquil'));
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
        Queue::fake();

        config([
            'services.whatsapp.enabled' => false,
        ]);

        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_full_system_flow_across_panels_and_clinical_journey(): void
    {
        $this->get('/')->assertOk();
        $this->get('/servicios')->assertOk();
        $this->get('/contacto')->assertOk();
        $this->get('/login')->assertOk();

        $superadmin = User::query()->where('email', 'superadmin@clinic.test')->firstOrFail();
        $this->loginThroughForm($superadmin, 'superadmin1234');
        $this->assertPagesLoad([
            route('superadmin.dashboard'),
            route('superadmin.users.index'),
            route('superadmin.admins.index'),
            route('superadmin.admins.create'),
            route('superadmin.solicitudes.personalizacion.index'),
            route('superadmin.personalizacion.bienvenida.edit'),
            route('superadmin.personalizacion.servicios.edit'),
            route('superadmin.personalizacion.contacto.edit'),
            route('superadmin.maintenance.edit'),
        ]);

        $adminPayload = $this->superadminAdminPayload();
        $this->postForm(route('superadmin.admins.store'), $adminPayload)
            ->assertRedirect();

        $admin = User::query()->where('email', $adminPayload['email'])->firstOrFail();
        $this->assertTrue($admin->hasRole('administrador'));
        $this->assertTrue(Hash::check('admin1234*', $admin->password));
        $this->assertPagesLoad([
            route('superadmin.admins.edit', $admin),
            route('superadmin.users.index', ['role' => 'administrador']),
        ]);

        $this->logoutThroughForm();

        $this->loginThroughForm($admin, 'admin1234*');
        $this->assertPagesLoad([
            route('admin.dashboard'),
            route('admin.perfil.edit'),
            route('admin.usuarios.index'),
            route('admin.usuarios.create'),
            route('admin.historial.index'),
            route('admin.pagos.index'),
            route('admin.horarios.index'),
        ]);

        $doctorSpecialty = Especialidad::query()
            ->orderBy('id')
            ->get()
            ->first(fn (Especialidad $especialidad) => ! $especialidad->isLaboratorioClinico());
        $this->assertNotNull($doctorSpecialty);

        $patientPayload = $this->adminUserPayload('paciente', [
            'email' => 'paciente.panel@clinic.test',
            'telefono' => '0990000002',
            'dni' => '0912345675',
            'cronico' => '1',
        ]);
        $doctorPayload = $this->adminUserPayload('doctor', [
            'email' => 'doctor.panel@clinic.test',
            'telefono' => '0990000003',
            'dni' => '1721543285',
            'especialidad_id' => (string) $doctorSpecialty->id,
            'precio_consulta' => '35.50',
        ]);
        $labPayload = $this->adminUserPayload('laboratorio', [
            'email' => 'laboratorio.panel@clinic.test',
            'telefono' => '0990000004',
            'dni' => '1104328905',
            'precio_consulta' => '18.00',
        ]);

        $this->postForm(route('admin.usuarios.store'), $patientPayload)
            ->assertRedirect(route('admin.usuarios.index'));
        $this->postForm(route('admin.usuarios.store'), $doctorPayload)
            ->assertRedirect(route('admin.usuarios.index'));
        $this->postForm(route('admin.usuarios.store'), $labPayload)
            ->assertRedirect(route('admin.usuarios.index'));

        $patient = User::query()->where('email', $patientPayload['email'])->firstOrFail();
        $doctor = User::query()->where('email', $doctorPayload['email'])->firstOrFail();
        $lab = User::query()->where('email', $labPayload['email'])->firstOrFail();

        $this->assertTrue($patient->hasRole('paciente'));
        $this->assertTrue($doctor->hasRole('doctor'));
        $this->assertTrue($lab->hasRole('laboratorio'));
        $this->assertTrue(Hash::check('admin1234*', $patient->password));
        $this->assertTrue(Hash::check('admin1234*', $doctor->password));
        $this->assertTrue(Hash::check('admin1234*', $lab->password));
        $this->assertDatabaseHas('patient_flags', [
            'user_id' => $patient->id,
            'cronico' => true,
        ]);
        $this->assertDatabaseHas('doctor_especialidad', [
            'user_id' => $doctor->id,
            'especialidad_id' => $doctorSpecialty->id,
        ]);

        $labSpecialty = Especialidad::query()
            ->orderBy('id')
            ->get()
            ->first(fn (Especialidad $especialidad) => $especialidad->isLaboratorioClinico());
        $this->assertNotNull($labSpecialty);
        $this->assertDatabaseHas('doctor_especialidad', [
            'user_id' => $lab->id,
            'especialidad_id' => $labSpecialty->id,
        ]);

        $this->assertPagesLoad([
            route('admin.usuarios.show', $patient),
            route('admin.usuarios.edit', $patient),
            route('admin.usuarios.show', $doctor),
            route('admin.usuarios.edit', $doctor),
            route('admin.usuarios.show', $lab),
            route('admin.usuarios.edit', $lab),
        ]);

        $this->logoutThroughForm();

        $appointmentDate = now('America/Guayaquil')->addDays(2)->format('Y-m-d');
        $labDate = now('America/Guayaquil')->addDays(3)->format('Y-m-d');

        $this->loginThroughForm($doctor, 'admin1234*');
        $this->assertPagesLoad([
            route('doctor.dashboard'),
            route('doctor.citas'),
            route('doctor.agenda'),
            route('doctor.perfil.edit'),
            route('doctor.horario.index'),
        ]);

        $this->postForm(route('doctor.horario.store'), [
            'fecha' => $appointmentDate,
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
            'intervalo_minutos' => '30',
        ])->assertRedirect();

        $this->assertDatabaseHas('horarios', [
            'doctor_id' => $doctor->id,
            'fecha' => Carbon::parse($appointmentDate)->startOfDay()->format('Y-m-d H:i:s'),
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
            'intervalo_minutos' => 30,
        ]);

        $this->logoutThroughForm();

        $this->loginThroughForm($lab, 'admin1234*');
        $this->assertPagesLoad([
            route('laboratorio.dashboard'),
            route('laboratorio.ordenes.index'),
            route('laboratorio.horario.index'),
        ]);

        $this->postForm(route('laboratorio.horario.store'), [
            'fecha' => $labDate,
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
            'intervalo_minutos' => '30',
        ])->assertRedirect();

        $this->assertDatabaseHas('horarios', [
            'doctor_id' => $lab->id,
            'fecha' => Carbon::parse($labDate)->startOfDay()->format('Y-m-d H:i:s'),
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
            'intervalo_minutos' => 30,
        ]);

        $this->logoutThroughForm();

        $this->loginThroughForm($patient, 'admin1234*');
        $this->assertPagesLoad([
            route('paciente.dashboard'),
            route('paciente.perfil.edit'),
            route('paciente.citas'),
            route('paciente.crear-cita'),
            route('paciente.pagos.index'),
            route('paciente.historial'),
            route('paciente.laboratorio.index'),
        ]);

        $this->postForm(route('paciente.crear-cita.store'), [
            'doctor_id' => $doctor->id,
            'especialidad_id' => $doctorSpecialty->id,
            'fecha' => $appointmentDate,
            'hora' => '09:00',
            'motivo_consulta' => 'control de cronico',
        ])->assertRedirect(route('paciente.citas'));

        $cita = Cita::query()
            ->where('paciente_id', $patient->id)
            ->where('doctor_id', $doctor->id)
            ->whereDate('fecha', $appointmentDate)
            ->firstOrFail();

        $this->assertSame(Cita::ESTADO_PENDIENTE, $cita->estado);
        $this->assertSame(Cita::PRIORIDAD_MEDIA, $cita->prioridad_nivel);

        $this->assertDatabaseMissing('pagos', [
            'cita_id' => $cita->id,
        ]);

        $this->logoutThroughForm();

        $this->loginThroughForm($doctor, 'admin1234*');
        $this->postForm(route('doctor.citas.aceptar', $cita->id))
            ->assertRedirect();

        $this->get(route('doctor.citas.soap', $cita->id))->assertOk();

        $soapPayload = $this->soapPayload();
        $this->postForm(route('doctor.citas.soap.firmar', $cita->id), $soapPayload)
            ->assertRedirect(route('doctor.citas.soap', $cita->id));

        $nota = NotaSoap::query()->where('cita_id', $cita->id)->firstOrFail();
        $this->assertSame(NotaSoap::ESTADO_FIRMADA, $nota->estado);

        $this->postForm(route('doctor.citas.realizar', $cita->id))
            ->assertRedirect();

        $this->assertPagesLoad([
            route('doctor.citas'),
            route('doctor.pacientes.historial', $patient),
        ]);

        $labCita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $lab->id,
            'especialidad_id' => $labSpecialty->id,
            'fecha' => $labDate,
            'hora' => '10:00:00',
            'motivo_consulta' => 'Examen de laboratorio',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        $labOrder = LaboratorioOrden::create([
            'cita_id' => $labCita->id,
            'clinical_record_id' => null,
            'solicitante_id' => $lab->id,
            'origen' => 'doctor',
            'prioridad' => 'normal',
            'tipo_examen' => 'Hemograma completo',
            'indicaciones' => 'Traer orden y documento.',
            'preparacion' => 'Ayuno de 8 horas.',
            'estado' => LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
        ]);

        $this->logoutThroughForm();

        $pago = Pago::query()->where('cita_id', $cita->id)->firstOrFail();
        $this->assertTrue($pago->tieneOrdenCobro());

        $this->loginThroughForm($patient, 'admin1234*');
        $this->assertPagesLoad([
            route('paciente.historial'),
            route('paciente.historial.show', $nota),
            route('paciente.pagos.index'),
        ]);

        $this->get(route('paciente.pagos.orden.pdf', $pago))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->postForm(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'referencia_transaccion' => 'TRX-0001',
            'comprobante' => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('paciente.pagos.index'));

        $pago = $pago->fresh();
        $this->assertSame(Pago::ESTADO_EN_VERIFICACION, $pago->estado);
        $this->assertSame(Pago::METODO_TRANSFERENCIA, $pago->metodo_pago);

        $this->get(route('paciente.pagos.comprobante', $pago))->assertOk();
        $this->logoutThroughForm();

        $this->loginThroughForm($admin, 'admin1234*');
        $this->assertPagesLoad([
            route('admin.historial.index'),
            route('admin.historial.paciente', $patient),
            route('admin.historial.show', $nota),
            route('admin.pagos.index'),
            route('admin.pagos.show', $pago),
        ]);

        $this->postForm(route('admin.pagos.aprobar', $pago), [
            'observacion_admin' => 'Pago validado.',
        ])->assertRedirect();

        $pago = $pago->fresh();
        $this->assertSame(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertNotNull($pago->receipt);

        $this->get(route('admin.pagos.recibo.pdf', $pago))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->logoutThroughForm();

        $this->loginThroughForm($doctor, 'admin1234*');
        $this->assertPagesLoad([
            route('doctor.pedidos-laboratorio.create', $cita),
        ]);

        $this->logoutThroughForm();

        $this->loginThroughForm($lab, 'admin1234*');
        $this->assertPagesLoad([
            route('laboratorio.dashboard'),
            route('laboratorio.ordenes.index'),
        ]);

        $this->postForm(route('laboratorio.ordenes.muestra', $labOrder))
            ->assertRedirect();

        $this->postForm(route('laboratorio.ordenes.resultado', $labOrder), [
            'resultado_pdf' => UploadedFile::fake()->create('resultado.pdf', 120, 'application/pdf'),
            'resultado_resumen' => 'Resultados dentro de rangos esperados.',
        ])->assertRedirect();

        $labOrder = $labOrder->fresh();
        $this->assertSame(LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE, $labOrder->estado);
        Mail::assertSent(ResultadoLaboratorioMail::class);

        $this->get(route('laboratorio.ordenes.download', $labOrder))->assertOk();
        $this->logoutThroughForm();

        $this->loginThroughForm($patient, 'admin1234*');
        $this->assertPagesLoad([
            route('paciente.laboratorio.index'),
            route('paciente.pagos.index'),
            route('paciente.historial'),
        ]);
        $this->get(route('paciente.laboratorio.download', $labOrder))->assertOk();

        $this->logoutThroughForm();
    }

    private function assertPagesLoad(array $urls): void
    {
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    private function loginThroughForm(User $user, string $password): void
    {
        $this->postForm('/login', [
            'email' => $user->email,
            'password' => $password,
            'remember' => '0',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    private function logoutThroughForm(): void
    {
        $this->get(route('salir.get'))->assertRedirect('/');
        $this->assertGuest();
    }

    private function postForm(string $url, array $data = [])
    {
        $token = 'test-csrf-token';

        return $this->withSession(['_token' => $token])
            ->post($url, array_merge(['_token' => $token], $data));
    }

    private function superadminAdminPayload(): array
    {
        return [
            'name' => 'Administrador Panel',
            'email' => 'admin.panel@clinic.test',
            'password' => 'admin1234*',
            'password_confirmation' => 'admin1234*',
            'telefono' => '0990000001',
            'dni' => '0923456789',
            'direccion' => 'Calle Admin 123',
            'fecha_nacimiento' => '1990-01-10',
            'sexo' => 'Masculino',
        ];
    }

    private function adminUserPayload(string $roleName, array $overrides = []): array
    {
        $roleId = Role::query()->where('name', $roleName)->value('id');

        $payload = [
            'name' => ucfirst($roleName).' Demo',
            'email' => $roleName.'.demo@clinic.test',
            'password' => 'admin1234*',
            'password_confirmation' => 'admin1234*',
            'telefono' => '0990000099',
            'dni' => '1712345675',
            'direccion' => 'Av. Principal 100',
            'fecha_nacimiento' => '1992-06-15',
            'sexo' => 'Femenino',
            'role_id' => (string) $roleId,
            'adulto_mayor' => '0',
            'embarazo' => '0',
            'discapacidad' => '0',
            'cronico' => '0',
        ];

        return array_merge($payload, $overrides);
    }

    private function soapPayload(): array
    {
        return [
            'subjetivo_motivo' => 'Dolor controlado y seguimiento general.',
            'subjetivo_hpi' => 'Paciente con antecedente cronico en seguimiento estable.',
            'subjetivo_ros' => 'Sin sintomas respiratorios ni digestivos.',
            'subjetivo_notas' => 'Sin alergias conocidas.',
            'examen_fisico' => 'Paciente orientado, sin hallazgos agudos.',
            'notas_objetivas' => 'Signos vitales dentro de parametros aceptables.',
            'assessment' => 'Paciente estable con necesidad de control.',
            'plan_general' => 'Mantener tratamiento actual.',
            'plan_seguimiento' => 'Control en dos semanas.',
            'plan_notas' => 'Vigilar signos de alarma.',
            'sv_ta' => '120/80',
            'sv_fc' => '72',
            'sv_fr' => '18',
            'sv_temp' => '36.7',
            'sv_spo2' => '98',
            'sv_peso' => '70',
            'sv_talla' => '170',
            'diagnosticos' => [
                [
                    'tipo' => 'principal',
                    'texto' => 'Control de enfermedad cronica',
                    'cie10' => 'Z09',
                ],
            ],
        ];
    }
}
