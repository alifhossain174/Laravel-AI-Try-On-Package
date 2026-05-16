<?php

namespace FahimHossain\LaravelAiTryon\Tests\Fakes;

use Illuminate\Support\Facades\Storage;
use FahimHossain\LaravelAiTryon\Contracts\AiTryOnProviderInterface;
use FahimHossain\LaravelAiTryon\Data\TryOnResult;

class FakeTryOnProvider implements AiTryOnProviderInterface
{
    public function __construct(private readonly bool $succeeds = true)
    {
    }

    public function generate(string $userImagePath, string $productImagePath, array $options = []): TryOnResult
    {
        if (! $this->succeeds) {
            return TryOnResult::failure('Fake provider failed.', 'fake', 'fake-model');
        }

        $disk = $options['disk'] ?? config('ai-tryon.storage_disk');
        $path = 'ai-tryon/previews/fake-preview.png';

        Storage::disk($disk)->put($path, 'fake image contents');

        return TryOnResult::success(
            $path,
            Storage::disk($disk)->url($path),
            'fake',
            'fake-model',
            ['fake' => true]
        );
    }
}
