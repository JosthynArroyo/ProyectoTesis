<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\NotaSoap;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DoctorSoapAutosaveUxTest extends TestCase
{
    use DatabaseTransactions;

    protected User $doctor;
    protected User $paciente;
    protected Especialidad $especialidad;
    protected Cita $cita;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['doctor', 'paciente', 'administrador'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $this->especialidad = Especialidad::firstOrCreate(['nombre' => 'Medicina General']);

        $this->doctor = User::factory()->create([
            'email' => 'doc_ux08_'.uniqid().'@example.com',
        ]);
        $this->doctor->roles()->sync([Role::where('name', 'doctor')->value('id')]);

        $this->paciente = User::factory()->create([
            'email' => 'pac_ux08_'.uniqid().'@example.com',
        ]);
        $this->paciente->roles()->sync([Role::where('name', 'paciente')->value('id')]);

        $this->cita = Cita::create([
            'doctor_id' => $this->doctor->id,
            'paciente_id' => $this->paciente->id,
            'especialidad_id' => $this->especialidad->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00:00',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'motivo_consulta' => 'Control de rutina',
        ]);
    }

    /**
     * TEST 1: La vista de nota clínica (borrador) incluye el indicador accesible de estado de autoguardado.
     * En el estado inicial sin corregir, data-soap-autosave-status no existe en el Blade (RED).
     */
    public function test_doctor_soap_draft_view_renders_autosave_status_indicator_element(): void
    {
        $response = $this->actingAs($this->doctor)->get(route('doctor.citas.soap', $this->cita->id));
        $response->assertOk();

        $content = $response->getContent();

        // Debe contener el elemento con el atributo data-soap-autosave-status
        $this->assertStringContainsString(
            'data-soap-autosave-status',
            $content,
            'El elemento con data-soap-autosave-status debe existir en la vista de la nota clínica.'
        );

        // Debe ser accesible mediante aria-live="polite" y role="status"
        $this->assertStringContainsString('role="status"', $content);
        $this->assertStringContainsString('aria-live="polite"', $content);

        // Debe contener el contenedor de texto de autosave
        $this->assertStringContainsString('data-soap-autosave-text', $content);
        $this->assertStringContainsString('Autoguardado activo', $content);
    }

    /**
     * TEST 2: Cuando la nota ya está firmada, no se renderizan los controles de borrador ni el autosave.
     */
    public function test_doctor_soap_signed_view_does_not_render_autosave_indicator(): void
    {
        NotaSoap::create([
            'cita_id' => $this->cita->id,
            'estado' => NotaSoap::ESTADO_FIRMADA,
            'signed_at' => now(),
            'signed_by' => $this->doctor->id,
            'subjetivo_motivo' => 'Consulta firmada',
            'assessment' => 'Evaluado',
            'plan_general' => 'Reposo',
            'plan_notas' => 'Cuidarse',
            'examen_fisico' => 'Normal',
            'notas_objetivas' => 'Estable',
        ]);

        $response = $this->actingAs($this->doctor)->get(route('doctor.citas.soap', $this->cita->id));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString('data-soap-autosave-status', $content);
        $this->assertStringNotContainsString('Guardar borrador', $content);
    }

    /**
     * TEST 3: El endpoint de autosave responde con JSON {"ok": true} ante peticiones XHR/JSON.
     */
    public function test_doctor_soap_autosave_endpoint_returns_json_ok_response(): void
    {
        $payload = [
            'subjetivo_motivo' => 'Motivo actualizado vía autosave',
            'examen_fisico' => 'Sin hallazgos patológicos',
            'notas_objetivas' => 'Signos dentro de límites normales',
            'assessment' => 'Paciente saludable',
            'plan_general' => 'Control anual',
            'plan_notas' => 'Dieta balanceada',
        ];

        $response = $this->actingAs($this->doctor)
            ->postJson(route('doctor.citas.soap.store', $this->cita->id), $payload);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        // Verificar persistencia como borrador en modos donde persiste (production)
        if (app(\App\Services\ApplicationModeService::class)->shouldPersist()) {
            $this->assertDatabaseHas('notas_soap', [
                'cita_id' => $this->cita->id,
                'estado' => NotaSoap::ESTADO_BORRADOR,
                'subjetivo_motivo' => 'Motivo actualizado vía autosave',
            ]);
        }
    }

    /**
     * TEST 4: El botón manual 'Guardar borrador' y 'Firmar y cerrar' permanecen intactos.
     */
    public function test_doctor_soap_preserves_manual_action_buttons(): void
    {
        $response = $this->actingAs($this->doctor)->get(route('doctor.citas.soap', $this->cita->id));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('Guardar borrador', $content);
        $this->assertStringContainsString('Firmar y cerrar', $content);
        $this->assertStringContainsString(route('doctor.citas.soap.firmar', $this->cita->id), $content);
    }
}
