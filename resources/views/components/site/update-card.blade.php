{{--
    One update as a card. Shared by the `updates` block and the `/updates` index,
    so the same announcement looks the same wherever a visitor meets it.

    Lives under `components/site/` deliberately: that directory is already inside
    site.css's @source list, and it is where the shared shell pieces live.

    The whole card is one stretched link labelled by the title — an <a> wrapped
    around the card instead would hand a screen reader a link whose name is the
    entire card, including the date and the badge.
--}}
@props(['post', 'layout'])
@php
    $coverUrl = resolve(\App\Site\MediaResolver::class)->url($post->cover_media_id);
@endphp
<article @class(['relative flex flex-col', $layout->item()])>
    @if ($coverUrl)
        {{-- Decorative: the title beside it is the accessible name, so an alt
             here would be announced twice. --}}
        <figure @class(['overflow-hidden rounded-box' => ! $layout->isCard()])>
            <img src="{{ $coverUrl }}" alt="" loading="lazy" class="{{ $layout->image() }} w-full object-cover" />
        </figure>
    @endif

    <div @class(['card-body' => $layout->isCard(), 'mt-4' => ! $layout->isCard() && $coverUrl])>
        <x-site.update-meta :post="$post" />

        <h3 @class(['card-title' => $layout->isCard(), 'site-h5' => ! $layout->isCard()])>
            <a href="{{ $post->getUrl() }}" class="after:absolute after:inset-0">{{ $post->title }}</a>
        </h3>

        @if (filled($post->excerpt))
            <p class="text-base-content/70">{{ $post->excerpt }}</p>
        @endif
    </div>
</article>
