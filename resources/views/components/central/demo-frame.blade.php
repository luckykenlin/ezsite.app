{{--
    A browser-chrome frame around a screenshot or iframe: three window dots
    and, when given, the real address the framed site is served from — the
    "this is live right now" cue the made-with wall and the detail page's
    demo panel both trade on.
--}}
@props(['url' => null])
<div {{ $attributes->class(['border-ink/10 shadow-ink/10 bg-paper overflow-hidden rounded-2xl border shadow-xl']) }}>
    <div class="border-ink/10 bg-mist flex items-center gap-2 border-b px-4 py-2.5">
        <span class="flex gap-1.5" aria-hidden="true">
            <span class="bg-ink/15 size-2.5 rounded-full"></span>
            <span class="bg-ink/15 size-2.5 rounded-full"></span>
            <span class="bg-ink/15 size-2.5 rounded-full"></span>
        </span>
        @if ($url)
            <span class="mx-auto truncate font-mono text-xs opacity-60">{{ $url }}</span>
            <span class="w-9" aria-hidden="true"></span>
        @endif
    </div>
    {{ $slot }}
</div>
