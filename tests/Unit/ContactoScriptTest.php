<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ContactoScriptTest extends TestCase
{
    public function test_contacto_script_disables_submit_on_real_submit_and_restores_on_pageshow(): void
    {
        $script = file_get_contents(__DIR__.'/../../resources/js/contacto.js');

        $this->assertIsString($script);
        $this->assertStringContainsString("contactoSubmitting", $script);
        $this->assertStringContainsString("checkValidity", $script);
        $this->assertStringContainsString("reportValidity", $script);
        $this->assertStringContainsString("pageshow", $script);
        $this->assertStringContainsString("data-contacto-submit-label", $script);
        $this->assertStringContainsString("Enviando...", $script);
        $this->assertStringContainsString("disabled = locked", $script);
    }
}
