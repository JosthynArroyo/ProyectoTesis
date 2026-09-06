<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\LaboratorioOrden;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NavbarAndDownloadOverlayRegressionTest extends TestCase
{
    use DatabaseTransactions;

    private function createUserForRole(string $roleName, string $email): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['label' => ucfirst($roleName)]);
        $user = User::factory()->create([
            'email' => $email,
            'password' => bcrypt('password123'),
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
        ]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    /**
     * Test guest navbar preserves public layout and login trigger.
     */
    public function test_guest_navbar_preserves_clean_layout_and_login_trigger(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('data-login-trigger', false);
        $response->assertSee('Inicio');
    }

    /**
     * Test authenticated navbar renders in one row with nowrap classes and correct panel links.
     */
    public function test_authenticated_navbar_renders_nowrap_items_and_logout_form(): void
    {
        $admin = $this->createUserForRole('administrador', 'admin_navbar@example.com');

        $response = $this->actingAs($admin)->get('/');
        $response->assertOk();
        $response->assertSee('Mi panel');
        $response->assertSee('Salir');
        $response->assertSee('whitespace-nowrap', false);
        $response->assertSee('<form method="POST" action="'.route('salir').'"', false);
        $response->assertSee($admin->dashboardPath());
    }

    /**
     * Test laboratorio ordenes view renders download link with download attribute.
     */
    public function test_laboratorio_ordenes_view_renders_download_attribute(): void
    {
        $lab = $this->createUserForRole('laboratorio', 'lab_download@example.com');
        $doctor = $this->createUserForRole('doctor', 'doctor_download@example.com');
        $patient = $this->createUserForRole('paciente', 'patient_download@example.com');
        $esp = Especialidad::factory()->create(['activo' => true]);

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $esp->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00:00',
            'motivo_consulta' => 'Exámenes de laboratorio',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        LaboratorioOrden::create([
            'cita_id' => $cita->id,
            'solicitante_id' => $patient->id,
            'origen' => 'doctor',
            'prioridad' => 'normal',
            'tipo_examen' => 'Perfil Lipidico',
            'estado' => LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE,
            'resultado_path' => 'lab_results/test_result.pdf',
            'resultado_publicado_at' => now(),
        ]);

        $response = $this->actingAs($lab)->get(route('laboratorio.ordenes.index'));
        $response->assertOk();
        $response->assertSee('Descargar resultado');
        $response->assertSee('download', false);
    }
}
