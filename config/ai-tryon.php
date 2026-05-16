<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Active AI Provider
    |--------------------------------------------------------------------------
    |
    | The provider key is resolved at runtime through the provider interface.
    | Keep model names in config so stores can move between Gemini Nano Banana,
    | newer Gemini image models, OpenAI, Replicate, or a custom provider.
    |
    */

    'provider' => env('AI_TRYON_PROVIDER', 'gemini'),

    'providers' => [
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'timeout' => env('GEMINI_TIMEOUT', 90),
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_IMAGE_MODEL'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout' => env('OPENAI_TIMEOUT', 90),
        ],

        'replicate' => [
            'api_key' => env('REPLICATE_API_KEY'),
            'model' => env('REPLICATE_MODEL'),
            'base_url' => env('REPLICATE_BASE_URL', 'https://api.replicate.com/v1'),
            'timeout' => env('REPLICATE_TIMEOUT', 120),
            'input_keys' => [
                'user_image' => 'user_image',
                'product_image' => 'product_image',
                'prompt' => 'prompt',
                'product_type' => 'product_type',
            ],
        ],
    ],

    'limits' => [
        'free_generations_per_user' => 5,
        'free_generations_per_ip' => 3,
        'daily_generations_per_user' => 20,
        'rate_per_minute' => env('AI_TRYON_RATE_PER_MINUTE', 6),
    ],

    'billing' => [
        'enabled' => false,
        'premium_url' => env('AI_TRYON_PREMIUM_URL'),
    ],

    'storage_disk' => env('AI_TRYON_DISK', 'public'),

    'queue' => [
        'enabled' => true,
        'connection' => env('AI_TRYON_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'queue' => env('AI_TRYON_QUEUE', 'default'),
    ],

    'routes' => [
        'enabled' => true,
        'prefix' => 'ai-tryon',
        'middleware' => ['web', 'throttle:ai-tryon'],
    ],

    'assets' => [
        'css_path' => 'vendor/ai-tryon/ai-tryon.css',
        'js_path' => 'vendor/ai-tryon/ai-tryon.js',
    ],

    'uploads' => [
        'max_file_size_kb' => env('AI_TRYON_MAX_FILE_SIZE_KB', 5120),
        'temporary_path' => 'ai-tryon/uploads',
        'product_path' => 'ai-tryon/products',
        'output_path' => 'ai-tryon/previews',
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'webp'],

        /*
         * Optional SSRF guard for remote product images. Leave empty to allow
         * any public ecommerce CDN URL, or list hosts such as cdn.example.com.
         */
        'allowed_product_image_hosts' => [],
    ],

    'privacy' => [
        /*
         * User photos are stored temporarily so a queued job can process them.
         * By default, the package deletes those uploads after generation and
         * clears the stored path from the generation row.
         */
        'store_user_uploads' => false,
        'auto_delete_uploads' => true,
        'require_consent' => false,
        'disclaimer' => 'AI try-on previews may be inaccurate. Upload only photos you have permission to use.',
    ],

    'product_types' => [
        'shirt',
        't-shirt',
        'pant',
        'cap',
        'dress',
        'shoes',
        'accessory',
        'other',
    ],

    'prompt' => 'Create a realistic ecommerce virtual try-on image. Keep the person\'s face, pose, body shape, skin tone, and background as natural as possible. Apply the clothing/accessory from the product image onto the person. Preserve product design, color, texture, pattern, and fit. Do not alter identity. Return only the final try-on image.',
];
