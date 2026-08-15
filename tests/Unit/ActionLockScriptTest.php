<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ActionLockScriptTest extends TestCase
{
    public function test_reagendar_control_message_takes_precedence_over_generic_reagendar_cita_copy(): void
    {
        $script = file_get_contents(__DIR__.'/../../resources/js/action-lock.js');

        $this->assertIsString($script);
        $this->assertStringContainsString("Reagendando control...", $script);
        $this->assertStringContainsString("Reagendando cita...", $script);
        $this->assertStringContainsString("Actualizando contraseña...", $script);
        $this->assertStringContainsString('button[type="button"]', $script);

        $controlPos = strpos($script, "Reagendando control...");
        $citaPos = strpos($script, "Reagendando cita...");
        $passwordPos = strpos($script, "Actualizando contraseña...");
        $resetPos = strpos($script, "Restableciendo contraseña...");

        $this->assertNotFalse($controlPos);
        $this->assertNotFalse($citaPos);
        $this->assertNotFalse($passwordPos);
        $this->assertNotFalse($resetPos);
        $this->assertLessThan($citaPos, $controlPos, 'La rama de control debe evaluarse antes que la rama generica de cita.');
        $this->assertLessThan($resetPos, $passwordPos, 'La rama de cambio de contrasena debe evaluarse antes que el fallback generico de password.');
    }

    public function test_submitter_takes_precedence_and_form_text_is_not_concatenated(): void
    {
        $script = file_get_contents(__DIR__.'/../../resources/js/action-lock.js');

        $this->assertIsString($script);
        $this->assertStringContainsString('Emitiendo certificado médico...', $script);
        $this->assertStringContainsString('Generando pedido de laboratorio...', $script);
        $this->assertStringContainsString('readCopy(submitter)', $script);
        $this->assertStringContainsString('readCopy(form)', $script);

        $submitterReadPos = strpos($script, 'readCopy(submitter)');
        $formReadPos = strpos($script, 'readCopy(form)');
        $this->assertNotFalse($submitterReadPos);
        $this->assertNotFalse($formReadPos);
        $this->assertLessThan($formReadPos, $submitterReadPos, 'El atributo explicito del submitter debe tener mayor prioridad que el del form.');

        $resolveOperationCopyCode = substr($script, strpos($script, 'function resolveOperationCopy'));
        $this->assertStringNotContainsString('sanitizedFormText', $resolveOperationCopyCode, 'La resolucion contextual de operaciones no debe depender del texto completo del formulario.');
    }
}
