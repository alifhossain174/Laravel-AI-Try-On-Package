@php
    $instanceId = 'ai-tryon-'.\Illuminate\Support\Str::uuid();
    $disclaimer = config('ai-tryon.privacy.disclaimer');
@endphp

@once
    <link rel="stylesheet" href="{{ asset(config('ai-tryon.assets.css_path', 'vendor/ai-tryon/ai-tryon.css')) }}">
    <script src="{{ asset(config('ai-tryon.assets.js_path', 'vendor/ai-tryon/ai-tryon.js')) }}" defer></script>
@endonce

<div
    id="{{ $instanceId }}"
    class="ai-tryon"
    data-ai-tryon-root
    data-ai-tryon-endpoint="{{ route('ai-tryon.generate') }}"
>
    <button type="button" class="ai-tryon__button" data-ai-tryon-open>
        {{ $label }}
    </button>

    <div class="ai-tryon__modal" data-ai-tryon-modal hidden>
        <div class="ai-tryon__backdrop" data-ai-tryon-close></div>

        <section
            class="ai-tryon__dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $instanceId }}-title"
        >
            <header class="ai-tryon__header">
                <h2 id="{{ $instanceId }}-title">Virtual try-on</h2>
                <button type="button" class="ai-tryon__icon-button" data-ai-tryon-close aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </header>

            <form class="ai-tryon__form" data-ai-tryon-form enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="product_id" value="{{ $productId }}">
                <input type="hidden" name="product_image" value="{{ $productImage }}">
                <input type="hidden" name="product_type" value="{{ $productType }}">

                <label class="ai-tryon__dropzone">
                    <input
                        type="file"
                        name="user_image"
                        accept="image/jpeg,image/png,image/webp"
                        data-ai-tryon-file
                        required
                    >
                    <span class="ai-tryon__dropzone-title">Upload your photo</span>
                    <span class="ai-tryon__dropzone-text">JPEG, PNG, or WebP up to {{ (int) config('ai-tryon.uploads.max_file_size_kb', 5120) / 1024 }} MB</span>
                </label>

                <figure class="ai-tryon__preview" data-ai-tryon-preview-wrap hidden>
                    <img data-ai-tryon-preview alt="Selected customer photo preview">
                </figure>

                @if (config('ai-tryon.privacy.require_consent', false))
                    <label class="ai-tryon__consent">
                        <input type="checkbox" name="consent" value="1" required>
                        <span>I have permission to use this photo.</span>
                    </label>
                @endif

                @if ($disclaimer)
                    <p class="ai-tryon__disclaimer">{{ $disclaimer }}</p>
                @endif

                <div class="ai-tryon__actions">
                    <button type="button" class="ai-tryon__secondary" data-ai-tryon-close>Cancel</button>
                    <button type="submit" class="ai-tryon__primary" data-ai-tryon-submit>Create preview</button>
                </div>

                <div class="ai-tryon__message" data-ai-tryon-message hidden></div>

                <figure class="ai-tryon__result" data-ai-tryon-result-wrap hidden>
                    <img data-ai-tryon-result alt="Generated virtual try-on preview">
                </figure>
            </form>
        </section>
    </div>
</div>
