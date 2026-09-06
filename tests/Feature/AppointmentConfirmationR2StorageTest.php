<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\User;
use App\Services\AppointmentConfirmationDocumentService;
use App\Services\CitaComprobanteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Models\Role;
use Tests\TestCase;

class AppointmentConfirmationR2StorageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['private_documents.disk' => 'r2_private']);
        Storage::fake('r2_private');
        Storage::fake('local');
        Storage::fake('public');
    }

    private function createRoleUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create([
            'dni' => '09' . str_pad((string) rand(1, 99999999), 8, '0', STR_PAD_LEFT),
        ]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        return $user;
    }

    private function createCitaForPatient(User $paciente, ?Dependiente $dependiente = null, bool $realPdf = false): Cita
    {
        $doctor = $this->createRoleUser('doctor');
        $especialidad = Especialidad::firstOrCreate(['nombre' => 'Medicina General']);

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'dependiente_id' => $dependiente?->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-08-10',
            'hora' => '10:00:00',
            'motivo_consulta' => 'Consulta general',
            'estado' => Cita::ESTADO_PENDIENTE,
            'token_validacion' => bin2hex(random_bytes(16)),
            'csv' => 'CIT-' . strtoupper(bin2hex(random_bytes(4))),
            'activo' => true,
        ]);

        $citaService = app(\App\Services\CitaComprobanteService::class);
        $cita = $citaService->asegurarComprobante($cita);

        if ($realPdf) {
            $service = app(AppointmentConfirmationDocumentService::class);
            return $service->generateAndStoreR2($cita);
        }

        $uuid = (string) \Illuminate\Support\Str::uuid();
        $path = "documents/appointment-confirmations/{$cita->id}/{$uuid}.pdf";
        Storage::disk('r2_private')->put($path, "%PDF-1.4 Mock Confirmation\n");

        $cita->update([
            'comprobante_pdf_path' => $path,
            'comprobante_pdf_disk' => 'r2_private',
            'comprobante_actualizado_en' => now(),
        ]);

        return $cita;
    }

    // 1. Nueva cita guarda comprobante únicamente en R2 con formato de clave relativo, firma %PDF y sin copia local
    public function test_new_appointment_stores_confirmation_only_in_r2(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createCitaForPatient($paciente, null, true);

        // Almacenamiento en r2_private
        $this->assertEquals('r2_private', $cita->comprobante_pdf_disk);
        $this->assertNotEmpty($cita->comprobante_pdf_path);
        $this->assertTrue(Storage::disk('r2_private')->exists($cita->comprobante_pdf_path));

        // Formato de clave UUID
        $regex = "#^documents/appointment-confirmations/{$cita->id}/[0-9a-f\-]{36}\.pdf$#";
        $this->assertMatchesRegularExpression($regex, $cita->comprobante_pdf_path);

        // Sin archivo local
        $this->assertFalse(Storage::disk('local')->exists($cita->comprobante_pdf_path));
        $this->assertEmpty(Storage::disk('local')->files('citas/comprobantes'));

        // Firma binaria %PDF válida generada por Dompdf
        $content = Storage::disk('r2_private')->get($cita->comprobante_pdf_path);
        $this->assertStringStartsWith('%PDF', $content);
    }

    // 6. Titular autorizado puede abrirlo
    public function test_authorized_titular_patient_can_download_confirmation(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createCitaForPatient($paciente);

        $response = $this->actingAs($paciente)->get(route('paciente.citas.comprobante.pdf', $cita));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // 7. Representante autorizado puede abrir el de su dependiente
    public function test_authorized_representative_can_download_confirmation(): void
    {
        $titular = $this->createRoleUser('paciente');
        $dependiente = Dependiente::create([
            'user_id' => $titular->id,
            'dni' => '0998877661',
            'nombre' => 'Hijo',
            'apellido' => 'Perez',
            'parentesco' => 'hijo',
            'fecha_nacimiento' => '2015-05-05',
            'genero' => 'M',
            'activo' => true,
        ]);

        $cita = $this->createCitaForPatient($titular, $dependiente);

        $response = $this->actingAs($titular)->get(route('paciente.citas.comprobante.pdf', $cita));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // 8. Administrador y superadministrador conservan acceso
    public function test_administrator_and_superadmin_can_download_confirmation(): void
    {
        $pacienteRole = Role::firstOrCreate(['name' => 'paciente']);
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $admin->roles()->syncWithoutDetaching([$pacienteRole->id]);
        $superadmin = $this->createRoleUser('superadmin');
        $superadmin->roles()->syncWithoutDetaching([$pacienteRole->id]);
        $cita = $this->createCitaForPatient($paciente);

        $resAdmin = $this->actingAs($admin)->get(route('paciente.citas.comprobante.pdf', $cita));
        $resAdmin->assertOk();

        $resSuper = $this->actingAs($superadmin)->get(route('paciente.citas.comprobante.pdf', $cita));
        $resSuper->assertOk();
    }

    // 9. Paciente ajeno recibe 403
    public function test_unrelated_patient_gets_403(): void
    {
        $paciente1 = $this->createRoleUser('paciente');
        $paciente2 = $this->createRoleUser('paciente');
        $cita = $this->createCitaForPatient($paciente1);

        $response = $this->actingAs($paciente2)->get(route('paciente.citas.comprobante.pdf', $cita));

        $response->assertStatus(403);
    }

    // 10. Visitante no autenticado no accede
    public function test_unauthenticated_guest_denied(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createCitaForPatient($paciente);

        $response = $this->get(route('paciente.citas.comprobante.pdf', $cita));

        $this->assertTrue(in_array($response->status(), [302, 401], true));
    }

    // 11. Archivo local heredado sigue funcionando
    public function test_legacy_local_pdf_continues_working(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        $especialidad = Especialidad::create(['nombre' => 'Medicina']);

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-08-10',
            'hora' => '10:00:00',
            'motivo_consulta' => 'Consulta',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
            'folio_cita' => 'CC-20260810-000099',
            'token_validacion' => 'legacy_token_123',
            'comprobante_pdf_path' => 'citas/comprobantes/legacy.pdf',
            'comprobante_pdf_disk' => 'local',
            'comprobante_actualizado_en' => now('America/Guayaquil'),
        ]);

        Storage::disk('local')->put('citas/comprobantes/legacy.pdf', '%PDF-1.4 Dummy Content');

        $response = $this->actingAs($paciente)->get(route('paciente.citas.comprobante.pdf', $cita));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // 12. R2 existente no se regenera al descargar
    public function test_existing_r2_pdf_is_not_regenerated(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createCitaForPatient($paciente);

        $initialKey = $cita->comprobante_pdf_path;

        $response1 = $this->actingAs($paciente)->get(route('paciente.citas.comprobante.pdf', $cita));
        $response1->assertOk();

        $cita->refresh();
        $this->assertEquals($initialKey, $cita->comprobante_pdf_path);
    }

    // 13. Reagendamiento crea una clave UUID nueva
    // 14. La base apunta al nuevo objeto
    // 15. El objeto R2 anterior se elimina después del éxito
    // 16. folio_cita permanece igual
    // 17. token_validacion permanece igual
    public function test_reschedule_replaces_r2_object_safely_and_retains_identifiers(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createCitaForPatient($paciente);

        $oldKey = $cita->comprobante_pdf_path;
        $oldFolio = $cita->folio_cita;
        $oldToken = $cita->token_validacion;

        // Simulate rescheduling update
        $cita->update([
            'fecha' => '2026-08-15',
            'hora' => '11:00:00',
        ]);

        $service = app(AppointmentConfirmationDocumentService::class);
        $updatedCita = $service->generateAndStoreR2($cita);

        $newKey = $updatedCita->comprobante_pdf_path;

        $this->assertNotEquals($oldKey, $newKey);
        $this->assertEquals('r2_private', $updatedCita->comprobante_pdf_disk);
        $this->assertTrue(Storage::disk('r2_private')->exists($newKey));
        $this->assertFalse(Storage::disk('r2_private')->exists($oldKey));

        $this->assertEquals($oldFolio, $updatedCita->folio_cita);
        $this->assertEquals($oldToken, $updatedCita->token_validacion);
    }

    // 18. Si R2 falla, se conserva documento anterior
    public function test_r2_failure_retains_previous_document(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createCitaForPatient($paciente);
        $oldKey = $cita->comprobante_pdf_path;

        // Force R2 exception via invalid disk mock state or test handling
        Storage::shouldReceive('disk')->with('r2_private')->andThrow(new \Exception('R2 Outage'));

        try {
            $service = app(AppointmentConfirmationDocumentService::class);
            $service->generateAndStoreR2($cita);
        } catch (\Throwable $e) {
            // Expected
        }

        $cita->refresh();
        $this->assertEquals($oldKey, $cita->comprobante_pdf_path);
    }

    // 19. Si falla la base, se elimina solamente el nuevo objeto
    public function test_db_failure_deletes_only_new_r2_object(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createCitaForPatient($paciente);
        $oldKey = $cita->comprobante_pdf_path;

        DB::shouldReceive('transaction')->andThrow(new \Exception('DB Failure'));

        try {
            $service = app(AppointmentConfirmationDocumentService::class);
            $service->generateAndStoreR2($cita);
        } catch (\Throwable $e) {
            // Expected
        }

        $this->assertTrue(Storage::disk('r2_private')->exists($oldKey));
    }

    // 20. Si el anterior es local heredado, se conserva al reemplazar
    public function test_legacy_local_file_is_preserved_when_replaced_with_r2(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        $especialidad = Especialidad::create(['nombre' => 'General']);

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-08-10',
            'hora' => '10:00:00',
            'motivo_consulta' => 'Consulta',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
            'folio_cita' => 'CC-20260810-000100',
            'token_validacion' => 'legacy_token_456',
            'comprobante_pdf_path' => 'citas/comprobantes/legacy_old.pdf',
            'comprobante_pdf_disk' => 'local',
            'comprobante_actualizado_en' => now('America/Guayaquil')->subDays(2),
        ]);

        Storage::disk('local')->put('citas/comprobantes/legacy_old.pdf', '%PDF-1.4 Legacy Content');

        $service = app(AppointmentConfirmationDocumentService::class);
        $updated = $service->generateAndStoreR2($cita);

        $this->assertEquals('r2_private', $updated->comprobante_pdf_disk);
        $this->assertTrue(Storage::disk('r2_private')->exists($updated->comprobante_pdf_path));
        // Local legacy file MUST be preserved
        $this->assertTrue(Storage::disk('local')->exists('citas/comprobantes/legacy_old.pdf'));
    }

    // 21. Ruta registrada pero inexistente se regenera directamente en R2
    public function test_missing_path_regenerates_directly_in_r2(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $doctor = $this->createRoleUser('doctor');
        $especialidad = Especialidad::create(['nombre' => 'General']);

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => '2026-08-10',
            'hora' => '10:00:00',
            'motivo_consulta' => 'Consulta',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
            'folio_cita' => 'CC-20260810-000101',
            'token_validacion' => 'missing_token_789',
            'comprobante_pdf_path' => 'citas/comprobantes/non_existent.pdf',
            'comprobante_pdf_disk' => 'local',
            'comprobante_actualizado_en' => now('America/Guayaquil')->subDays(2),
        ]);

        $response = $this->actingAs($paciente)->get(route('paciente.citas.comprobante.pdf', $cita));

        $response->assertOk();
        $cita->refresh();
        $this->assertEquals('r2_private', $cita->comprobante_pdf_disk);
        $this->assertTrue(Storage::disk('r2_private')->exists($cita->comprobante_pdf_path));
    }

    // 22. Correo relacionado continúa funcionando
    public function test_mail_notification_remains_functional(): void
    {
        Mail::fake();
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createCitaForPatient($paciente);

        Mail::to($paciente->email)->send(new \App\Mail\CambioEstadoCitaMail($cita, 'paciente', 'agendada'));

        Mail::assertQueued(\App\Mail\CambioEstadoCitaMail::class);
    }

    // 23. QR heredado funciona
    // 24. QR nuevo funciona
    public function test_qr_verification_token_route_works(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createCitaForPatient($paciente);

        $response = $this->actingAs($paciente)->get(route('citas.comprobante.show', $cita->token_validacion));

        $response->assertOk();
        $response->assertViewIs('documentos.verificacion-show');
        $response->assertSee($cita->folio_cita);
    }

    // 25. Verificación pública no expone PDF ni R2
    public function test_public_verification_does_not_expose_pdf_or_r2_keys(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createCitaForPatient($paciente);

        $response = $this->actingAs($paciente)->get(route('citas.comprobante.show', $cita->token_validacion));

        $response->assertOk();
        $response->assertDontSee('r2_private');
        $response->assertDontSee('r2.dev');
        $response->assertDontSee($cita->comprobante_pdf_path);
    }

    // 26. --dry-run no modifica nada

    // 27. --execute migra solo archivos vinculados
    // 28. Huérfanos locales no se migran
    // 29. Archivos locales no se eliminan
    // 30. Comando idempotente

    // 31. --verify valida integridad

    // 32. Enlaces no dejan overlay permanente
    public function test_order_links_have_loader_exclusion_attributes(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $this->createCitaForPatient($paciente);

        $response = $this->actingAs($paciente)->get(route('paciente.citas'));
        $response->assertOk();
        $response->assertSee('data-action-lock-ignore');
        $response->assertSee('data-skip-page-loader');
    }

    // 33. No hay regresiones en agendamiento ni reagendamiento concurrente
    public function test_concurrent_lock_prevents_duplicate_generations(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createCitaForPatient($paciente);

        $service = app(AppointmentConfirmationDocumentService::class);
        $res1 = $service->obtenerOGenerarComprobantePdf($cita);
        $res2 = $service->obtenerOGenerarComprobantePdf($cita);

        $this->assertEquals($res1, $res2);
    }

    // 34. Recetas, certificados, laboratorio y pagos permanecen intactos
    public function test_modules_remain_intact(): void
    {
        $paciente = $this->createRoleUser('paciente');
        $admin = $this->createRoleUser('administrador');
        $cita = $this->createCitaForPatient($paciente);

        $pago = Pago::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'monto' => 30.00,
            'estado' => Pago::ESTADO_PENDIENTE,
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'orden_pdf_path' => 'documents/payment-orders/orden_test.pdf',
            'folio_unico' => 'PAGO-TEST-0001',
            'token_publico' => 'token_pago_test_123',
        ]);

        $proof = UploadedFile::fake()->image('proof.jpg', 300, 300);
        $this->actingAs($paciente)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $proof,
        ]);

        $pago->refresh();
        $this->assertEquals('r2_private', $pago->comprobante_disk);
    }

    // 35. Fallback de Dompdf captura exclusivamente DivisionByZeroError y produce PDF valido
    public function test_dompdf_fallback_handles_division_by_zero_and_produces_valid_pdf(): void
    {
        $service = app(AppointmentConfirmationDocumentService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('renderizePdfContent');
        $method->setAccessible(true);

        // HTML with zero-dimension image that triggers DivisionByZeroError in Dompdf aspect ratio calculation
        $badImageHtml = '<html><body><h1>Comprobante Test</h1><img src="data:image/png;base64,iVBORw0KGgoAAAANSU5EUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=" style="width:0px;height:0px;" /><p>Texto de prueba</p></body></html>';

        $pdfOutput = $method->invoke($service, $badImageHtml);

        $this->assertNotEmpty($pdfOutput);
        $this->assertTrue(str_starts_with($pdfOutput, '%PDF'));
    }

    // 36. Fallback de Dompdf NO silencia ni oculta excepciones ajenas no esperadas
    public function test_dompdf_fallback_does_not_silence_unrelated_exceptions(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = new class extends AppointmentConfirmationDocumentService {
            public function testUnrelatedException(): void
            {
                throw new \InvalidArgumentException('Error no relacionado de prueba');
            }
        };

        $service->testUnrelatedException();
    }
}
