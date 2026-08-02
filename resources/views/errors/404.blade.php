{{--
    The 404 every domain in the installation serves.

    Two audiences, one file, because Laravel resolves exactly one view per
    status: a visitor on a tenant domain gets that tenant's site, themed with
    its own tokens; a visitor on the central domain gets the marketing frame.
    Before this, both got the framework's grey default page, which on a
    customer's own domain reads as "this business's website is broken".

    Kept free of utility classes: the two components it delegates to are inside
    site.css's declared @source paths and this directory is not.
--}}
@use('App\Site\BindResolver')

@if (tenant() === null)
    <x-central.layout>
        <x-site.section>
            <div class="mx-auto max-w-2xl text-center">
                <h1 class="site-h1 font-heading">Page not found</h1>
                <p class="mt-4 text-lg text-base-content/70">
                    That link does not lead anywhere. The templates are a better place to start.
                </p>
                <a href="{{ route('central.templates.index') }}" class="btn btn-primary mt-8">Browse templates</a>
            </div>
        </x-site.section>
    </x-central.layout>
@else
    <x-site.notice
        :business="resolve(BindResolver::class)->business()"
        title="Page not found"
        heading="We could not find that page"
        body="The link may be out of date, or the page may have moved."
    >
        <a href="{{ url('/') }}" class="btn btn-primary">Back to the home page</a>
    </x-site.notice>
@endif
