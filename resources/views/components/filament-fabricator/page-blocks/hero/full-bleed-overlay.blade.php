@aware(['page'])
@props([
    'eyebrow' => null,
    'heading' => null,
    'subheading' => null,
    'cta_label' => null,
    'cta_url' => null,
    'image_url' => null,
])
<section class="relative isolate overflow-hidden bg-neutral text-neutral-content">
    @if ($image_url)
        <img src="{{ $image_url }}" alt="{{ $heading }}" class="absolute inset-0 -z-10 h-full w-full object-cover" />
        <div class="absolute inset-0 -z-10 bg-neutral/60"></div>
    @endif

    <div class="mx-auto flex max-w-4xl flex-col items-start px-6 py-32 md:py-48">
        @if ($eyebrow)
            <p class="mb-4 text-sm font-semibold uppercase tracking-widest">{{ $eyebrow }}</p>
        @endif

        <h1 class="text-balance text-5xl font-bold tracking-tight md:text-7xl">{{ $heading }}</h1>

        @if ($subheading)
            <p class="mt-6 max-w-2xl text-lg opacity-80">{{ $subheading }}</p>
        @endif

        @if ($cta_label && $cta_url)
            <a href="{{ $cta_url }}" class="btn btn-primary mt-10">{{ $cta_label }}</a>
        @endif
    </div>
</section>
