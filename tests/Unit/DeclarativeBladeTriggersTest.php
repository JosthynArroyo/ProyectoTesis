<?php

namespace Tests\Unit;

use Tests\TestCase;

class DeclarativeBladeTriggersTest extends TestCase
{
    /**
     * Criterio 1: Cero inline event handlers obsoletos en vistas productivas:
     * - onchange="this.form.submit()"
     * - toggleChatbotWidget()
     * - onclick con document.getElementById(...).submit() para logout
     */
    public function test_no_obsolete_inline_event_handlers_in_productive_views(): void
    {
        $viewsRoot = resource_path('views');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsRoot, \FilesystemIterator::SKIP_DOTS)
        );

        $autoSubmitViolations = [];
        $chatbotViolations = [];
        $logoutViolations = [];

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $rel = str_replace($viewsRoot . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $normalizedRel = str_replace('\\', '/', $rel);

            // Excluir vistas demo
            if (str_starts_with($normalizedRel, 'demo/')) {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            if (preg_match('/onchange\s*=\s*["\']this\.form\.submit\(\)["\']/', $content)) {
                $autoSubmitViolations[] = $normalizedRel;
            }

            if (preg_match('/toggleChatbotWidget/', $content)) {
                $chatbotViolations[] = $normalizedRel;
            }

            if (preg_match('/onclick\s*=\s*["\']event\.preventDefault\(\);\s*document\.getElementById\(/', $content)) {
                $logoutViolations[] = $normalizedRel;
            }
        }

        $this->assertEmpty(
            $autoSubmitViolations,
            'Vistas productivas con onchange inline auto-submit: ' . implode(', ', $autoSubmitViolations)
        );
        $this->assertEmpty(
            $chatbotViolations,
            'Vistas productivas con referencias a toggleChatbotWidget: ' . implode(', ', $chatbotViolations)
        );
        $this->assertEmpty(
            $logoutViolations,
            'Vistas productivas con onclick inline de logout: ' . implode(', ', $logoutViolations)
        );
    }

    /**
     * Criterio 2: Los 6 controles de filtros conservan markup, name y data-auto-submit.
     */
    public function test_auto_submit_filter_controls_have_declarative_contract(): void
    {
        $controls = [
            [
                'path'       => 'superadmin/solicitudes/personalizacion.blade.php',
                'name'       => 'status',
                'attributes' => ['data-auto-submit', 'required'],
            ],
            [
                'path'       => 'superadmin/admins/index.blade.php',
                'name'       => 'per_page',
                'attributes' => ['data-auto-submit'],
            ],
            [
                'path'       => 'paciente/historial.blade.php',
                'name'       => 'paciente',
                'attributes' => ['data-auto-submit', 'id="paciente"'],
            ],
            [
                'path'       => 'doctor/pacientes/index.blade.php',
                'name'       => 'sort',
                'attributes' => ['data-auto-submit', 'id="sort"'],
            ],
            [
                'path'       => 'admin/usuarios.blade.php',
                'name'       => 'role',
                'attributes' => ['data-auto-submit'],
            ],
            [
                'path'       => 'admin/usuarios.blade.php',
                'name'       => 'per_page',
                'attributes' => ['data-auto-submit'],
            ],
        ];

        foreach ($controls as $ctrl) {
            $fullPath = resource_path('views/' . $ctrl['path']);
            $this->assertFileExists($fullPath, "Vista no encontrada: {$ctrl['path']}");
            $content = file_get_contents($fullPath);

            $this->assertStringContainsString(
                'name="' . $ctrl['name'] . '"',
                $content,
                "{$ctrl['path']} debe conservar name=\"{$ctrl['name']}\"."
            );

            foreach ($ctrl['attributes'] as $attr) {
                $this->assertStringContainsString(
                    $attr,
                    $content,
                    "{$ctrl['path']} debe tener el atributo {$attr}."
                );
            }
        }
    }

    /**
     * Criterio 3: Módulo auto-submit.js usa selector estricto y loader condicional en app.js.
     */
    public function test_auto_submit_module_is_correct_and_has_no_redundant_entry(): void
    {
        $jsPath = base_path('resources/js/forms/auto-submit.js');
        $this->assertFileExists($jsPath, 'El módulo auto-submit.js debe existir.');
        $content = file_get_contents($jsPath);

        $this->assertStringContainsString('[data-auto-submit]', $content);
        $this->assertStringContainsString('form.submit()', $content);
        $this->assertStringNotContainsString("querySelector('form')", $content);

        $viteConfig = base_path('vite.config.js');
        $this->assertFileExists($viteConfig);
        $this->assertStringNotContainsString(
            "'resources/js/forms/auto-submit.js'",
            file_get_contents($viteConfig)
        );

        $appJs = base_path('resources/js/app.js');
        $this->assertFileExists($appJs);
        $appContent = file_get_contents($appJs);
        $this->assertStringContainsString("hasElement('[data-auto-submit]')", $appContent);
        $this->assertStringContainsString("import('./forms/auto-submit')", $appContent);
    }

    /**
     * Criterio 4: Footer y Servicios usan contratos declarativos de chatbot.
     */
    public function test_chatbot_views_use_declarative_contracts(): void
    {
        $footerPath = resource_path('views/partials/footer.blade.php');
        $this->assertFileExists($footerPath);
        $footerContent = file_get_contents($footerPath);
        $this->assertStringContainsString('data-chatbot-toggle', $footerContent);
        $this->assertStringNotContainsString('toggleChatbotWidget', $footerContent);
        $this->assertStringNotContainsString('onclick', $footerContent);

        $serviciosPath = resource_path('views/servicios.blade.php');
        $this->assertFileExists($serviciosPath);
        $serviciosContent = file_get_contents($serviciosPath);
        $this->assertStringContainsString('data-chatbot-open', $serviciosContent);
        $this->assertStringNotContainsString('querySelector', $serviciosContent);
        $this->assertStringNotContainsString('onclick', $serviciosContent);
    }

    /**
     * Criterio 5: widget.js maneja openPanel y togglePanel con semántica ARIA.
     */
    public function test_chatbot_widget_js_has_declarative_handlers_and_aria_semantics(): void
    {
        $widgetJsPath = resource_path('js/chatbot/widget.js');
        $this->assertFileExists($widgetJsPath);
        $content = file_get_contents($widgetJsPath);

        $this->assertStringContainsString('[data-chatbot-open]', $content);
        $this->assertStringContainsString('[data-chatbot-toggle]', $content);
        $this->assertStringContainsString('function openPanel()', $content);
        $this->assertStringContainsString('function togglePanel()', $content);
        $this->assertStringContainsString('toggleTrigger !== toggle', $content);
        $this->assertStringNotContainsString('window.toggleChatbotWidget', $content);

        $this->assertStringContainsString("panel.setAttribute('aria-hidden', 'false')", $content);
        $this->assertStringContainsString("toggle.setAttribute('aria-expanded', 'true')", $content);

        $viteConfig = file_get_contents(base_path('vite.config.js'));
        $this->assertStringContainsString('resources/js/chatbot/widget.js', $viteConfig);
    }

    /**
     * Criterio 6: Sidebars y headers usan data-logout-trigger y declaran sus formularios específicos.
     */
    public function test_sidebars_and_headers_use_declarative_logout_triggers(): void
    {
        $viewsToCheck = [
            'admin/partials/sidebar.blade.php',
            'superadmin/partials/sidebar.blade.php',
            'paciente/partials/sidebar.blade.php',
            'doctor/partials/sidebar.blade.php',
            'laboratorio/partials/sidebar.blade.php',
            'components/layout/dashboard-header.blade.php',
            'auth/must-change-password.blade.php',
        ];

        foreach ($viewsToCheck as $relPath) {
            $fullPath = resource_path('views/' . $relPath);
            $this->assertFileExists($fullPath, "View {$relPath} must exist.");
            $content = file_get_contents($fullPath);

            $this->assertStringContainsString(
                'data-logout-trigger',
                $content,
                "{$relPath} must have data-logout-trigger attribute."
            );
            $this->assertStringNotContainsString(
                'onclick="event.preventDefault(); document.getElementById(',
                $content,
                "{$relPath} should not contain inline onclick submit handlers."
            );
        }

        $specialForms = [
            'doctor/partials/sidebar.blade.php'              => 'id="doctor-logout-form"',
            'laboratorio/partials/sidebar.blade.php'         => 'id="laboratorio-logout-form"',
            'components/layout/dashboard-header.blade.php'   => 'id="logout-form-header"',
            'auth/must-change-password.blade.php'            => 'id="logout-form-cancel"',
        ];

        foreach ($specialForms as $relPath => $expectedId) {
            $fullPath = resource_path('views/' . $relPath);
            $this->assertFileExists($fullPath);
            $content = file_get_contents($fullPath);
            $this->assertStringContainsString(
                $expectedId,
                $content,
                "{$relPath} must declare the form {$expectedId}."
            );
        }
    }

    /**
     * Criterio 7: logout.js no usa selectores laxos ni heurísticas por URL.
     */
    public function test_logout_js_selector_is_strict_and_has_no_heuristic_fallback(): void
    {
        $jsPath = base_path('resources/js/auth/logout.js');
        $this->assertFileExists($jsPath);
        $content = file_get_contents($jsPath);

        $this->assertStringContainsString("closest('[data-logout-trigger]')", $content);
        $this->assertStringNotContainsString("closest('[data-logout-trigger], [data-logout-form]')", $content);
        $this->assertStringNotContainsString('action*="salir"', $content);
        $this->assertStringNotContainsString('form[action*="logout"]', $content);

        $viteConfig = file_get_contents(base_path('vite.config.js'));
        $this->assertStringNotContainsString("'resources/js/auth/logout.js'", $viteConfig);

        $appJs = base_path('resources/js/app.js');
        $this->assertFileExists($appJs);
        $appContent = file_get_contents($appJs);
        $this->assertStringContainsString("hasElement('[data-logout-trigger]')", $appContent);
        $this->assertStringNotContainsString("hasElement('[data-logout-trigger], [data-logout-form]')", $appContent);
    }
}
