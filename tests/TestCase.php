<?php

namespace Vendor\LaravelAiTryon\Tests;

use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase as Orchestra;
use Vendor\LaravelAiTryon\AiTryOnServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AiTryOnServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('filesystems.disks.ai-tryon-test', [
            'driver' => 'local',
            'root' => sys_get_temp_dir().'/ai-tryon-test',
            'url' => 'http://localhost/storage',
        ]);

        $app['config']->set('ai-tryon.storage_disk', 'ai-tryon-test');
        $app['config']->set('ai-tryon.queue.enabled', false);
        $app['config']->set('ai-tryon.provider', 'gemini');
        $app['config']->set('ai-tryon.providers.gemini.api_key', 'test-key');
        $app['config']->set('ai-tryon.providers.gemini.model', 'test-image-model');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('ai-tryon-test');
    }
}
