<?php

namespace FahimHossain\LaravelAiTryon\Contracts;

use FahimHossain\LaravelAiTryon\Data\TryOnResult;

interface AiTryOnProviderInterface
{
    public function generate(
        string $userImagePath,
        string $productImagePath,
        array $options = []
    ): TryOnResult;
}
