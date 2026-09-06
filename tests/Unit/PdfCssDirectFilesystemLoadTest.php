<?php

namespace Tests\Unit;

use App\Models\Cita;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PdfCssDirectFilesystemLoadTest extends TestCase
{
    /**
     * Confirma que los archivos físicos de CSS para PDF existen y son legibles desde PHP.
     */
    public function test_pdf_css_files_exist_and_are_readable_from_resources(): void
    {
        $files = [
            'admin/cambios-citas-pdf.css',
            'admin/usuarios-pdf.css',
            'doctor/receta-pdf.css',
        ];

        foreach ($files as $relative) {
            $path = resource_path('css/'.$relative);
            $this->assertFileExists($path, "El archivo [{$relative}] debe existir en resources/css.");
            $content = file_get_contents($path);
            $this->assertNotEmpty($content, "El contenido de [{$relative}] no debe estar vacío.");
        }
    }

    /**
     * Confirma que el PDF de auditoría de cambios de citas renderiza con su CSS real de resources.
     */
    public function test_cambios_citas_pdf_renders_with_real_css_content(): void
    {
        $cssContent = (string) file_get_contents(resource_path('css/admin/cambios-citas-pdf.css'));

        $cita = new Cita([
            'id' => 10,
            'fecha' => '2026-05-01',
            'hora' => '10:00:00',
            'estado' => Cita::ESTADO_CONFIRMADA,
            'activo' => true,
        ]);
        $cita->setRelation('paciente', new User(['name' => 'Carlos Lopez']));
        $cita->setRelation('doctor', new User(['name' => 'Dra. Maria']));

        $evento = (object) [
            'created_at' => Carbon::parse('2026-05-01 09:00:00'),
            'tipo' => 'confirmada',
            'cita_id' => 10,
            'cita' => $cita,
            'de_estado' => 'pendiente',
            'a_estado' => 'confirmada',
            'de_fecha' => null,
            'a_fecha' => null,
            'de_hora' => null,
            'a_hora' => null,
            'valor_anterior' => null,
            'valor_nuevo' => null,
            'comentario' => 'Confirmación telefónica.',
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
            'pdfCss' => $cssContent,
            'eventLabels' => [
                'confirmada' => 'Confirmada',
            ],
        ])->render();

        $this->assertStringContainsString('Auditoria de cambios de citas', $html);
        $this->assertStringContainsString('Carlos Lopez', $html);
        $this->assertStringContainsString($cssContent, $html);
    }

    /**
     * Confirma que el PDF de reporte de usuarios renderiza con su CSS real de resources.
     */
    public function test_usuarios_pdf_renders_with_real_css_content(): void
    {
        $cssContent = (string) file_get_contents(resource_path('css/admin/usuarios-pdf.css'));

        $user = new User([
            'id' => 20,
            'name' => 'Usuario Test',
            'email' => 'test@example.com',
            'telefono' => '0990001122',
            'dni' => '0900001122',
            'status' => User::STATUS_ACTIVE,
            'created_at' => Carbon::parse('2026-05-01 10:00:00'),
        ]);
        $user->setRelation('roles', Collection::make([(object) ['name' => 'paciente']]));
        $user->setRelation('especialidades', Collection::make([]));

        $html = view('admin.users.usuarios-pdf', [
            'users' => Collection::make([$user]),
            'role' => 'paciente',
            'pdfCss' => $cssContent,
            'statusLabels' => [
                User::STATUS_ACTIVE => 'Activo',
            ],
        ])->render();

        $this->assertStringContainsString('Reporte de usuarios', $html);
        $this->assertStringContainsString('Usuario Test', $html);
        $this->assertStringContainsString($cssContent, $html);
    }

    /**
     * Confirma que el PDF de receta médica renderiza con su CSS real de resources.
     */
    public function test_receta_pdf_renders_with_real_css_content(): void
    {
        $baseCss = (string) file_get_contents(resource_path('css/pdf/base.css'));
        $specificCss = (string) file_get_contents(resource_path('css/doctor/receta-pdf.css'));
        $combinedCss = $baseCss."\n".$specificCss;

        $cita = new Cita([
            'id' => 30,
            'fecha' => '2026-05-01',
            'hora' => '11:00:00',
            'estado' => Cita::ESTADO_REALIZADA,
        ]);
        $cita->setRelation('paciente', new User(['id' => 31, 'name' => 'Paciente Receta']));
        $cita->setRelation('doctor', new User(['name' => 'Dr. Gomez']));

        $html = view('pdf.receta', [
            'cita' => $cita,
            'diagnostico' => 'Gripe común.',
            'medicamentos' => 'Ibuprofeno 400mg.',
            'indicaciones' => 'Tomar cada 8 horas.',
            'fechaPdf' => Carbon::parse('2026-05-01 11:30:00'),
            'logoBase64' => null,
            'pdfCss' => $combinedCss,
        ])->render();

        $this->assertStringContainsString('Receta medica', $html);
        $this->assertStringContainsString('Paciente Receta', $html);
        $this->assertStringContainsString('Ibuprofeno 400mg.', $html);
        $this->assertStringContainsString($specificCss, $html);
    }

    /**
     * Confirma que las tres entradas fueron retiradas de vite.config.js y no aparecen en el manifest.
     */
    public function test_pdf_css_entries_are_not_registered_in_vite_nor_manifest(): void
    {
        $viteConfig = (string) file_get_contents(base_path('vite.config.js'));
        $this->assertStringNotContainsString('resources/css/admin/cambios-citas-pdf.css', $viteConfig);
        $this->assertStringNotContainsString('resources/css/admin/usuarios-pdf.css', $viteConfig);
        $this->assertStringNotContainsString('resources/css/doctor/receta-pdf.css', $viteConfig);

        $manifestPath = public_path('build/manifest.json');
        if (file_exists($manifestPath)) {
            $manifest = (string) file_get_contents($manifestPath);
            $this->assertStringNotContainsString('cambios-citas-pdf.css', $manifest);
            $this->assertStringNotContainsString('usuarios-pdf.css', $manifest);
            $this->assertStringNotContainsString('receta-pdf.css', $manifest);
        }
    }
}
