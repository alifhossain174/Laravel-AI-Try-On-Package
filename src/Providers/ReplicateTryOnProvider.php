<?php

namespace FahimHossain\LaravelAiTryon\Providers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use FahimHossain\LaravelAiTryon\Contracts\AiTryOnProviderInterface;
use FahimHossain\LaravelAiTryon\Data\TryOnResult;
use FahimHossain\LaravelAiTryon\Providers\Concerns\HandlesProviderImages;

class ReplicateTryOnProvider implements AiTryOnProviderInterface
{
    use HandlesProviderImages;

    public function generate(string $userImagePath, string $productImagePath, array $options = []): TryOnResult
    {
        $config = config('ai-tryon.providers.replicate', []);
        $apiKey = $config['api_key'] ?? null;
        $model = $config['model'] ?? null;

        if (! $apiKey) {
            return TryOnResult::failure('Replicate API key is not configured.', 'replicate', $model);
        }

        if (! $model) {
            return TryOnResult::failure('Replicate model is not configured.', 'replicate', null);
        }

        try {
            $disk = $options['disk'] ?? null;
            $keys = $config['input_keys'] ?? [];
            $input = [
                $keys['prompt'] ?? 'prompt' => $this->configuredPrompt($options),
                $keys['user_image'] ?? 'user_image' => $this->dataUri($userImagePath, $disk),
                $keys['product_image'] ?? 'product_image' => $this->dataUri($productImagePath, $disk),
                $keys['product_type'] ?? 'product_type' => $options['product_type'] ?? 'other',
            ];

            $response = Http::timeout((int) ($config['timeout'] ?? 120))
                ->withToken($apiKey)
                ->withHeaders(['Prefer' => 'wait'])
                ->post(rtrim((string) ($config['base_url'] ?? 'https://api.replicate.com/v1'), '/').'/predictions', [
                    'version' => $model,
                    'input' => array_merge($input, (array) ($options['replicate_input'] ?? [])),
                ]);

            $json = $response->json();

            if (! $response->successful()) {
                return TryOnResult::failure(
                    Arr::get($json, 'detail', 'Replicate image generation request failed.'),
                    'replicate',
                    $model,
                    $json
                );
            }

            $output = Arr::get($json, 'output');
            $image = is_array($output) ? Arr::first($output) : $output;

            if (is_string($image) && str_starts_with($image, 'data:image/')) {
                [$mime, $base64] = explode(';base64,', substr($image, 5), 2);
                [$path, $url] = $this->saveBase64Image($base64, $mime, $disk);

                return TryOnResult::success($path, $url, 'replicate', $model, $json);
            }

            if (is_string($image) && str_starts_with($image, 'http')) {
                [$path, $url] = $this->saveRemoteImage($image, $disk, (int) ($config['timeout'] ?? 120));

                return TryOnResult::success($path, $url, 'replicate', $model, $json);
            }

            return TryOnResult::failure('Replicate did not return an image.', 'replicate', $model, $json);
        } catch (\Throwable $exception) {
            return TryOnResult::failure($exception->getMessage(), 'replicate', $model);
        }
    }
}
