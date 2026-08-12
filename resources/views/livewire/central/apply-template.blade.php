{{--
    The three-step apply wizard, plus the success screen.

    Steps are server-rendered rather than hidden-and-shown: the whole point of
    a step is that the person is looking at one question at a time, and a
    hidden fieldset is still a form someone can tab into.
--}}
<x-central.section>
    <div class="mx-auto flex max-w-2xl flex-col gap-8 px-6">
        @if ($step < 4)
            <div class="flex flex-col gap-3">
                <a href="{{ route('central.templates.show', $template) }}" class="text-sm opacity-70 hover:opacity-100">
                    &larr; {{ $template->label() }}
                </a>
                <h1 class="central-display-sm">{{ __('marketing.wizard.title') }}</h1>

                <ol class="flex items-center gap-2 text-sm" aria-label="{{ __('marketing.wizard.progress') }}">
                    @foreach ([__('marketing.wizard.steps.business'), __('marketing.wizard.steps.content'), __('marketing.wizard.steps.account')] as $index => $label)
                        <li @class([
                            'flex items-center gap-2',
                            'font-semibold' => $step === $index + 1,
                            'opacity-50' => $step !== $index + 1,
                        ])>
                            <span>{{ $index + 1 }}. {{ $label }}</span>
                            @unless ($loop->last)
                                <span aria-hidden="true" class="opacity-40">/</span>
                            @endunless
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if ($step === 1)
            <form wire:submit="next" class="flex flex-col gap-6">
                <label class="flex flex-col gap-2">
                    <span class="font-semibold">{{ __('marketing.wizard.business_name') }}</span>
                    <input
                        type="text"
                        wire:model.live.debounce.500ms="businessName"
                        class="central-field"
                        autocomplete="organization"
                        required
                    />
                    @error('businessName')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </label>

                <div class="flex flex-col gap-2">
                    <span class="font-semibold">{{ __('marketing.wizard.address') }}</span>
                    <div class="flex items-center gap-1">
                        <input
                            type="text"
                            wire:model.live.debounce.500ms="subdomain"
                            class="central-field flex-1"
                            required
                        />
                        <span class="opacity-60">.{{ parse_url(config('app.url'), PHP_URL_HOST) }}</span>
                    </div>

                    @error('subdomain')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                        @if ($subdomainSuggestion)
                            <button
                                type="button"
                                wire:click="useSuggestedSubdomain"
                                class="central-link self-start text-sm"
                            >
                                {{ __('marketing.wizard.use_suggestion', ['subdomain' => $subdomainSuggestion]) }}
                            </button>
                        @endif
                    @else
                        @if ($subdomain !== '')
                            <span class="text-sm text-green-700">{{ __('marketing.wizard.available', ['domain' => $subdomain.'.'.parse_url(config('app.url'), PHP_URL_HOST)]) }}</span>
                        @endif
                    @enderror
                </div>

                <label class="flex flex-col gap-2">
                    <span class="font-semibold"
                        >{{ __('marketing.wizard.tagline') }}
                        <span class="font-normal opacity-60">{{ __('marketing.wizard.optional') }}</span></span>
                    <input
                        type="text"
                        wire:model="tagline"
                        class="central-field"
                        placeholder="{{ $definition->demoProfile->tagline }}"
                    />
                    @error('tagline')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </label>

                <label class="flex flex-col gap-2">
                    <span class="font-semibold"
                        >{{ __('marketing.wizard.city') }}
                        <span class="font-normal opacity-60">{{ __('marketing.wizard.optional') }}</span></span>
                    <input
                        type="text"
                        wire:model="city"
                        class="central-field"
                        autocomplete="address-level2"
                        placeholder="{{ $definition->demoProfile->city }}"
                    />
                    @error('city')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </label>

                <label class="flex flex-col gap-2">
                    <span class="font-semibold"
                        >{{ __('marketing.wizard.phone') }}
                        <span class="font-normal opacity-60">{{ __('marketing.wizard.optional') }}</span></span>
                    <input type="tel" wire:model="phone" class="central-field" autocomplete="tel" />
                    @error('phone')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </label>

                <button type="submit" class="central-btn self-start">{{ __('marketing.actions.continue') }}</button>
            </form>
        @elseif ($step === 2)
            <form wire:submit="next" class="flex flex-col gap-6">
                <p class="opacity-80">{{ __('marketing.wizard.content_intro') }}</p>

                @foreach ($definition->extraFields as $field)
                    @php $help = $template->fieldHelp($field); @endphp
                    <label class="flex flex-col gap-2">
                        <span class="font-semibold">{{ $template->fieldLabel($field) }}</span>
                        @if ($field->multiline)
                            <textarea
                                wire:model="answers.{{ $field->key }}"
                                rows="3"
                                class="central-field"
                                placeholder="{{ $field->example }}"
                            ></textarea>
                        @else
                            <input
                                type="text"
                                wire:model="answers.{{ $field->key }}"
                                class="central-field"
                                placeholder="{{ $field->example }}"
                            />
                        @endif
                        @if ($help)
                            <span class="text-sm opacity-60">{{ $help }}</span>
                        @endif
                    </label>
                @endforeach

                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit" class="central-btn">{{ __('marketing.actions.continue') }}</button>
                    <button type="button" wire:click="skipDetails" class="central-link text-sm">
                        {{ __('marketing.wizard.skip') }}
                    </button>
                    <button type="button" wire:click="back" class="text-sm opacity-70 hover:opacity-100">
                        {{ __('marketing.actions.back') }}
                    </button>
                </div>
            </form>
        @elseif ($step === 3)
            <form wire:submit="submit" class="flex flex-col gap-6">
                <p class="opacity-80">{{ __('marketing.wizard.account_intro') }}</p>

                <label class="flex flex-col gap-2">
                    <span class="font-semibold">{{ __('marketing.wizard.email') }}</span>
                    <input type="email" wire:model="email" class="central-field" autocomplete="email" required />
                    @error('email')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </label>

                <label class="flex flex-col gap-2">
                    <span class="font-semibold">{{ __('marketing.wizard.password') }}</span>
                    <input
                        type="password"
                        wire:model="password"
                        class="central-field"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    />
                    @error('password')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </label>

                {{-- The honeypot. Hidden from people, irresistible to a form
                     filler; a submission that fills it is silently dropped. --}}
                <div aria-hidden="true" class="hidden">
                    <label
                        >{{ __('marketing.wizard.honeypot') }}<input
                            type="text"
                            wire:model="website"
                            tabindex="-1"
                            autocomplete="off"
                    /></label>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit" class="central-btn" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submit">{{ __('marketing.wizard.submit') }}</span>
                        <span wire:loading wire:target="submit">{{ __('marketing.wizard.submitting') }}</span>
                    </button>
                    <button type="button" wire:click="back" class="text-sm opacity-70 hover:opacity-100">
                        {{ __('marketing.actions.back') }}
                    </button>
                </div>
            </form>
        @else
            <div class="flex flex-col items-start gap-6">
                <p class="central-eyebrow">{{ __('marketing.wizard.done.eyebrow') }}</p>
                <h1 class="central-display-sm">
                    {{ __('marketing.wizard.done.title', ['business' => $businessName]) }}
                </h1>
                {{-- The emphasis markup is passed INTO the string rather than
                     wrapped around a fragment of it: a sentence split around
                     the address cannot be punctuated per language, and Chinese
                     ends it with 。not a full stop. $siteUrl is escaped before
                     it goes in. --}}
                <p class="central-intro">
                    {!! __('marketing.wizard.done.published', ['url' => '<span class="font-semibold">'.e($siteUrl).'</span>']) !!} {{ __('marketing.wizard.done.drafts') }}
                </p>

                <div class="flex flex-wrap items-center gap-4">
                    <a href="{{ $claimUrl }}" class="central-btn">{{ __('marketing.wizard.done.open_editor') }}</a>
                    <a
                        href="{{ $siteUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="central-link text-sm"
                    >{{ __('marketing.wizard.done.view_site') }}</a>
                </div>
            </div>
        @endif
    </div>
</x-central.section>
