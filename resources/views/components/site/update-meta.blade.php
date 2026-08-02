{{--
    The dateline of one update: when it was published and — for anything other
    than a plain update — what kind of announcement it is.

    One component because it renders in three places (the update card, the
    updates block's list variant, and the update's own page) and the badge rule
    must not drift: a plain update carries no badge, because "Update" as a
    label says nothing the date does not.
--}}
@props(['post'])
<p {{ $attributes->class(['site-eyebrow']) }}>
    <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->isoFormat('LL') }}</time>
    @if ($post->kind !== \App\Enums\PostKind::Update)
        <span class="site-tone-accent">{{ $post->kind->getLabel() }}</span>
    @endif
</p>
