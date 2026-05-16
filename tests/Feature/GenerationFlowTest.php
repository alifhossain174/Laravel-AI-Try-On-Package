<?php

namespace FahimHossain\LaravelAiTryon\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use FahimHossain\LaravelAiTryon\Contracts\AiTryOnProviderInterface;
use FahimHossain\LaravelAiTryon\Models\AiTryOnGeneration;
use FahimHossain\LaravelAiTryon\Models\AiTryOnUsage;
use FahimHossain\LaravelAiTryon\Tests\Fakes\FakeTryOnProvider;
use FahimHossain\LaravelAiTryon\Tests\TestCase;

class GenerationFlowTest extends TestCase
{
    public function test_limit_exceeded_response_includes_premium_cta(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        config()->set('ai-tryon.limits.free_generations_per_ip', 0);
        config()->set('ai-tryon.billing.enabled', true);
        config()->set('ai-tryon.billing.premium_url', 'https://example.test/upgrade');

        $response = $this->postJson(route('ai-tryon.generate'), [
            'user_image' => UploadedFile::fake()->image('person.jpg'),
            'product_image_file' => UploadedFile::fake()->image('product.png'),
            'product_type' => 'shirt',
        ]);

        $response->assertStatus(402);
        $response->assertJsonPath('code', 'limit_exceeded');
        $response->assertJsonPath('premium_url', 'https://example.test/upgrade');
    }

    public function test_generation_record_is_created_and_provider_can_be_mocked(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->app->bind(AiTryOnProviderInterface::class, fn () => new FakeTryOnProvider());

        Storage::disk('ai-tryon-test')->put('products/shirt.png', 'fake product image');

        $response = $this->postJson(route('ai-tryon.generate'), [
            'user_image' => UploadedFile::fake()->image('person.jpg'),
            'product_image' => 'products/shirt.png',
            'product_id' => 'sku-123',
            'product_type' => 'shirt',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('generation.status', AiTryOnGeneration::STATUS_COMPLETED);
        $response->assertJsonPath('generation.provider', 'fake');

        $this->assertDatabaseHas('ai_tryon_generations', [
            'product_id' => 'sku-123',
            'product_type' => 'shirt',
            'status' => AiTryOnGeneration::STATUS_COMPLETED,
            'provider' => 'fake',
        ]);

        $this->assertDatabaseCount('ai_tryon_usages', 1);
        $this->assertNull(AiTryOnGeneration::query()->first()->original_user_image_path);
    }

    public function test_failed_mock_provider_marks_generation_failed(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->app->bind(AiTryOnProviderInterface::class, fn () => new FakeTryOnProvider(false));

        Storage::disk('ai-tryon-test')->put('products/shirt.png', 'fake product image');

        $response = $this->postJson(route('ai-tryon.generate'), [
            'user_image' => UploadedFile::fake()->image('person.jpg'),
            'product_image' => 'products/shirt.png',
            'product_type' => 'shirt',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('ai_tryon_generations', [
            'status' => AiTryOnGeneration::STATUS_FAILED,
            'error_message' => 'Fake provider failed.',
        ]);
    }
}
