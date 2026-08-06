{{--
    The one nudge urgent enough to follow the operator around the panel: their
    site does not answer yet.

    A panel-scoped render hook rather than something on the dashboard, because
    the owner who needs it most is the one the signup wizard dropped straight
    into the page editor and who never saw a dashboard. There is no dismiss
    button — dismissing it would leave the site just as invisible.

    Whether to render at all is decided in TenantPanelProvider (it must not
    greet an anonymous visitor on the login page); this view only draws it.
--}}
<div class="flex flex-col gap-3 border-b border-amber-300 bg-amber-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-amber-400/30 dark:bg-amber-400/10">
    <div class="flex items-start gap-3">
        <x-filament::icon
            icon="heroicon-o-exclamation-triangle"
            class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400"
        />

        <div class="text-sm">
            <p class="font-semibold text-amber-900 dark:text-amber-200">{{ __('Your site is not live yet') }}</p>
            <p class="text-amber-800 dark:text-amber-300/90">
                {{ __('Everything you have built is still a draft, so anyone who visits gets a "page not found". Open your pages and publish them.') }}
            </p>
        </div>
    </div>

    <x-filament::button :href="$pagesUrl" tag="a" color="warning" icon="heroicon-m-rocket-launch" class="shrink-0">
        {{ __('Open your pages') }}
    </x-filament::button>
</div>
