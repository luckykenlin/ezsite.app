@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'count' => 3,
    'kindFilter' => null,
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('updates', 'cards')->resolve($appearance);

    $feed = resolve(\App\Site\PostFeed::class);
    // Stored data, so tryFrom: an unreadable filter means "everything" rather
    // than an empty section.
    $kind = \App\Enums\PostKind::tryFrom(is_string($kindFilter) ? $kindFilter : '');
    $posts = $feed->isFresh() ? $feed->latest(max(1, (int) $count), $kind) : collect();
@endphp
{{--
    Renders NOTHING when there is nothing recent — see PostFeed::isFresh().

    Not politeness: a "Latest updates" strip whose freshest item is dated 14
    January, still showing every day of September, tells the visitor comparing
    three salons that this business may have closed. That makes the site convert
    WORSE than not having the section, which is the exact inverse of the freshness
    this block exists to signal. The operator does not have to remember to remove
    it, because they will not.
--}}
@if ($posts->isNotEmpty())
    <x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
        <div class="{{ $layout->container() }}">
            <x-site.section-header :layout="$layout" :heading="$heading" :intro="$intro" />

            <div class="mt-12 grid gap-8 {{ $layout->gridFor($posts->count()) }}">
                @foreach ($posts as $post)
                    <x-site.update-card :post="$post" :layout="$layout" />
                @endforeach
            </div>
        </div>
    </x-site.section>
@endif
