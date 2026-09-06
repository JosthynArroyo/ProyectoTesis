<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Receta;
use App\Models\Role;
use App\Models\User;
use App\Services\ClinicalRecordService;
use App\Services\DocumentoCsvService;
use App\Services\RecipeDocumentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecipeDocumentR2ConsistencyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['private_documents.recipe_disk' => 'r2_private']);
        Storage::fake('r2_private');
        Storage::fake('local');
        Mail::fake();

        $this->seedRoles();
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

    private function createInitialRecipe(Cita $cita, string $diag = 'Diagnóstico inicial', string $meds = 'Medicamento inicial'): Receta
    {
        $receta = Receta::create([
            'cita_id' => $cita->id,
            'diagnostico' => $diag,
            'medicamentos' => $meds,
            'csv' => 'REC-' . strtoupper(bin2hex(random_bytes(4))),
            'pdf_path' => '',
            'pdf_disk' => 'r2_private',
        ]);

        $uuid = (string) \Illuminate\Support\Str::uuid();
        $path = "documents/recipes/{$receta->id}/{$uuid}.pdf";
        Storage::disk('r2_private')->put($path, "%PDF-1.4 Mock Initial Recipe\n");
        $receta->update(['pdf_path' => $path]);

        return $receta;
    }

    /**
     * PR1 — Store normal
     */
    public function test_pr1_normal_store_creates_recipe_and_valid_r2_pdf(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $response = $this->actingAs($doctor)->post(route('doctor.recetas.store'), [
            'cita_id' => $cita->id,
            'diagnostico' => 'Faringitis aguda',
            'medicamentos' => 'Amoxicilina 500mg c/8h',
            'indicaciones' => 'Tomar con abundante agua',
        ]);

        $response->assertRedirect(route('doctor.citas'));

        $receta = Receta::where('cita_id', $cita->id)->firstOrFail();
        $this->assertEquals('r2_private', $receta->pdf_disk);
        $this->assertNotNull($receta->pdf_path);
        $this->assertTrue(Storage::disk('r2_private')->exists($receta->pdf_path));

        $files = Storage::disk('r2_private')->allFiles("documents/recipes/{$receta->id}");
        $this->assertCount(1, $files);
    }

    /**
     * PR2 — Store: R2 OK / DB Fail
     * El upload a R2 termina correctamente, pero ocurre un fallo DB posterior.
     * Resultado: Rollback de receta y eliminación inmediata del nuevo archivo R2.
     */
    public function test_pr2_store_r2_success_then_db_failure_cleans_up_new_r2_object(): void
    {
        $this->withoutExceptionHandling();
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        DB::listen(function ($query) {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'update') && str_contains($sql, 'recetas') && str_contains($sql, 'pdf_path')) {
                // Comprobamos que el upload previo a R2 ya existía efectivamente
                $allR2Files = Storage::disk('r2_private')->allFiles();
                if (count($allR2Files) > 0) {
                    throw new \Exception('Forced Database Failure after R2 Put');
                }
            }
        });

        try {
            $this->actingAs($doctor)->post(route('doctor.recetas.store'), [
                'cita_id' => $cita->id,
                'diagnostico' => 'Faringitis aguda',
                'medicamentos' => 'Amoxicilina 500mg c/8h',
                'indicaciones' => 'Tomar con abundante agua',
            ]);
            $this->fail('Debió lanzar excepción');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Forced Database Failure after R2 Put', $e->getMessage());
        }

        // 0 recetas en BD
        $this->assertNull(Receta::where('cita_id', $cita->id)->first());

        // 0 objetos en R2
        $allFiles = Storage::disk('r2_private')->allFiles();
        $this->assertCount(0, $allFiles, 'No debe quedar ningún archivo huérfano en R2');
    }

    /**
     * PR3 — Store: PDF path guardado / Operación posterior falla antes de commit
     */
    public function test_pr3_store_failure_in_transaction_after_save_cleans_up_r2(): void
    {
        $this->withoutExceptionHandling();
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $updateObserved = false;

        DB::listen(function ($query) use (&$updateObserved) {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'update') && str_contains($sql, 'recetas') && str_contains($sql, 'pdf_path')) {
                $updateObserved = true;
                // Objeto ya subido y verificado en R2
                throw new \Exception('Forced Post-Save DB Failure');
            }
        });

        try {
            $this->actingAs($doctor)->post(route('doctor.recetas.store'), [
                'cita_id' => $cita->id,
                'diagnostico' => 'Faringitis aguda',
                'medicamentos' => 'Amoxicilina 500mg c/8h',
                'indicaciones' => 'Tomar con abundante agua',
            ]);
            $this->fail('Debió lanzar excepción');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Forced Post-Save DB Failure', $e->getMessage());
        }

        $this->assertTrue($updateObserved);
        $this->assertNull(Receta::where('cita_id', $cita->id)->first());
        $this->assertCount(0, Storage::disk('r2_private')->allFiles());
    }

    /**
     * PR4 — Update normal
     */
    public function test_pr4_normal_update_replaces_pdf_and_cleans_old_after_commit(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        // Crear receta inicial
        $receta = $this->createInitialRecipe($cita);
        $oldPdfPath = $receta->pdf_path;
        $this->assertTrue(Storage::disk('r2_private')->exists($oldPdfPath));

        // Actualizar receta
        $response = $this->actingAs($doctor)->post(route('doctor.recetas.update'), [
            'cita_id' => $cita->id,
            'diagnostico' => 'Diagnóstico actualizado',
            'medicamentos' => 'Medicamento actualizado',
            'regenerar_pdf' => true,
            'reenviar' => false,
        ]);

        $response->assertRedirect(route('doctor.recetas.edit', $cita->id));

        $receta->refresh();
        $newPdfPath = $receta->pdf_path;
        $this->assertNotEquals($oldPdfPath, $newPdfPath);

        // El nuevo existe, el antiguo fue eliminado
        $this->assertTrue(Storage::disk('r2_private')->exists($newPdfPath));
        $this->assertFalse(Storage::disk('r2_private')->exists($oldPdfPath));

        $files = Storage::disk('r2_private')->allFiles("documents/recipes/{$receta->id}");
        $this->assertCount(1, $files);
    }

    /**
     * PR5 — Update: nuevo PDF subido / DB Fail
     * El nuevo PDF se sube a R2, pero la transacción de actualización en BD falla.
     * Resultado: BD sigue apuntando a old.pdf, old.pdf sigue existiendo, y new.pdf es eliminado de R2.
     */
    public function test_pr5_update_db_failure_retains_old_pdf_and_cleans_up_new_pdf(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        // Crear receta inicial
        $receta = $this->createInitialRecipe($cita);
        $oldPdfPath = $receta->pdf_path;

        // Simulamos fallo DB durante la actualización
        Receta::updating(function ($model) {
            if ($model->diagnostico === 'Diagnóstico con fallo DB') {
                throw new \Exception('Forced DB Failure during Update');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($doctor)->post(route('doctor.recetas.update'), [
                'cita_id' => $cita->id,
                'diagnostico' => 'Diagnóstico con fallo DB',
                'medicamentos' => 'Medicamento nuevo',
                'regenerar_pdf' => true,
                'reenviar' => false,
            ]);
            $this->fail('Debió lanzar excepción');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Forced DB Failure during Update', $e->getMessage());
        }

        $receta->refresh();
        // BD sigue apuntando al PDF anterior
        $this->assertEquals($oldPdfPath, $receta->pdf_path);
        // El PDF anterior continúa existiendo intacto
        $this->assertTrue(Storage::disk('r2_private')->exists($oldPdfPath));

        // En R2 debe haber exactamente 1 archivo (el antiguo), 0 archivos huérfanos
        $files = Storage::disk('r2_private')->allFiles("documents/recipes/{$receta->id}");
        $this->assertCount(1, $files);
        $this->assertEquals([$oldPdfPath], $files);
    }

    /**
     * PR6 — No borrar old antes de commit
     */
    public function test_pr6_old_pdf_is_not_deleted_if_update_fails(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $receta = $this->createInitialRecipe($cita);
        $oldPdfPath = $receta->pdf_path;

        Receta::updating(function ($model) {
            throw new \RuntimeException('Failure before commit');
        });

        try {
            $this->actingAs($doctor)->post(route('doctor.recetas.update'), [
                'cita_id' => $cita->id,
                'diagnostico' => 'Nuevo diagnostico',
                'medicamentos' => 'Nuevo medicamento',
                'regenerar_pdf' => true,
                'reenviar' => false,
            ]);
        } catch (\Throwable $e) {
            // Expected
        }

        $receta->refresh();
        $this->assertEquals($oldPdfPath, $receta->pdf_path);
        $this->assertTrue(Storage::disk('r2_private')->exists($oldPdfPath));
    }

    /**
     * PR7 — Resend normal
     */
    public function test_pr7_resend_generates_new_pdf_and_cleans_up_old(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $receta = $this->createInitialRecipe($cita);
        $oldPdfPath = $receta->pdf_path;

        $response = $this->actingAs($doctor)->post(route('doctor.recetas.resend', $cita->id));
        $response->assertSessionHas('success');

        $receta->refresh();
        $newPdfPath = $receta->pdf_path;
        $this->assertNotEquals($oldPdfPath, $newPdfPath);
        $this->assertTrue(Storage::disk('r2_private')->exists($newPdfPath));
        $this->assertFalse(Storage::disk('r2_private')->exists($oldPdfPath));
    }

    /**
     * PR8 — Resend: DB Fail después de upload elimina el nuevo PDF y conserva el anterior
     */
    public function test_pr8_resend_db_failure_cleans_up_new_pdf_and_retains_old(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $receta = $this->createInitialRecipe($cita);
        $oldPdfPath = $receta->pdf_path;

        $shouldFail = true;
        DB::listen(function ($query) use (&$shouldFail) {
            $sql = strtolower($query->sql);
            if ($shouldFail && str_contains($sql, 'update') && str_contains($sql, 'recetas') && str_contains($sql, 'pdf_path')) {
                throw new \Exception('Forced DB Failure in resend');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($doctor)->post(route('doctor.recetas.resend', $cita->id));
            $this->fail('Debió lanzar excepción');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Forced DB Failure in resend', $e->getMessage());
        }

        $shouldFail = false;
        $receta->refresh();
        $this->assertEquals($oldPdfPath, $receta->pdf_path);
        $this->assertTrue(Storage::disk('r2_private')->exists($oldPdfPath));

        $files = Storage::disk('r2_private')->allFiles("documents/recipes/{$receta->id}");
        $this->assertCount(1, $files);
    }

    /**
     * PR9 — Download con PDF existente sirve el archivo sin subir otro
     */
    public function test_pr9_download_with_existing_pdf_does_not_upload_duplicate(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $receta = $this->createInitialRecipe($cita);
        $pdfPath = $receta->pdf_path;

        $response = $this->actingAs($doctor)->get(route('doctor.recetas.download', $cita->id));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $receta->refresh();
        $this->assertEquals($pdfPath, $receta->pdf_path);

        $files = Storage::disk('r2_private')->allFiles("documents/recipes/{$receta->id}");
        $this->assertCount(1, $files);
    }

    /**
     * PR10 — Download regenera PDF faltante
     */
    public function test_pr10_download_regenerates_missing_pdf(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $receta = $this->createInitialRecipe($cita);
        $oldPdfPath = $receta->pdf_path;

        // Eliminamos el archivo físico para simular archivo faltante
        Storage::disk('r2_private')->delete($oldPdfPath);
        $this->assertFalse(Storage::disk('r2_private')->exists($oldPdfPath));

        $response = $this->actingAs($doctor)->get(route('doctor.recetas.download', $cita->id));
        $response->assertOk();

        $receta->refresh();
        $newPdfPath = $receta->pdf_path;
        $this->assertNotEquals($oldPdfPath, $newPdfPath);
        $this->assertTrue(Storage::disk('r2_private')->exists($newPdfPath));
    }

    /**
     * PR11 — Download: Upload OK / Persistencia Fail
     */
    public function test_pr11_download_regeneration_db_failure_cleans_up_new_r2_object(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $receta = $this->createInitialRecipe($cita);
        $oldPdfPath = $receta->pdf_path;
        Storage::disk('r2_private')->delete($oldPdfPath);

        $shouldFail = true;
        DB::listen(function ($query) use (&$shouldFail) {
            $sql = strtolower($query->sql);
            if ($shouldFail && str_contains($sql, 'update') && str_contains($sql, 'recetas') && str_contains($sql, 'pdf_path')) {
                throw new \Exception('Forced DB Failure in download regeneration');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($doctor)->get(route('doctor.recetas.download', $cita->id));
            $this->fail('Debió lanzar excepción');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Forced DB Failure in download regeneration', $e->getMessage());
        }

        $shouldFail = false;
        $files = Storage::disk('r2_private')->allFiles("documents/recipes/{$receta->id}");
        $this->assertCount(0, $files, 'No debe quedar ningún archivo huérfano en R2');
    }

    /**
     * PR12 — R2 verification fail (contenido no es PDF válido)
     */
    public function test_pr12_corrupted_content_fails_verification_and_cleans_up(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $receta = Receta::create([
            'cita_id' => $cita->id,
            'diagnostico' => 'Diagnóstico',
            'medicamentos' => 'Medicamentos',
            'csv' => 'CSVTEST',
            'pdf_path' => '',
            'pdf_disk' => null,
        ]);

        $service = app(RecipeDocumentService::class);

        try {
            $service->storeRecipePdf($receta, 'INVALID_NOT_A_PDF_CONTENT');
            $this->fail('Debió lanzar InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('does not have a valid %PDF header', $e->getMessage());
        }

        $files = Storage::disk('r2_private')->allFiles("documents/recipes/{$receta->id}");
        $this->assertCount(0, $files);
    }

    /**
     * PR13 — Retry después de un fallo
     */
    public function test_pr13_retry_succeeds_after_initial_failure(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente');
        $cita = $this->createRealizedCita($doctor, $paciente);

        $shouldFail = true;

        DB::listen(function ($query) use (&$shouldFail) {
            $sql = strtolower($query->sql);
            if ($shouldFail && str_contains($sql, 'update') && str_contains($sql, 'recetas') && str_contains($sql, 'pdf_path')) {
                throw new \Exception('Transient DB Failure');
            }
        });

        // Intento 1: Falla
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($doctor)->post(route('doctor.recetas.store'), [
                'cita_id' => $cita->id,
                'diagnostico' => 'Diagnóstico',
                'medicamentos' => 'Medicamentos',
            ]);
        } catch (\Throwable $e) {
            // Expected
        }

        $this->assertNull(Receta::where('cita_id', $cita->id)->first());
        $this->assertCount(0, Storage::disk('r2_private')->allFiles());

        // Intento 2: Éxito
        $shouldFail = false;

        $response = $this->actingAs($doctor)->post(route('doctor.recetas.store'), [
            'cita_id' => $cita->id,
            'diagnostico' => 'Diagnóstico',
            'medicamentos' => 'Medicamentos',
        ]);

        $response->assertRedirect(route('doctor.citas'));
        $receta = Receta::where('cita_id', $cita->id)->firstOrFail();
        $this->assertTrue(Storage::disk('r2_private')->exists($receta->pdf_path));
        $this->assertCount(1, Storage::disk('r2_private')->allFiles("documents/recipes/{$receta->id}"));
    }
}
