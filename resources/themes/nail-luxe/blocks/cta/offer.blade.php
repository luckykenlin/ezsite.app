@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'body' => null,
    'cta_label' => null,
    'cta_url' => null,
    'secondary_label' => null,
    'secondary_url' => null,
    'image_url' => null,
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('cta', 'offer')->resolve($appearance);
@endphp
{{-- The offer: a rounded photograph band with the pitch on a card laid over
     it. Without a photograph the band falls back to a soft blush-to-rose
     wash, so the card still sits on something rather than on nothing. The
     card re-asserts the page's own ink, so the button is the brand fill
     whatever the band's tone. --}}
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <div class="site-offer">
            @if ($image_url)
                <img src="{{ $image_url }}" alt="" loading="lazy" class="site-offer-img" />
            @else
                <div class="site-offer-wash" aria-hidden="true"></div>
            @endif

            <div class="site-offer-card">
                <x-site.ornament />
                <h2 data-editor-field="heading" class="site-h2 mt-3"><x-site.emphasis :text="$heading" /></h2>

                @if ($body)
                    <p data-editor-field="body" class="site-dim mt-4">{{ $body }}</p>
                @endif

                <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                    @if ($cta_label && $cta_url)
                        <a href="{{ $cta_url }}" class="site-btn site-btn-primary"
                            >{{ $cta_label }}<span class="site-btn-dot" aria-hidden="true"></span
                        ></a>
                    @endif
                    @if ($secondary_label && $secondary_url)
                        <a href="{{ $secondary_url }}" class="site-link-cta">{{ $secondary_label }}</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-site.section>
