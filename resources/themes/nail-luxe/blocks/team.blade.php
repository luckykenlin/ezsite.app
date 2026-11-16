@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'members' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('team')->resolve($appearance);

    $items = array_values(array_filter(is_array($members) ? $members : [], 'is_array'));
    $centered = str_contains($layout->heading(), 'text-center');
@endphp
{{-- The artists: an arched portrait each, the name in the serif and the role
     in small caps underneath. No photo yet keeps the arch, with the initial
     set in the italic serif instead of a broken frame. --}}
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

        <div class="{{ $layout->headerGap() }} grid gap-10 {{ $layout->gridFor(count($items)) }}">
            @foreach ($items as $item)
                @continue(! ($item['name'] ?? null))
                <div class="site-artist">
                    <div class="site-arch site-arch-portrait">
                        @if ($item['avatar_url'] ?? null)
                            <img src="{{ $item['avatar_url'] }}" alt="" loading="lazy" class="site-arch-img" />
                        @else
                            <div class="site-arch-empty" aria-hidden="true">
                                <span class="site-arch-initial">{{ mb_strtoupper(mb_substr(mb_trim($item['name']), 0, 1)) }}</span>
                            </div>
                        @endif
                    </div>
                    <h3 data-editor-field="members.{{ $loop->index }}.name" class="site-h4 mt-6">
                        {{ $item['name'] }}
                    </h3>
                    @if ($item['role'] ?? null)
                        <p data-editor-field="members.{{ $loop->index }}.role" class="site-role">{{ $item['role'] }}</p>
                    @endif
                    @if ($item['bio'] ?? null)
                        <p data-editor-field="members.{{ $loop->index }}.bio" class="site-dim mt-3">
                            {{ $item['bio'] }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-site.section>
