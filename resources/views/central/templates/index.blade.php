{{-- The full gallery: every template, one card each. --}}
<x-central.layout :seo="$seo">
    <x-site.section tone="base" spacing="airy">
        <div class="mx-auto max-w-7xl px-6">
            <div class="flex max-w-3xl flex-col gap-4">
                <p class="site-eyebrow text-primary">{{ __('marketing.gallery.eyebrow') }}</p>
                <h1 class="site-display font-heading">{{ __('marketing.gallery.title', ['count' => \App\Templates\SiteTemplate::libraryCount()]) }}</h1>
                <p class="site-intro opacity-80">{{ __('marketing.gallery.intro') }}</p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($templates as $template)
                    <x-central.template-card :template="$template" />
                @endforeach
            </div>
        </div>
    </x-site.section>
</x-central.layout>
