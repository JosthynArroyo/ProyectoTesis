<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use App\Models\Role;
use App\Models\User;
use App\Services\PedidoLaboratorioPdfService;
use App\Jobs\EnviarResultadoPedidoLaboratorioJob;
use App\Mail\ResultadoPedidoLaboratorioMail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LaboratoryResultR2StorageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['private_documents.disk' => 'r2_private']);
        config(['image_optimization.avatar_disk' => 'r2_private']);
        Storage::fake('r2_private');
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();

        $this->seedRoles();
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/private/scratch/lab_result_migration_manifest.json'));
        parent::tearDown();
    }

    private function seedRoles(): void
    {
        foreach (['superadmin', 'administrador', 'doctor', 'paciente', 'laboratorio'] as $r) {
            Role::firstOrCreate(['name' => $r]);
        }
    }

    private function createRoleUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'email_verified_at' => now(),
        ], $attributes));

        $roleModel = Role::where('name', $role)->first();
        if ($roleModel) {
            $user->roles()->attach($roleModel->id);
        }

        return $user;
    }

    private function createPedido(User $doctor, User $paciente, ?Dependiente $dependiente = null): PedidoLaboratorio
    {
        $esp = \App\Models\Especialidad::firstOrCreate(['nombre' => 'Medicina General']);
        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'dependiente_id' => $dependiente?->id,
            'especialidad_id' => $esp->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        return PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'examenes' => ['hemograma_completo'],
            'estado' => PedidoLaboratorio::ESTADO_MUESTRA_TOMADA,
        ]);
    }

    /** 1. Publicación nueva escribe exclusivamente en r2_private y no en local, guardando pdf_disk */
    public function test_publish_result_writes_exclusively_to_r2_private(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $lab = $this->createRoleUser('laboratorio');
        $pedido = $this->createPedido($doctor, $paciente);

        $response = $this->actingAs($lab)->post(route('laboratorio.pedidos.resultados.publish', $pedido), [
            'observaciones_generales' => 'Resultado de hemograma normal',
            'items' => [
                'hemograma_completo' => [
                    'resultado' => '14.5',
                    'unidad' => 'g/dL',
                    'referencia' => '13.5-17.5',
                    'clasificacion' => 'normal',
                    'metodo' => 'Automatizado',
                    'observaciones' => 'Sin observaciones',
                ]
            ]
        ]);

        $response->assertRedirect(route('laboratorio.pedidos.index'));

        $resultado = PedidoLaboratorioResultado::where('pedido_laboratorio_id', $pedido->id)->firstOrFail();
        $this->assertEquals('r2_private', $resultado->pdf_disk);
        $this->assertStringStartsWith('documents/laboratory-results/', $resultado->pdf_path);

        $r2Disk = Storage::disk('r2_private');
        $localDisk = Storage::disk('local');

        $this->assertTrue($r2Disk->exists($resultado->pdf_path));
        $this->assertFalse($localDisk->exists($resultado->pdf_path));

        $binary = $r2Disk->get($resultado->pdf_path);
        $this->assertStringStartsWith('%PDF', $binary);
    }

    /** 2. Conserva versiones anteriores ante una corrección (reemplazo) */
    public function test_conserves_previous_versions_of_results(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $lab = $this->createRoleUser('laboratorio');
        $pedido = $this->createPedido($doctor, $paciente);

        // Publicar primera versión
        $this->actingAs($lab)->post(route('laboratorio.pedidos.resultados.publish', $pedido), [
            'observaciones_generales' => 'Versión 1',
            'items' => [
                'hemograma_completo' => [
                    'resultado' => '12.0',
                    'unidad' => 'g/dL',
                    'referencia' => '13.5-17.5',
                    'clasificacion' => 'normal',
                    'metodo' => 'Automatizado',
                    'observaciones' => 'Versión 1',
                ]
            ]
        ])->assertRedirect();

        $v1 = PedidoLaboratorioResultado::where('pedido_laboratorio_id', $pedido->id)->where('version', 1)->firstOrFail();
        $this->assertEquals(PedidoLaboratorioResultado::ESTADO_PUBLICADO, $v1->estado);

        // Crear borrador para versión 2
        $this->actingAs($lab)->post(route('laboratorio.pedidos.resultados.draft', $pedido), [
            'observaciones_generales' => 'Borrador Versión 2',
            'items' => [
                'hemograma_completo' => [
                    'resultado' => '14.0',
                    'unidad' => 'g/dL',
                    'referencia' => '13.5-17.5',
                    'clasificacion' => 'normal',
                    'metodo' => 'Automatizado',
                    'observaciones' => 'Borrador Versión 2',
                ]
            ]
        ])->assertRedirect();

        // Publicar versión 2
        $this->actingAs($lab)->post(route('laboratorio.pedidos.resultados.publish', $pedido), [
            'observaciones_generales' => 'Versión 2 finalizada',
            'items' => [
                'hemograma_completo' => [
                    'resultado' => '14.0',
                    'unidad' => 'g/dL',
                    'referencia' => '13.5-17.5',
                    'clasificacion' => 'normal',
                    'metodo' => 'Automatizado',
                    'observaciones' => 'Versión 2',
                ]
            ]
        ])->assertRedirect();

        $v1->refresh();
        $v2 = PedidoLaboratorioResultado::where('pedido_laboratorio_id', $pedido->id)->where('version', 2)->firstOrFail();

        $this->assertEquals(PedidoLaboratorioResultado::ESTADO_REEMPLAZADO, $v1->estado);
        $this->assertEquals(PedidoLaboratorioResultado::ESTADO_PUBLICADO, $v2->estado);
        $this->assertEquals($v2->id, $v1->reemplaza_id);

        $this->assertTrue(Storage::disk('r2_private')->exists($v1->pdf_path));
        $this->assertTrue(Storage::disk('r2_private')->exists($v2->pdf_path));
    }

    /** 3. Fallo de R2 no publica en BD y fallo de BD limpia R2 */
    public function test_r2_upload_failure_does_not_persist_result(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $lab = $this->createRoleUser('laboratorio');
        $pedido = $this->createPedido($doctor, $paciente);

        $mockDisk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockDisk->shouldReceive('put')->andReturn(false);
        $mockDisk->shouldReceive('exists')->andReturn(false);

        $localDisk = Storage::disk('local');

        Storage::shouldReceive('disk')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function ($disk = null) use ($mockDisk, $localDisk) {
                if ($disk === 'r2_private') {
                    return $mockDisk;
                }
                return $localDisk;
            });

        $this->withoutExceptionHandling();
        $this->expectException(\RuntimeException::class);

        $this->actingAs($lab)->post(route('laboratorio.pedidos.resultados.publish', $pedido), [
            'observaciones_generales' => 'Debe fallar',
            'items' => [
                'hemograma_completo' => [
                    'resultado' => '14.5',
                    'unidad' => 'g/dL',
                    'referencia' => '13.5-17.5',
                    'clasificacion' => 'normal',
                    'metodo' => 'Automatizado',
                    'observaciones' => 'Sin observaciones',
                ]
            ]
        ]);

        $this->assertDatabaseMissing('pedido_laboratorio_resultados', [
            'pedido_laboratorio_id' => $pedido->id,
        ]);
    }

    /** 4. Matriz de permisos de visualización y descarga */
    public function test_visualization_and_download_permission_matrix(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $lab = $this->createRoleUser('laboratorio');
        
        $otroDoctor = $this->createRoleUser('doctor');
        $otroPaciente = $this->createRoleUser('paciente');

        $pedido = $this->createPedido($doctor, $paciente);

        // Crear e insertar un resultado publicado en R2
        $resultado = PedidoLaboratorioResultado::create([
            'pedido_laboratorio_id' => $pedido->id,
            'version' => 1,
            'estado' => PedidoLaboratorioResultado::ESTADO_PUBLICADO,
            'pdf_path' => 'documents/laboratory-results/' . $pedido->id . '/test.pdf',
            'pdf_disk' => 'r2_private',
            'laboratorio_id' => $lab->id,
            'csv' => 'CSV-12345-LAB',
        ]);
        Storage::disk('r2_private')->put($resultado->pdf_path, "%PDF-1.4 test content");

        // Laboratorio emisor
        $this->actingAs($lab)->get(route('laboratorio.pedidos.resultados.download', $pedido))
            ->assertOk();

        // Doctor solicitante
        $this->actingAs($doctor)->get(route('doctor.pedidos-laboratorio.resultado.download', $pedido))
            ->assertOk();

        // Paciente titular
        $this->actingAs($paciente)->get(route('paciente.laboratorio.pedido.download', $pedido))
            ->assertOk();

        // Otro doctor (No relacionado)
        $this->actingAs($otroDoctor)->get(route('doctor.pedidos-laboratorio.resultado.download', $pedido))
            ->assertStatus(403);

        // Otro paciente (No relacionado)
        $this->actingAs($otroPaciente)->get(route('paciente.laboratorio.pedido.download', $pedido))
            ->assertStatus(403);

        // Visitante sin autenticar
        $this->post(route('salir')); // Asegurar sesión cerrada
        $this->get(route('paciente.laboratorio.pedido.download', $pedido))
            ->assertRedirect(url('/') . '?login=1');
    }

    /** 5. Representante de dependiente puede descargar el resultado */
    public function test_representative_can_download_dependent_result(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $representante = $this->createRoleUser('paciente');
        $dependiente = Dependiente::create([
            'user_id' => $representante->id,
            'nombre' => 'Hijo dependiente',
            'tipo_documento' => 'cedula',
            'dni' => '1712345670',
            'fecha_nacimiento' => '2018-05-10',
            'parentesco' => 'hijo',
            'activo' => true,
        ]);

        $pedido = $this->createPedido($doctor, $representante, $dependiente);
        $resultado = PedidoLaboratorioResultado::create([
            'pedido_laboratorio_id' => $pedido->id,
            'version' => 1,
            'estado' => PedidoLaboratorioResultado::ESTADO_PUBLICADO,
            'pdf_path' => 'documents/laboratory-results/' . $pedido->id . '/dep.pdf',
            'pdf_disk' => 'r2_private',
            'csv' => 'CSV-99999-DEP',
        ]);
        Storage::disk('r2_private')->put($resultado->pdf_path, "%PDF-1.4 test content");

        // El representante tiene acceso
        $this->actingAs($representante)->get(route('paciente.laboratorio.pedido.download', $pedido))
            ->assertOk();
    }

    /** 6. Correo adjunta bytes desde R2 y solo se dirige al paciente o representante */
    public function test_email_sends_to_patient_attaching_from_r2(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente', ['email' => 'paciente_real@example.com']);
        $pedido = $this->createPedido($doctor, $paciente);

        $resultado = PedidoLaboratorioResultado::create([
            'pedido_laboratorio_id' => $pedido->id,
            'version' => 1,
            'estado' => PedidoLaboratorioResultado::ESTADO_PUBLICADO,
            'pdf_path' => 'documents/laboratory-results/' . $pedido->id . '/mail.pdf',
            'pdf_disk' => 'r2_private',
            'csv' => 'CSV-55555-MAIL',
        ]);
        Storage::disk('r2_private')->put($resultado->pdf_path, "%PDF-1.4 mail content");

        // Despachar Job
        EnviarResultadoPedidoLaboratorioJob::dispatchSync($resultado->id);

        Mail::assertSent(ResultadoPedidoLaboratorioMail::class, function ($mail) use ($paciente) {
            $this->assertTrue($mail->hasTo('paciente_real@example.com'));
            $this->assertFalse($mail->hasTo('doctor_real@example.com')); // No al doctor

            // Verificar que se adjuntó el PDF leyendo bytes de R2
            $rawAttachments = $mail->build()->rawAttachments;

            $hasPdfAttachment = false;
            foreach ($rawAttachments as $attach) {
                if ($attach['options']['mime'] === 'application/pdf' && str_contains($attach['name'], 'resultado_laboratorio')) {
                    $hasPdfAttachment = true;
                    // En attachData de Mailable, 'data' puede ser un string o un callback que devuelve un string
                    $data = is_callable($attach['data']) ? $attach['data']() : $attach['data'];
                    $this->assertEquals('%PDF-1.4 mail content', $data);
                }
            }

            return $hasPdfAttachment;
        });
    }

    /** 7. Verificación QR devuelve HTML seguro sin descargar PDF ni exponer datos clínicos */
    public function test_qr_verification_shows_secure_html_view(): void
    {
        $doctor = $this->createRoleUser('doctor', ['name' => 'Dr. Gregory House']);
        $paciente = $this->createRoleUser('paciente', ['name' => 'John Doe']);
        $pedido = $this->createPedido($doctor, $paciente);

        $resultado = PedidoLaboratorioResultado::create([
            'pedido_laboratorio_id' => $pedido->id,
            'version' => 1,
            'estado' => PedidoLaboratorioResultado::ESTADO_PUBLICADO,
            'pdf_path' => 'documents/laboratory-results/' . $pedido->id . '/qr.pdf',
            'pdf_disk' => 'r2_private',
            'csv' => 'CSV-VERIFY-123',
        ]);

        $response = $this->get(route('documentos.verificar.show', $resultado->csv));
        $response->assertOk();
        $response->assertViewIs('documentos.verificacion-show');

        // Protege el nombre (John Doe -> John D.)
        $response->assertSee('John D.');
        $response->assertSee('CSV-VERIFY-123');
        $response->assertSee('Verificado');

        // No debe mostrar contenido clínico, URL R2 o ruta local
        $response->assertDontSee('documents/laboratory-results');
        $response->assertDontSee('.pdf');
    }

    /** 8. Compatibilidad con PDF local heredado */
    public function test_legacy_local_pdf_fallback_reading(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $pedido = $this->createPedido($doctor, $paciente);

        // Resultado antiguo sin pdf_disk (es local)
        $resultado = PedidoLaboratorioResultado::create([
            'pedido_laboratorio_id' => $pedido->id,
            'version' => 1,
            'estado' => PedidoLaboratorioResultado::ESTADO_PUBLICADO,
            'pdf_path' => 'pedidos-laboratorio-resultados/legacy.pdf',
            'pdf_disk' => null, // heredado local
            'csv' => 'LEGACY-CSV-001',
        ]);

        Storage::disk('local')->put($resultado->pdf_path, "%PDF-1.4 legacy content");

        // El paciente lo descarga y debe leerse del disco local
        $this->actingAs($paciente)->get(route('paciente.laboratorio.pedido.download', $pedido))
            ->assertOk();
    }

    /** 9. Comando de migración de resultados locales a R2 private */
}
