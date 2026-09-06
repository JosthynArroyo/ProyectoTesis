<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\NotaSoap;
use App\Models\Receta;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DemoDoctorRecetasTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.mode' => 'demo']);
    }

    /**
     * Grupo A: Dataset de recetas coherente y renderizado del índice para el Doctor canónico.
     *
     * Contratos cubiertos:
     *   — El Dr. Fernando Alvarado tiene entre 2 y 5 recetas
     *   — Cada receta tiene diagnóstico, medicamentos, pdf_path y está asociada al doctor
     *   — El CSV de cada receta sigue el formato REC-2026-*
     *   — Sin PII (josthyn, arroyo) en diagnósticos ni medicamentos
     *   — Al menos una receta pertenece al paciente canónico Javier Espinoza
     *   — GET /doctor/recetas responde 200 y muestra tabla con recetas y botón de descarga
     */
    public function test_canonical_doctor_prescriptions_dataset_and_index(): void
    {
        $this->seed(DemoSeeder::class);

        $doctor = User::where('email', 'doctor.medicina@demo-clinigest.test')->firstOrFail();

        $recetas = Receta::whereHas('cita', function ($q) use ($doctor) {
            $q->where('doctor_id', $doctor->id);
        })->with(['cita.paciente', 'cita.especialidad'])->get();

        // Rango esperado de recetas
        $this->assertGreaterThanOrEqual(2, $recetas->count(), 'El Dr. Fernando Alvarado debe tener al menos 2 recetas.');
        $this->assertLessThanOrEqual(5, $recetas->count(), 'El Dr. Fernando Alvarado no debe exceder 5 recetas.');

        foreach ($recetas as $receta) {
            $this->assertNotEmpty($receta->diagnostico, "Receta #{$receta->id}: diagnóstico no debe estar vacío.");
            $this->assertNotEmpty($receta->medicamentos, "Receta #{$receta->id}: medicamentos no debe estar vacío.");
            $this->assertNotEmpty($receta->pdf_path, "Receta #{$receta->id}: pdf_path es obligatorio para descarga.");
            $this->assertNotNull($receta->cita, "Receta #{$receta->id}: debe estar asociada a una cita.");
            $this->assertNotNull($receta->cita->paciente, "Receta #{$receta->id}: la cita debe tener paciente.");
            $this->assertEquals($doctor->id, $receta->cita->doctor_id, "Receta #{$receta->id}: debe pertenecer al Dr. Fernando.");
            $this->assertStringStartsWith('REC-2026-', $receta->csv, "Receta #{$receta->id}: CSV debe seguir formato REC-2026-.");

            // Sin PII
            $this->assertStringNotContainsStringIgnoringCase('josthyn', $receta->diagnostico);
            $this->assertStringNotContainsStringIgnoringCase('arroyo', $receta->diagnostico);
            $this->assertStringNotContainsStringIgnoringCase('josthyn', $receta->medicamentos);
            $this->assertStringNotContainsStringIgnoringCase('arroyo', $receta->medicamentos);
        }

        // Al menos una receta de Javier Espinoza
        $recetaJavier = $recetas->first(fn ($r) => $r->cita->paciente->email === 'paciente@demo-clinigest.test');
        $this->assertNotNull($recetaJavier, 'Al menos una receta del Dr. Fernando debe pertenecer a Javier Espinoza.');

        // HTTP index renderiza correctamente
        $response = $this->actingAs($doctor)->get(route('doctor.recetas.index'));
        $response->assertStatus(200);
        $response->assertSee('Historial de recetas');
        $response->assertSee('Descargar');
        $response->assertSee('Javier Espinoza');
        $response->assertDontSee('Aún no hay recetas.');
    }

    /**
     * Grupo B: Descarga de PDF de receta y control de acceso entre médicos.
     *
     * Contratos cubiertos:
     *   — GET /doctor/recetas/descargar/{cita} genera y transmite PDF (Content-Type: application/pdf)
     *   — Otro doctor recibe HTTP 403 al intentar descargar receta ajena
     */
    public function test_canonical_doctor_prescription_download_and_access_control(): void
    {
        $this->seed(DemoSeeder::class);

        $doctor1 = User::where('email', 'doctor.medicina@demo-clinigest.test')->firstOrFail();
        $doctor2 = User::where('email', 'doctor.pediatria@demo-clinigest.test')->firstOrFail();

        $receta = Receta::whereHas('cita', function ($q) use ($doctor1) {
            $q->where('doctor_id', $doctor1->id);
        })->firstOrFail();

        // Doctor propietario puede descargar → 200 con PDF
        $response = $this->actingAs($doctor1)->get(route('doctor.recetas.download', $receta->cita->id));
        $response->assertStatus(200);
        $this->assertTrue(
            str_contains($response->headers->get('content-type'), 'application/pdf'),
            'El response debe tener content-type application/pdf'
        );

        // Otro doctor intenta descargar receta ajena → 403
        $response = $this->actingAs($doctor2)->get(route('doctor.recetas.download', $receta->cita->id));
        $response->assertStatus(403);
    }

    /**
     * Idempotencia: Reejecutar DemoSeeder no duplica recetas.
     * — Requiere 2 runs del seeder por diseño (inherente al contrato de idempotencia).
     */
    public function test_demo_clinical_history_seeder_is_idempotent(): void
    {
        $this->seed(DemoSeeder::class);
        $countInitial = Receta::count();

        // Segunda ejecución
        $this->seed(DemoSeeder::class);
        $countAfter = Receta::count();

        $this->assertEquals($countInitial, $countAfter, 'Reejecutar DemoSeeder no debe duplicar recetas.');
    }
}
