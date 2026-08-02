{{--
    A whole public document on a tenant site: the base layout, the site chrome
    around the body, and the floating capture surfaces under it.

    Extracted out of `layouts/main.blade.php` when `/updates` arrived, because an
    update is not a Fabricator page and cannot be rendered through a layout typed
    to one — but it must come out looking like the same website, with the same
    header, the same footer and the same offer popup. One shell means a change to
    the footer or the popup is one edit, not two that drift.

    `page` is a Fabricator Page or null. It reaches two places, both of which
    already tolerate null:

    - the block loop's `@aware(['page' => null])`, which only uses it to call
      `preloadRelatedData()` — a no-op on every block this app defines, and
      explicitly guarded in our override of that view;
    - the lead form, which emits a `page_id` only when it has one. That guard is
      what keeps an enquiry from an UPDATE page out of the `pages` id space:
      `posts` and `pages` are separate sequences, so a `page_id` borrowed from an
      update would silently attribute the lead to an unrelated page. Update pages
      pass null and attribute through `landing_path` + `utm_*` instead.

    `editorKeys` / `editorChrome` are passed only by the page editor's canvas
    preview and only ever from `layouts/main.blade.php`. They are the reason this
    shell has conditionals at all; nothing else may pass them.
--}}
@use('App\Enums\ChromeSlot')
@props([
    'page' => null,
    'title' => null,
    'seoData' => null,
    'editorKeys' => null,
    'editorChrome' => null,
])

<x-filament-fabricator::layouts.base :page="$page" :title="$title" :seo-data="$seoData">
    {{-- Tells resources/js/site.ts to stand down: the canvas has its own
         interaction model (canvas-glue.ts swallows every click), and a popup
         opened over the page being edited could not be dismissed. The marker
         lives here rather than in the base layout, which is a deliberate
         verbatim copy of the package's. --}}
    @if (is_array($editorKeys))
        <div data-editor-canvas hidden></div>
    @endif

    @if (is_array($editorChrome))
        <x-editor-chrome-slot
            :chrome-slot="ChromeSlot::Header"
            :entries="$editorChrome[ChromeSlot::Header->value] ?? []"
        />
    @else
        <x-site.hours-notice />

        <x-filament-fabricator::page-blocks :blocks="resolve(\App\Site\SiteChrome::class)->headerBlocks()" />
    @endif

    {{ $slot }}

    @if (is_array($editorChrome))
        <x-editor-chrome-slot
            :chrome-slot="ChromeSlot::Footer"
            :entries="$editorChrome[ChromeSlot::Footer->value] ?? []"
        />
    @else
        <x-filament-fabricator::page-blocks :blocks="resolve(\App\Site\SiteChrome::class)->footerBlocks()" />
    @endif

    {{-- The floating capture surfaces, last so they layer over the page and so
         the call bar's spacer sits below the footer. Public renders only: an
         undismissable modal over the editor canvas, or a fixed bar covering the
         block the operator is editing, would both be bugs. --}}
    @unless (is_array($editorKeys))
        @php($capture = resolve(\App\Site\SiteCapture::class))

        @if ($capture->popupEnabled())
            <x-site.popup :capture="$capture" :page="$page" />
        @endif

        @if ($capture->callBarEnabled())
            <x-site.call-bar :capture="$capture" />
        @endif
    @endunless
</x-filament-fabricator::layouts.base>
