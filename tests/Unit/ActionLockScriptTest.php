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

        $controlPos = strpos($script, "Reagendando control...");
        $citaPos = strpos($script, "Reagendando cita...");

        $this->assertNotFalse($controlPos);
        $this->assertNotFalse($citaPos);
        $this->assertLessThan($citaPos, $controlPos, 'La rama de control debe evaluarse antes que la rama genérica de cita.');
    }
}
