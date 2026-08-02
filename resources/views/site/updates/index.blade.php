{{--
    The list of a tenant's updates, at /updates.

    Rendered inside `x-site.document`, the same shell every page uses, so it
    carries the tenant's header, footer, theme and capture surfaces — a visitor
    should not be able to tell this page is not a Fabricator page.

    `:page="null"` is deliberate and load-bearing: the popup's lead form emits a
    `page_id` only when it has one, and `posts` and `pages` are separate id
    sequences — a borrowed id would silently attribute this enquiry to an
    unrelated page. With no page it attributes through `landing_path` + `utm_*`
    instead, which RememberLeadAttribution already records.

    No utility literals here: the layout comes from the same SectionLayout the
    `updates` block resolves, so this page inherits the design system rather than
    re-deciding it.
--}}
@php
    $layout = \App\Site\Blocks\SectionLayout::for('updates', 'cards')->resolve(null);
@endphp
<x-site.document :page="null" :title="__('Updates')" :seo-data="$seoData">
    <x-site.section :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
        <div class="{{ $layout->container() }}">
            <x-site.section-header
                :layout="$layout"
                :heading="__('Updates')"
                :intro="__('Offers, news and opening-hours changes.')"
            />

            @if ($posts->isEmpty())
                {{-- 200, not 404: this address is linked from the site's own
                     chrome and from share cards, and a 404 on a page the site
                     points at reads as a broken website. --}}
                <p class="mt-10 text-center text-base-content/70">{{ __('Nothing here just yet — check back soon.') }}</p>
            @else
                <div class="mt-12 grid gap-8 {{ $layout->gridFor($posts->count()) }}">
                    @foreach ($posts as $post)
                        <x-site.update-card :post="$post" :layout="$layout" />
                    @endforeach
                </div>
            @endif
        </div>
    </x-site.section>
</x-site.document>
