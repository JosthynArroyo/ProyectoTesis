<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Receta;
use App\Models\Role;
use App\Models\User;
use App\Services\RecipeDocumentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentoVerificacionUxTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.mode' => 'production']);
        config(['private_documents.disk' => 'r2_private']);
        config(['private_documents.recipe_disk' => 'r2_private']);
        config(['private_documents.certificate_disk' => 'r2_private']);

        Storage::fake('r2_private');
        Storage::fake('local');
        Storage::fake('public');
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

    private function createRealizedCita(User $doctor, User $paciente): Cita
    {
        $esp = Especialidad::firstOrCreate(['nombre' => 'Medicina General']);

        return Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);
    }

    /**
     * RED TEST: POST /verificar-documento with non-existent CSV must NOT result in 404,
     * but redirect back to form with friendly error and preserved old input.
     */
    public function test_post_search_with_invalid_csv_redirects_to_form_with_friendly_error_and_input(): void
    {
        $response = $this->from(route('documentos.verificar.form'))
            ->post(route('documentos.verificar.search'), [
                'csv' => 'INVALID-1234',
            ]);

        // Must redirect to verification form, NOT throw 404 or redirect to a 404 page
        $response->assertStatus(302);
        $response->assertRedirect(route('documentos.verificar.form'));
        $response->assertSessionHasErrors([
            'csv' => 'No se encontró un documento asociado al código ingresado. Verifique el código e intente nuevamente.',
        ]);
        $response->assertSessionHasInput('csv', 'INVALID-1234');

        // Follow redirect to verify page renders friendly message and preserves input value
        $pageResponse = $this->followRedirects($response);
        $pageResponse->assertStatus(200);
        $pageResponse->assertSee('No se encontró un documento asociado al código ingresado. Verifique el código e intente nuevamente.');
        $pageResponse->assertSee('value="INVALID-1234"', false);
        $pageResponse->assertDontSee('Página no encontrada');
    }

    /**
     * RED TEST: Direct GET /verificar/{csv} with non-existent CSV must NOT return generic 404,
     * but redirect to the verification form with friendly error and preserved input.
     */
    public function test_get_show_with_invalid_csv_redirects_to_form_with_friendly_error_and_input(): void
    {
        $response = $this->get(route('documentos.verificar.show', ['csv' => 'INVALID-1234']));

        // Must NOT return 404
        $this->assertNotEquals(404, $response->getStatusCode());
        $response->assertStatus(302);
        $response->assertRedirect(route('documentos.verificar.form'));
        $response->assertSessionHasErrors([
            'csv' => 'No se encontró un documento asociado al código ingresado. Verifique el código e intente nuevamente.',
        ]);
        $response->assertSessionHasInput('csv', 'INVALID-1234');

        // Follow redirect to verify rendered content
        $pageResponse = $this->followRedirects($response);
        $pageResponse->assertStatus(200);
        $pageResponse->assertSee('No se encontró un documento asociado al código ingresado. Verifique el código e intente nuevamente.');
        $pageResponse->assertSee('value="INVALID-1234"', false);
        $pageResponse->assertDontSee('Página no encontrada');
    }

    /**
     * Empty input validation test.
     */
    public function test_post_search_with_empty_input_fails_validation(): void
    {
        $response = $this->from(route('documentos.verificar.form'))
            ->post(route('documentos.verificar.search'), [
                'csv' => '',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['csv']);
    }

    /**
     * Oversized format validation test.
     */
    public function test_post_search_with_oversized_input_fails_validation(): void
    {
        $response = $this->from(route('documentos.verificar.form'))
            ->post(route('documentos.verificar.search'), [
                'csv' => str_repeat('A', 33),
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['csv']);
    }

    /**
     * Valid document must continue to verify correctly.
     */
    public function test_valid_document_verification_continues_to_work_seamlessly(): void
    {
        $doctor = $this->createRoleUser('doctor', ['name' => 'Dr. Patricio Estrella']);
        $paciente = $this->createRoleUser('paciente', ['name' => 'Bob Esponja Pantalones']);
        $cita = $this->createRealizedCita($doctor, $paciente);

        $recipeService = app(RecipeDocumentService::class);
        [$pdfBinary, $csv] = $recipeService->generatePdfOutput($cita, 'Diagnostico OK', 'Medicamento OK');

        $receta = Receta::create([
            'cita_id' => $cita->id,
            'diagnostico' => 'Diagnostico OK',
            'medicamentos' => 'Medicamento OK',
            'csv' => $csv,
            'pdf_path' => '',
        ]);
        $st = $recipeService->storeRecipePdf($receta, $pdfBinary);
        $receta->update(['pdf_path' => $st['pdf_path'], 'pdf_disk' => $st['pdf_disk']]);

        // Form POST
        $postResponse = $this->post(route('documentos.verificar.search'), [
            'csv' => $csv,
        ]);
        $postResponse->assertRedirect(route('documentos.verificar.show', ['csv' => $csv]));

        // Show GET
        $getResponse = $this->get(route('documentos.verificar.show', ['csv' => $csv]));
        $getResponse->assertStatus(200);
        $getResponse->assertSee('Receta médica');
        $getResponse->assertSee($csv);
        $getResponse->assertSee('Dr. Patricio Estrella');
        $getResponse->assertSee('Bob E.');
        $getResponse->assertSee('Verificado');
    }

    /**
     * Error message does not leak sensitive information or PII.
     */
    public function test_error_message_is_generic_and_leaks_no_pii_or_internals(): void
    {
        $response = $this->post(route('documentos.verificar.search'), [
            'csv' => 'NON-EXISTENT-CODE',
        ]);

        $content = session('errors') ? session('errors')->first('csv') : '';
        $this->assertEquals(
            'No se encontró un documento asociado al código ingresado. Verifique el código e intente nuevamente.',
            $content
        );

        $this->assertStringNotContainsString('SQL', $content);
        $this->assertStringNotContainsString('Exception', $content);
        $this->assertStringNotContainsString('table', $content);
        $this->assertStringNotContainsString('paciente', strtolower($content));
        $this->assertStringNotContainsString('doctor', strtolower($content));
    }
}
