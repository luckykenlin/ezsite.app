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
<x-site.section :appearance="$appearance" tone="base" spacing="normal">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-6 md:grid-cols-2">
        <div>
            @if ($eyebrow)
                <p class="mb-4 text-sm font-semibold uppercase tracking-widest text-primary">{{ $eyebrow }}</p>
            @endif

            <h1 class="text-balance text-4xl font-bold tracking-tight md:text-5xl">{{ $heading }}</h1>

            @if ($subheading)
                <p class="mt-6 text-lg text-base-content/70">{{ $subheading }}</p>
            @endif

            @if ($cta_label && $cta_url)
                <a href="{{ $cta_url }}" class="btn btn-primary mt-8">{{ $cta_label }}</a>
            @endif
        </div>

        @if ($image_url)
            <div class="overflow-hidden rounded-box">
                <img src="{{ $image_url }}" alt="{{ $heading }}" class="h-full w-full object-cover" />
            </div>
        @endif
    </div>
</x-site.section>
