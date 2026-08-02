{{--
    One update, at its permanent address.

    Same shell, same theme, same chrome as every other page — see the note in
    `index.blade.php` about `:page="null"` and lead attribution.

    An EXPIRED update still renders here, on purpose: a link already live in
    somebody's Facebook feed, or on a Google Business Profile post, must not start
    returning a 404 three weeks later. What changes is the call to action — the
    button is replaced by a plain sentence saying when the offer ended, so the
    page is honest without being broken.

    The body is emitted as one escaped <p> per paragraph. Never `{!! !!}`: these
    pages are built from tenant-authored text on a shared domain, so unescaped
    output is stored XSS — the same reasoning documented on PageBlocks\Prose, and
    arch-enforced across this directory.
--}}
@php
    $layout = \App\Site\Blocks\SectionLayout::for('updates', 'list')->resolve(null);
    $coverUrl = resolve(\App\Site\MediaResolver::class)->url($post->cover_media_id);
    $expired = $post->isExpired();
@endphp
<x-site.document :page="null" :title="$post->title" :seo-data="$seoData">
    <x-site.section :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
        <article class="{{ $layout->container() }}">
            <x-site.update-meta :post="$post" />

            <h1 class="site-h1 mt-2">{{ $post->title }}</h1>

            @if (filled($post->excerpt))
                <p class="site-intro mt-4">{{ $post->excerpt }}</p>
            @endif

            @if ($coverUrl)
                <figure class="mt-8 overflow-hidden rounded-box">
                    <img src="{{ $coverUrl }}" alt="{{ $post->title }}" class="{{ $layout->image() }} w-full object-cover">
                </figure>
            @endif

            @foreach ($paragraphs as $paragraph)
                <p class="mt-6 text-base-content/80">{{ $paragraph }}</p>
            @endforeach

            @if ($post->kind->isOffer() && (filled($post->offer_coupon_code) || filled($post->offer_terms)))
                {{-- Not `.site-card`: that class is owned by SectionItemStyle::Card
                     and arch-banned from a view. This is a callout inside an
                     article, not a section item. --}}
                <div class="mt-8 rounded-box border border-base-content/15 p-6">
                    @if (filled($post->offer_coupon_code))
                        <p class="site-eyebrow">{{ __('Use code') }}</p>
                        <p class="site-h4">{{ $post->offer_coupon_code }}</p>
                    @endif

                    @if (filled($post->offer_terms))
                        <p class="mt-2 text-sm text-base-content/60">{{ $post->offer_terms }}</p>
                    @endif
                </div>
            @endif

            <div class="mt-10">
                @if ($expired)
                    <p class="text-base-content/60">
                        {{ __('This :kind ended on :date.', [
                            'kind' => mb_strtolower($post->kind->getLabel()),
                            'date' => $post->ends_at?->isoFormat('LL'),
                        ]) }}
                    </p>
                @elseif ($post->cta_action !== null)
                    <a href="{{ $post->ctaHref($business) }}" class="btn btn-primary">{{ $post->cta_action->getLabel() }}</a>
                @endif
            </div>

            <p class="mt-12">
                <a href="{{ '/'.\App\Models\Post::PATH_PREFIX }}" class="site-link-cta">{{ __('All updates') }}</a>
            </p>
        </article>
    </x-site.section>
</x-site.document>
