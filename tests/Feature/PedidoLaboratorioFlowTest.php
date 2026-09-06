<?php

namespace Tests\Feature;

use App\Mail\PedidoLaboratorioMail;
use App\Mail\ResultadoPedidoLaboratorioMail;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\PedidoLaboratorio;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PedidoLaboratorioFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['private_documents.disk' => 'r2_private']);
        Storage::fake('local');
        Storage::fake('r2_private');
        Mail::fake();
    }

    public function test_full_laboratory_order_flow_generates_pdf_and_sends_mail(): void
    {
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);
        $patientRole = Role::firstOrCreate(['name' => 'paciente']);
        $labRole = Role::firstOrCreate(['name' => 'laboratorio']);

        $especialidad = Especialidad::factory()->create(['nombre' => 'Cardiología']);

        $doctor = User::factory()->create([
            'email' => 'doctor.test@clinic.test',
            'status' => User::STATUS_ACTIVE,
        ]);
        $doctor->roles()->sync([$doctorRole->id]);
        $doctor->especialidades()->sync([$especialidad->id]);

        $patient = User::factory()->create([
            'email' => 'patient.test@clinic.test',
            'status' => User::STATUS_ACTIVE,
        ]);
        $patient->roles()->sync([$patientRole->id]);

        $labUser = User::factory()->create([
            'email' => 'lab.test@clinic.test',
            'status' => User::STATUS_ACTIVE,
        ]);
        $labUser->roles()->sync([$labRole->id]);

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00:00',
            'motivo_consulta' => 'Revisión cardíaca de rutina',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $this->actingAs($doctor)
            ->get(route('doctor.pedidos-laboratorio.create', $cita))
            ->assertOk()
            ->assertSee('Seleccionar Exámenes')
            ->assertSee('Biometría Hemática completa');

        $response = $this->actingAs($doctor)
            ->post(route('doctor.pedidos-laboratorio.store', $cita), [
                'examenes' => ['biometria_hematica', 'glucosa', 'trigliceridos'],
            ]);

        $response->assertRedirect(route('doctor.citas'));

        $pedido = PedidoLaboratorio::first();
        $this->assertNotNull($pedido);
        $this->assertSame($cita->id, (int) $pedido->cita_id);
        $this->assertSame($patient->id, (int) $pedido->paciente_id);
        $this->assertSame($doctor->id, (int) $pedido->doctor_id);
        $this->assertEquals(['biometria_hematica', 'glucosa', 'trigliceridos'], $pedido->examenes);
        $this->assertSame('pendiente_toma', $pedido->estado);
        $this->assertSame('sent', $pedido->fresh()->envio_estado);
        $this->assertSame($patient->email, $pedido->fresh()->enviado_a);

        $this->assertNotNull($pedido->pdf_path);
        Storage::disk($pedido->pdf_disk ?: 'local')->assertExists($pedido->pdf_path);

        Mail::assertSent(PedidoLaboratorioMail::class, function ($mail) use ($patient) {
            return $mail->hasTo($patient->email) && ! empty($mail->relativePath);
        });

        $this->actingAs($labUser)
            ->get(route('laboratorio.pedidos.index'))
            ->assertOk()
            ->assertSee($patient->name)
            ->assertSee('Biometría Hemática')
            ->assertSee('Glucosa')
            ->assertSee('Triglicéridos')
            ->assertSee('Pendiente');

        $this->actingAs($labUser)
            ->post(route('laboratorio.pedidos.muestra', $pedido))
            ->assertRedirect();

        $pedido->refresh();
        $this->assertSame('muestra_tomada', $pedido->estado);

        $resultPdf = UploadedFile::fake()
            ->createWithContent('resultados_analisis.pdf', '%PDF-1.4 resultado de laboratorio')
            ->mimeType('application/pdf');

        $responseResult = $this->actingAs($labUser)
            ->post(route('laboratorio.pedidos.resultado', $pedido), [
                'resultado_pdf' => $resultPdf,
                'resultado_resumen' => 'Hemoglobina y glucosa dentro de los límites normales.',
            ]);

        $responseResult->assertRedirect();

        $pedido->refresh();
        $this->assertSame('resultado_listo', $pedido->estado);
        $this->assertSame('Hemoglobina y glucosa dentro de los límites normales.', $pedido->resultado_resumen);
        $this->assertNotNull($pedido->resultado_path);
        $this->assertStringStartsWith('documents/laboratory-results/legacy-medical-orders/', $pedido->resultado_path);
        Storage::disk('r2_private')->assertExists($pedido->resultado_path);
        Storage::disk('local')->assertMissing($pedido->resultado_path);

        $this->actingAs($labUser)
            ->get(route('laboratorio.pedidos.download-resultado', $pedido))
            ->assertOk();

        $this->actingAs($patient)
            ->get(route('paciente.laboratorio.pedido.download', $pedido))
            ->assertOk();

        Mail::assertSent(ResultadoPedidoLaboratorioMail::class, function ($mail) use ($patient) {
            $rawAttachments = $mail->build()->rawAttachments;

            return $mail->hasTo($patient->email)
                && collect($rawAttachments)->contains(function (array $attachment): bool {
                    $data = is_callable($attachment['data']) ? $attachment['data']() : $attachment['data'];

                    return $attachment['options']['mime'] === 'application/pdf'
                        && $data !== '';
                });
        });
    }

    public function test_second_submission_reuses_existing_laboratory_order_without_duplication(): void
    {
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);
        $patientRole = Role::firstOrCreate(['name' => 'paciente']);

        $especialidad = Especialidad::factory()->create(['nombre' => 'Cardiología']);

        $doctor = User::factory()->create([
            'email' => 'doctor.dup@clinic.test',
            'status' => User::STATUS_ACTIVE,
        ]);
        $doctor->roles()->sync([$doctorRole->id]);
        $doctor->especialidades()->sync([$especialidad->id]);

        $patient = User::factory()->create([
            'email' => 'patient.dup@clinic.test',
            'status' => User::STATUS_ACTIVE,
        ]);
        $patient->roles()->sync([$patientRole->id]);

        $cita = Cita::create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->toDateString(),
            'hora' => '11:00:00',
            'motivo_consulta' => 'Control',
            'estado' => Cita::ESTADO_REALIZADA,
            'activo' => true,
        ]);

        $first = $this->actingAs($doctor)->post(route('doctor.pedidos-laboratorio.store', $cita), [
            'examenes' => ['biometria_hematica'],
        ]);

        $first->assertRedirect(route('doctor.citas'));
        $pedido = PedidoLaboratorio::firstOrFail();

        $second = $this->actingAs($doctor)->post(route('doctor.pedidos-laboratorio.store', $cita), [
            'examenes' => ['glucosa'],
        ]);

        $second->assertRedirect(route('doctor.pedidos-laboratorio.download', $pedido));
        $second->assertSessionHas('info', 'Ya existe un pedido de laboratorio para esta cita.');

        $this->assertSame(1, PedidoLaboratorio::count());
        Mail::assertSent(PedidoLaboratorioMail::class, 1);
    }
}
