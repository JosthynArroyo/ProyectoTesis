<?php

namespace Tests\Feature;

use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Services\DemoExternalEffectsGuard;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoPatientReceiptDownloadTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<string> */
    private array $generatedReceiptPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->generatedReceiptPaths as $path) {
            if (str_starts_with($path, 'documents/payment-receipts/')) {
                Storage::disk('local')->delete($path);
            }
        }

        parent::tearDown();
    }

    public function test_javier_can_download_a_seeded_receipt_after_storage_is_resolved_again(): void
    {
        config([
            'app.env' => 'local',
            'app.mode' => 'demo',
            'private_documents.disk' => 'local',
            'private_documents.persist_demo_receipts_in_tests' => true,
        ]);
        Storage::fake('r2_private');
        Storage::forgetDisk('local');
        app(DemoExternalEffectsGuard::class)->guardStorageDisks();

        $this->seed(DemoSeeder::class);

        $javier = User::where('email', 'paciente@demo-clinigest.test')->firstOrFail();
        $this->generatedReceiptPaths = PaymentReceipt::query()
            ->whereHas('pago', fn ($query) => $query->where('paciente_id', $javier->id))
            ->whereNotNull('pdf_path')
            ->pluck('pdf_path')
            ->all();
        $payments = Pago::query()
            ->with('receipt')
            ->where('paciente_id', $javier->id)
            ->where('estado', Pago::ESTADO_PAGADO)
            ->orderBy('id')
            ->get();
        $this->assertGreaterThanOrEqual(2, $payments->count());
        $payment = $payments->firstOrFail();

        $this->actingAs($javier)
            ->get(route('paciente.pagos.index'))
            ->assertOk()
            ->assertSee('Descargar recibo')
            ->assertSee(route('paciente.pagos.recibo.pdf', $payment));

        Storage::forgetDisk('local');
        app(DemoExternalEffectsGuard::class)->guardStorageDisks();

        foreach ($payments as $paidPayment) {
            $receipt = $paidPayment->receipt;
            $this->assertNotNull($receipt);
            $this->assertSame('local', $receipt->pdf_disk);
            $this->assertNotEmpty($receipt->pdf_path);
            $this->assertTrue(Storage::disk('local')->exists($receipt->pdf_path));
        }

        $response = $this->actingAs($javier)
            ->get(route('paciente.pagos.recibo.pdf', $payment));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline; filename="recibo_pago_', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());

        $this->assertSame([], Storage::disk('r2_private')->allFiles());

        $otherPatient = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'paciente'))
            ->whereKeyNot($javier->id)
            ->firstOrFail();

        $this->actingAs($otherPatient)
            ->get(route('paciente.pagos.recibo.pdf', $payment))
            ->assertForbidden();
    }

    public function test_demo_payment_receipt_seeding_preserves_financial_identifiers(): void
    {
        config([
            'app.mode' => 'demo',
            'private_documents.disk' => 'local',
        ]);

        $this->seed(DemoSeeder::class);
        $before = $this->canonicalReceiptIdentifiers();

        $this->seed(DemoSeeder::class);

        $this->assertSame($before, $this->canonicalReceiptIdentifiers());
    }

    /** @return array<int, array<string, int|string|null>> */
    private function canonicalReceiptIdentifiers(): array
    {
        $javier = User::where('email', 'paciente@demo-clinigest.test')->firstOrFail();

        return Pago::query()
            ->with('receipt')
            ->where('paciente_id', $javier->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Pago $payment) => [
                'payment_id' => $payment->id,
                'payment_token' => $payment->token_publico,
                'transaction_reference' => $payment->referencia_transaccion,
                'receipt_id' => $payment->receipt?->id,
                'receipt_token' => $payment->receipt?->verification_token,
                'receipt_csv' => $payment->receipt?->csv,
                'receipt_path' => $payment->receipt?->pdf_path,
                'receipt_disk' => $payment->receipt?->pdf_disk,
            ])
            ->all();
    }
}
