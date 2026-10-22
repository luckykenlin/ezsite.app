@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'items' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('offerings', 'category-cards')->resolve($appearance);

    $entries = array_values(array_filter(is_array($items) ? $items : [], 'is_array'));
    $groups = [];

    foreach ($entries as $entry) {
        $group = is_string($entry['group'] ?? null) && mb_trim($entry['group']) !== '' ? mb_trim($entry['group']) : __('Services');
        $groups[$group][] = $entry;
    }

    $centered = str_contains($layout->heading(), 'text-center');
@endphp
{{-- The menu at a glance: one rounded card per category with its count and
     the first few names, each card linking through to the full price list.
     The block holds no link of its own, so "View all" points at the
     theme's services page and the price list's `#services` anchor. --}}
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        @if ($heading || $intro)
            <div @class(['site-head', 'site-head-center' => $centered])>
                <x-site.ornament />
                @if ($heading)
                    <h2 data-editor-field="heading" class="{{ $layout->heading() }}">
                        <x-site.emphasis :text="$heading" />
                    </h2>
                @endif
                @if ($intro)
                    <p data-editor-field="intro" class="{{ $layout->intro() }}">{{ $intro }}</p>
                @endif
            </div>
        @endif

        <div class="{{ $layout->headerGap() }} grid gap-6 {{ $layout->gridFor(count($groups)) }}">
            @foreach ($groups as $group => $groupItems)
                <a href="/services#services" class="{{ $layout->item() }} site-category-card">
                    <div @class(['site-card-body' => $layout->isCard(), 'site-category-body'])>
                        <x-site.ornament />
                        <h3 class="site-h4">{{ $group }}</h3>
                        <p class="site-eyebrow site-accent-ink">
                            {{ trans_choice(':count service|:count services', count($groupItems)) }}
                        </p>
                        <ul class="site-category-list site-dim">
                            @foreach (array_slice($groupItems, 0, 3) as $item)
                                <li>{{ $item['name'] ?? '' }}</li>
                            @endforeach
                        </ul>
                        <span class="site-link-cta">{{ __('View all') }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</x-site.section>
