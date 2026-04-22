<?php

namespace Tests\Feature;

use App\Mail\ResultadoLaboratorioMail;
use App\Models\Especialidad;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LabOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
    }

    public function test_patient_self_service_lab_order_is_processed_by_laboratory_panel(): void
    {
        $patientRole = Role::firstOrCreate(['name' => 'paciente']);
        $labRole = Role::firstOrCreate(['name' => 'laboratorio']);
        $specialty = Especialidad::factory()->create(['nombre' => 'Laboratorio Clínico']);
        $test = LabTest::query()->create([
            'nombre' => 'Hemograma completo',
            'tipo' => 'rutina',
            'categoria' => 'Sangre',
            'requiere_orden' => false,
            'es_rutina' => true,
            'preparacion_default' => 'Ayuno: No requiere.',
            'indicaciones_default' => 'Traer documento.',
            'activo' => true,
        ]);

        $patient = User::factory()->create([
            'email' => 'patient.lab@clinic.test',
        ]);
        $patient->roles()->sync([$patientRole->id]);

        $lab = User::factory()->create([
            'email' => 'lab.user@clinic.test',
            'precio_consulta' => 18.00,
            'moneda' => 'USD',
        ]);
        $lab->roles()->sync([$labRole->id]);
        $lab->especialidades()->sync([$specialty->id]);

        $this->actingAs($patient)
            ->post(route('paciente.laboratorio.solicitar.store'), [
                'source' => LabOrder::SOURCE_ROUTINE,
                'priority' => 'normal',
                'lab_test_id' => $test->id,
            ])
            ->assertRedirect(route('paciente.laboratorio.index'));

        $order = LabOrder::query()->with('items.test')->latest('id')->firstOrFail();
        $this->assertSame(LabOrder::STATUS_PENDIENTE_TOMA, $order->status);
        $this->assertNull($order->laboratorio_id);

        $this->actingAs($lab)
            ->get(route('laboratorio.ordenes.index'))
            ->assertOk()
            ->assertSee('Hemograma completo');

        $this->actingAs($lab)
            ->post(route('laboratorio.lab-orders.muestra', $order))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(LabOrder::STATUS_MUESTRA_TOMADA, $order->status);
        $this->assertSame($lab->id, $order->laboratorio_id);

        $this->actingAs($lab)
            ->post(route('laboratorio.lab-orders.resultado', $order), [
                'resultado_resumen' => 'Parametros dentro de rangos esperados.',
                'resultado_pdf' => UploadedFile::fake()->create('resultado.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(LabOrder::STATUS_RESULTADO_LISTO, $order->status);
        $this->assertNotNull($order->resultado_path);
        $this->assertNotNull($order->resultado_publicado_at);
        Mail::assertSent(ResultadoLaboratorioMail::class);

        $this->actingAs($patient)
            ->get(route('paciente.laboratorio.index'))
            ->assertOk()
            ->assertSee('Resultado listo');

        $this->actingAs($patient)
            ->get(route('paciente.lab-orders.download', $order))
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
