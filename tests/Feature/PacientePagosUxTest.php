<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PacientePagosUxTest extends TestCase
{
    use DatabaseTransactions;

    private function createRoleUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['label' => ucfirst($roleName)]);
        $user = User::factory()->create([
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function createPagoWithToken(User $paciente, string $estado = Pago::ESTADO_PENDIENTE): Pago
    {
        $doctor = $this->createRoleUser('doctor');
        $especialidad = Especialidad::create([
            'nombre' => 'Medicina General ' . Str::random(5),
            'descripcion' => 'General',
            'activo' => true,
        ]);

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => Carbon::today()->toDateString(),
            'hora' => '10:00:00',
            'motivo' => 'Consulta de control',
            'estado' => Cita::ESTADO_REALIZADA,
        ]);

        $uniqueSuffix = Str::random(10);

        return Pago::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'monto' => 35.00,
            'moneda' => 'USD',
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => $estado,
            'folio_unico' => 'OC-' . $uniqueSuffix,
            'token_publico' => 'testtokenpublico' . $uniqueSuffix,
            'csv' => 'OC-' . $uniqueSuffix,
        ]);
    }

    /**
     * TEST 1 (UX-02 RED): The patient payments list must display "Verificar en línea"
     * instead of the technical jargon "Abrir token", while keeping the exact same route and attributes.
     */
    public function test_pending_payment_renders_verificar_en_linea_and_not_abrir_token(): void
    {
        config(['app.mode' => 'production']);
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoWithToken($paciente, Pago::ESTADO_PENDIENTE);

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.index'));

        $response->assertOk();
        $response->assertSee(route('pagos.token.show', $pago->token_publico));
        $response->assertSee('Verificar en línea');
        $response->assertDontSee('Abrir token');
    }

    /**
     * TEST 2: Payment without public token does NOT render verification action.
     */
    public function test_payment_without_token_does_not_render_verification_button(): void
    {
        config(['app.mode' => 'production']);
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoWithToken($paciente, Pago::ESTADO_PENDIENTE);
        $pago->update(['token_publico' => null]);

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.index'));

        $response->assertOk();
        $response->assertDontSee('Verificar en línea');
        $response->assertDontSee('Abrir token');
    }

    /**
     * TEST 3: Paid payment renders "Descargar recibo" as an independent action.
     */
    public function test_paid_payment_renders_descargar_recibo_distinct_from_verification(): void
    {
        config(['app.mode' => 'production']);
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoWithToken($paciente, Pago::ESTADO_PAGADO);

        PaymentReceipt::create([
            'pago_id' => $pago->id,
            'folio_recibo' => 'REC-20260903-000001',
            'monto' => 35.00,
            'metodo_pago' => 'efectivo',
            'pdf_path' => 'receipts/test.pdf',
            'pdf_disk' => 'local',
            'emitido_en' => now(),
        ]);

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.index'));

        $response->assertOk();
        $response->assertSee('Descargar recibo');
        $response->assertSee(route('paciente.pagos.recibo.pdf', $pago));
        // Paid payment does not show order/verification button in current design
        $response->assertDontSee('Abrir token');
    }

    /**
     * TEST 4: Payment with proof renders "Ver comprobante" independently.
     */
    public function test_payment_with_proof_renders_ver_comprobante_distinctly(): void
    {
        config(['app.mode' => 'production']);
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoWithToken($paciente, Pago::ESTADO_EN_VERIFICACION);
        $pago->update(['comprobante_path' => 'proofs/test.jpg']);

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.index'));

        $response->assertOk();
        $response->assertSee('Ver comprobante');
        $response->assertSee(route('paciente.pagos.comprobante', $pago));
    }

    /**
     * TEST 5: Demo mode uses the canonical view and also renders "Verificar en línea".
     */
    public function test_demo_mode_renders_verificar_en_linea(): void
    {
        config(['app.mode' => 'demo']);
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoWithToken($paciente, Pago::ESTADO_PENDIENTE);

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.index'));

        $response->assertOk();
        $response->assertSee(route('pagos.token.show', $pago->token_publico));
        $response->assertSee('Verificar en línea');
        $response->assertDontSee('Abrir token');
    }

    /**
     * TEST 6 (UX-05 RED): The patient payments list must display "Pagos y recibos"
     * as its main title and header, replacing "Órdenes de cobro" as module name,
     * while preserving document action "Descargar orden" and "Verificar en línea".
     */
    public function test_patient_pagos_index_renders_pagos_y_recibos_header_and_title(): void
    {
        config(['app.mode' => 'production']);
        $paciente = $this->createRoleUser('paciente');
        $pago = $this->createPagoWithToken($paciente, Pago::ESTADO_PENDIENTE);

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.index'));

        $response->assertOk();
        $response->assertSee('<h1 class="text-lg font-semibold text-gray-900">Pagos y recibos</h1>', false);
        $response->assertSee('<title>Pagos y recibos</title>', false);
        $response->assertSee('Pagos y recibos');
        // Document action "Descargar orden" must still be preserved
        $response->assertSee('Descargar orden');
        // Verification action from UX-02 must still be preserved
        $response->assertSee('Verificar en línea');
    }

    /**
     * TEST 7 (UX-05 RED): Patient sidebar renders "Pagos y recibos" with active state.
     */
    public function test_patient_sidebar_renders_pagos_y_recibos_with_active_state(): void
    {
        config(['app.mode' => 'production']);
        $paciente = $this->createRoleUser('paciente');
        $this->createPagoWithToken($paciente, Pago::ESTADO_PENDIENTE);

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.index'));

        $response->assertOk();
        $response->assertSee('href="' . route('paciente.pagos.index') . '"', false);
        $response->assertSee('Pagos y recibos');
        // Ensure the sidebar navigation link does not say "Órdenes de cobro"
        $content = $response->getContent();
        $this->assertStringContainsString('Pagos y recibos', $content);
        $this->assertStringNotContainsString('i class="ri-wallet-3-line text-lg"></i> Órdenes de cobro', $content);
    }

    /**
     * TEST 8 (UX-05 RED): Demo mode uses canonical view and renders "Pagos y recibos".
     */
    public function test_demo_mode_renders_pagos_y_recibos_consistently(): void
    {
        config(['app.mode' => 'demo']);
        $paciente = $this->createRoleUser('paciente');
        $this->createPagoWithToken($paciente, Pago::ESTADO_PENDIENTE);

        $response = $this->actingAs($paciente)->get(route('paciente.pagos.index'));

        $response->assertOk();
        $response->assertSee('<h1 class="text-lg font-semibold text-gray-900">Pagos y recibos</h1>', false);
        $response->assertSee('Pagos y recibos');
        $content = $response->getContent();
        $this->assertStringNotContainsString('i class="ri-wallet-3-line text-lg"></i> Órdenes de cobro', $content);
        $response->assertSee('Verificar en línea');
    }
}
