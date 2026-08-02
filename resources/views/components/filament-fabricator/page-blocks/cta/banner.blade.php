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
            <h2 data-editor-field="heading" @class(['site-h2', 'text-center' => $card && $centered])>{{ $heading }}</h2>

            @if ($body)
                <p data-editor-field="body" @class(['site-intro max-w-2xl', 'mt-4' => ! $card, 'text-base-content/70' => $card, 'text-primary-content/80' => ! $card])>{{ $body }}</p>
            @endif

            <div @class(['flex flex-wrap items-center gap-4', 'mt-8' => ! $card, 'mt-4' => $card, 'justify-center' => $centered])>
                @if ($cta_label && $cta_url)
                    <a href="{{ $cta_url }}" @class(['btn', 'btn-primary' => $card, 'btn-neutral' => ! $card])>{{ $cta_label }}</a>
                @endif
                @if ($secondary_label && $secondary_url)
                    <a href="{{ $secondary_url }}" class="site-link-cta">{{ $secondary_label }}</a>
                @endif
            </div>
        </div>
    </div>
</x-site.section>
