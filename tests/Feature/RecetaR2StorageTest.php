<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Receta;
use App\Models\Role;
use App\Models\User;
use App\Services\RecipeDocumentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecetaR2StorageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['private_documents.recipe_disk' => 'r2_private']);
        config(['image_optimization.avatar_disk' => 'r2_private']);
        Storage::fake('r2_private');
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();

        $this->seedRoles();
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/private/scratch/recipe_migration_manifest.json'));
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

    private function createRealizedCita(User $doctor, User $paciente, ?Dependiente $dependiente = null): Cita
    {
        $esp = \App\Models\Especialidad::firstOrCreate(['nombre' => 'Medicina General']);

        return Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'dependiente_id' => $dependiente?->id,
            'especialidad_id' => $esp->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);
    }

    // 1. Nueva receta escribe únicamente en r2_private
    // 2. BD guarda clave relativa y pdf_disk r2_private
    // 3. El contenido comienza con firma %PDF
    // 4. No se escribe PDF nuevo en local
    public function test_new_recipe_creation_writes_exclusively_to_r2_private(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $response = $this->actingAs($doctor)->post(route('doctor.recetas.store'), [
            'cita_id' => $cita->id,
            'diagnostico' => 'Gripe común y amigdalitis',
            'medicamentos' => 'Paracetamol 500mg c/8h por 3 días',
            'indicaciones' => 'Reposo e hidratación',
        ]);

        $response->assertRedirect(route('doctor.citas'));

        $receta = Receta::where('cita_id', $cita->id)->firstOrFail();

        $this->assertEquals('r2_private', $receta->pdf_disk);
        $this->assertStringStartsWith('documents/recipes/', $receta->pdf_path);
        $this->assertStringEndsWith('.pdf', $receta->pdf_path);

        $r2Disk = Storage::disk('r2_private');
        $localDisk = Storage::disk('local');

        $this->assertTrue($r2Disk->exists($receta->pdf_path));
        $this->assertFalse($localDisk->exists($receta->pdf_path));

        $binary = $r2Disk->get($receta->pdf_path);
        $this->assertStringStartsWith('%PDF', $binary);
    }

    // 5. Doctor autorizado descarga desde R2
    // 6. Paciente titular descarga
    // 7. Representante de dependiente descarga
    // 8. Usuario ajeno recibe 403
    // 9. Visitante no descarga (302/401)
    // 10. Descarga responde application/pdf y cuerpo no vacío
    public function test_recipe_download_authorization_and_mime_headers(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $otherPatient = $this->createRoleUser('paciente');

        $dependiente = Dependiente::create([
            'user_id' => $paciente->id,
            'nombre' => 'Hijo Test',
            'tipo_documento' => 'cedula',
            'dni' => '1754504635',
            'fecha_nacimiento' => '2020-01-01',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
            'activo' => true,
        ]);

        $cita = $this->createRealizedCita($doctor, $paciente, $dependiente);

        $service = app(RecipeDocumentService::class);
        [$pdfBinary, $csv] = $service->generatePdfOutput($cita, 'Diag Test', 'Med Test');

        $receta = Receta::create([
            'cita_id' => $cita->id,
            'diagnostico' => 'Diag Test',
            'medicamentos' => 'Med Test',
            'csv' => $csv,
            'pdf_path' => '',
        ]);

        $st = $service->storeRecipePdf($receta, $pdfBinary);
        $receta->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $docRoute = route('doctor.recetas.download', $cita->id);
        $pacRoute = route('paciente.recetas.download', $cita->id);

        // 5. Doctor: 200 OK, application/pdf
        $respDoc = $this->actingAs($doctor)->get($docRoute);
        $respDoc->assertStatus(200);
        $respDoc->assertHeader('Content-Type', 'application/pdf');
        $this->assertNotEmpty($respDoc->getContent());

        // 6 & 7. Paciente Titular (representante del dependiente): 200 OK
        $respPac = $this->actingAs($paciente)->get($pacRoute);
        $respPac->assertStatus(200);
        $respPac->assertHeader('Content-Type', 'application/pdf');

        // 8. Usuario ajeno: 403
        $this->actingAs($otherPatient)->get($pacRoute)->assertStatus(403);

        // 9. Visitante no autenticado: 302 or 401 or 403
        auth()->logout();
        $respGuest = $this->get($pacRoute);
        $this->assertTrue($respGuest->isRedirect() || in_array($respGuest->status(), [302, 401, 403], true));
    }

    // 11. Correo adjunta el PDF desde R2
    // 12. Job de correo funciona con receta R2
    public function test_email_attaches_pdf_from_r2_storage(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $service = app(RecipeDocumentService::class);
        [$pdfBinary, $csv] = $service->generatePdfOutput($cita, 'Diag Email', 'Med Email');

        $receta = Receta::create([
            'cita_id' => $cita->id,
            'diagnostico' => 'Diag Email',
            'medicamentos' => 'Med Email',
            'csv' => $csv,
            'pdf_path' => '',
        ]);

        $st = $service->storeRecipePdf($receta, $pdfBinary);
        $receta->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $mailable = new \App\Mail\RecetaMedicaMail($cita, $receta->pdf_path, null, 'receta.pdf', 'creacion', $receta);

        $attachment = $service->getMailAttachment($receta, 'receta.pdf', null);
        $this->assertNotNull($attachment);
    }

    // 13. Receta local heredada continúa descargándose
    public function test_legacy_local_recipe_continues_downloading(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $localPath = 'recetas/legacy_receta_1.pdf';
        Storage::disk('local')->put($localPath, '%PDF-1.4 Legacy PDF Content');

        $receta = Receta::create([
            'cita_id' => $cita->id,
            'diagnostico' => 'Diag Legacy',
            'medicamentos' => 'Med Legacy',
            'csv' => 'LEG-12345-ACY',
            'pdf_path' => $localPath,
            'pdf_disk' => null, // Legacy local
        ]);

        $response = $this->actingAs($doctor)->get(route('doctor.recetas.download', $cita->id));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $content = $response->streamedContent() ?: $response->getContent();
        $this->assertStringContainsString('Legacy PDF Content', $content);
    }

    // 14. Edición genera nueva clave en R2
    // 15. Edición conserva el mismo CSV
    // 16. Después del éxito elimina el PDF R2 anterior
    // 17. Si el anterior es local, lo conserva
    public function test_recipe_editing_flow_and_atomic_cleanup(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        // 1. Create initial recipe
        $this->actingAs($doctor)->post(route('doctor.recetas.store'), [
            'cita_id' => $cita->id,
            'diagnostico' => 'Original Diag',
            'medicamentos' => 'Original Meds',
        ]);

        $receta = Receta::where('cita_id', $cita->id)->firstOrFail();
        $key1 = $receta->pdf_path;
        $csv1 = $receta->csv;

        $disk = Storage::disk('r2_private');
        $this->assertTrue($disk->exists($key1));

        // 2. Edit recipe
        $response = $this->actingAs($doctor)->post(route('doctor.recetas.update'), [
            'cita_id' => $cita->id,
            'diagnostico' => 'Updated Diag',
            'medicamentos' => 'Updated Meds',
            'regenerar_pdf' => 1,
            'reenviar' => 0,
        ]);

        $response->assertRedirect(route('doctor.recetas.edit', $cita->id));

        $receta->refresh();
        $key2 = $receta->pdf_path;
        $csv2 = $receta->csv;

        // 14 & 15. New key created, CSV preserved
        $this->assertNotEquals($key1, $key2);
        $this->assertEquals($csv1, $csv2);
        $this->assertTrue($disk->exists($key2));

        // 16. Old R2 PDF deleted
        $this->assertFalse($disk->exists($key1));
    }

    // 17. Si el anterior es local, lo conserva al editar
    public function test_editing_legacy_local_recipe_preserves_old_local_file(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $localPath = 'recetas/legacy_old.pdf';
        Storage::disk('local')->put($localPath, '%PDF-1.4 Legacy Content');

        $receta = Receta::create([
            'cita_id' => $cita->id,
            'diagnostico' => 'Old Local Diag',
            'medicamentos' => 'Old Local Meds',
            'csv' => 'OLD-12345-LOCAL',
            'pdf_path' => $localPath,
            'pdf_disk' => 'local',
        ]);

        $this->actingAs($doctor)->post(route('doctor.recetas.update'), [
            'cita_id' => $cita->id,
            'diagnostico' => 'New R2 Diag',
            'medicamentos' => 'New R2 Meds',
            'regenerar_pdf' => 1,
            'reenviar' => 0,
        ]);

        $receta->refresh();
        $this->assertEquals('r2_private', $receta->pdf_disk);
        $this->assertTrue(Storage::disk('r2_private')->exists($receta->pdf_path));

        // Old local file is preserved
        $this->assertTrue(Storage::disk('local')->exists($localPath));
    }

    // 18. Fallo R2 mantiene BD y PDF anterior
    public function test_r2_upload_failure_preserves_previous_state(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $this->actingAs($doctor)->post(route('doctor.recetas.store'), [
            'cita_id' => $cita->id,
            'diagnostico' => 'Diag 1',
            'medicamentos' => 'Med 1',
        ]);

        $receta = Receta::where('cita_id', $cita->id)->firstOrFail();
        $key1 = $receta->pdf_path;

        // Mock R2 disk put to throw exception
        Storage::shouldReceive('disk')->with('r2_private')->andReturnSelf();
        Storage::shouldReceive('exists')->andReturn(true);
        Storage::shouldReceive('put')->andThrow(new \RuntimeException('R2 Connection Exception'));

        try {
            $this->actingAs($doctor)->post(route('doctor.recetas.update'), [
                'cita_id' => $cita->id,
                'diagnostico' => 'Diag Fail',
                'medicamentos' => 'Med Fail',
                'regenerar_pdf' => 1,
                'reenviar' => 0,
            ]);
        } catch (\Throwable $e) {
            // Expected
        }

        $receta->refresh();
        $this->assertEquals('Diag 1', $receta->diagnostico);
        $this->assertEquals($key1, $receta->pdf_path);
    }

    // 21. Dry-run no modifica BD ni discos
    // 22. Execute migra únicamente registros activos
    // 23. Los 50 huérfanos no se migran
    // 24. Verify compara hash y tamaño
    // 25. Comando idempotente

    // 26. Página pública de verificación no expone URL privada R2
    public function test_public_verification_page_does_not_expose_private_r2_url(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $service = app(RecipeDocumentService::class);
        [$pdfBinary, $csv] = $service->generatePdfOutput($cita, 'Diag Verif', 'Med Verif');

        $receta = Receta::create([
            'cita_id' => $cita->id,
            'diagnostico' => 'Diag Verif',
            'medicamentos' => 'Med Verif',
            'csv' => $csv,
            'pdf_path' => '',
        ]);

        $st = $service->storeRecipePdf($receta, $pdfBinary);
        $receta->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $route = route('documentos.verificar.show', ['csv' => $csv]);
        $response = $this->get($route);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('r2.cloudflarestorage.com', $response->getContent());
        $this->assertStringNotContainsString('r2.dev', $response->getContent());
    }

    // 27. Avatares y funcionalidades públicas siguen funcionando
    public function test_existing_avatars_and_public_features_remain_functional(): void
    {
        $user = $this->createRoleUser('paciente', [
            'avatar' => 'avatars/1/uuid1/original.jpg',
        ]);

        $this->assertStringContainsString('/media/avatars/', $user->avatar_thumb_url);

        $resp = $this->get(route('servicios.index'));
        $resp->assertStatus(200);
    }

    // 28. Botón de descarga de receta incluye atributos de exclusión del cargador de navegación
    public function test_recipe_download_link_has_loader_exclusion_attributes(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $service = app(RecipeDocumentService::class);
        [$pdfBinary, $csv] = $service->generatePdfOutput($cita, 'Diag Test', 'Med Test');

        $receta = Receta::create([
            'cita_id' => $cita->id,
            'diagnostico' => 'Diag Test',
            'medicamentos' => 'Med Test',
            'csv' => $csv,
            'pdf_path' => '',
        ]);

        $st = $service->storeRecipePdf($receta, $pdfBinary);
        $receta->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        $response = $this->actingAs($doctor)->get(route('doctor.recetas.edit', $cita->id));
        $response->assertStatus(200);
        $response->assertSee('data-skip-page-loader', false);
        $response->assertSee('data-action-lock-ignore', false);
    }
}
