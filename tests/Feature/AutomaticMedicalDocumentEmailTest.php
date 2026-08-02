<?php

namespace Tests\Feature;

use App\Jobs\EnviarCertificadoMedicoJob;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Jobs\EnviarPedidoLaboratorioJob;
use App\Jobs\NotificarCambioEstadoCitaJob;
use App\Mail\CambioEstadoCitaMail;
use App\Mail\CertificadoMedicoMail;
use App\Mail\PedidoLaboratorioMail;
use App\Mail\RecetaMedicaMail;
use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\PedidoLaboratorio;
use App\Models\Receta;
use App\Models\Role;
use App\Models\User;
use App\Services\CertificadoMedicoPdfService;
use App\Services\DocumentoCsvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AutomaticMedicalDocumentEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function createRealizedCita(User $doctor, User $paciente, ?Dependiente $dependiente = null): Cita
    {
        $esp = Especialidad::firstOrCreate(['nombre' => 'Medicina General']);

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

    // --- CITAS & NOTIFICACIONES TESTS ---

    /** 1 & 7. Creación de cita despacha la notificación existente al correo real */
    public function test_cita_creation_dispatches_confirmation_email_to_patient_and_doctor(): void
    {
        $doctor = $this->createRoleUser('doctor', ['email' => 'doc.cita@test.com']);
        $paciente = $this->createRoleUser('paciente', ['email' => 'paciente.cita@test.com']);
        $cita = $this->createRealizedCita($doctor, $paciente);

        $job = new EnviarConfirmacionCitaJob($cita);
        $job->handle();

        Mail::assertQueued(CambioEstadoCitaMail::class, function ($mail) {
            return $mail->hasTo('paciente.cita@test.com') && $mail->rolReceptor === 'paciente';
        });

        Mail::assertQueued(CambioEstadoCitaMail::class, function ($mail) {
            return $mail->hasTo('doc.cita@test.com') && $mail->rolReceptor === 'doctor';
        });
    }

    /** 2 & 8. Cambios de estado (cancelada, reagendada, aceptada) despachan notificación y al representante si es dependiente */
    public function test_cita_status_changes_dispatch_email_to_representative(): void
    {
        $doctor = $this->createRoleUser('doctor', ['email' => 'doc.status@test.com']);
        $representante = $this->createRoleUser('paciente', ['email' => 'rep.status@test.com']);

        $dependiente = Dependiente::create([
            'user_id' => $representante->id,
            'nombre' => 'Nino Status',
            'tipo_documento' => 'cedula',
            'dni' => '1754504999',
            'fecha_nacimiento' => '2021-05-05',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
            'activo' => true,
        ]);

        $cita = $this->createRealizedCita($doctor, $representante, $dependiente);

        $job = new NotificarCambioEstadoCitaJob($cita, 'cancelada', 'paciente');
        $job->handle();

        Mail::assertQueued(CambioEstadoCitaMail::class, function ($mail) {
            return $mail->hasTo('rep.status@test.com') && $mail->evento === 'cancelada';
        });
    }

    // --- CERTIFICADO MÉDICO TESTS ---

    /** 3 & 4. Certificado automático despacha una vez y el reenvío manual fuerza un segundo despacho */
    public function test_certificate_automatic_dispatch_and_manual_resend(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente', ['email' => 'cert.manual@test.com']);
        $cita = $this->createRealizedCita($doctor, $paciente);

        $this->actingAs($doctor)->post(route('doctor.certificados.store', $cita), [
            'texto_constancia' => 'Constancia test',
            'dias_reposo' => 0,
        ]);

        $cert = CertificadoMedico::where('cita_id', $cita->id)->firstOrFail();
        $this->assertEquals('sent', $cert->envio_estado);
        $this->assertEquals('cert.manual@test.com', $cert->enviado_a);
        $this->assertEquals(1, $cert->envio_intentos);

        Mail::assertSent(CertificadoMedicoMail::class, 1);

        // Manual resend (forceResend = true) sends second email
        $resendResp = $this->actingAs($doctor)->post(route('doctor.certificados.resend', $cert));
        $resendResp->assertRedirect();

        $cert->refresh();
        $this->assertEquals('sent', $cert->envio_estado);
        $this->assertEquals(2, $cert->envio_intentos);
        Mail::assertSent(CertificadoMedicoMail::class, 2);
    }

    /** 9. Destinatario nulo marca fallo y no marca sent */
    public function test_certificate_null_recipient_marks_failed_and_not_sent(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $pacienteSinEmail = $this->createRoleUser('paciente', ['email' => '']);
        $cita = $this->createRealizedCita($doctor, $pacienteSinEmail);

        $this->actingAs($doctor)->post(route('doctor.certificados.store', $cita), [
            'texto_constancia' => 'Constancia sin email',
            'dias_reposo' => 0,
        ]);

        $cert = CertificadoMedico::where('cita_id', $cita->id)->firstOrFail();

        $job = new EnviarCertificadoMedicoJob($cert->id);
        $job->handle(app(CertificadoMedicoPdfService::class));

        $cert->refresh();
        $this->assertEquals('failed', $cert->envio_estado);
        $this->assertStringContainsString('No hay un correo registrado', $cert->envio_error);
    }

    /** 10 & 11. Excepción de transporte marca failed pero el documento y el PDF en R2 sobreviven */
    public function test_certificate_transport_exception_marks_failed_and_preserves_r2_file(): void
    {
        Mail::shouldReceive('to')->andThrow(new \Exception('SMTP Error Simulated'));

        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente', ['email' => 'smtp.fail@test.com']);
        $cita = $this->createRealizedCita($doctor, $paciente);

        $this->actingAs($doctor)->post(route('doctor.certificados.store', $cita), [
            'texto_constancia' => 'Constancia failure test',
            'dias_reposo' => 0,
        ]);

        $cert = CertificadoMedico::where('cita_id', $cita->id)->firstOrFail();

        $job = new EnviarCertificadoMedicoJob($cert->id);
        $job->handle(app(CertificadoMedicoPdfService::class));

        $cert->refresh();
        $this->assertEquals('failed', $cert->envio_estado);
        $this->assertStringContainsString('SMTP Error Simulated', $cert->envio_error);
        $this->assertTrue(Storage::disk('r2_private')->exists($cert->pdf_path));
    }

    // --- PEDIDO DE LABORATORIO TESTS ---

    /** 5 & 6. Orden automática despacha una vez y reenvío manual fuerza otro envío */
    public function test_lab_order_automatic_dispatch_and_manual_resend(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente', ['email' => 'order.manual@test.com']);
        $cita = $this->createRealizedCita($doctor, $paciente);

        $this->actingAs($doctor)->post(route('doctor.pedidos-laboratorio.store', $cita), [
            'examenes' => ['biometria_hematica'],
        ]);

        $pedido = PedidoLaboratorio::where('cita_id', $cita->id)->firstOrFail();
        $this->assertEquals('sent', $pedido->envio_estado);
        $this->assertEquals('order.manual@test.com', $pedido->enviado_a);
        $this->assertEquals(1, $pedido->envio_intentos);

        Mail::assertSent(PedidoLaboratorioMail::class, 1);

        // Manual resend
        $this->actingAs($doctor)->post(route('doctor.pedidos-laboratorio.resend', $pedido));

        $pedido->refresh();
        $this->assertEquals('sent', $pedido->envio_estado);
        $this->assertEquals(2, $pedido->envio_intentos);
        Mail::assertSent(PedidoLaboratorioMail::class, 2);
    }

    /** 12. Receta conserva su comportamiento y envía correo */
    public function test_recipe_preserves_existing_email_behavior(): void
    {
        $doctor = $this->createRoleUser('doctor');
        $paciente = $this->createRoleUser('paciente', ['email' => 'receta.keep@test.com']);
        $cita = $this->createRealizedCita($doctor, $paciente);

        $response = $this->actingAs($doctor)->post(route('doctor.recetas.store', $cita), [
            'cita_id' => $cita->id,
            'diagnostico' => 'Diagnostico test',
            'medicamentos' => 'Paracetamol 500mg',
            'indicaciones' => 'Cada 8 horas',
        ]);

        $response->assertRedirect();

        $receta = Receta::where('cita_id', $cita->id)->firstOrFail();
        $this->assertNotEmpty($receta->pdf_path);
        $this->assertEquals('r2_private', $receta->pdf_disk);

        Mail::assertSent(RecetaMedicaMail::class, function ($mail) {
            return $mail->hasTo('receta.keep@test.com');
        });
    }
}
