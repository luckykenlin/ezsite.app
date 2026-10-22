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
@php
    $layout = \App\Site\Blocks\SectionLayout::for('hero', 'centered-minimal')->resolve($appearance);

    // A hero's headline has its own display scale, so the align axis lands on
    // the flex column rather than through the shared section header.
    $centered = str_contains($layout->heading(), 'text-center');
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div @class([$layout->container(), 'flex flex-col', 'items-center text-center' => $centered, 'items-start' => ! $centered])>
        @if ($eyebrow)
            <p data-editor-field="eyebrow" class="site-eyebrow text-primary mb-4">{{ $eyebrow }}</p>
        @endif

        <h1 data-editor-field="heading" class="site-display">{{ $heading }}</h1>

        @if ($subheading)
            <p data-editor-field="subheading" class="site-intro site-dim mt-6 max-w-2xl">{{ $subheading }}</p>
        @endif

        @if ($cta_label && $cta_url)
            <a href="{{ $cta_url }}" class="{{ $layout->button() }} mt-10">{{ $cta_label }}</a>
        @endif

        {{-- Minimal is about the COPY, not about refusing a photograph: the
             block offers an image field, and a layout that quietly dropped
             whatever was chosen read as a broken canvas. Below the pitch and
             full width, so the reading order the variant exists for is
             untouched. --}}
        @if ($image_url)
            <div class="site-frame isolate mt-14 w-full">
                <div class="rounded-box overflow-hidden">
                    <img
                        src="{{ $image_url }}"
                        alt="{{ $heading }}"
                        class="{{ $layout->image() }} w-full object-cover"
                    />
                </div>
            </div>
        @endif
    </div>
</x-site.section>
