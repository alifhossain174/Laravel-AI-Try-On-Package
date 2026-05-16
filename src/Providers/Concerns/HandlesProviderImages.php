<?php

namespace FahimHossain\LaravelAiTryon\Providers\Concerns;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandlesProviderImages
{
    protected function readImageBytes(string $path, ?string $disk = null): string
    {
        if (is_file($path)) {
            $contents = file_get_contents($path);

            if ($contents !== false) {
                return $contents;
            }
        }

        $disk = $disk ?: (string) config('ai-tryon.storage_disk', 'public');

        return Storage::disk($disk)->get($path);
    }

    protected function imagePart(string $path, ?string $disk = null): array
    {
        $bytes = $this->readImageBytes($path, $disk);

        return [
            'inline_data' => [
                'mime_type' => $this->detectMimeType($bytes, $path),
                'data' => base64_encode($bytes),
            ],
        ];
    }

    protected function dataUri(string $path, ?string $disk = null): string
    {
        $bytes = $this->readImageBytes($path, $disk);

        return sprintf('data:%s;base64,%s', $this->detectMimeType($bytes, $path), base64_encode($bytes));
    }

    protected function saveBase64Image(string $base64, ?string $mimeType = null, ?string $disk = null): array
    {
        $disk = $disk ?: (string) config('ai-tryon.storage_disk', 'public');
        $bytes = base64_decode($base64, true);

        if ($bytes === false) {
            throw new \RuntimeException('The AI provider returned invalid base64 image data.');
        }

        $mimeType = $mimeType ?: $this->detectMimeType($bytes);
        $path = trim((string) config('ai-tryon.uploads.output_path', 'ai-tryon/previews'), '/')
            .'/'.Str::uuid().'.'.$this->extensionForMime($mimeType);

        Storage::disk($disk)->put($path, $bytes);

        return [$path, $this->publicUrl($path, $disk)];
    }

    protected function saveRemoteImage(string $url, ?string $disk = null, int $timeout = 90): array
    {
        $response = Http::timeout($timeout)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Unable to download generated image from provider.');
        }

        return $this->saveBase64Image(
            base64_encode($response->body()),
            $response->header('Content-Type') ?: null,
            $disk
        );
    }

    protected function detectMimeType(string $bytes, ?string $path = null): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($bytes) ?: null;

        if ($mime && str_starts_with($mime, 'image/')) {
            return $mime;
        }

        $extension = $path ? strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION)) : null;

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    protected function extensionForMime(string $mimeType): string
    {
        return match (strtolower(trim(explode(';', $mimeType)[0]))) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
    }

    protected function publicUrl(string $path, string $disk): ?string
    {
        try {
            return Storage::disk($disk)->url($path);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function configuredPrompt(array $options = []): string
    {
        $prompt = (string) ($options['prompt'] ?? config('ai-tryon.prompt'));
        $productType = (string) ($options['product_type'] ?? 'other');

        return trim($prompt."\n\nProduct type: ".$productType.'.');
    }
}
