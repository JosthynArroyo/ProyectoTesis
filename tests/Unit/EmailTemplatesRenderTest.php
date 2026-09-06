<?php

namespace Tests\Unit;

use App\Mail\CambioEstadoCitaMail;
use App\Mail\CertificadoMedicoMail;
use App\Mail\ContactoRecibido;
use App\Mail\CuentaCreadaDesdeChat;
use App\Mail\RecetaMedicaMail;
use App\Mail\ResultadoLaboratorioMail;
use App\Models\Cita;
use App\Models\CertificadoMedico;
use App\Models\Especialidad;
use App\Models\LaboratorioOrden;
use App\Models\User;
use App\Services\ClinicIdentityService;
use Tests\TestCase;

class EmailTemplatesRenderTest extends TestCase
{
    public function test_renderiza_correo_de_cita_con_diseno_unificado(): void
    {
        $html = (new CambioEstadoCitaMail($this->makeCita(), 'paciente', 'agendada', 'paciente'))->render();

        $this->assertStringContainsString('Resumen de la cita', $html);
        $this->assertStringContainsString(app(ClinicIdentityService::class)->name(), $html);
    }

    public function test_renderiza_correo_de_contacto_con_nuevo_patron(): void
    {
        $html = (new ContactoRecibido([
            'nombre' => 'Maria Lopez',
            'email' => 'maria@example.com',
            'telefono' => '0999999999',
            'asunto' => 'Consulta de horarios',
            'mensaje' => 'Necesito confirmar la disponibilidad para esta semana.',
        ]))->render();

        $this->assertStringContainsString('Nuevo mensaje recibido', $html);
        $this->assertStringContainsString('Consulta de horarios', $html);
    }

    public function test_renderiza_correo_de_cuenta_laboratorio_receta_y_certificado(): void
    {
        $user = new User([
            'name' => 'Carlos Ruiz',
            'email' => 'carlos@example.com',
        ]);

        $cita = $this->makeCita();
        $orden = new LaboratorioOrden([
            'tipo_examen' => 'Biometria hematica',
            'prioridad' => 'urgente',
            'resultado_resumen' => 'Los parametros evaluados se encuentran dentro del rango esperado.',
        ]);
        $orden->id = 24;
        $orden->setRelation('cita', $cita);

        $cuentaHtml = (new CuentaCreadaDesdeChat($user, '0912345678'))->render();
        $laboratorioHtml = (new ResultadoLaboratorioMail($orden))->render();
        $recetaHtml = (new RecetaMedicaMail($cita, 'recetas/24.pdf', '', 'receta_24.pdf'))->render();

        $certificado = new CertificadoMedico([
            'codigo' => 'CM-20260416-000010',
            'fecha_emision' => now(),
            'texto_constancia' => 'Constancia de reposo.',
        ]);
        $certificado->setRelation('paciente', $user);
        $certificado->setRelation('doctor', $cita->doctor);
        $certificado->setRelation('dependiente', null);
        $certificado->setRelation('cita', $cita);

        $certHtml = (new CertificadoMedicoMail($certificado, 'certificados/10.pdf', 'certificado_10.pdf'))->render();

        $this->assertStringContainsString('Credenciales de acceso', $cuentaHtml);
        $this->assertStringContainsString('Usuario (correo)', $cuentaHtml);
        $this->assertStringContainsString('Contraseña temporal', $cuentaHtml);
        $this->assertStringContainsString('Detalles del resultado', $laboratorioHtml);
        $this->assertStringContainsString('Datos de la atención', $recetaHtml);
        $this->assertStringContainsString('Tu certificado medico fue emitido', $certHtml);
    }

    private function makeCita(): Cita
    {
        $paciente = new User([
            'name' => 'Ana Perez',
            'email' => 'ana@example.com',
        ]);

        $doctor = new User([
            'name' => 'Dr. Luis Torres',
            'email' => 'doctor@example.com',
        ]);

        $especialidad = new Especialidad([
            'nombre' => 'Cardiologia',
        ]);

        $cita = new Cita([
            'fecha' => '2026-03-12',
            'hora' => '10:30:00',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
            'motivo_consulta' => 'Chequeo general',
            'prioridad_nivel' => Cita::PRIORIDAD_MEDIA,
            'prioridad_fuente' => Cita::FUENTE_PRIORIDAD_MANUAL,
            'prioridad_red_flag' => false,
            'prioridad_es_adulto_mayor' => false,
            'prioridad_es_embarazo' => false,
            'prioridad_es_discapacidad' => false,
            'prioridad_es_cronico' => false,
        ]);

        $cita->id = 42;
        $cita->setRelation('paciente', $paciente);
        $cita->setRelation('doctor', $doctor);
        $cita->setRelation('especialidad', $especialidad);

        return $cita;
    }
}
