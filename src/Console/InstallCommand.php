<?php

namespace Vendor\LaravelAiTryon\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'ai-tryon:install {--force : Overwrite already published files}';

    protected $description = 'Publish AI Try-On config, assets, views, and migrations.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $this->components->info('Publishing AI Try-On configuration...');
        $this->call('vendor:publish', ['--tag' => 'ai-tryon-config', '--force' => $force]);

        $this->components->info('Publishing AI Try-On assets...');
        $this->call('vendor:publish', ['--tag' => 'ai-tryon-assets', '--force' => $force]);

        $this->components->info('Publishing AI Try-On views...');
        $this->call('vendor:publish', ['--tag' => 'ai-tryon-views', '--force' => $force]);

        $this->components->info('Publishing AI Try-On migrations...');
        $this->call('vendor:publish', ['--tag' => 'ai-tryon-migrations', '--force' => $force]);

        $this->newLine();
        $this->components->info('AI Try-On installed. Run php artisan migrate and configure your AI provider API key.');

        return self::SUCCESS;
    }
}
