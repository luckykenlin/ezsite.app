{{-- The full gallery: every template, one card each. --}}
@php
    $seo = new \RalphJSmit\Laravel\SEO\Support\SEOData(
        title: 'Website templates for small businesses',
        description: 'Eight finished websites, one for each trade. Open the live demo, then make it yours in a few minutes.',
        url: route('central.templates'),
        enableTitleSuffix: false,
        site_name: config('app.name'),
    );
@endphp
<x-central.layout :seo="$seo">
    <x-site.section tone="base" spacing="airy">
        <div class="mx-auto max-w-7xl px-6">
            <div class="flex max-w-3xl flex-col gap-4">
                <p class="site-eyebrow text-primary">Templates</p>
                <h1 class="site-display font-heading">Eight finished sites. Pick the one that fits.</h1>
                <p class="site-intro opacity-80">
                    Each one is a real published site, not a wireframe — open the live demo, read the copy,
                    scroll it on your phone. When you find the right one, it takes a few minutes to make it yours.
                </p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($templates as $template)
                    <x-central.template-card :template="$template" />
                @endforeach
            </div>
        </div>
    </x-site.section>
</x-central.layout>
