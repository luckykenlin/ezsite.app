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
{{-- The only view that uses the shell's `backdrop` slot: the artwork is
     absolutely positioned against the section itself and must sit OUTSIDE the
     padding wrapper, so `-z-10` resolves in the same stacking context that
     `isolate` creates here. --}}
<x-site.section
    :appearance="$appearance"
    tone="inverted"
    spacing="tall"
    class="relative isolate overflow-hidden"
>
    <x-slot:backdrop>
        @if ($image_url)
            <img src="{{ $image_url }}" alt="{{ $heading }}" class="absolute inset-0 -z-10 h-full w-full object-cover" />
            <div class="absolute inset-0 -z-10 bg-neutral/60"></div>
        @endif
    </x-slot:backdrop>

    <div class="mx-auto flex max-w-4xl flex-col items-start px-6">
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
</x-site.section>
