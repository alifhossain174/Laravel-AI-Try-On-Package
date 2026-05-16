<?php

namespace FahimHossain\LaravelAiTryon\Providers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use FahimHossain\LaravelAiTryon\Contracts\AiTryOnProviderInterface;
use FahimHossain\LaravelAiTryon\Data\TryOnResult;
use FahimHossain\LaravelAiTryon\Providers\Concerns\HandlesProviderImages;

class GeminiTryOnProvider implements AiTryOnProviderInterface
{
    use HandlesProviderImages;

    public function generate(string $userImagePath, string $productImagePath, array $options = []): TryOnResult
    {
        $config = config('ai-tryon.providers.gemini', []);
        $apiKey = $config['api_key'] ?? null;
        $model = $config['model'] ?? null;

        if (! $apiKey) {
            return TryOnResult::failure('Gemini API key is not configured.', 'gemini', $model);
        }

        if (! $model) {
            return TryOnResult::failure('Gemini image model is not configured.', 'gemini', null);
        }

        try {
            $payload = [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [
                        ['text' => $this->configuredPrompt($options)],
                        $this->imagePart($userImagePath, $options['disk'] ?? null),
                        $this->imagePart($productImagePath, $options['disk'] ?? null),
                    ],
                ]],
                'generationConfig' => [
                    'responseModalities' => ['TEXT', 'IMAGE'],
                ],
            ];

            if (! empty($options['image_config']) && is_array($options['image_config'])) {
                $payload['generationConfig']['imageConfig'] = $options['image_config'];
            }

            $response = Http::timeout((int) ($config['timeout'] ?? 90))
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->endpoint((string) ($config['base_url'] ?? ''), (string) $model), $payload);

            $json = $response->json();

            if (! $response->successful()) {
                return TryOnResult::failure(
                    Arr::get($json, 'error.message', 'Gemini image generation request failed.'),
                    'gemini',
                    $model,
                    $json
                );
            }

            $imagePart = $this->firstInlineImage($json);

            if (! $imagePart) {
                return TryOnResult::failure('Gemini did not return an image.', 'gemini', $model, $json);
            }

            [$path, $url] = $this->saveBase64Image(
                $imagePart['data'],
                $imagePart['mime_type'] ?? $imagePart['mimeType'] ?? 'image/png',
                $options['disk'] ?? null
            );

            return TryOnResult::success($path, $url, 'gemini', $model, $json);
        } catch (\Throwable $exception) {
            return TryOnResult::failure($exception->getMessage(), 'gemini', $model);
        }
    }

    private function endpoint(string $baseUrl, string $model): string
    {
        return rtrim($baseUrl ?: 'https://generativelanguage.googleapis.com/v1beta', '/')
            .'/models/'.rawurlencode($model).':generateContent';
    }

    private function firstInlineImage(array $payload): ?array
    {
        foreach ((array) Arr::get($payload, 'candidates', []) as $candidate) {
            foreach ((array) Arr::get($candidate, 'content.parts', []) as $part) {
                $inlineData = $part['inlineData'] ?? $part['inline_data'] ?? null;

                if (is_array($inlineData) && ! empty($inlineData['data'])) {
                    return $inlineData;
                }
            }
        }

        return null;
    }
}
