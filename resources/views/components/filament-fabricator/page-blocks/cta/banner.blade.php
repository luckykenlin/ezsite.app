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
    $layout = \App\Site\Blocks\SectionLayout::for('cta', 'banner')->resolve($appearance);

    // item_style=card is the old "boxed" variant: the pitch sits on a card
    // that re-asserts its own readable foreground, so the copy and the
    // primary button switch to the on-card treatments.
    $card = $layout->isCard();
    $centered = str_contains($layout->heading(), 'text-center');
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <div @class([
            $layout->item(),
            'card-body items-center gap-4 py-12' => $card && $centered,
            'card-body gap-4 py-12' => $card && ! $centered,
            'flex flex-col' => ! $card,
            'items-center text-center' => ! $card && $centered,
        ])>
            {{-- The block offers an image; a layout that ignored it left the
                 operator's choice invisible. Small and above the pitch — the
                 photograph is the illustration here, not the subject, which is
                 what the full-photo variant is for. --}}
            @if ($image_url)
                {{-- Uncropped, unlike every other item image: a CTA declares no
                     image_shape axis (the full-photo variant needs none), and
                     an aspect class hand-rolled here would be exactly the drift
                     ConventionsTest forbids. Capped width does the work. --}}
                <div class="w-full max-w-xs overflow-hidden rounded-box">
                    <img src="{{ $image_url }}" alt="" loading="lazy" class="w-full object-cover" />
                </div>
            @endif

            <h2 data-editor-field="heading" @class(['site-h2', 'text-center' => $card && $centered])>{{ $heading }}</h2>

            @if ($body)
                {{-- `site-dim` rather than one literal per item style: the two
                     branches this used to have were really one guess about the
                     TONE — a card sits on the page, so base-content; a bare
                     banner sits on the accent surface, so primary-content. The
                     guess breaks the moment a preset moves the banner off accent
                     (white text on a pale band, invisible). Dimming currentColor
                     is right on every tone and identical on the two it covered. --}}
                <p data-editor-field="body" @class(['site-intro site-dim max-w-2xl', 'mt-4' => ! $card])>{{ $body }}</p>
            @endif

            <div @class(['flex flex-wrap items-center gap-4', 'mt-8' => ! $card, 'mt-4' => $card, 'justify-center' => $centered])>
                @if ($cta_label && $cta_url)
                    {{-- On a card the button contrasts with the CARD (always a
                         light surface, see SectionTone::itemSurface()), so it
                         stays the brand colour. Standing on the band it has to
                         contrast with the band, which is the tone's call. --}}
                    <a href="{{ $cta_url }}" class="{{ $card ? 'btn btn-primary' : $layout->button() }}">{{ $cta_label }}</a>
                @endif
                @if ($secondary_label && $secondary_url)
                    <a href="{{ $secondary_url }}" class="site-link-cta">{{ $secondary_label }}</a>
                @endif
            </div>
        </div>
    </div>
</x-site.section>
