<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Cita;
use App\Models\Especialidad;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExportCitasTest extends TestCase
{
    use RefreshDatabase;

    public function test_exportar_citas_generar_archivo_excel()
    {
        $this->withoutMiddleware();

        $admin = User::factory()->create([
            'name'  => 'Admin Test',
            'email' => 'admin@test.com',
        ]);

        $paciente = User::factory()->create([
            'name'  => 'Paciente Test',
            'email' => 'paciente@test.com',
        ]);

        $doctor = User::factory()->create([
            'name'  => 'Doctor Test',
            'email' => 'doctor@test.com',
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('admin.citas.export'));

        $response->assertStatus(200);

        $this->assertTrue(
            str_contains($response->headers->get('content-type'), 'spreadsheetml')
            || str_contains($response->headers->get('content-type'), 'excel'),
            'La respuesta debe ser un archivo Excel'
        );
    }

    public function test_exportar_citas_con_datos_minimos()
    {
        $this->withoutMiddleware();

        $user = User::factory()->create();
        $this->actingAs($user);

        $paciente      = User::factory()->create();
        $doctor        = User::factory()->create();
        $especialidad  = Especialidad::factory()->create();

        Cita::factory()->create([
            'paciente_id'     => $paciente->id,
            'doctor_id'       => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha'           => '2024-01-15',
            'hora'            => '10:00:00',
            'estado'          => 'pendiente',
        ]);

        $response = $this->get(route('admin.citas.export'));

        $response->assertStatus(200);

        $this->assertTrue(
            str_contains($response->headers->get('content-type'), 'spreadsheetml')
            || str_contains($response->headers->get('content-type'), 'excel'),
            'La respuesta debe ser un archivo Excel'
        );
    }

    public function test_exportar_citas_con_dataset_grande()
    {
        $this->withoutMiddleware();

        $admin = User::factory()->create();
        $this->actingAs($admin);

        $paciente     = User::factory()->create();
        $doctor       = User::factory()->create();
        $especialidad = Especialidad::factory()->create();

        Cita::factory()->count(1000)->create([
            'paciente_id'     => $paciente->id,
            'doctor_id'       => $doctor->id,
            'especialidad_id' => $especialidad->id,
        ]);

        $response = $this->get(route('admin.citas.export'));
        $response->assertStatus(200);

        $tempFile = tempnam(sys_get_temp_dir(), 'citas');
        file_put_contents($tempFile, $response->streamedContent());

        $spreadsheet = IOFactory::load($tempFile);
        $sheet       = $spreadsheet->getActiveSheet();
        $highestRow  = $sheet->getHighestDataRow();

        // 1 fila de encabezado + 1000 citas = 1001
        $this->assertEquals(1001, $highestRow);

        unlink($tempFile);
    }
}
