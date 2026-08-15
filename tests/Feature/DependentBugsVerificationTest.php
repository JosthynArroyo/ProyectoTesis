<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\ClinicalRecord;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\NotaSoap;
use App\Models\NotaSoapDiagnostico;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DependentBugsVerificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dependent_bugs_resolution_flow(): void
    {
        $this->seed(DatabaseSeeder::class);

        $doctorRole = Role::query()->where('name', 'doctor')->firstOrFail();
        $patientRole = Role::query()->where('name', 'paciente')->firstOrFail();
        $specialty = Especialidad::query()->orderBy('id')->firstOrFail();

        // 1. Crear titular y dependiente
        $titular = User::factory()->create([
            'name' => 'Josthyn Arroyo',
            'email' => 'josthyn@example.com',
            'dni' => '0999999999',
            'fecha_nacimiento' => '1995-05-15',
            'sexo' => 'Masculino',
        ]);
        $titular->roles()->attach($patientRole);

        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'nombre' => 'Anabel Arroyo',
            'dni' => '0988888888',
            'fecha_nacimiento' => '2015-08-20', // Menor de edad (< 18 años)
            'sexo' => 'Femenino',
            'parentesco' => 'hija',
            'activo' => true,
        ]);

        $doctor = User::factory()->create([
            'name' => 'Dr. House',
        ]);
        $doctor->roles()->attach($doctorRole);

        // 2. Crear cita para el dependiente
        $cita = Cita::create([
            'paciente_id' => $titular->id,
            'dependiente_id' => $dependiente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => now()->addDays(2)->format('Y-m-d'),
            'hora' => '10:00:00',
            'motivo_consulta' => 'Fiebre persistente',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
        ]);

        // BUG 1 VERIFICACIÓN: Panel del Doctor (Mis Citas)
        // Debe mostrar el nombre del dependiente ('Anabel Arroyo') en lugar del titular ('Josthyn Arroyo')
        $this->actingAs($doctor);
        $response = $this->get(route('doctor.citas'));
        $response->assertOk();
        $response->assertSee('Anabel Arroyo');
        $response->assertDontSee('Josthyn Arroyo</td>'); // Verificamos que en la columna paciente no sale el titular

        // BUG 2 VERIFICACIÓN: Expediente Médico y Perfil Clínico
        // Debe cargar e inicializar el ClinicalRecord del dependiente de forma aislada
        $responseHistorial = $this->get(route('doctor.pacientes.historial', [
            'paciente' => $titular->id,
            'dependiente_id' => $dependiente->id,
        ]));
        $responseHistorial->assertOk();
        $responseHistorial->assertSee('Anabel Arroyo');
        
        // Verificar que se creó el expediente para el dependiente y no para el titular
        $this->assertDatabaseHas('clinical_records', [
            'dependiente_id' => $dependiente->id,
            'patient_id' => null,
        ]);
        $this->assertDatabaseMissing('clinical_records', [
            'patient_id' => $titular->id,
        ]);

        // Probar actualizar los datos maestros del dependiente
        $updatePayload = [
            'allergies_status' => 'documented',
            'clinical_summary' => 'Paciente infantil con alergia a la penicilina.',
            'allergies' => [
                ['allergen' => 'Penicilina', 'reaction' => 'Urticaria', 'severity' => 'severe', 'status' => 'active'],
            ],
            'personal_histories' => [],
            'family_histories' => [],
            'surgeries' => [],
            'hospitalizations' => [],
            'immunizations' => [],
            'problems' => [],
            'medications' => [],
            'alerts' => [],
        ];

        $responseUpdate = $this->put(route('doctor.pacientes.historial.update', [
            'paciente' => $titular->id,
            'dependiente_id' => $dependiente->id,
        ]), $updatePayload);
        $responseUpdate->assertRedirect(route('doctor.pacientes.historial', [
            'paciente' => $titular->id,
        ]) . '?dependiente_id=' . $dependiente->id);

        $this->assertDatabaseHas('clinical_records', [
            'dependiente_id' => $dependiente->id,
            'allergies_status' => 'documented',
            'clinical_summary' => 'Paciente infantil con alergia a la penicilina.',
        ]);

        // BUG 3 VERIFICACIÓN: PDF (Comprobante)
        // El bloque 'Representante' debe tener el nombre del titular sin parentesco inverso, y el de 'Paciente' debe tener el parentesco
        // Nota: para validar el contenido del PDF generado con Dompdf de manera básica, renderizamos la vista directamente
        $fechaPdf = now();
        $clinica = 'Clinica de Prueba';
        $logoBase64 = '';
        $qrDataUri = '';
        $qrUrl = '';
        $html = view('pdf.comprobante-cita', compact('cita', 'fechaPdf', 'clinica', 'logoBase64', 'qrDataUri', 'qrUrl'))->render();
        
        $this->assertStringContainsString('Anabel Arroyo', $html);
        $this->assertStringContainsString('(Hija)', $html);
        $this->assertStringContainsString('Representante', $html);
        $this->assertStringContainsString('Josthyn Arroyo', $html);
        // Asegurarse de que no diga "Josthyn Arroyo (Hijo)"
        $this->assertStringNotContainsString('Josthyn Arroyo (Hijo)', $html);

        // BUG 4 VERIFICACIÓN: Notas Médicas (Bloqueo en Borrador)
        // Guardar y firmar la nota SOAP para un dependiente
        $soapPayload = [
            'subjetivo_motivo' => 'Fiebre persistente',
            'subjetivo_hpi' => 'Presenta picos febriles desde hace 48 horas.',
            'subjetivo_ros' => 'Sin hallazgos.',
            'subjetivo_notas' => 'Sin otros síntomas.',
            'examen_fisico' => 'Faringe congestiva.',
            'notas_objetivas' => 'Temperatura de 38.5C.',
            'assessment' => 'Faringoamigdalitis aguda.',
            'plan_general' => 'Reposo e hidratación.',
            'plan_seguimiento' => 'Control en 72 horas.',
            'follow_up_date' => now()->addDays(3)->format('Y-m-d'),
            'follow_up_notes' => 'Vigilar temperatura.',
            'plan_notas' => 'Paracetamol en jarabe.',
            'sv_ta' => '100/60',
            'sv_fc' => 90,
            'sv_fr' => 20,
            'sv_temp' => 38.5,
            'sv_spo2' => 98,
            'sv_peso' => 22,
            'sv_talla' => 120,
            'diagnosticos' => [
                ['tipo' => 'principal', 'texto' => 'Faringoamigdalitis aguda', 'cie10' => 'J03.9'],
            ],
        ];

        // Guardar borrador
        $draftResponse = $this->post(route('doctor.citas.soap.store', $cita->id), $soapPayload);
        $draftResponse->assertRedirect(route('doctor.citas.soap', $cita->id));

        $this->assertDatabaseHas('notas_soap', [
            'cita_id' => $cita->id,
            'estado' => NotaSoap::ESTADO_BORRADOR,
        ]);

        // Firmar nota
        $signResponse = $this->post(route('doctor.citas.soap.firmar', $cita->id), $soapPayload);
        $signResponse->assertRedirect(route('doctor.citas.soap', $cita->id));

        $this->assertDatabaseHas('notas_soap', [
            'cita_id' => $cita->id,
            'estado' => NotaSoap::ESTADO_FIRMADA,
        ]);

        // Cambiar cita a realizada
        $realizarResponse = $this->post(route('doctor.citas.realizar', $cita->id));
        $realizarResponse->assertRedirect();

        $this->assertDatabaseHas('citas_medicas', [
            'id' => $cita->id,
            'estado' => Cita::ESTADO_REALIZADA,
        ]);
    }
}
