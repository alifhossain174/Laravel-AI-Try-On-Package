<?php

namespace Vendor\LaravelAiTryon\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class TryOnButton extends Component
{
    public function __construct(
        public string $productImage,
        public string|int|null $productId = null,
        public string $productType = 'other',
        public string $label = 'Try Out',
    ) {
    }

    public function render(): View
    {
        return view('ai-tryon::components.button');
    }
}
