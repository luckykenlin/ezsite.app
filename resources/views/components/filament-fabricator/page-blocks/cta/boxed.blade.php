@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'body' => null,
    'cta_label' => null,
    'cta_url' => null,
    'secondary_label' => null,
    'secondary_url' => null,
])
<x-site.section :appearance="$appearance" tone="base" spacing="tight">
    <div class="mx-auto max-w-5xl px-6">
        <div class="card bg-base-200">
            <div class="card-body items-center gap-4 py-12 text-center">
                <h2 data-editor-field="heading" class="card-title text-balance text-3xl tracking-tight md:text-4xl">{{ $heading }}</h2>

                @if ($body)
                    <p data-editor-field="body" class="max-w-2xl text-lg text-base-content/70">{{ $body }}</p>
                @endif

                <div class="card-actions mt-4 justify-center">
                    @if ($cta_label && $cta_url)
                        <a href="{{ $cta_url }}" class="btn btn-primary">{{ $cta_label }}</a>
                    @endif
                    @if ($secondary_label && $secondary_url)
                        <a href="{{ $secondary_url }}" class="btn btn-ghost">{{ $secondary_label }}</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-site.section>
