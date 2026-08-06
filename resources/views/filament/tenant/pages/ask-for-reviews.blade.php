{{--
    One card per branch: the tracked link, the QR to print, and — where the Google
    place ID is missing — the one thing standing in the way.

    The compliance line at the bottom is not boilerplate. Review gating (asking only
    the customers you expect to be happy) is banned outright by Yelp and incentivised
    reviews are banned by Google, and an operator who does it can lose the profile
    this feature exists to help. Saying so where they are about to print the card is
    the only place it will be read.
--}}
<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($this->cards as $card)
            <x-filament::section :heading="$card['location']->label">
                @if ($card['url'] === null)
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('This branch has no Google place ID yet, so there is nothing to point a review link at.') }}
                    </p>

                    <x-filament::button
                        :href="$this->settingsUrl()"
                        tag="a"
                        color="gray"
                        class="mt-4"
                    >{{ __('Add it on the branch') }}</x-filament::button>
                @else
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                        {{-- An HtmlString: the "this markup is trusted" judgement
                             lives on RenderReviewQr, not in this template. --}}
                        <div class="[&>svg]:h-auto [&>svg]:w-full w-40 shrink-0">{{ $card['qr'] }}</div>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('Print this by the till, or put the link on a receipt. It opens the review box for this branch straight away.') }}
                            </p>

                            <p class="mt-3 font-mono text-sm break-all text-gray-950 dark:text-white">
                                {{ $card['url'] }}
                            </p>

                            <p class="mt-3 text-sm">
                                @if ($card['clicked'])
                                    <span class="text-success-600 dark:text-success-400 font-medium">{{ __('Somebody has used this one.') }}</span>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">{{ __('Nobody has opened it yet.') }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                @endif
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section :heading="__('The rules, briefly')" collapsible collapsed>
        <ul class="list-disc space-y-2 ps-5 text-sm text-gray-500 dark:text-gray-400">
            <li>
                {{ __('Ask every customer, not only the ones you think will say something nice. Filtering who you ask is against Yelp\'s rules outright and can cost you your profile.') }}
            </li>
            <li>
                {{ __('Never offer a discount or a freebie for a review. Google forbids it, and a bought review is worth less than none.') }}
            </li>
            <li>
                {{ __('Asking in person, right after good work, is what actually gets reviews written. The card is a reminder, not a substitute.') }}
            </li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
