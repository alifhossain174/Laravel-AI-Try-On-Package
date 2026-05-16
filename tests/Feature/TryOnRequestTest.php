<?php

namespace Vendor\LaravelAiTryon\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\UploadedFile;
use Vendor\LaravelAiTryon\Tests\TestCase;

class TryOnRequestTest extends TestCase
{
    public function test_upload_validation_requires_user_image(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $response = $this->postJson(route('ai-tryon.generate'), [
            'product_image' => 'products/shirt.png',
            'product_type' => 'shirt',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('user_image');
    }

    public function test_upload_validation_rejects_unsupported_product_type(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $response = $this->postJson(route('ai-tryon.generate'), [
            'user_image' => UploadedFile::fake()->image('person.jpg'),
            'product_image' => 'products/shirt.png',
            'product_type' => 'helmet',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('product_type');
    }
}
