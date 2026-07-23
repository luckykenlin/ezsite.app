@aware(['page'])
@props([
    'eyebrow' => null,
    'heading' => null,
    'subheading' => null,
    'cta_label' => null,
    'cta_url' => null,
    'image_url' => null,
])
<section class="bg-base-100 text-base-content">
    <div class="mx-auto flex max-w-3xl flex-col items-center px-6 py-24 text-center md:py-32">
        @if ($eyebrow)
            <p class="mb-4 text-sm font-semibold uppercase tracking-widest text-primary">{{ $eyebrow }}</p>
        @endif

        <h1 class="text-balance text-4xl font-bold tracking-tight md:text-6xl">{{ $heading }}</h1>

        @if ($subheading)
            <p class="mt-6 max-w-2xl text-lg text-base-content/70">{{ $subheading }}</p>
        @endif

        @if ($cta_label && $cta_url)
            <a href="{{ $cta_url }}" class="btn btn-primary mt-10">{{ $cta_label }}</a>
        @endif
    </div>
</section>
