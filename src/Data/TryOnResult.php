<?php

namespace Vendor\LaravelAiTryon\Data;

class TryOnResult
{
    public function __construct(
        public bool $success,
        public ?string $imagePath = null,
        public ?string $imageUrl = null,
        public ?string $provider = null,
        public ?string $model = null,
        public mixed $rawResponse = null,
        public ?string $errorMessage = null,
    ) {
    }

    public static function success(
        string $imagePath,
        ?string $imageUrl,
        string $provider,
        ?string $model,
        mixed $rawResponse = null
    ): self {
        return new self(
            success: true,
            imagePath: $imagePath,
            imageUrl: $imageUrl,
            provider: $provider,
            model: $model,
            rawResponse: $rawResponse,
        );
    }

    public static function failure(
        string $errorMessage,
        string $provider,
        ?string $model = null,
        mixed $rawResponse = null
    ): self {
        return new self(
            success: false,
            provider: $provider,
            model: $model,
            rawResponse: $rawResponse,
            errorMessage: $errorMessage,
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'image_path' => $this->imagePath,
            'image_url' => $this->imageUrl,
            'provider' => $this->provider,
            'model' => $this->model,
            'raw_response' => $this->rawResponse,
            'error_message' => $this->errorMessage,
        ];
    }
}
