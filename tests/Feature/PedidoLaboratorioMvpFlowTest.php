<?php

namespace Tests\Feature;

use App\Jobs\EnviarResultadoPedidoLaboratorioJob;
use App\Mail\ResultadoPedidoLaboratorioMail;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\PedidoLaboratorio;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PedidoLaboratorioMvpFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('r2_private');
    }

    /**
     * Caso A — Fecha inválida:
     * Enviar una fecha inválida al endpoint marcarMuestra.
     * Debe devolver error de validación controlado (sin 500) y no modificar el pedido.
     */
    public function test_caso_a_marcar_muestra_with_invalid_date_returns_validation_error_and_does_not_mutate_order(): void
    {
        [$labUser, $pedido] = $this->createLabScenario();

        $originalState = $pedido->estado;
        $originalSampleDate = $pedido->sample_collected_at;

        $response = $this->actingAs($labUser)->post(
            route('laboratorio.pedidos.muestra', $pedido),
            ['sample_collected_at' => 'fecha_totalmente_invalida_12345'],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sample_collected_at']);

        $pedido->refresh();
        $this->assertSame($originalState, $pedido->estado);
        $this->assertSame($originalSampleDate, $pedido->sample_collected_at);
    }

    /**
     * Caso B — Publicación válida:
     * Un pedido en estado correcto se publica normalmente.
     * Debe cambiar a resultado_listo, conservar datos del resultado y responder exitosamente.
     */
    public function test_caso_b_valid_publication_updates_order_state_and_stores_result(): void
    {
        Mail::fake();
        [$labUser, $pedido] = $this->createLabScenario();

        $pdf = UploadedFile::fake()->createWithContent('resultado_lab.pdf', '%PDF-1.4 '.str_repeat('resultado ', 64));

        $response = $this->actingAs($labUser)->post(
            route('laboratorio.pedidos.resultado', $pedido),
            [
                'resultado_pdf' => $pdf,
                'resultado_resumen' => 'Valores de glucosa y perfil lipídico dentro del rango normal.',
            ]
        );

        $response->assertSessionHas('success');

        $pedido->refresh();
        $this->assertSame(PedidoLaboratorio::ESTADO_RESULTADO_LISTO, $pedido->estado);
        $this->assertNotNull($pedido->resultado_path);
        $this->assertSame('Valores de glucosa y perfil lipídico dentro del rango normal.', $pedido->resultado_resumen);
        $this->assertNotNull($pedido->resultado_publicado_at);
    }

    /**
     * Caso C — Pedido ya publicado:
     * Intentar publicar nuevamente un pedido ya procesado.
     * Debe rechazarse de forma controlada y conservar intacto el resultado original.
     */
    public function test_caso_c_already_published_order_rejects_second_publication_and_preserves_original(): void
    {
        Mail::fake();
        [$labUser, $pedido] = $this->createLabScenario();

        // 1. Primera publicación válida
        $pdf1 = UploadedFile::fake()->createWithContent('resultado_v1.pdf', '%PDF-1.4 '.str_repeat('resultado1 ', 64));
        $this->actingAs($labUser)->post(
            route('laboratorio.pedidos.resultado', $pedido),
            [
                'resultado_pdf' => $pdf1,
                'resultado_resumen' => 'Resumen Original V1',
            ]
        );

        $pedido->refresh();
        $originalPath = $pedido->resultado_path;
        $originalResumen = $pedido->resultado_resumen;
        $originalPublicadoAt = $pedido->resultado_publicado_at;

        // 2. Segundo intento de publicación sobre el mismo pedido
        $pdf2 = UploadedFile::fake()->createWithContent('resultado_v2.pdf', '%PDF-1.4 '.str_repeat('resultado2 ', 64));
        $response2 = $this->actingAs($labUser)->post(
            route('laboratorio.pedidos.resultado', $pedido),
            [
                'resultado_pdf' => $pdf2,
                'resultado_resumen' => 'Intento Duplicado V2',
            ]
        );

        $response2->assertSessionHasErrors('error');

        $pedido->refresh();
        $this->assertSame(PedidoLaboratorio::ESTADO_RESULTADO_LISTO, $pedido->estado);
        $this->assertSame($originalPath, $pedido->resultado_path);
        $this->assertSame($originalResumen, $pedido->resultado_resumen);
        $this->assertEquals($originalPublicadoAt, $pedido->resultado_publicado_at);
    }

    /**
     * Caso D — Concurrencia / Atomicidad:
     * Si el pedido ya cambió a resultado_listo durante la transacción atómica,
     * la segunda operación es abortada sin sobrescribir datos.
     */
    public function test_caso_d_atomic_protection_prevents_concurrent_overwriting(): void
    {
        Mail::fake();
        [$labUser, $pedido] = $this->createLabScenario();

        // Simular que el pedido pasa a resultado_listo
        $pedido->update([
            'estado' => PedidoLaboratorio::ESTADO_RESULTADO_LISTO,
            'resultado_path' => 'legacy-medical-orders/pedido-existente.pdf',
            'resultado_resumen' => 'Resumen Inicial Protegido',
            'resultado_publicado_at' => now(),
        ]);

        $pdf = UploadedFile::fake()->createWithContent('resultado_concurrente.pdf', '%PDF-1.4 '.str_repeat('resultado3 ', 64));

        $response = $this->actingAs($labUser)->post(
            route('laboratorio.pedidos.resultado', $pedido),
            [
                'resultado_pdf' => $pdf,
                'resultado_resumen' => 'Resumen Concurrente No Autorizado',
            ]
        );

        $response->assertSessionHasErrors('error');

        $pedido->refresh();
        $this->assertSame('Resumen Inicial Protegido', $pedido->resultado_resumen);
        $this->assertSame('legacy-medical-orders/pedido-existente.pdf', $pedido->resultado_path);
    }

    /**
     * Caso E — Fallo / Desacoplamiento de correo:
     * La publicación del resultado clínico no depende de un transporte SMTP síncrono.
     * Se despacha el job oficial EnviarResultadoPedidoLaboratorioJob a la cola.
     */
    public function test_caso_e_email_is_queued_asynchronously_and_does_not_block_http_success(): void
    {
        Queue::fake();
        [$labUser, $pedido] = $this->createLabScenario();

        $pdf = UploadedFile::fake()->createWithContent('resultado_lab_async.pdf', '%PDF-1.4 '.str_repeat('resultado4 ', 64));

        $response = $this->actingAs($labUser)->post(
            route('laboratorio.pedidos.resultado', $pedido),
            [
                'resultado_pdf' => $pdf,
                'resultado_resumen' => 'Examen de orina y cultivo negativo.',
            ]
        );

        $response->assertSessionHas('success');

        $pedido->refresh();
        $this->assertSame(PedidoLaboratorio::ESTADO_RESULTADO_LISTO, $pedido->estado);

        // Se verifica que el job oficial fue encolado para el pedido
        Queue::assertPushed(EnviarResultadoPedidoLaboratorioJob::class, function ($job) use ($pedido) {
            return $job->pedidoId === $pedido->id;
        });
    }

    /**
     * Caso F — Job no duplicado:
     * Una segunda publicación rechazada no despacha otro correo ni job.
     */
    public function test_caso_f_rejected_publication_does_not_dispatch_additional_email(): void
    {
        Queue::fake();
        [$labUser, $pedido] = $this->createLabScenario();

        // 1. Primera publicación
        $pdf1 = UploadedFile::fake()->createWithContent('resultado_1.pdf', '%PDF-1.4 '.str_repeat('resultado5 ', 64));
        $this->actingAs($labUser)->post(
            route('laboratorio.pedidos.resultado', $pedido),
            [
                'resultado_pdf' => $pdf1,
                'resultado_resumen' => 'Resumen 1',
            ]
        );

        Queue::assertPushed(EnviarResultadoPedidoLaboratorioJob::class, 1);

        // 2. Segunda publicación rechazada
        $pdf2 = UploadedFile::fake()->createWithContent('resultado_2.pdf', '%PDF-1.4 '.str_repeat('resultado6 ', 64));
        $this->actingAs($labUser)->post(
            route('laboratorio.pedidos.resultado', $pedido),
            [
                'resultado_pdf' => $pdf2,
                'resultado_resumen' => 'Resumen 2',
            ]
        );

        // La cantidad de jobs encolados sigue siendo exactamente 1
        Queue::assertPushed(EnviarResultadoPedidoLaboratorioJob::class, 1);
    }

    private function createLabScenario(): array
    {
        $labRole = Role::firstOrCreate(['name' => 'laboratorio']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);
        $pacienteRole = Role::firstOrCreate(['name' => 'paciente']);

        $labUser = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'active' => true,
        ]);
        $labUser->roles()->sync([$labRole->id]);

        $doctor = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'active' => true,
        ]);
        $doctor->roles()->sync([$doctorRole->id]);

        $patient = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'active' => true,
            'email' => 'paciente.lab.mvp@example.com',
        ]);
        $patient->roles()->sync([$pacienteRole->id]);

        $specialty = Especialidad::firstOrCreate([
            'nombre' => 'Medicina General',
        ], [
            'descripcion' => 'General',
            'activo' => true,
        ]);

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => '2026-08-20',
            'hora' => '10:00:00',
            'motivo_consulta' => 'Examen de control',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-MVP-TEST',
            'examenes' => ['glucosa', 'colesterol'],
            'estado' => PedidoLaboratorio::ESTADO_PENDIENTE_TOMA,
        ]);

        return [$labUser, $pedido];
    }
}
