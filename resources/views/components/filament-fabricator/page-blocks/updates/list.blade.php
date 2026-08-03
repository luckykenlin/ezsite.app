@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'count' => 3,
    'kind_filter' => null,
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('updates', 'list')->resolve($appearance);
    $posts = resolve(\App\Site\PostFeed::class)->forBlock($count, $kind_filter);
@endphp
{{-- Hides itself when there is nothing recent, for the reason spelled out in
     the sibling `cards` view. --}}
@if ($posts->isNotEmpty())
    <x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
        <div class="{{ $layout->container() }}">
            <x-site.section-header :layout="$layout" :heading="$heading" :intro="$intro" />

            <ul class="mt-10 divide-y divide-base-content/10">
                @foreach ($posts as $post)
                    <li class="relative py-5">
                        <x-site.update-meta :post="$post" />

                        <h3 class="site-h5 mt-1">
                            <a href="{{ $post->getUrl() }}" class="after:absolute after:inset-0">{{ $post->title }}</a>
                        </h3>

                        @if (filled($post->excerpt))
                            <p class="mt-1 text-base-content/70">{{ $post->excerpt }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </x-site.section>
@endif
