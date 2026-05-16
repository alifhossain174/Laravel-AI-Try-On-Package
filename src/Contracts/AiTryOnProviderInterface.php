<?php

namespace Vendor\LaravelAiTryon\Contracts;

use Vendor\LaravelAiTryon\Data\TryOnResult;

interface AiTryOnProviderInterface
{
    public function generate(
        string $userImagePath,
        string $productImagePath,
        array $options = []
    ): TryOnResult;
}
