<?php

namespace Tests\Feature;

use App\Jobs\EnviarResultadoPedidoLaboratorioJob;
use App\Mail\ResultadoPedidoLaboratorioMail;
use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use App\Models\Role;
use App\Models\User;
use App\Services\LabTestCatalogService;
use App\Services\PedidoLaboratorioPdfService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PedidoLaboratorioResultadosFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('r2_private');
        Mail::fake();
    }

    public function test_flujo_estructurado_publica_resultados_para_dependiente_y_muestra_qr_verificacion(): void
    {
        [$titular, $dependiente, $doctor, $lab, $pedido] = $this->crearPedidoConDependiente([
            'biometria_hematica',
            'glucosa',
        ]);

        $catalogo = app(LabTestCatalogService::class);

        $this->actingAs($lab)
            ->get(route('laboratorio.pedidos.resultados.form', $pedido))
            ->assertOk()
            ->assertSee($catalogo->label('biometria_hematica'))
            ->assertSee($catalogo->label('glucosa'))
            ->assertDontSee($catalogo->label('trigliceridos'));

        $payload = $this->payloadResultados([
            'biometria_hematica' => [
                'resultado' => '13.8',
                'unidad' => 'g/dL',
                'referencia' => '12.0 - 16.0',
                'clasificacion' => 'normal',
                'metodo' => 'Automatizado',
                'observaciones' => 'Sin alteraciones hematologicas.',
            ],
            'glucosa' => [
                'resultado' => '92',
                'unidad' => 'mg/dL',
                'referencia' => '70 - 100',
                'clasificacion' => 'normal',
                'metodo' => 'Enzimatico',
                'observaciones' => '',
            ],
        ], 'Resultados dentro de rango para la edad y sexo.');

        $this->actingAs($lab)
            ->post(route('laboratorio.pedidos.resultados.draft', $pedido), $payload)
            ->assertRedirect(route('laboratorio.pedidos.resultados.form', $pedido));

        $resultadoBorrador = $pedido->fresh()->resultados()->latest('version')->firstOrFail();
        $this->assertSame(PedidoLaboratorioResultado::ESTADO_BORRADOR, $resultadoBorrador->estado);
        $this->assertSame(1, $resultadoBorrador->version);
        $this->assertCount(2, $resultadoBorrador->resultado_items);

        $preview = $this->actingAs($lab)
            ->post(route('laboratorio.pedidos.resultados.preview', $pedido), $payload);

        $preview->assertOk();
        $preview->assertHeader('Content-Type', 'application/pdf');

        $resultadoParaPreview = $pedido->fresh()->resultados()->latest('version')->firstOrFail();
        $htmlPreview = app(PedidoLaboratorioPdfService::class)->previewHtml(
            $pedido->fresh(['cita.paciente', 'cita.dependiente.responsable', 'cita.doctor', 'doctor', 'paciente', 'resultados.laboratorio']),
            $resultadoParaPreview
        );
        $this->assertStringContainsString('Informe de resultados de laboratorio', $htmlPreview);
        $this->assertStringContainsString('data:image/png;base64,', $htmlPreview);
        $this->assertNotEmpty($resultadoParaPreview->csv);
        $this->assertStringContainsString(route('documentos.verificar.show', $resultadoParaPreview->csv), $htmlPreview);

        $this->actingAs($lab)
            ->post(route('laboratorio.pedidos.resultados.publish', $pedido), $payload)
            ->assertRedirect(route('laboratorio.pedidos.index'));

        $pedido->refresh();
        $resultadoPublicado = $pedido->resultados()->where('estado', PedidoLaboratorioResultado::ESTADO_PUBLICADO)->firstOrFail();

        $this->assertSame(PedidoLaboratorio::ESTADO_RESULTADO_LISTO, $pedido->estado);
        $this->assertNotNull($pedido->resultado_path);
        $this->assertNotNull($pedido->resultado_publicado_at);
        $this->assertSame($resultadoPublicado->pdf_path, $pedido->resultado_path);
        $this->assertSame($dependiente->nombre, $pedido->nombrePacienteReal());
        $this->assertSame($titular->name, $pedido->representanteNombre());
        $diskName = $resultadoPublicado->pdf_disk ?: 'local';
        Storage::disk($diskName)->assertExists($resultadoPublicado->pdf_path);

        Mail::assertSent(ResultadoPedidoLaboratorioMail::class, function ($mail) use ($titular, $doctor, $lab) {
            return $mail->hasTo($titular->email)
                && ! $mail->hasTo($doctor->email)
                && ! $mail->hasTo($lab->email);
        });
        Mail::assertSent(ResultadoPedidoLaboratorioMail::class, 1);

        $this->get(route('documentos.verificar.show', $resultadoPublicado->csv))
            ->assertOk()
            ->assertSee('Informe de laboratorio verificado.')
            ->assertSee('Verificado')
            ->assertSee('V1');

        $this->actingAs($doctor)
            ->get(route('doctor.pedidos-laboratorio.index'))
            ->assertOk()
            ->assertSee($dependiente->nombre)
            ->assertSee($titular->name)
            ->assertSee('Descargar informe');

        $this->actingAs($doctor)
            ->get(route('doctor.pedidos-laboratorio.resultado.download', $pedido))
            ->assertOk();

        $this->actingAs($titular)
            ->get(route('paciente.laboratorio.index'))
            ->assertOk()
            ->assertSee('Resultados de laboratorio publicados')
            ->assertSee('Descargar PDF');

        $this->actingAs($titular)
            ->get(route('paciente.laboratorio.pedido.download', $pedido))
            ->assertOk();

        $intruso = $this->userWithRole('paciente', 'intruso@example.test');
        $this->actingAs($intruso)
            ->get(route('paciente.laboratorio.pedido.download', $pedido))
            ->assertForbidden();

        $this->get(route('documentos.verificar.show', 'TOKEN-INVALIDO-123'))
            ->assertNotFound();
    }

    public function test_resultado_publicado_se_corrige_con_nueva_version_y_reenvia_correo_solo_al_paciente(): void
    {
        [$titular, $doctor, $lab, $pedido] = $this->crearPedidoTitular([
            'biometria_hematica',
            'trigliceridos',
            'dengue',
        ]);

        $payloadV1 = $this->payloadResultados([
            'biometria_hematica' => [
                'resultado' => '11.9',
                'unidad' => 'g/dL',
                'referencia' => '12.0 - 16.0',
                'clasificacion' => 'bajo',
                'metodo' => 'Automatizado',
                'observaciones' => 'Ligeramente bajo.',
            ],
            'trigliceridos' => [
                'resultado' => '144',
                'unidad' => 'mg/dL',
                'referencia' => '0 - 150',
                'clasificacion' => 'normal',
                'metodo' => 'Enzimatico',
                'observaciones' => '',
            ],
            'dengue' => [
                'resultado' => 'Negativo',
                'unidad' => '',
                'referencia' => 'No reactivo',
                'clasificacion' => 'normal',
                'metodo' => 'Serologico',
                'observaciones' => '',
            ],
        ], 'Version inicial.');

        $this->actingAs($lab)
            ->post(route('laboratorio.pedidos.resultados.publish', $pedido), $payloadV1)
            ->assertRedirect(route('laboratorio.pedidos.index'));

        $pedido->refresh();
        $resultadoV1 = $pedido->resultados()->where('version', 1)->firstOrFail();
        $this->assertSame(PedidoLaboratorioResultado::ESTADO_PUBLICADO, $resultadoV1->estado);

        $duplicado = $this->actingAs($lab)
            ->post(route('laboratorio.pedidos.resultados.publish', $pedido), $payloadV1);

        $duplicado->assertRedirect();
        $duplicado->assertSessionHas('info');
        $this->assertSame(1, $pedido->fresh()->resultados()->count());

        $this->actingAs($lab)
            ->get(route('laboratorio.pedidos.resultados.form', $pedido))
            ->assertOk();

        $payloadV2 = $this->payloadResultados([
            'biometria_hematica' => [
                'resultado' => '12.4',
                'unidad' => 'g/dL',
                'referencia' => '12.0 - 16.0',
                'clasificacion' => 'normal',
                'metodo' => 'Automatizado',
                'observaciones' => 'Correccion de referencia.',
            ],
            'trigliceridos' => [
                'resultado' => '160',
                'unidad' => 'mg/dL',
                'referencia' => '0 - 150',
                'clasificacion' => 'alto',
                'metodo' => 'Enzimatico',
                'observaciones' => 'Revisar dieta y control.',
            ],
            'dengue' => [
                'resultado' => 'No reactivo',
                'unidad' => '',
                'referencia' => 'No reactivo',
                'clasificacion' => 'normal',
                'metodo' => 'Serologico',
                'observaciones' => '',
            ],
        ], 'Version corregida y final.');

        $this->actingAs($lab)
            ->post(route('laboratorio.pedidos.resultados.publish', $pedido), $payloadV2)
            ->assertRedirect(route('laboratorio.pedidos.index'));

        $pedido->refresh();
        $resultadoV2 = $pedido->resultados()->where('version', 2)->firstOrFail();
        $resultadoV1->refresh();

        $this->assertSame(PedidoLaboratorioResultado::ESTADO_REEMPLAZADO, $resultadoV1->estado);
        $this->assertSame($resultadoV2->id, (int) $resultadoV1->reemplaza_id);
        $this->assertSame(PedidoLaboratorioResultado::ESTADO_PUBLICADO, $resultadoV2->estado);
        $this->assertSame(2, $pedido->resultados()->count());

        $this->actingAs($lab)
            ->post(route('laboratorio.pedidos.resultados.resend', $pedido))
            ->assertRedirect();

        Mail::assertSent(ResultadoPedidoLaboratorioMail::class, 3);
        Mail::assertSent(ResultadoPedidoLaboratorioMail::class, function ($mail) use ($titular, $doctor, $lab) {
            return $mail->hasTo($titular->email)
                && ! $mail->hasTo($doctor->email)
                && ! $mail->hasTo($lab->email);
        });
    }

    private function crearPedidoConDependiente(array $examenes): array
    {
        $titular = $this->userWithRole('paciente', 'titular-dependiente@example.test');
        $doctor = $this->userWithRole('doctor', 'doctor-dependiente@example.test');
        $lab = $this->userWithRole('laboratorio', 'lab-dependiente@example.test');

        $especialidad = Especialidad::factory()->create(['nombre' => 'Laboratorio clinico']);
        $doctor->especialidades()->attach($especialidad->id);

        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'nombre' => 'Paciente Dependiente',
            'dni' => '0999999999',
            'fecha_nacimiento' => now()->subYears(10)->toDateString(),
            'sexo' => 'Femenino',
            'parentesco' => 'hija',
            'activo' => true,
        ]);

        $cita = Cita::create([
            'paciente_id' => $titular->id,
            'dependiente_id' => $dependiente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->toDateString(),
            'hora' => '09:00:00',
            'motivo_consulta' => 'Control',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $titular->id,
            'doctor_id' => $doctor->id,
            'csv' => 'PL-DEPENDIENTE-001',
            'examenes' => $examenes,
            'estado' => PedidoLaboratorio::ESTADO_PENDIENTE_TOMA,
            'envio_estado' => 'queued',
            'envio_intentos' => 0,
        ]);

        return [$titular, $dependiente, $doctor, $lab, $pedido];
    }

    private function crearPedidoTitular(array $examenes): array
    {
        $titular = $this->userWithRole('paciente', 'titular@example.test');
        $doctor = $this->userWithRole('doctor', 'doctor@example.test');
        $lab = $this->userWithRole('laboratorio', 'lab@example.test');

        $especialidad = Especialidad::factory()->create(['nombre' => 'Laboratorio clinico']);
        $doctor->especialidades()->attach($especialidad->id);

        $cita = Cita::create([
            'paciente_id' => $titular->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:30:00',
            'motivo_consulta' => 'Chequeo general',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $titular->id,
            'doctor_id' => $doctor->id,
            'csv' => 'PL-TITULAR-001',
            'examenes' => $examenes,
            'estado' => PedidoLaboratorio::ESTADO_PENDIENTE_TOMA,
            'envio_estado' => 'queued',
            'envio_intentos' => 0,
        ]);

        return [$titular, $doctor, $lab, $pedido];
    }

    private function payloadResultados(array $items, ?string $observacionesGenerales = null): array
    {
        return [
            'items' => $items,
            'observaciones_generales' => $observacionesGenerales,
        ];
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }
}
