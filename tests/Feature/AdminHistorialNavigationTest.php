<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\NotaSoap;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminHistorialNavigationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.mode' => 'demo']);
    }

    private function createHistorialFixture(): array
    {
        $adminRole = Role::firstOrCreate(['name' => 'administrador'], ['label' => 'Administrador']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor'], ['label' => 'Doctor']);
        $pacienteRole = Role::firstOrCreate(['name' => 'paciente'], ['label' => 'Paciente']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@demo-clinigest.test'],
            [
                'name' => 'Dra. Valeria Mendoza',
                'password' => Hash::make('Demo1234!'),
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => false,
                'email_verified_at' => now(),
            ]
        );
        $admin->update(['must_change_password' => false, 'status' => User::STATUS_ACTIVE]);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $doctor = User::firstOrCreate(
            ['email' => 'doctor.medicina@demo-clinigest.test'],
            [
                'name' => 'Dr. Fernando Alvarado',
                'password' => Hash::make('Demo1234!'),
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => false,
                'email_verified_at' => now(),
            ]
        );
        $doctor->update(['must_change_password' => false, 'status' => User::STATUS_ACTIVE]);
        $doctor->roles()->syncWithoutDetaching([$doctorRole->id]);

        $paciente = User::firstOrCreate(
            ['email' => 'paciente@demo-clinigest.test'],
            [
                'name' => 'Javier Espinoza',
                'password' => Hash::make('Demo1234!'),
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => false,
                'email_verified_at' => now(),
            ]
        );
        $paciente->update(['must_change_password' => false, 'status' => User::STATUS_ACTIVE]);
        $paciente->roles()->syncWithoutDetaching([$pacienteRole->id]);

        $especialidad = Especialidad::firstOrCreate(
            ['nombre' => 'Medicina General'],
            ['descripcion' => 'General', 'activo' => true]
        );
        $doctor->especialidades()->syncWithoutDetaching([$especialidad->id]);

        $cita = Cita::firstOrCreate(
            [
                'doctor_id' => $doctor->id,
                'paciente_id' => $paciente->id,
                'fecha' => now()->toDateString(),
                'hora' => '09:00:00',
            ],
            [
                'especialidad_id' => $especialidad->id,
                'motivo' => 'Control rutinario',
                'estado' => Cita::ESTADO_REALIZADA,
            ]
        );

        $clinicalRecord = \App\Models\ClinicalRecord::firstOrCreate(
            ['patient_id' => $paciente->id],
            [
                'allergies_status' => \App\Models\ClinicalRecord::ALLERGIES_NONE,
                'clinical_summary' => 'Paciente en seguimiento médico ambulatorio.',
                'last_reviewed_at' => now(),
            ]
        );

        $nota = NotaSoap::firstOrCreate(
            [
                'cita_id' => $cita->id,
            ],
            [
                'clinical_record_id' => $clinicalRecord->id,
                'doctor_id' => $doctor->id,
                'paciente_id' => $paciente->id,
                'subjetivo' => 'Paciente refiere sentirse bien',
                'objetivo' => 'Signos vitales estables',
                'analisis' => 'Evolución favorable',
                'plan' => 'Continuar tratamiento',
                'estado' => NotaSoap::ESTADO_FIRMADA,
                'firmada_por_id' => $doctor->id,
                'firmada_en' => now(),
            ]
        );
        $nota->update([
            'clinical_record_id' => $clinicalRecord->id,
            'estado' => NotaSoap::ESTADO_FIRMADA,
        ]);

        return [$admin->fresh(['roles']), $doctor->fresh(['roles']), $paciente->fresh(['roles']), $nota];
    }

    /**
     * TEST 1: Como administradora demo (Dra. Valeria Mendoza), al ver el expediente longitudinal del paciente,
     * las acciones "Abrir consulta", "Ver última nota" y "Ver nota" deben navegar a la ruta de nota firmada
     * del administrador (/admin/historial/nota/{nota}) y responder HTTP 200 sin redirigir a '/'.
     */
    public function test_admin_medical_record_actions_navigate_to_admin_note_view(): void
    {
        [$admin, $doctor, $paciente, $nota] = $this->createHistorialFixture();

        // 1. Administrador accede al expediente longitudinal del paciente a través de la nota
        $response = $this->actingAs($admin)->get(route('admin.historial.show', $nota->id));
        $response->assertStatus(200);
        $response->assertSee('Expediente clínico del paciente');

        // 2. Las acciones no deben apuntar a rutas de doctor (/doctor/...)
        $response->assertDontSee('/doctor/citas/'.$nota->cita_id.'/historial-clinico');

        // 3. Las acciones deben generar el enlace hacia la ruta de nota de admin: /admin/historial/nota/{id}
        $expectedNoteUrl = route('admin.historial.nota', $nota->id);
        $response->assertSee($expectedNoteUrl);

        // 4. Al solicitar directamente el enlace de la nota como admin, debe responder HTTP 200 (y no redirigir a '/')
        $noteResponse = $this->actingAs($admin)->get($expectedNoteUrl);
        $noteResponse->assertStatus(200);
        $noteResponse->assertSee('Nota clínica firmada');
        $noteResponse->assertDontSee('Redirecting to http://localhost');
    }

    /**
     * TEST 2: Como doctor demo (Dr. Fernando Alvarado), al ver el expediente longitudinal del paciente,
     * las acciones "Abrir consulta", "Ver última nota" y "Ver nota" deben mantener sus rutas de doctor (doctor.citas.soap)
     * y responder HTTP 200 sin apuntar a admin.historial.nota ni terminar en 403 o '/'.
     */
    public function test_doctor_medical_record_actions_retain_doctor_routes(): void
    {
        [$admin, $doctor, $paciente, $nota] = $this->createHistorialFixture();

        // 1. Doctor accede al historial del paciente
        $response = $this->actingAs($doctor)->get(route('doctor.pacientes.historial', $paciente->id));
        $response->assertStatus(200);
        $response->assertSee('Expediente clínico del paciente');

        // 2. Las acciones no deben apuntar a rutas de admin (/admin/historial/nota/...)
        $response->assertDontSee(route('admin.historial.nota', $nota->id));

        // 3. Las acciones deben generar el enlace hacia la ruta de doctor: /doctor/citas/{cita}/historial-clinico
        $expectedSoapUrl = route('doctor.citas.soap', $nota->cita_id);
        $response->assertSee($expectedSoapUrl);

        // 4. Al solicitar directamente la ruta como doctor, debe responder HTTP 200
        $soapResponse = $this->actingAs($doctor)->get($expectedSoapUrl);
        $soapResponse->assertStatus(200);
        $soapResponse->assertSee('Nota médica de consulta');
        $soapResponse->assertDontSee('Redirecting to http://localhost');
    }

    /**
     * TEST 3: Seguridad y Autorización Cruzada:
     * - Doctor NO puede acceder a admin.historial.nota (bloqueado por middleware de rol con redirección a /).
     * - Administrador NO puede acceder a doctor.citas.soap (bloqueado por middleware de rol con redirección a /).
     */
    public function test_cross_role_authorization_security(): void
    {
        [$admin, $doctor, $paciente, $nota] = $this->createHistorialFixture();

        // Doctor intentando acceder a la ruta de nota de admin -> Debe ser bloqueado
        $docToAdmin = $this->actingAs($doctor)->get(route('admin.historial.nota', $nota->id));
        $docToAdmin->assertRedirect('/');

        // Admin intentando acceder a la ruta de nota SOAP de doctor -> Debe ser bloqueado
        $adminToDoc = $this->actingAs($admin)->get(route('doctor.citas.soap', $nota->cita_id));
        $adminToDoc->assertRedirect('/');
    }
}
