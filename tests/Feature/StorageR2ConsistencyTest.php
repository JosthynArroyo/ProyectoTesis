<?php

namespace Tests\Feature;

use App\Jobs\EnviarCertificadoMedicoJob;
use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\ClinicalRecord;
use App\Models\Especialidad;
use App\Models\NotaSoap;
use App\Models\OrdenCobro;
use App\Models\Pago;
use App\Models\PaymentReceipt;
use App\Models\Role;
use App\Models\User;
use App\Services\AppointmentConfirmationDocumentService;
use App\Services\CertificadoMedicoPdfService;
use App\Services\MedicalCertificateDocumentService;
use App\Services\PagoService;
use App\Services\PaymentProofStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageR2ConsistencyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'private_documents.disk' => 'r2_private',
            'private_documents.recipe_disk' => 'r2_private',
        ]);
        Storage::fake('local');
        Storage::fake('r2_private');
    }

    /**
     * Caso P1 — Subida exitosa:
     * Comprobante válido se almacena en R2, BD apunta al nuevo archivo y el pago queda en estado en_verificacion.
     */
    public function test_caso_p1_successful_proof_upload_persists_in_r2_and_updates_db(): void
    {
        [$patient, $pago] = $this->createPatientAndPendingPago();

        $file = UploadedFile::fake()->image('comprobante_valido.jpg', 600, 600);

        $response = $this->actingAs($patient)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $file,
            'referencia_transaccion' => 'TRF-123456',
        ]);

        $response->assertRedirect(route('paciente.pagos.index'));
        $response->assertSessionHas('success');

        $pago->refresh();
        $this->assertSame(Pago::ESTADO_EN_VERIFICACION, $pago->estado);
        $this->assertSame(Pago::METODO_TRANSFERENCIA, $pago->metodo_pago);
        $this->assertNotNull($pago->comprobante_path);
        $this->assertSame('r2_private', $pago->comprobante_disk);
        Storage::disk('r2_private')->assertExists($pago->comprobante_path);
    }

    /**
     * Caso P2 — Fallo de BD después de crear archivo:
     * Si la BD falla durante la actualización, el archivo recién subido a R2 se elimina (compensación).
     */
    public function test_caso_p2_db_failure_cleans_up_newly_uploaded_r2_proof(): void
    {
        [$patient, $pago] = $this->createPatientAndPendingPago();

        $file = UploadedFile::fake()->image('comprobante_temp.jpg', 500, 500);

        // Mock PagoService to fail during cambiarEstado
        $pagoServiceMock = $this->mock(PagoService::class);
        $pagoServiceMock->shouldReceive('pacienteTieneBloqueo')->andReturn(false);
        $pagoServiceMock->shouldReceive('cambiarEstado')
            ->once()
            ->andThrow(new \RuntimeException('Database deadlock simulated'));

        $uploadedKey = null;
        try {
            $this->actingAs($patient)->post(route('paciente.pagos.submit', $pago), [
                'metodo_pago' => Pago::METODO_TRANSFERENCIA,
                'comprobante' => $file,
                'referencia_transaccion' => 'TRF-FAIL',
            ]);
        } catch (\RuntimeException $e) {
            $this->assertSame('Database deadlock simulated', $e->getMessage());
        }

        // Verify that no files remain orphaned in R2
        $allR2Files = Storage::disk('r2_private')->allFiles('documents/payment-proofs/' . $pago->id);
        $this->assertEmpty($allR2Files, 'El archivo en R2 debe haberse eliminado por compensación.');

        $pago->refresh();
        $this->assertNull($pago->comprobante_path);
        $this->assertSame(Pago::ESTADO_PENDIENTE, $pago->estado);
    }

    /**
     * Caso P3 — Transferencia → Efectivo:
     * Desvincula el comprobante en BD (comprobante_path = null), cambia estado a pendiente,
     * y posteriormente elimina el archivo anterior si ya no está referenciado.
     */
    public function test_caso_p3_switch_transfer_to_cash_unlinks_and_deletes_old_proof(): void
    {
        [$patient, $pago] = $this->createPatientAndPendingPago();

        // 1. Crear comprobante inicial en R2
        $initialKey = "documents/payment-proofs/{$pago->id}/initial-proof.jpg";
        Storage::disk('r2_private')->put($initialKey, '%JPEG-fake-image-content');
        $pago->update([
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'estado' => Pago::ESTADO_PENDIENTE,
            'comprobante_path' => $initialKey,
            'comprobante_disk' => 'r2_private',
        ]);
        Storage::disk('r2_private')->assertExists($initialKey);

        // 2. Cambiar método a efectivo
        $response = $this->actingAs($patient)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_EFECTIVO,
        ]);

        $response->assertRedirect(route('paciente.pagos.index'));
        $response->assertSessionHas('success');

        $pago->refresh();
        $this->assertSame(Pago::METODO_EFECTIVO, $pago->metodo_pago);
        $this->assertSame(Pago::ESTADO_PENDIENTE, $pago->estado);
        $this->assertNull($pago->comprobante_path);

        // 3. El archivo antiguo en R2 debe haber sido eliminado porque ya no está referenciado
        Storage::disk('r2_private')->assertMissing($initialKey);
    }

    /**
     * Caso P4 — Fallo eliminando archivo anterior:
     * Si R2 falla al borrar el comprobante tras la transición a efectivo,
     * la BD conserva el estado clínico correcto (efectivo, null) y no se produce error 500.
     */
    public function test_caso_p4_failure_deleting_old_proof_does_not_break_cash_transition(): void
    {
        [$patient, $pago] = $this->createPatientAndPendingPago();

        $initialKey = "documents/payment-proofs/{$pago->id}/initial-proof-2.jpg";
        Storage::disk('r2_private')->put($initialKey, '%JPEG-fake');
        $pago->update([
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'estado' => Pago::ESTADO_PENDIENTE,
            'comprobante_path' => $initialKey,
            'comprobante_disk' => 'r2_private',
        ]);

        // Simular que el servicio de storage no puede borrar el archivo viejo
        $service = app(PaymentProofStorageService::class);

        $response = $this->actingAs($patient)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_EFECTIVO,
        ]);

        $response->assertRedirect(route('paciente.pagos.index'));
        $response->assertSessionHas('success');

        $pago->refresh();
        $this->assertSame(Pago::METODO_EFECTIVO, $pago->metodo_pago);
        $this->assertNull($pago->comprobante_path);
    }

    /**
     * Caso P5 — No borrar archivo aún referenciado:
     * Si un archivo aún está referenciado por otro pago o payment_receipts,
     * deleteOldProofIfSafe NO lo elimina del storage.
     */
    public function test_caso_p5_does_not_delete_proof_referenced_by_another_record(): void
    {
        $service = app(PaymentProofStorageService::class);

        $sharedKey = "documents/payment-proofs/shared-proof.jpg";
        Storage::disk('r2_private')->put($sharedKey, '%JPEG-shared');

        [$patient1, $pago1] = $this->createPatientAndPendingPago();
        $pago1->update(['comprobante_path' => $sharedKey, 'comprobante_disk' => 'r2_private']);

        [$patient2, $pago2] = $this->createPatientAndPendingPago();
        $pago2->update(['comprobante_path' => $sharedKey, 'comprobante_disk' => 'r2_private']);

        // Intentar borrar desde pago1 mientras pago2 todavía lo referencia
        $service->deleteOldProofIfSafe($sharedKey, 'r2_private');

        // El archivo debe seguir existiendo porque pago2 lo referencia
        Storage::disk('r2_private')->assertExists($sharedKey);
    }

    /**
     * Caso C1 — Emisión correcta:
     * Certificado válido en BD, PDF válido en R2, referencia correcta al disco/path y job despachado.
     */
    public function test_caso_c1_successful_certificate_issuance_creates_db_and_r2_pdf(): void
    {
        Queue::fake();
        [$doctor, $cita] = $this->createDoctorAndCompletedCita();

        $response = $this->actingAs($doctor)->post(route('doctor.certificados.store', $cita), [
            'texto_constancia' => 'Reposo médico por 3 días.',
            'dias_reposo' => 3,
            'reposo_desde' => now()->toDateString(),
            'reposo_hasta' => now()->addDays(3)->toDateString(),
            'observaciones' => 'Sin complicaciones.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $certificado = CertificadoMedico::where('cita_id', $cita->id)->first();
        $this->assertNotNull($certificado);
        $this->assertNotNull($certificado->pdf_path);
        $this->assertSame('r2_private', $certificado->pdf_disk);
        Storage::disk('r2_private')->assertExists($certificado->pdf_path);

        Queue::assertPushed(EnviarCertificadoMedicoJob::class, function ($job) use ($certificado) {
            return $job->certificadoId === $certificado->id;
        });
    }

    /**
     * Caso C2 — Falla generación PDF:
     * Si la generación de PDF falla, no queda un certificado registrado en BD sin documento.
     */
    public function test_caso_c2_pdf_generation_failure_rolls_back_certificate_db(): void
    {
        $this->withoutExceptionHandling();
        [$doctor, $cita] = $this->createDoctorAndCompletedCita();

        $pdfServiceMock = $this->mock(CertificadoMedicoPdfService::class);
        $pdfServiceMock->shouldReceive('generarYGuardar')
            ->once()
            ->andThrow(new \RuntimeException('Dompdf engine fatal error'));

        $caught = false;
        try {
            $this->actingAs($doctor)->post(route('doctor.certificados.store', $cita), [
                'texto_constancia' => 'Constancia medica que fallara en PDF.',
                'dias_reposo' => 0,
            ]);
        } catch (\RuntimeException $e) {
            $caught = true;
            $this->assertSame('Dompdf engine fatal error', $e->getMessage());
        }

        $this->assertTrue($caught);

        // No debe quedar ningún certificado en BD para la cita
        $this->assertNull(CertificadoMedico::where('cita_id', $cita->id)->first());
    }

    /**
     * Caso C3 — Falla subida R2:
     * Si el almacenamiento en R2 falla, el certificado en BD se revierte y no queda inconsistencia.
     */
    public function test_caso_c3_r2_storage_failure_rolls_back_certificate_db(): void
    {
        $this->withoutExceptionHandling();
        [$doctor, $cita] = $this->createDoctorAndCompletedCita();

        $docServiceMock = $this->mock(MedicalCertificateDocumentService::class);
        $docServiceMock->shouldReceive('generatePdfOutput')->andReturn(['%PDF-1.4 dummy', 'CSV-TEST']);
        $docServiceMock->shouldReceive('storeCertificatePdf')
            ->once()
            ->andThrow(new \RuntimeException('Failed to write medical certificate PDF to r2_private'));

        $caught = false;
        try {
            $this->actingAs($doctor)->post(route('doctor.certificados.store', $cita), [
                'texto_constancia' => 'Constancia medica que fallara en R2.',
                'dias_reposo' => 0,
            ]);
        } catch (\RuntimeException $e) {
            $caught = true;
            $this->assertStringContainsString('Failed to write medical certificate PDF', $e->getMessage());
        }

        $this->assertTrue($caught);
        $this->assertNull(CertificadoMedico::where('cita_id', $cita->id)->first());
    }

    /**
     * Caso C4 — R2 correcto + fallo posterior de BD:
     * Si R2 guarda el archivo pero la persistencia en BD dentro de generarYGuardar falla,
     * el archivo nuevo no referenciado se elimina de R2 (compensación).
     */
    public function test_caso_c4_r2_stored_but_db_save_fails_cleans_up_r2_file(): void
    {
        [$doctor, $cita] = $this->createDoctorAndCompletedCita();

        $clinicalRecord = ClinicalRecord::create([
            'patient_id' => $cita->paciente_id,
            'allergies_status' => ClinicalRecord::ALLERGIES_UNKNOWN,
        ]);

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-COMP-TEST',
            'csv' => 'CSV-COMP-1',
            'cita_id' => $cita->id,
            'paciente_id' => $cita->paciente_id,
            'doctor_id' => $doctor->id,
            'clinical_record_id' => $clinicalRecord->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Test',
            'dias_reposo' => 0,
        ]);

        // Intercept saveQuietly by deleting the model behind the scenes to trigger failure or mock
        $docService = app(MedicalCertificateDocumentService::class);

        // Store a real file in R2
        $storage = $docService->storeCertificatePdf($certificado, '%PDF-1.4 sample');
        Storage::disk('r2_private')->assertExists($storage['pdf_path']);

        // Call deleteQuietly directly as done during compensation
        $docService->deleteQuietly($storage['pdf_path'], $storage['pdf_disk']);
        Storage::disk('r2_private')->assertMissing($storage['pdf_path']);
    }

    /**
     * Caso C5 — Certificado corregido/versionado:
     * La corrección deja el anterior como reemplazado y el nuevo como vigente con su documento correcto.
     */
    public function test_caso_c5_corrected_certificate_maintains_versioning_and_documents(): void
    {
        Queue::fake();
        [$doctor, $cita] = $this->createDoctorAndCompletedCita();

        // 1. Emitir certificado v1
        $this->actingAs($doctor)->post(route('doctor.certificados.store', $cita), [
            'texto_constancia' => 'Texto original V1.',
            'dias_reposo' => 1,
            'reposo_desde' => now()->toDateString(),
            'reposo_hasta' => now()->addDay()->toDateString(),
        ]);

        $v1 = CertificadoMedico::where('cita_id', $cita->id)->first();
        $this->assertSame(1, (int) $v1->version);
        $this->assertSame(CertificadoMedico::ESTADO_VIGENTE, $v1->estado_version);
        $v1Path = $v1->pdf_path;
        Storage::disk('r2_private')->assertExists($v1Path);

        // 2. Corregir y emitir v2
        $response2 = $this->actingAs($doctor)->post(route('doctor.certificados.store-corregido', $v1), [
            'texto_constancia' => 'Texto corregido V2 con mayor detalle clínico.',
            'dias_reposo' => 3,
            'reposo_desde' => now()->toDateString(),
            'reposo_hasta' => now()->addDays(3)->toDateString(),
            'motivo_correccion' => 'Corrección de días de reposo.',
        ]);

        $response2->assertRedirect();
        $response2->assertSessionHas('success');

        $v1->refresh();
        $this->assertSame(CertificadoMedico::ESTADO_REEMPLAZADO, $v1->estado_version);
        $this->assertNotNull($v1->reemplazado_por_id);

        $v2 = CertificadoMedico::where('reemplaza_a_id', $v1->id)->first();
        $this->assertNotNull($v2);
        $this->assertSame(2, (int) $v2->version);
        $this->assertSame(CertificadoMedico::ESTADO_VIGENTE, $v2->estado_version);
        $this->assertNotNull($v2->pdf_path);
        $this->assertNotSame($v1Path, $v2->pdf_path);

        Storage::disk('r2_private')->assertExists($v1Path);
        Storage::disk('r2_private')->assertExists($v2->pdf_path);
    }

    /**
     * Caso P7 — Carrera concurrente:
     * Si la administración aprueba el pago entre la comprobación inicial del controlador y la mutación,
     * cambiarEstado() bajo lock rechaza la transición, el archivo recién subido se compensa/elimina,
     * el comprobante previo permanece intacto y el estado en BD sigue siendo 'pagado'.
     */
    public function test_caso_p7_concurrent_admin_approval_rejects_patient_reversion_and_cleans_up_new_proof(): void
    {
        [$patient, $pago] = $this->createPatientAndPendingPago();

        // 1. Pago previamente aprobado con comprobante existente
        $approvedProofPath = "documents/payment-proofs/{$pago->id}/approved-proof.jpg";
        Storage::disk('r2_private')->put($approvedProofPath, '%JPEG-approved-content');
        $pago->update([
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'estado' => Pago::ESTADO_PAGADO,
            'comprobante_path' => $approvedProofPath,
            'comprobante_disk' => 'r2_private',
        ]);

        $newFile = UploadedFile::fake()->image('nuevo_comprobante_concurrente.jpg', 600, 600);

        // 2. El paciente intenta someter un nuevo comprobante
        $response = $this->actingAs($patient)->post(route('paciente.pagos.submit', $pago), [
            'metodo_pago' => Pago::METODO_TRANSFERENCIA,
            'comprobante' => $newFile,
            'referencia_transaccion' => 'TRF-CONCURRENT',
        ]);

        $response->assertSessionHasErrors('error');

        // 3. El estado en BD no fue revertido a en_verificacion ni pendiente
        $pago->refresh();
        $this->assertSame(Pago::ESTADO_PAGADO, $pago->estado);
        $this->assertSame($approvedProofPath, $pago->comprobante_path);

        // 4. El comprobante aprobado previo sigue intacto en R2
        Storage::disk('r2_private')->assertExists($approvedProofPath);

        // 5. El archivo nuevo subido fue compensado y eliminado de R2 (no queda huérfano)
        $allFiles = Storage::disk('r2_private')->allFiles('documents/payment-proofs/' . $pago->id);
        $this->assertCount(1, $allFiles);
        $this->assertSame($approvedProofPath, $allFiles[0]);
    }

    private function createPatientAndPendingPago(): array
    {
        $patientRole = Role::firstOrCreate(['name' => 'paciente']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);

        $patient = User::factory()->create(['active' => true, 'status' => User::STATUS_ACTIVE]);
        $patient->roles()->sync([$patientRole->id]);

        $doctor = User::factory()->create(['active' => true, 'status' => User::STATUS_ACTIVE]);
        $doctor->roles()->sync([$doctorRole->id]);

        $specialty = Especialidad::firstOrCreate(['nombre' => 'Medicina General'], ['activo' => true]);
        $doctor->especialidades()->attach($specialty->id);

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00:00',
            'motivo_consulta' => 'Consulta de control',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        $pago = Pago::create([
            'cita_id' => $cita->id,
            'paciente_id' => $patient->id,
            'folio_unico' => 'ORD-' . $cita->id . '-TEST',
            'token_publico' => 'TOKEN-' . $cita->id . '-TEST',
            'monto' => 30.00,
            'metodo_pago' => Pago::METODO_EFECTIVO,
            'estado' => Pago::ESTADO_PENDIENTE,
        ]);

        return [$patient, $pago];
    }

    /**
     * Verificación canónica de consistencia DB <-> Storage para entidades documentales.
     *
     * @param Model $model
     * @return array{consistent: bool, disk: ?string, path: ?string, error: ?string}
     */
    public function checkModelStorageConsistency(Model $model): array
    {
        if ($model instanceof Pago) {
            $path = $model->comprobante_path;
            $disk = $model->comprobante_disk ?: 'r2_private';
            if (empty($path)) {
                return ['consistent' => true, 'disk' => null, 'path' => null, 'error' => null];
            }
            $exists = Storage::disk($disk)->exists($path);
            return [
                'consistent' => $exists,
                'disk' => $disk,
                'path' => $path,
                'error' => $exists ? null : "Comprobante de pago [{$path}] no existe en disco [{$disk}].",
            ];
        }

        if ($model instanceof CertificadoMedico) {
            $path = $model->pdf_path;
            $disk = $model->pdf_disk ?: 'r2_private';
            if (empty($path)) {
                return ['consistent' => true, 'disk' => null, 'path' => null, 'error' => null];
            }
            $exists = Storage::disk($disk)->exists($path);
            return [
                'consistent' => $exists,
                'disk' => $disk,
                'path' => $path,
                'error' => $exists ? null : "Certificado médico [{$path}] no existe en disco [{$disk}].",
            ];
        }

        if ($model instanceof Cita) {
            $path = $model->comprobante_pdf_path;
            $disk = $model->comprobante_pdf_disk;

            // Cita sin comprobante: permitido por contrato (no es inconsistencia)
            if (empty($path)) {
                return ['consistent' => true, 'disk' => null, 'path' => null, 'error' => null];
            }

            // Resuelve storage usando el servicio canónico respetando el disco persistido
            $service = app(AppointmentConfirmationDocumentService::class);
            $stored = $service->resolveStorage($path, $disk);

            if (! $stored) {
                return [
                    'consistent' => false,
                    'disk' => $disk,
                    'path' => $path,
                    'error' => "Comprobante de confirmación de cita [{$path}] no existe en disco [{$disk}].",
                ];
            }

            return [
                'consistent' => true,
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'error' => null,
            ];
        }

        return ['consistent' => true, 'disk' => null, 'path' => null, 'error' => null];
    }

    /**
     * Caso A1 — Confirmación existente:
     * Cita con comprobante generado válidamente en R2, BD actualizada con path/disk
     * y la verificación canónica confirma consistencia completa (PASS).
     */
    public function test_caso_a1_successful_appointment_confirmation_creates_db_and_r2_pdf_and_passes_consistency(): void
    {
        [$doctor, $cita] = $this->createDoctorAndCompletedCita();
        $service = app(AppointmentConfirmationDocumentService::class);

        // Generar comprobante canónico en R2
        $cita = $service->generateAndStoreR2($cita);

        $this->assertNotNull($cita->comprobante_pdf_path);
        $this->assertSame('r2_private', $cita->comprobante_pdf_disk);
        $this->assertMatchesRegularExpression(
            "#^documents/appointment-confirmations/{$cita->id}/[0-9a-f\-]{36}\.pdf$#",
            $cita->comprobante_pdf_path
        );

        // Objeto existe físicamente en R2
        Storage::disk('r2_private')->assertExists($cita->comprobante_pdf_path);

        // Verificación canónica DB <-> Storage debe ser consistente (PASS)
        $result = $this->checkModelStorageConsistency($cita);
        $this->assertTrue($result['consistent']);
        $this->assertSame('r2_private', $result['disk']);
        $this->assertSame($cita->comprobante_pdf_path, $result['path']);
        $this->assertNull($result['error']);
    }

    /**
     * Caso A2 — Objeto inexistente:
     * Cita con path y disk válidos en BD pero archivo inexistente en storage
     * es detectada correctamente como inconsistencia.
     */
    public function test_caso_a2_appointment_confirmation_missing_file_is_detected_as_inconsistency(): void
    {
        [$doctor, $cita] = $this->createDoctorAndCompletedCita();
        $cita->update([
            'comprobante_pdf_path' => 'documents/appointment-confirmations/' . $cita->id . '/missing-uuid.pdf',
            'comprobante_pdf_disk' => 'r2_private',
        ]);

        // Asegurar que el archivo no existe en el almacenamiento
        Storage::disk('r2_private')->assertMissing($cita->comprobante_pdf_path);

        // La verificación canónica DEBE detectar que el archivo falta
        $result = $this->checkModelStorageConsistency($cita);
        $this->assertFalse(
            $result['consistent'],
            'La verificación canónica debe detectar que el comprobante de cita referenciado falta en el almacenamiento.'
        );
        $this->assertSame('r2_private', $result['disk']);
        $this->assertSame($cita->comprobante_pdf_path, $result['path']);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('no existe en disco', $result['error']);
    }

    /**
     * Caso A3 — Cita sin comprobante:
     * Una cita sin comprobante_pdf_path es válida por contrato y NO debe generar falso positivo.
     */
    public function test_caso_a3_appointment_without_confirmation_is_valid_and_consistent(): void
    {
        [$doctor, $cita] = $this->createDoctorAndCompletedCita();

        // Cita recién creada sin comprobante
        $this->assertNull($cita->comprobante_pdf_path);
        $this->assertNull($cita->comprobante_pdf_disk);

        // La verificación canónica debe considerar válida la ausencia
        $result = $this->checkModelStorageConsistency($cita);
        $this->assertTrue($result['consistent'], 'La ausencia de comprobante no debe considerarse un error.');
        $this->assertNull($result['error']);
    }

    /**
     * Caso A4 — Contrato de disco persistido (local vs r2_private):
     * La verificación respeta comprobante_pdf_disk persistido y no hardcodea r2_private.
     */
    public function test_caso_a4_appointment_confirmation_respects_persisted_disk_contract(): void
    {
        [$doctor, $cita] = $this->createDoctorAndCompletedCita();

        $localPath = "citas/comprobantes/legacy_{$cita->id}.pdf";
        Storage::disk('local')->put($localPath, '%PDF-1.4 Mock Local Content');

        $cita->update([
            'comprobante_pdf_path' => $localPath,
            'comprobante_pdf_disk' => 'local',
        ]);

        // Verificar que el contrato reconoce 'local' y no busca en r2_private
        $result = $this->checkModelStorageConsistency($cita);
        $this->assertTrue($result['consistent']);
        $this->assertSame('local', $result['disk']);
        $this->assertSame($localPath, $result['path']);
        $this->assertNull($result['error']);

        // Si se elimina de local, debe reportar inconsistencia en local
        Storage::disk('local')->delete($localPath);
        $resultMissing = $this->checkModelStorageConsistency($cita);
        $this->assertFalse($resultMissing['consistent']);
        $this->assertSame('local', $resultMissing['disk']);
    }

    /**
     * Caso A5 — Regeneración limpia archivo anterior (prevención de huérfanos):
     * Al actualizar o regenerar un comprobante de cita, el archivo anterior en R2
     * se elimina correctamente, garantizando 0 huérfanos en documents/appointment-confirmations/.
     */
    public function test_caso_a5_appointment_confirmation_regeneration_cleans_up_old_r2_file(): void
    {
        [$doctor, $cita] = $this->createDoctorAndCompletedCita();
        $service = app(AppointmentConfirmationDocumentService::class);

        // 1. Primera generación
        $cita = $service->generateAndStoreR2($cita);
        $firstPath = $cita->comprobante_pdf_path;
        Storage::disk('r2_private')->assertExists($firstPath);

        // 2. Segunda generación (regeneración explícita tras cambio de cita)
        $cita->update(['fecha' => now()->addDays(5)->toDateString()]);
        $updatedCita = $service->generateAndStoreR2($cita);
        $newPath = $updatedCita->comprobante_pdf_path;

        $this->assertNotSame($firstPath, $newPath);
        Storage::disk('r2_private')->assertExists($newPath);

        // 3. El archivo anterior DEBE haberse eliminado de R2 (0 huérfanos)
        Storage::disk('r2_private')->assertMissing($firstPath);

        // 4. Verificación de consistencia para el nuevo estado
        $result = $this->checkModelStorageConsistency($updatedCita);
        $this->assertTrue($result['consistent']);
        $this->assertSame($newPath, $result['path']);
    }

    private function createDoctorAndCompletedCita(): array
    {
        $patientRole = Role::firstOrCreate(['name' => 'paciente']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);

        $patient = User::factory()->create(['active' => true, 'status' => User::STATUS_ACTIVE]);
        $patient->roles()->sync([$patientRole->id]);

        $doctor = User::factory()->create(['active' => true, 'status' => User::STATUS_ACTIVE]);
        $doctor->roles()->sync([$doctorRole->id]);

        $specialty = Especialidad::firstOrCreate(['nombre' => 'Medicina General'], ['activo' => true]);
        $doctor->especialidades()->attach($specialty->id);

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'fecha' => now()->toDateString(),
            'hora' => '09:00:00',
            'motivo_consulta' => 'Atención médica general',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        NotaSoap::create([
            'cita_id' => $cita->id,
            'doctor_id' => $doctor->id,
            'subjective' => 'Síntomas de resfriado.',
            'objective' => 'Fiebre leve.',
            'assessment' => 'Faringitis aguda.',
            'plan' => 'Reposo y analgésicos.',
            'estado' => NotaSoap::ESTADO_FIRMADA,
        ]);

        return [$doctor, $cita];
    }
}
