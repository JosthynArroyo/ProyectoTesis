<?php

namespace Tests\Feature;

use App\Models\LaboratoryComponent;
use App\Models\LaboratoryExam;
use App\Models\LaboratoryExamComponent;
use App\Models\LaboratoryReferenceRange;
use App\Models\User;
use App\Services\LabTestCatalogConfigService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LabTestZeroNumericLimitsTest extends TestCase
{
    use DatabaseTransactions;

    private LabTestCatalogConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LabTestCatalogConfigService::class);
    }

    /**
     * Test Z1 & Z5: lower_limit = 0 / 0.0 no se convierte en null y preserva el rango [0.0 - 10.0].
     */
    public function test_z1_and_z5_lower_limit_zero_is_preserved_as_float_zero_and_not_null(): void
    {
        [$pedido, $exam, $component] = $this->createScenarioWithRange([
            'lower_limit' => 0.0,
            'upper_limit' => 10.0,
            'critical_lower_limit' => 0.0,
            'critical_upper_limit' => 15.0,
        ]);

        $structure = $this->service->resolvePedidoItems($pedido);
        $compConfig = $structure[0]['components'][0];

        // Se deben conservar exactamente 0.0 y no null
        $this->assertSame(0.0, $compConfig['lower_limit']);
        $this->assertSame(10.0, $compConfig['upper_limit']);
        $this->assertSame(0.0, $compConfig['critical_lower']);
        $this->assertSame(15.0, $compConfig['critical_upper']);
    }

    /**
     * Test Z2: upper_limit = 0.0 se conserva como 0.0 y no se convierte en null.
     */
    public function test_z2_upper_limit_zero_is_preserved_as_float_zero(): void
    {
        [$pedido, $exam, $component] = $this->createScenarioWithRange([
            'reference_type' => 'upper_limit',
            'lower_limit' => null,
            'upper_limit' => 0.0,
            'critical_lower_limit' => null,
            'critical_upper_limit' => 0.0,
        ]);

        $structure = $this->service->resolvePedidoItems($pedido);
        $compConfig = $structure[0]['components'][0];

        $this->assertNull($compConfig['lower_limit']);
        $this->assertSame(0.0, $compConfig['upper_limit']);
        $this->assertSame(0.0, $compConfig['critical_upper']);
    }

    /**
     * Test Z3: lower_limit = null permanece null (ausencia real).
     */
    public function test_z3_real_null_remains_null(): void
    {
        [$pedido, $exam, $component] = $this->createScenarioWithRange([
            'reference_type' => 'upper_limit',
            'lower_limit' => null,
            'upper_limit' => 5.0,
        ]);

        $structure = $this->service->resolvePedidoItems($pedido);
        $compConfig = $structure[0]['components'][0];

        $this->assertNull($compConfig['lower_limit']);
        $this->assertSame(5.0, $compConfig['upper_limit']);
    }

    /**
     * Test Z4: Valores positivos estándar funcionan correctamente.
     */
    public function test_z4_standard_positive_limits_continue_working(): void
    {
        [$pedido, $exam, $component] = $this->createScenarioWithRange([
            'lower_limit' => 70.0,
            'upper_limit' => 110.0,
            'critical_lower_limit' => 45.0,
            'critical_upper_limit' => 400.0,
        ]);

        $structure = $this->service->resolvePedidoItems($pedido);
        $compConfig = $structure[0]['components'][0];

        $this->assertSame(70.0, $compConfig['lower_limit']);
        $this->assertSame(110.0, $compConfig['upper_limit']);
        $this->assertSame(45.0, $compConfig['critical_lower']);
        $this->assertSame(400.0, $compConfig['critical_upper']);
    }

    /**
     * Test Z6: Valores negativos (como exceso de base -2 a +2) se preservan correctamente.
     */
    public function test_z6_negative_numeric_limits_are_preserved(): void
    {
        [$pedido, $exam, $component] = $this->createScenarioWithRange([
            'lower_limit' => -2.0,
            'upper_limit' => 2.0,
        ]);

        $structure = $this->service->resolvePedidoItems($pedido);
        $compConfig = $structure[0]['components'][0];

        $this->assertSame(-2.0, $compConfig['lower_limit']);
        $this->assertSame(2.0, $compConfig['upper_limit']);
    }

    /**
     * Test Z7: Cero almacenado como string "0" o "0.0000" en decimal DB se normaliza a float 0.0.
     */
    public function test_z7_string_decimal_zero_from_database_is_normalized_to_float_zero(): void
    {
        [$pedido, $exam, $component] = $this->createScenarioWithRange([
            'lower_limit' => '0.0000',
            'upper_limit' => '5.5000',
        ]);

        $structure = $this->service->resolvePedidoItems($pedido);
        $compConfig = $structure[0]['components'][0];

        $this->assertSame(0.0, $compConfig['lower_limit']);
        $this->assertSame(5.5, $compConfig['upper_limit']);
    }

    private function createScenarioWithRange(array $rangeOverrides = []): array
    {
        $code = 'test_zero_limit_' . uniqid();

        $patient = User::factory()->create(['sexo' => 'Masculino', 'fecha_nacimiento' => '1990-01-01']);

        $exam = LaboratoryExam::query()->create([
            'code' => $code,
            'name' => 'Examen de Prueba Límites',
            'category' => 'PRUEBAS',
            'is_panel' => false,
            'active' => true,
        ]);

        $component = LaboratoryComponent::query()->create([
            'code' => $code . '_comp',
            'name' => 'Componente Límites',
            'result_type' => 'numeric',
            'default_unit' => 'mg/dL',
            'default_method' => 'Método Test',
            'authorized_methods' => ['Método Test'],
            'validation_status' => 'provisional',
            'active' => true,
        ]);

        LaboratoryExamComponent::query()->create([
            'exam_id' => $exam->id,
            'component_id' => $component->id,
            'display_order' => 1,
            'required' => true,
        ]);

        LaboratoryReferenceRange::query()->create(array_merge([
            'component_id' => $component->id,
            'sex' => 'both',
            'reference_type' => 'interval',
            'minimum_age_days' => 0,
            'maximum_age_days' => 36500,
            'method' => 'Método Test',
            'lower_limit' => 0.0,
            'upper_limit' => 10.0,
            'validation_status' => 'provisional',
            'reference_text' => null,
        ], $rangeOverrides));

        $pedido = \App\Models\PedidoLaboratorio::query()->create([
            'paciente_id' => $patient->id,
            'doctor_id' => $patient->id,
            'csv' => 'CSV-ZERO-' . uniqid(),
            'examenes' => [$code],
            'estado' => \App\Models\PedidoLaboratorio::ESTADO_PENDIENTE_TOMA,
        ]);

        return [$pedido, $exam, $component];
    }
}
