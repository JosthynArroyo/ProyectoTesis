<?php

namespace Tests\Unit;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\PedidoLaboratorio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PdfViewsRenderTest extends TestCase
{
    public function test_renderiza_pdf_de_usuarios_sin_codigo_blade_visible(): void
    {
        $user = new User([
            'id' => 15,
            'name' => 'Doctor Demo',
            'email' => 'doctor@example.com',
            'telefono' => '0991234567',
            'dni' => '0912345678',
            'status' => User::STATUS_ACTIVE,
            'created_at' => Carbon::parse('2026-04-09 10:00:00'),
        ]);

        $user->setRelation('roles', Collection::make([(object) ['name' => 'doctor']]));
        $user->setRelation('especialidades', Collection::make([
            new Especialidad(['nombre' => 'Cardiologia']),
        ]));

        $html = view('admin.users.usuarios-pdf', [
            'users' => Collection::make([$user]),
            'role' => 'doctor',
            'pdfCss' => 'body{color:#000;}',
            'statusLabels' => [
                User::STATUS_ACTIVE => 'Activo',
                User::STATUS_INACTIVE => 'Inactivo',
                User::STATUS_BLOCKED => 'Bloqueado',
            ],
        ])->render();

        $this->assertStringContainsString('Reporte de usuarios', $html);
        $this->assertStringContainsString('Activo', $html);
        $this->assertStringNotContainsString('@php', $html);
        $this->assertStringNotContainsString('{!!', $html);
        $this->assertStringNotContainsString('file_exists($cssPath)', $html);
    }

    public function test_renderiza_pdf_de_cambios_de_citas_sin_codigo_blade_visible(): void
    {
        $cita = new Cita([
            'id' => 8,
            'fecha' => '2026-04-10',
            'hora' => '11:30:00',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
        ]);

        $cita->setRelation('paciente', new User(['name' => 'Ana Torres']));
        $cita->setRelation('doctor', new User(['name' => 'Dr. Luis Mora']));

        $evento = (object) [
            'created_at' => Carbon::parse('2026-04-09 09:15:00'),
            'tipo' => 'no_se_presento',
            'cita_id' => 8,
            'cita' => $cita,
            'de_estado' => 'confirmada',
            'a_estado' => 'no_se_presento',
            'de_fecha' => null,
            'a_fecha' => null,
            'de_hora' => null,
            'a_hora' => null,
            'valor_anterior' => null,
            'valor_nuevo' => null,
            'comentario' => 'Paciente no asistio.',
        ];

        $html = view('admin.cambios-citas.pdf', [
            'rows' => Collection::make([$evento]),
            'tipo' => null,
            'estado' => null,
            'doctorId' => null,
            'paciente' => null,
            'desde' => null,
            'hasta' => null,
            'q' => null,
            'pdfCss' => 'body{color:#000;}',
            'eventLabels' => [
                'agendada' => 'Agendada',
                'confirmada' => 'Confirmada',
                'cancelada' => 'Cancelada',
                'realizada' => 'Realizada',
                'no_se_presento' => 'No se presento',
                'reprogramada' => 'Reprogramada',
                'prioridad_manual' => 'Prioridad manual',
            ],
        ])->render();

        $this->assertStringContainsString('Auditoria de cambios de citas', $html);
        $this->assertStringContainsString('No se presento', $html);
        $this->assertStringNotContainsString('@php', $html);
        $this->assertStringNotContainsString('{!!', $html);
        $this->assertStringNotContainsString('file_exists($cssPath)', $html);
    }

    public function test_renderiza_pdf_de_receta_sin_codigo_blade_visible(): void
    {
        $cita = new Cita([
            'id' => 42,
            'fecha' => '2026-04-09',
            'hora' => '08:30:00',
            'estado' => Cita::ESTADO_REALIZADA,
        ]);

        $cita->setRelation('paciente', new User([
            'id' => 5,
            'name' => 'Paciente Demo',
        ]));
        $cita->setRelation('doctor', new User([
            'name' => 'Dra. Sofia Paz',
        ]));

        $html = view('pdf.receta', [
            'cita' => $cita,
            'diagnostico' => 'Control general.',
            'medicamentos' => 'Paracetamol 500 mg.',
            'indicaciones' => 'Tomar despues de los alimentos.',
            'fechaPdf' => Carbon::parse('2026-04-09 09:00:00'),
            'logoBase64' => null,
            'pdfCss' => 'body{color:#000;}',
        ])->render();

        $this->assertStringContainsString('Receta', $html);
        $this->assertStringContainsString('Paciente Demo', $html);
        $this->assertStringNotContainsString('@php', $html);
        $this->assertStringNotContainsString('{!!', $html);
        $this->assertStringNotContainsString('file_exists($cssPath)', $html);
        $this->assertStringNotContainsString('Firma y sello del medico', $html);
        $this->assertStringNotContainsString('Firma del paciente o responsable', $html);
    }

    public function test_renderiza_pdf_de_pedido_laboratorio_con_checks_por_categoria(): void
    {
        $paciente = new User(['name' => 'Paciente Demo', 'dni' => '0999999999', 'telefono' => '0987654321']);
        $doctor = new User(['name' => 'Dra. Sofia Paz', 'dni' => '0101010101']);
        $cita = new Cita([
            'id' => 24,
            'fecha' => '2026-04-09',
            'hora' => '08:30:00',
            'motivo_consulta' => 'Control y chequeo general',
        ]);
        $cita->setRelation('paciente', $paciente);
        $cita->setRelation('doctor', $doctor);
        $cita->setRelation('especialidad', new Especialidad(['nombre' => 'Medicina General']));
        $cita->setRelation('notaSoap', null);

        $pedido = new PedidoLaboratorio([
            'id' => 9,
            'examenes' => ['biometria_hematica', 'glucosa'],
        ]);
        $pedido->setRelation('cita', $cita);
        $pedido->setRelation('paciente', $paciente);
        $pedido->setRelation('doctor', $doctor);

        $html = view('pdf.pedido-laboratorio', [
            'pedido' => $pedido,
            'clinica' => 'Clinica Demo',
            'slogan' => 'Atencion integral',
            'logoBase64' => null,
            'pdfCss' => 'body{color:#000;}',
            'csv' => 'CSV123',
            'verificationUrl' => 'https://example.test/verificar/CSV123',
            'qrDataUri' => null,
        ])->render();

        $this->assertStringContainsString('ORDEN DE LABORATORIO', $html);
        $this->assertStringContainsString('Biometria hematica completa', $html);
        $this->assertStringContainsString('No seleccionado en esta orden', $html);
        $this->assertStringContainsString('Control y chequeo general', $html);
        $this->assertStringNotContainsString('@php', $html);
    }
}
