<?php

namespace FahimHossain\LaravelAiTryon\Providers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use FahimHossain\LaravelAiTryon\Contracts\AiTryOnProviderInterface;
use FahimHossain\LaravelAiTryon\Data\TryOnResult;
use FahimHossain\LaravelAiTryon\Providers\Concerns\HandlesProviderImages;

class OpenAiTryOnProvider implements AiTryOnProviderInterface
{
    use HandlesProviderImages;

    public function generate(string $userImagePath, string $productImagePath, array $options = []): TryOnResult
    {
        $config = config('ai-tryon.providers.openai', []);
        $apiKey = $config['api_key'] ?? null;
        $model = $config['model'] ?? null;

        if (! $apiKey) {
            return TryOnResult::failure('OpenAI API key is not configured.', 'openai', $model);
        }

        if (! $model) {
            return TryOnResult::failure('OpenAI image model is not configured.', 'openai', null);
        }

        try {
            $disk = $options['disk'] ?? null;
            $userBytes = $this->readImageBytes($userImagePath, $disk);
            $productBytes = $this->readImageBytes($productImagePath, $disk);

            $response = Http::timeout((int) ($config['timeout'] ?? 90))
                ->withToken($apiKey)
                ->attach('image[]', $userBytes, 'user-image.png', ['Content-Type' => $this->detectMimeType($userBytes, $userImagePath)])
                ->attach('image[]', $productBytes, 'product-image.png', ['Content-Type' => $this->detectMimeType($productBytes, $productImagePath)])
                ->post(rtrim((string) ($config['base_url'] ?? 'https://api.openai.com/v1'), '/').'/images/edits', [
                    'model' => $model,
                    'prompt' => $this->configuredPrompt($options),
                    'n' => 1,
                ]);

            $json = $response->json();

            if (! $response->successful()) {
                return TryOnResult::failure(
                    Arr::get($json, 'error.message', 'OpenAI image generation request failed.'),
                    'openai',
                    $model,
                    $json
                );
            }

            $image = Arr::first((array) Arr::get($json, 'data', []));
            $base64 = is_array($image) ? ($image['b64_json'] ?? null) : null;

            if ($base64) {
                [$path, $url] = $this->saveBase64Image($base64, 'image/png', $disk);

                return TryOnResult::success($path, $url, 'openai', $model, $json);
            }

            $remoteUrl = is_array($image) ? ($image['url'] ?? null) : null;

            if ($remoteUrl) {
                [$path, $url] = $this->saveRemoteImage($remoteUrl, $disk, (int) ($config['timeout'] ?? 90));

                return TryOnResult::success($path, $url, 'openai', $model, $json);
            }

            return TryOnResult::failure('OpenAI did not return an image.', 'openai', $model, $json);
        } catch (\Throwable $exception) {
            return TryOnResult::failure($exception->getMessage(), 'openai', $model);
        }
    }
}
