<?php

namespace Tests\Unit;

use App\Jobs\EnviarCertificadoMedicoJob;
use App\Jobs\EnviarPedidoLaboratorioJob;
use App\Mail\CertificadoMedicoMail;
use App\Mail\PedidoLaboratorioMail;
use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\ClinicalRecord;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\PedidoLaboratorio;
use App\Models\Role;
use App\Models\User;
use App\Services\CertificadoMedicoPdfService;
use App\Services\DocumentoCsvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentEmailJobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
    }

    public function test_certificado_medico_job_envia_pdf_y_no_duplica_correo(): void
    {
        $paciente = $this->userWithRole('paciente', 'titular@example.com');
        $dependiente = Dependiente::create([
            'user_id' => $paciente->id,
            'nombre' => 'Paciente Dependiente',
            'dni' => '0999999999',
            'fecha_nacimiento' => now()->subYears(8)->toDateString(),
            'sexo' => 'Femenino',
            'parentesco' => 'hija',
            'activo' => true,
        ]);
        $doctor = $this->userWithRole('doctor', 'doctor@example.com');
        $especialidad = Especialidad::factory()->create();
        $doctor->especialidades()->attach($especialidad->id);
        $clinicalRecord = ClinicalRecord::create([
            'patient_id' => $paciente->id,
            'allergies_status' => ClinicalRecord::ALLERGIES_UNKNOWN,
        ]);

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'dependiente_id' => $dependiente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->toDateString(),
            'hora' => '09:00:00',
            'motivo_consulta' => 'Control',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-20260710-000001',
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'dependiente_id' => $dependiente->id,
            'doctor_id' => $doctor->id,
            'clinical_record_id' => $clinicalRecord->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Constancia de reposo.',
            'dias_reposo' => 2,
            'pdf_path' => 'certificados-medicos/cm-test.pdf',
            'envio_estado' => 'queued',
            'envio_intentos' => 0,
        ]);

        Storage::disk('local')->put($certificado->pdf_path, '%PDF-1.4 fake');

        (new EnviarCertificadoMedicoJob($certificado->id))->handle(app(CertificadoMedicoPdfService::class));
        (new EnviarCertificadoMedicoJob($certificado->id))->handle(app(CertificadoMedicoPdfService::class));

        $certificado->refresh();
        $this->assertSame('sent', $certificado->envio_estado);
        $this->assertSame($paciente->email, $certificado->enviado_a);
        Mail::assertSent(CertificadoMedicoMail::class, 1);
    }

    public function test_certificado_medico_job_marca_error_sin_destinatario(): void
    {
        $paciente = $this->userWithRole('paciente', '');
        $doctor = $this->userWithRole('doctor', 'doctor@example.com');
        $especialidad = Especialidad::factory()->create();
        $doctor->especialidades()->attach($especialidad->id);
        $clinicalRecord = ClinicalRecord::create([
            'patient_id' => $paciente->id,
            'allergies_status' => ClinicalRecord::ALLERGIES_UNKNOWN,
        ]);

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->toDateString(),
            'hora' => '09:00:00',
            'motivo_consulta' => 'Control',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-20260710-000002',
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'clinical_record_id' => $clinicalRecord->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Constancia de reposo.',
            'dias_reposo' => 0,
            'pdf_path' => 'certificados-medicos/cm-test-2.pdf',
            'envio_estado' => 'queued',
            'envio_intentos' => 0,
        ]);

        Storage::disk('local')->put($certificado->pdf_path, '%PDF-1.4 fake');

        (new EnviarCertificadoMedicoJob($certificado->id))->handle(app(CertificadoMedicoPdfService::class));

        $certificado->refresh();
        $this->assertSame('failed', $certificado->envio_estado);
        $this->assertNotNull($certificado->envio_error);
        Mail::assertNothingSent();
    }

    public function test_certificado_medico_job_permite_reintento_despues_de_falla(): void
    {
        $paciente = $this->userWithRole('paciente', '');
        $doctor = $this->userWithRole('doctor', 'doctor@example.com');
        $especialidad = Especialidad::factory()->create();
        $doctor->especialidades()->attach($especialidad->id);
        $clinicalRecord = ClinicalRecord::create([
            'patient_id' => $paciente->id,
            'allergies_status' => ClinicalRecord::ALLERGIES_UNKNOWN,
        ]);

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->toDateString(),
            'hora' => '09:00:00',
            'motivo_consulta' => 'Control',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $certificado = CertificadoMedico::create([
            'codigo' => 'CM-20260710-000003',
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'clinical_record_id' => $clinicalRecord->id,
            'fecha_emision' => now(),
            'texto_constancia' => 'Constancia de reposo.',
            'dias_reposo' => 0,
            'pdf_path' => 'certificados-medicos/cm-test-3.pdf',
            'envio_estado' => 'queued',
            'envio_intentos' => 0,
        ]);

        Storage::disk('local')->put($certificado->pdf_path, '%PDF-1.4 fake');

        (new EnviarCertificadoMedicoJob($certificado->id))->handle(app(CertificadoMedicoPdfService::class));

        $paciente->forceFill(['email' => 'titular@example.com'])->save();
        Mail::fake();

        (new EnviarCertificadoMedicoJob($certificado->id))->handle(app(CertificadoMedicoPdfService::class));

        $certificado->refresh();
        $this->assertSame('sent', $certificado->envio_estado);
        $this->assertSame('titular@example.com', $certificado->enviado_a);
        Mail::assertSent(CertificadoMedicoMail::class, 1);
    }

    public function test_pedido_laboratorio_job_envia_pdf_y_no_duplica_correo(): void
    {
        $paciente = $this->userWithRole('paciente', 'titular@example.com');
        $doctor = $this->userWithRole('doctor', 'doctor@example.com');
        $especialidad = Especialidad::factory()->create();
        $doctor->especialidades()->attach($especialidad->id);

        $cita = Cita::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->toDateString(),
            'hora' => '09:00:00',
            'motivo_consulta' => 'Control',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $pedido = PedidoLaboratorio::create([
            'cita_id' => $cita->id,
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'csv' => 'CSV-TEST-1',
            'examenes' => ['biometria_hematica'],
            'pdf_path' => 'pedidos-laboratorio/pl-test.pdf',
            'estado' => 'pendiente_toma',
            'envio_estado' => 'queued',
            'envio_intentos' => 0,
        ]);

        Storage::disk('local')->put($pedido->pdf_path, '%PDF-1.4 fake');

        (new EnviarPedidoLaboratorioJob($pedido->id))->handle(app(DocumentoCsvService::class));
        (new EnviarPedidoLaboratorioJob($pedido->id))->handle(app(DocumentoCsvService::class));

        $pedido->refresh();
        $this->assertSame('sent', $pedido->envio_estado);
        $this->assertSame($paciente->email, $pedido->enviado_a);
        Mail::assertSent(PedidoLaboratorioMail::class, 1);
    }

    private function userWithRole(string $role, ?string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }
}
