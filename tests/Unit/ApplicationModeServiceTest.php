<?php

namespace Tests\Unit;

use App\Services\ApplicationModeService;
use Tests\TestCase;

class ApplicationModeServiceTest extends TestCase
{
    private ApplicationModeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ApplicationModeService;
    }

    public function test_it_resolves_production_mode_correctly(): void
    {
        config(['app.mode' => 'production']);

        $this->assertSame('production', $this->service->getMode());
        $this->assertTrue($this->service->isProduction());
        $this->assertFalse($this->service->isDemo());
        $this->assertTrue($this->service->shouldPersist());
        $this->assertTrue($this->service->allowExternalEffects());
    }

    public function test_it_resolves_demo_mode_correctly(): void
    {
        config(['app.mode' => 'demo']);

        $this->assertSame('demo', $this->service->getMode());
        $this->assertTrue($this->service->isDemo());
        $this->assertFalse($this->service->isProduction());
        $this->assertFalse($this->service->shouldPersist());
        $this->assertFalse($this->service->allowExternalEffects());
    }

    public function test_default_fallback_is_production_when_config_is_unset_or_null(): void
    {
        config(['app.mode' => null]);

        $this->assertSame('production', $this->service->getMode());
        $this->assertTrue($this->service->isProduction());
        $this->assertFalse($this->service->isDemo());
        $this->assertTrue($this->service->shouldPersist());
        $this->assertTrue($this->service->allowExternalEffects());
    }

    public function test_invalid_values_safely_fallback_to_production(): void
    {
        $invalidValues = ['staging', 'development', 'test', 'sandbox', 'local', '', 'invalid_mode'];

        foreach ($invalidValues as $invalid) {
            config(['app.mode' => $invalid]);

            $this->assertSame(
                'production',
                $this->service->getMode(),
                "Failed asserting that invalid mode '{$invalid}' falls back to production"
            );
            $this->assertTrue($this->service->isProduction());
            $this->assertFalse($this->service->isDemo());
            $this->assertTrue($this->service->shouldPersist());
            $this->assertTrue($this->service->allowExternalEffects());
        }
    }

    public function test_it_handles_whitespace_and_mixed_case(): void
    {
        config(['app.mode' => ' DEMO ']);
        $this->assertSame('demo', $this->service->getMode());
        $this->assertTrue($this->service->isDemo());

        config(['app.mode' => 'Production']);
        $this->assertSame('production', $this->service->getMode());
        $this->assertTrue($this->service->isProduction());
    }

    public function test_it_can_be_resolved_from_service_container(): void
    {
        $resolved = app(ApplicationModeService::class);

        $this->assertInstanceOf(ApplicationModeService::class, $resolved);
    }
}
