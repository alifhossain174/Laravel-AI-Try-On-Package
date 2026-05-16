<?php

namespace Vendor\LaravelAiTryon;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Vendor\LaravelAiTryon\Console\InstallCommand;
use Vendor\LaravelAiTryon\Contracts\AiTryOnProviderInterface;
use Vendor\LaravelAiTryon\Providers\GeminiTryOnProvider;
use Vendor\LaravelAiTryon\Providers\OpenAiTryOnProvider;
use Vendor\LaravelAiTryon\Providers\ReplicateTryOnProvider;
use Vendor\LaravelAiTryon\Services\TryOnService;
use Vendor\LaravelAiTryon\View\Components\TryOnButton;

class AiTryOnServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-tryon.php', 'ai-tryon');

        $this->app->singleton(AiTryOnProviderInterface::class, function (Container $app): AiTryOnProviderInterface {
            return match (config('ai-tryon.provider', 'gemini')) {
                'openai' => $app->make(OpenAiTryOnProvider::class),
                'replicate' => $app->make(ReplicateTryOnProvider::class),
                default => $app->make(GeminiTryOnProvider::class),
            };
        });

        $this->app->singleton(TryOnService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ai-tryon');

        Blade::component(TryOnButton::class, 'ai-tryon::button');

        $this->configureRateLimiting();

        if (config('ai-tryon.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ai-tryon.php' => config_path('ai-tryon.php'),
            ], 'ai-tryon-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/ai-tryon'),
            ], 'ai-tryon-views');

            $this->publishes([
                __DIR__.'/../resources/css/ai-tryon.css' => public_path('vendor/ai-tryon/ai-tryon.css'),
                __DIR__.'/../resources/js/ai-tryon.js' => public_path('vendor/ai-tryon/ai-tryon.js'),
            ], 'ai-tryon-assets');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'ai-tryon-migrations');

            $this->commands([
                InstallCommand::class,
            ]);
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('ai-tryon', function (Request $request): Limit {
            $key = optional($request->user())->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute((int) config('ai-tryon.limits.rate_per_minute', 6))->by((string) $key);
        });
    }
}
