<?php

namespace FahimHossain\LaravelAiTryon\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use FahimHossain\LaravelAiTryon\Tests\TestCase;

class TryOnButtonTest extends TestCase
{
    public function test_try_on_button_renders_modal(): void
    {
        $html = Blade::render('<x-ai-tryon::button product-id="123" product-image="products/shirt.png" product-type="shirt" />');

        $this->assertStringContainsString('Try Out', $html);
        $this->assertStringContainsString('Virtual try-on', $html);
        $this->assertStringContainsString('name="user_image"', $html);
        $this->assertStringContainsString('products/shirt.png', $html);
    }
}
