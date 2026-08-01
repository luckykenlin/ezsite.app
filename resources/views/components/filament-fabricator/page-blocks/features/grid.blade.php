@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'features' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('features', 'grid')->resolve($appearance);

    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    $items = is_array($features) ? $features : [];
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <x-site.section-header :layout="$layout" :heading="$heading" :intro="$intro" />

        <div class="mt-12 grid gap-8 {{ $layout->grid() }}">
            @foreach ($items as $item)
                @continue(! is_array($item))
                @php
                    // resolveMediaUrls() injects image_url per repeater item and
                    // drops any URL whose scheme executes, so both of these are
                    // already safe to emit — see BlockRegistry::denyExecutableUrls().
                    $image = $item['image_url'] ?? null;
                    $link = $item['link_url'] ?? null;
                @endphp
                <div @class(['relative', $layout->item(), 'transition hover:shadow-lg' => $link && $layout->isCard()])>
                    @if ($image)
                        {{-- Decorative: the title next to it is the accessible
                             name, so an alt here would be read out twice. --}}
                        <figure @class(['overflow-hidden rounded-box' => ! $layout->isCard()])><img src="{{ $image }}" alt="" loading="lazy" class="{{ $layout->image() }} w-full object-cover"></figure>
                    @endif
                    <div @class(['card-body' => $layout->isCard(), 'mt-4' => ! $layout->isCard() && $image])>
                        {{-- The glyph is a stand-in for imagery; with a real
                             photo above it, it is just noise. --}}
                        @if (($item['icon'] ?? null) && ! $image)
                            <span class="text-3xl" aria-hidden="true">{{ $item['icon'] }}</span>
                        @endif
                        <h3 @class(['card-title' => $layout->isCard(), 'text-lg font-semibold' => ! $layout->isCard()])>
                            {{-- One link, labelled by the title, stretched over
                                 the whole card by the ::after overlay. Wrapping
                                 the card in an <a> instead would swallow the
                                 heading and hand a screen reader a link whose
                                 name is the entire card. --}}
                            @if ($link)
                                <a href="{{ $link }}" class="after:absolute after:inset-0">{{ $item['title'] ?? '' }}</a>
                            @else
                                {{ $item['title'] ?? '' }}
                            @endif
                        </h3>
                        @if ($item['description'] ?? null)
                            <p class="text-base-content/70">{{ $item['description'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-site.section>
