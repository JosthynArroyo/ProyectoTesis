<?php

namespace Tests\Feature;

use App\Enums\LabResultClassification;
use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\LaboratoryComponent;
use App\Models\LaboratoryResultValue;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DynamicLaboratoryCatalogAndResultsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('r2_private');
        Mail::fake();

        $this->seedRoles();
        Artisan::call('laboratory-catalog:install', ['--execute' => true]);
    }

    private function seedRoles(): void
    {
        foreach (['admin', 'superadmin', 'doctor', 'paciente', 'laboratorio'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }
    }

    private function createRoleUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'email' => strtolower($role).'_'.uniqid().'@example.com',
        ], $attributes));
        $roleId = Role::where('name', $role)->value('id');
        $user->roles()->sync([$roleId]);

        return $user;
    }

    private function createPedido(User $doctor, User $paciente, array $examenes = ['creatinina', 'colesterol_ldl', 'brusella'], ?Dependiente $dependiente = null): PedidoLaboratorio
    {
        $especialidad = Especialidad::firstOrCreate(['nombre' => 'Medicina General']);
        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'dependiente_id' => $dependiente?->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->addDay()->toDateString(),
            'hora' => '10:00:00',
            'estado' => 'realizada',
        ]);

        return PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'doctor_id' => $doctor->id,
            'paciente_id' => $paciente->id,
            'examenes' => $examenes,
            'indicaciones' => 'Ayuno de 8 horas',
            'estado' => PedidoLaboratorio::ESTADO_PENDIENTE_TOMA,
        ]);
    }

    /** 1. Un pedido con Creatinina, LDL y Brucella abortus muestra únicamente esos exámenes con métodos institucionales */
    public function test_pedido_displays_only_requested_exams_with_institutional_methods(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $lab = $this->createRoleUser('laboratorio');
        $pedido = $this->createPedido($doctor, $paciente, ['creatinina', 'colesterol_ldl', 'brusella']);

        $this->actingAs($lab)
            ->get(route('laboratorio.pedidos.resultados.form', $pedido))
            ->assertOk()
            ->assertSee('Creatinina')
            ->assertSee('Colesterol LDL')
            ->assertSee('Brucella abortus')
            ->assertSee('Jaffé cinético')
            ->assertSee('Rosa de Bengala')
            ->assertDontSee('Automatizado');
    }

    /** 2. Publicación no exige motivo de modificación (override_reason) al editar unidad, método o referencia */
    public function test_publishing_without_override_reason_succeeds_and_tracks_flags(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $lab = $this->createRoleUser('laboratorio');
        $pedido = $this->createPedido($doctor, $paciente, ['creatinina', 'brusella']);

        $payload = [
            'observaciones_generales' => 'Valores procesados sin novedad.',
            'items' => [
                'creatinina_comp' => [
                    'value_numeric' => '0.90',
                    'comparator' => '=',
                    'unit' => 'µmol/L',
                    'method' => 'Enzimático',
                    'reference' => '45 – 90 µmol/L',
                    'classification' => 'normal',
                    'confirm_reference' => '1',
                ],
                'brusella_comp' => [
                    'value_code' => 'no_reactivo',
                    'unit' => 'No aplica',
                    'method' => 'Rosa de Bengala',
                    'reference' => 'No reactivo',
                    'classification' => 'normal',
                    'confirm_reference' => '1',
                ],
            ],
        ];

        $this->actingAs($lab)
            ->post(route('laboratorio.pedidos.resultados.publish', $pedido), $payload)
            ->assertRedirect(route('laboratorio.pedidos.index'));

        $resultado = $pedido->fresh()->resultados()->where('estado', PedidoLaboratorioResultado::ESTADO_PUBLICADO)->firstOrFail();

        $valCreatinina = LaboratoryResultValue::where('result_id', $resultado->id)
            ->whereHas('component', fn ($q) => $q->where('code', 'creatinina_comp'))
            ->firstOrFail();

        $this->assertSame('µmol/L', $valCreatinina->unit_snapshot);
        $this->assertSame('Enzimático', $valCreatinina->method_snapshot);
        $this->assertTrue($valCreatinina->unit_was_overridden);
        $this->assertTrue($valCreatinina->method_was_overridden);
        $this->assertNull($valCreatinina->override_reason);
    }

    /** 3. Brucella abortus exige una opción cualitativa y la conserva */
    public function test_brucella_abortus_requires_coded_value(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $lab = $this->createRoleUser('laboratorio');
        $pedido = $this->createPedido($doctor, $paciente, ['brusella']);

        // 1. Envío sin seleccionar opción para Brucella -> Error de validación
        $payloadVacio = [
            'items' => [
                'brusella_comp' => [
                    'value_code' => '',
                    'classification' => 'normal',
                ],
            ],
        ];

        $this->actingAs($lab)
            ->post(route('laboratorio.pedidos.resultados.publish', $pedido), $payloadVacio)
            ->assertSessionHasErrors(['items.brusella_comp.value_code']);

        // 2. Envío con opción 'no_reactivo' -> Éxito
        $payloadValido = [
            'items' => [
                'brusella_comp' => [
                    'value_code' => 'no_reactivo',
                    'unit' => 'No aplica',
                    'method' => 'Rosa de Bengala',
                    'reference' => 'No reactivo',
                    'classification' => 'normal',
                    'confirm_reference' => '1',
                ],
            ],
        ];

        $this->actingAs($lab)
            ->post(route('laboratorio.pedidos.resultados.publish', $pedido), $payloadValido)
            ->assertRedirect(route('laboratorio.pedidos.index'));
    }

    /** 4. Todos los 66 exámenes tienen correspondencia e integridad en el catálogo */
    public function test_all_66_exams_have_components_methods_and_references(): void
    {
        $this->artisan('laboratory-catalog:install', ['--verify' => true])
            ->assertExitCode(0);
    }

    /** 5. El pedido para un dependiente utiliza exclusivamente el sexo y la fecha de nacimiento del dependiente */
    public function test_dependent_order_uses_dependent_sex_and_birth_date(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $titularPadre = $this->createRoleUser('paciente', ['sexo' => 'Masculino', 'fecha_nacimiento' => '1980-05-12']);

        $hijaDependiente = Dependiente::create([
            'paciente_id' => $titularPadre->id,
            'user_id' => $titularPadre->id,
            'nombre' => 'Sofía Pérez',
            'parentesco' => 'hija',
            'fecha_nacimiento' => '2018-09-20',
            'sexo' => 'Femenino',
            'tipo_documento' => 'cedula',
            'dni' => '0987654321',
        ]);

        $pedido = $this->createPedido($doctor, $titularPadre, ['creatinina'], $hijaDependiente);

        $payload = [
            'items' => [
                'creatinina_comp' => [
                    'value_numeric' => '0.55',
                    'unit' => 'mg/dL',
                    'method' => 'Jaffé cinético',
                    'reference' => '0.50 – 0.95 mg/dL',
                    'classification' => 'normal',
                    'confirm_reference' => '1',
                ],
            ],
        ];

        $this->actingAs($this->createRoleUser('laboratorio'))
            ->post(route('laboratorio.pedidos.resultados.publish', $pedido), $payload)
            ->assertRedirect(route('laboratorio.pedidos.index'));

        $resultado = $pedido->fresh()->resultados()->where('estado', PedidoLaboratorioResultado::ESTADO_PUBLICADO)->firstOrFail();
        $val = LaboratoryResultValue::where('result_id', $resultado->id)->firstOrFail();

        $this->assertSame('femenino', $val->patient_sex_snapshot);
        $this->assertSame('2018-09-20', $val->patient_birth_date_snapshot?->format('Y-m-d'));
        $this->assertSame('dependiente', $val->subject_type);
        $this->assertSame($hijaDependiente->id, $val->subject_id);
    }

    /** 6. Jerarquía estricta de fechas para cálculo de edad (sample_collection -> processing -> order_created_fallback) */
    public function test_age_calculation_date_priority_and_source_tracking(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente', ['fecha_nacimiento' => '2000-01-01']);
        $lab = $this->createRoleUser('laboratorio');

        $pedido = $this->createPedido($doctor, $paciente, ['creatinina']);
        $pedido->update([
            'created_at' => '2026-01-01 10:00:00',
            'processed_at' => '2026-01-03 10:00:00',
            'sample_collected_at' => '2026-01-05 10:00:00',
            'sample_collected_by' => $lab->id,
        ]);

        $payload = [
            'items' => [
                'creatinina_comp' => [
                    'value_numeric' => '0.85',
                    'unit' => 'mg/dL',
                    'method' => 'Jaffé cinético',
                    'reference' => '0.50 – 0.95 mg/dL',
                    'classification' => 'normal',
                ],
            ],
        ];

        $this->actingAs($lab)
            ->post(route('laboratorio.pedidos.resultados.publish', $pedido), $payload)
            ->assertRedirect(route('laboratorio.pedidos.index'));

        $resultado = $pedido->fresh()->resultados()->where('estado', PedidoLaboratorioResultado::ESTADO_PUBLICADO)->firstOrFail();
        $val = LaboratoryResultValue::where('result_id', $resultado->id)->firstOrFail();

        $this->assertSame('sample_collection', $val->age_calculation_source);
        $this->assertSame('2026-01-05', $val->age_calculation_date_snapshot?->format('Y-m-d'));
    }

    /** 7. Operadores numéricos (=, <, >, ≤, ≥) se imprimen correctamente en PDF y vista previa, y Brucella No Reactivo no lleva operador */
    public function test_numeric_result_operators_render_correctly_in_pdf_and_preview(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $lab = $this->createRoleUser('laboratorio');

        // Exámenes: Creatinina, Colesterol LDL, Brucella
        $pedido = $this->createPedido($doctor, $paciente, ['creatinina', 'colesterol_ldl', 'brusella']);

        // Pruebas para distintos operadores: =, <, >, ≤, ≥
        $cases = [
            ['op' => '=', 'num' => '0.9', 'expected_pdf' => '= 0.9'],
            ['op' => '<', 'num' => '5', 'expected_pdf' => '&lt; 5'],
            ['op' => '>', 'num' => '10', 'expected_pdf' => '&gt; 10'],
            ['op' => '≤', 'num' => '100', 'expected_pdf' => '≤ 100'],
            ['op' => '≥', 'num' => '20', 'expected_pdf' => '≥ 20'],
        ];

        foreach ($cases as $case) {
            $payload = [
                'items' => [
                    'creatinina_comp' => [
                        'value_numeric' => $case['num'],
                        'comparator' => $case['op'],
                        'unit' => 'mg/dL',
                        'method' => 'Jaffé cinético',
                        'reference' => '0.7 – 1.3 mg/dL',
                        'classification' => 'normal',
                        'confirm_reference' => '1',
                    ],
                    'colesterol_ldl_comp' => [
                        'value_numeric' => '95',
                        'comparator' => '=',
                        'unit' => 'mg/dL',
                        'method' => 'Enzimático',
                        'reference' => '< 100 mg/dL',
                        'classification' => 'normal',
                        'confirm_reference' => '1',
                    ],
                    'brusella_comp' => [
                        'value_code' => 'no_reactivo',
                        'unit' => 'No aplica',
                        'method' => 'Rosa de Bengala',
                        'reference' => 'No reactivo',
                        'classification' => 'normal',
                        'confirm_reference' => '1',
                    ],
                ],
            ];

            // 1. Verificar Vista Previa (HTML preview)
            $responsePreview = $this->actingAs($lab)
                ->post(route('laboratorio.pedidos.resultados.preview', $pedido), $payload);
            $responsePreview->assertOk();

            // 2. Verificar Publicación
            $responsePublish = $this->actingAs($lab)
                ->post(route('laboratorio.pedidos.resultados.publish', $pedido), $payload);
            $responsePublish->assertRedirect(route('laboratorio.pedidos.index'));

            $resultado = $pedido->fresh()->resultados()
                ->where('estado', PedidoLaboratorioResultado::ESTADO_PUBLICADO)
                ->orderByDesc('version')
                ->firstOrFail();

            $pdfService = app(\App\Services\PedidoLaboratorioPdfService::class);
            $previewHtml = $pdfService->previewHtml($pedido->fresh(), $resultado);

            // Verificar formato en el PDF de vista previa / publicado
            $this->assertStringContainsString($case['expected_pdf'], $previewHtml);
            // Brucella debe ser únicamente 'No Reactivo' sin operador
            $this->assertStringContainsString('No Reactivo', $previewHtml);
            $this->assertStringNotContainsString('= No Reactivo', $previewHtml);
            $this->assertStringNotContainsString('< No Reactivo', $previewHtml);

            // Limpiar resultado borrador/publicado para siguiente iteración
            $pedido->resultados()->delete();
            $pedido->update(['resultado_path' => null, 'resultado_publicado_at' => null]);
        }
    }

    /** 8. El operador se conserva al guardar borrador y volver a abrir el formulario */
    public function test_operator_is_retained_when_saving_and_reopening_draft(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $lab = $this->createRoleUser('laboratorio');

        $pedido = $this->createPedido($doctor, $paciente, ['creatinina', 'colesterol_ldl']);

        $draftPayload = [
            'items' => [
                'creatinina_comp' => [
                    'value_numeric' => '0.9',
                    'comparator' => '<',
                    'unit' => 'mg/dL',
                    'method' => 'Jaffé cinético',
                    'reference' => '0.7 – 1.3 mg/dL',
                    'classification' => 'normal',
                ],
                'colesterol_ldl_comp' => [
                    'value_numeric' => '95',
                    'comparator' => '≤',
                    'unit' => 'mg/dL',
                    'method' => 'Enzimático',
                    'reference' => '< 100 mg/dL',
                    'classification' => 'normal',
                ],
            ],
        ];

        $this->actingAs($lab)
            ->post(route('laboratorio.pedidos.resultados.draft', $pedido), $draftPayload)
            ->assertRedirect(route('laboratorio.pedidos.resultados.form', $pedido));

        // Reabrir formulario
        $response = $this->actingAs($lab)
            ->get(route('laboratorio.pedidos.resultados.form', $pedido));

        $response->assertOk();
        $response->assertSee('<option value="<" selected>&lt;</option>', false);
        $response->assertSee('<option value="≤" selected>&le;</option>', false);
    }

    /** 9. Los PDFs publicados anteriormente no se modifican y R2 permanece intacto */
    public function test_previously_published_pdfs_and_r2_storage_remain_unmodified(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $lab = $this->createRoleUser('laboratorio');

        $pedido = $this->createPedido($doctor, $paciente, ['creatinina']);

        $fakePdfContent = '%PDF-1.4 Fake Published PDF V1 Content';
        $r2Disk = Storage::disk('r2_private');
        $r2Path = "documents/laboratory-results/999/fake_v1.pdf";
        $r2Disk->put($r2Path, $fakePdfContent);

        $resultadoV1 = $pedido->resultados()->create([
            'version' => 1,
            'estado' => PedidoLaboratorioResultado::ESTADO_PUBLICADO,
            'pdf_path' => $r2Path,
            'pdf_disk' => 'r2_private',
            'resultado_items' => [
                [
                    'key' => 'creatinina',
                    'nombre' => 'Creatinina',
                    'resultado' => '0.9', // antiguo sin operador
                    'unidad' => 'mg/dL',
                    'referencia' => '0.7 – 1.3',
                    'clasificacion' => 'normal',
                    'metodo' => 'Jaffé cinético',
                ],
            ],
            'publicado_at' => now(),
            'laboratorio_id' => $lab->id,
        ]);

        $this->assertSame($fakePdfContent, $r2Disk->get($r2Path));

        // Publicar una V2 (nueva versión)
        $v2Payload = [
            'items' => [
                'creatinina_comp' => [
                    'value_numeric' => '0.9',
                    'comparator' => '=',
                    'unit' => 'mg/dL',
                    'method' => 'Jaffé cinético',
                    'reference' => '0.7 – 1.3 mg/dL',
                    'classification' => 'normal',
                    'confirm_reference' => '1',
                ],
            ],
        ];

        $this->actingAs($lab)
            ->post(route('laboratorio.pedidos.resultados.publish', $pedido), $v2Payload)
            ->assertRedirect(route('laboratorio.pedidos.index'));

        // El PDF de V1 en R2 debe permanecer 100% inalterado
        $this->assertSame($fakePdfContent, $r2Disk->get($r2Path));

        // El resultado V1 histórico mantiene sus datos sin ser modificado
        $this->assertSame('0.9', $resultadoV1->fresh()->resultado_items[0]['resultado']);
    }
}
