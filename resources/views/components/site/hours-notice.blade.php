{{--
    A slim bar above the header announcing a closure or a change of hours.

    "Are you open?" is the question that loses a local business the most
    customers, and answering it needs no channel, no approval and no third party
    — just the tenant's own site saying so before the visitor has to look. It is
    the whole reason PostKind::Hours exists.

    Derived at read time from the current Hours update, never written into
    site_settings: it appears when the update's window opens and disappears when
    it closes, with nothing to switch it off and nothing to drift out of step.
--}}
@php($notice = resolve(\App\Site\PostFeed::class)->currentHoursNotice())

@if ($notice !== null)
    <aside class="site-notice-bar" role="status">
        <div class="site-notice-bar-inner">
            <span class="site-notice-bar-label">{{ $notice->title }}</span>

            @if (filled($notice->excerpt))
                <span class="site-notice-bar-detail">{{ $notice->excerpt }}</span>
            @endif

            <a href="{{ $notice->getUrl() }}" class="site-notice-bar-link">{{ __('Details') }}</a>
        </div>
    </aside>
@endif
