@aware(['page'])
@props([
    'appearance' => null,
    'eyebrow' => null,
    'heading' => null,
    'subheading' => null,
    'cta_label' => null,
    'cta_url' => null,
    'image_url' => null,
])
<x-site.section :appearance="$appearance" tone="base" spacing="airy">
    <div class="mx-auto flex max-w-3xl flex-col items-center px-6 text-center">
        @if ($eyebrow)
            <p data-editor-field="eyebrow" class="mb-4 text-sm font-semibold uppercase tracking-widest text-primary">{{ $eyebrow }}</p>
        @endif

        <h1 data-editor-field="heading" class="text-balance text-4xl font-bold tracking-tight md:text-6xl">{{ $heading }}</h1>

        @if ($subheading)
            <p data-editor-field="subheading" class="mt-6 max-w-2xl text-lg text-base-content/70">{{ $subheading }}</p>
        @endif

        @if ($cta_label && $cta_url)
            <a href="{{ $cta_url }}" class="btn btn-primary mt-10">{{ $cta_label }}</a>
        @endif
    </div>
</x-site.section>
