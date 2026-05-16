<?php

namespace FahimHossain\LaravelAiTryon\Services;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use FahimHossain\LaravelAiTryon\Contracts\AiTryOnProviderInterface;
use FahimHossain\LaravelAiTryon\Data\TryOnResult;
use FahimHossain\LaravelAiTryon\Models\AiTryOnGeneration;
use FahimHossain\LaravelAiTryon\Models\AiTryOnUsage;

class TryOnService
{
    public function __construct(private readonly AiTryOnProviderInterface $provider)
    {
    }

    public function checkLimits(?Authenticatable $user, ?string $ipAddress): array
    {
        $userId = $user?->getAuthIdentifier();
        $limits = config('ai-tryon.limits', []);
        $premiumUrl = config('ai-tryon.billing.premium_url');

        if ($userId) {
            $freeLimit = (int) ($limits['free_generations_per_user'] ?? 5);
            $dailyLimit = (int) ($limits['daily_generations_per_user'] ?? 20);
            $total = AiTryOnUsage::query()->where('user_id', $userId)->count();

            if ($freeLimit >= 0 && $total >= $freeLimit) {
                return $this->limitResponse(false, 'free_generations_per_user', 'You have reached your free virtual try-on limit.', $premiumUrl);
            }

            $daily = AiTryOnUsage::query()
                ->where('user_id', $userId)
                ->whereDate('generated_at', now()->toDateString())
                ->count();

            if ($dailyLimit >= 0 && $daily >= $dailyLimit) {
                return $this->limitResponse(false, 'daily_generations_per_user', 'You have reached your daily virtual try-on limit.', $premiumUrl);
            }

            return $this->limitResponse(true);
        }

        $freeIpLimit = (int) ($limits['free_generations_per_ip'] ?? 3);
        $ipTotal = AiTryOnUsage::query()
            ->whereNull('user_id')
            ->where('ip_address', $ipAddress)
            ->count();

        if ($freeIpLimit >= 0 && $ipTotal >= $freeIpLimit) {
            return $this->limitResponse(false, 'free_generations_per_ip', 'You have reached the guest virtual try-on limit.', $premiumUrl);
        }

        return $this->limitResponse(true);
    }

    public function createGeneration(
        UploadedFile $userImage,
        string|UploadedFile $productImage,
        string|int|null $productId,
        string $productType,
        ?Authenticatable $user,
        ?string $ipAddress
    ): AiTryOnGeneration {
        $disk = $this->disk();
        $userImagePath = $userImage->store(trim((string) config('ai-tryon.uploads.temporary_path', 'ai-tryon/uploads'), '/'), $disk);
        $productImagePath = $this->materializeProductImage($productImage);
        $provider = (string) config('ai-tryon.provider', 'gemini');
        $model = (string) config("ai-tryon.providers.{$provider}.model");

        $generation = AiTryOnGeneration::query()->create([
            'user_id' => $user?->getAuthIdentifier(),
            'ip_address' => $ipAddress,
            'product_id' => $productId !== null ? (string) $productId : null,
            'product_type' => $productType,
            'original_user_image_path' => $userImagePath,
            'product_image_path' => $productImagePath,
            'status' => AiTryOnGeneration::STATUS_PENDING,
            'provider' => $provider,
            'model' => $model,
        ]);

        $this->recordUsage($generation);

        return $generation;
    }

    public function processGeneration(int|string $generationId): TryOnResult
    {
        /** @var AiTryOnGeneration $generation */
        $generation = AiTryOnGeneration::query()->findOrFail($generationId);

        $generation->forceFill([
            'status' => AiTryOnGeneration::STATUS_PROCESSING,
            'error_message' => null,
        ])->save();

        $result = $this->provider->generate(
            (string) $generation->original_user_image_path,
            (string) $generation->product_image_path,
            [
                'disk' => $this->disk(),
                'product_type' => $generation->product_type ?: 'other',
                'prompt' => config('ai-tryon.prompt'),
            ]
        );

        if ($result->success) {
            $generation->forceFill([
                'status' => AiTryOnGeneration::STATUS_COMPLETED,
                'generated_image_path' => $result->imagePath,
                'provider' => $result->provider ?: $generation->provider,
                'model' => $result->model ?: $generation->model,
                'error_message' => null,
            ])->save();
        } else {
            $generation->forceFill([
                'status' => AiTryOnGeneration::STATUS_FAILED,
                'provider' => $result->provider ?: $generation->provider,
                'model' => $result->model ?: $generation->model,
                'error_message' => $result->errorMessage,
            ])->save();
        }

        $this->cleanupUserUpload($generation);

        return $result;
    }

    public function generationPayload(AiTryOnGeneration $generation): array
    {
        return [
            'id' => $generation->uuid,
            'status' => $generation->status,
            'product_id' => $generation->product_id,
            'product_type' => $generation->product_type,
            'provider' => $generation->provider,
            'model' => $generation->model,
            'generated_image_path' => $generation->generated_image_path,
            'generated_image_url' => $generation->generated_image_path
                ? $this->url($generation->generated_image_path)
                : null,
            'error_message' => $generation->error_message,
        ];
    }

    public function url(string $path): ?string
    {
        try {
            return Storage::disk($this->disk())->url($path);
        } catch (\Throwable) {
            return null;
        }
    }

    public function materializeProductImage(string|UploadedFile $source): string
    {
        $disk = $this->disk();

        if ($source instanceof UploadedFile) {
            return $source->store(trim((string) config('ai-tryon.uploads.product_path', 'ai-tryon/products'), '/'), $disk);
        }

        if (Storage::disk($disk)->exists($source)) {
            return $source;
        }

        if (is_file($source)) {
            $path = trim((string) config('ai-tryon.uploads.product_path', 'ai-tryon/products'), '/')
                .'/'.Str::uuid().'.'.(pathinfo($source, PATHINFO_EXTENSION) ?: 'png');
            Storage::disk($disk)->put($path, file_get_contents($source));

            return $path;
        }

        if (Str::startsWith($source, ['http://', 'https://'])) {
            return $this->downloadProductImage($source);
        }

        throw ValidationException::withMessages([
            'product_image' => 'The product image must be an uploaded image, storage path, local path, or absolute URL.',
        ]);
    }

    private function downloadProductImage(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $allowedHosts = array_filter((array) config('ai-tryon.uploads.allowed_product_image_hosts', []));

        if ($allowedHosts !== [] && (! $host || ! in_array($host, $allowedHosts, true))) {
            throw ValidationException::withMessages([
                'product_image' => 'The product image host is not allowed.',
            ]);
        }

        $response = Http::timeout(20)->get($url);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'product_image' => 'The product image could not be downloaded.',
            ]);
        }

        $body = $response->body();
        $maxBytes = ((int) config('ai-tryon.uploads.max_file_size_kb', 5120)) * 1024;

        if (strlen($body) > $maxBytes) {
            throw ValidationException::withMessages([
                'product_image' => 'The product image may not be larger than the configured max size.',
            ]);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body) ?: $response->header('Content-Type');

        if (! str_starts_with((string) $mime, 'image/')) {
            throw ValidationException::withMessages([
                'product_image' => 'The product image must be a valid image.',
            ]);
        }

        $extension = match (strtolower((string) $mime)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        $path = trim((string) config('ai-tryon.uploads.product_path', 'ai-tryon/products'), '/')
            .'/'.Str::uuid().'.'.$extension;

        Storage::disk($this->disk())->put($path, $body);

        return $path;
    }

    private function recordUsage(AiTryOnGeneration $generation): void
    {
        AiTryOnUsage::query()->create([
            'user_id' => $generation->user_id,
            'ip_address' => $generation->ip_address,
            'provider' => $generation->provider,
            'model' => $generation->model,
            'generated_at' => now(),
        ]);
    }

    private function cleanupUserUpload(AiTryOnGeneration $generation): void
    {
        if (! config('ai-tryon.privacy.auto_delete_uploads', true) || ! $generation->original_user_image_path) {
            return;
        }

        Storage::disk($this->disk())->delete($generation->original_user_image_path);

        if (! config('ai-tryon.privacy.store_user_uploads', false)) {
            $generation->forceFill(['original_user_image_path' => null])->save();
        }
    }

    private function disk(): string
    {
        return (string) config('ai-tryon.storage_disk', 'public');
    }

    private function limitResponse(
        bool $allowed,
        ?string $limit = null,
        ?string $message = null,
        ?string $premiumUrl = null
    ): array {
        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'message' => $message,
            'premium_url' => $premiumUrl,
            'billing_enabled' => (bool) config('ai-tryon.billing.enabled', false),
        ];
    }
}
