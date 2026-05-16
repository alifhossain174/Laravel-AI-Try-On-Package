<?php

namespace Vendor\LaravelAiTryon\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TryOnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('ai-tryon.uploads.max_file_size_kb', 5120);
        $mimes = implode(',', (array) config('ai-tryon.uploads.allowed_mimes', ['jpg', 'jpeg', 'png', 'webp']));

        return [
            'user_image' => ['required', 'file', 'image', "mimes:{$mimes}", "max:{$max}"],
            'product_image' => ['required_without:product_image_file', 'nullable', 'string', 'max:4096'],
            'product_image_file' => ['required_without:product_image', 'nullable', 'file', 'image', "mimes:{$mimes}", "max:{$max}"],
            'product_id' => ['nullable', 'string', 'max:191'],
            'product_type' => ['nullable', Rule::in((array) config('ai-tryon.product_types', ['other']))],
            'consent' => [config('ai-tryon.privacy.require_consent', false) ? 'accepted' : 'nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_image.required' => 'Please upload a photo to create your try-on preview.',
            'user_image.image' => 'Your uploaded photo must be a valid image.',
            'product_image.required_without' => 'A product image is required.',
            'product_image_file.required_without' => 'A product image is required.',
            'consent.accepted' => 'Please confirm that you have permission to use the uploaded photo.',
        ];
    }
}
