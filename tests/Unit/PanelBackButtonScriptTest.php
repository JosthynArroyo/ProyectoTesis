<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PanelBackButtonScriptTest extends TestCase
{
    public function test_panel_back_button_script_does_not_reparent_button_into_content_cards(): void
    {
        $script = file_get_contents(__DIR__.'/../../resources/js/panel-back-button.js');

        $this->assertIsString($script);
        $this->assertStringNotContainsString('target.prepend(anchor)', $script);
        $this->assertStringNotContainsString('resolveTarget', $script);
        $this->assertStringNotContainsString('panel-card--with-back', $script);
        $this->assertStringNotContainsString('panel-action-bar--top-shell', $script);
    }
}
