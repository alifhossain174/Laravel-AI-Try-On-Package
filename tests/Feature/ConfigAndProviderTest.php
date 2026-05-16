<?php

namespace Vendor\LaravelAiTryon\Tests\Feature;

use Vendor\LaravelAiTryon\Contracts\AiTryOnProviderInterface;
use Vendor\LaravelAiTryon\Providers\GeminiTryOnProvider;
use Vendor\LaravelAiTryon\Services\TryOnService;
use Vendor\LaravelAiTryon\Tests\TestCase;

class ConfigAndProviderTest extends TestCase
{
    public function test_config_loads(): void
    {
        $this->assertSame('gemini', config('ai-tryon.provider'));
        $this->assertSame('test-image-model', config('ai-tryon.providers.gemini.model'));
    }

    public function test_service_provider_registers_provider_and_service(): void
    {
        $this->assertInstanceOf(GeminiTryOnProvider::class, $this->app->make(AiTryOnProviderInterface::class));
        $this->assertInstanceOf(TryOnService::class, $this->app->make(TryOnService::class));
    }
}
