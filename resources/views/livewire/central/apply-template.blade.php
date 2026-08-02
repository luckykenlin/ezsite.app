{{--
    The three-step apply wizard, plus the success screen.

    Steps are server-rendered rather than hidden-and-shown: the whole point of
    a step is that the person is looking at one question at a time, and a
    hidden fieldset is still a form someone can tab into.
--}}
<x-site.section tone="base" spacing="airy">
    <div class="mx-auto flex max-w-2xl flex-col gap-8 px-6">
        @if ($step < 4)
            <div class="flex flex-col gap-3">
                <a href="{{ route('central.templates.show', $template) }}" class="text-sm opacity-70 hover:opacity-100">
                    &larr; {{ $template->label() }}
                </a>
                <h1 class="site-h2 font-heading">Let&rsquo;s build your site</h1>

                <ol class="flex items-center gap-2 text-sm" aria-label="Progress">
                    @foreach (['Your business', 'Your content', 'Your account'] as $index => $label)
                        <li @class([
                            'flex items-center gap-2',
                            'font-semibold text-primary' => $step === $index + 1,
                            'opacity-50' => $step !== $index + 1,
                        ])>
                            <span>{{ $index + 1 }}. {{ $label }}</span>
                            @unless ($loop->last)<span aria-hidden="true" class="opacity-40">/</span>@endunless
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if ($step === 1)
            <form wire:submit="next" class="flex flex-col gap-6">
                <label class="flex flex-col gap-2">
                    <span class="font-semibold">What is the business called?</span>
                    <input type="text" wire:model.live.debounce.500ms="businessName" class="input input-bordered w-full" autocomplete="organization" required>
                    @error('businessName') <span class="text-sm text-error">{{ $message }}</span> @enderror
                </label>

                <div class="flex flex-col gap-2">
                    <span class="font-semibold">Your web address</span>
                    <div class="flex items-center gap-1">
                        <input type="text" wire:model.live.debounce.500ms="subdomain" class="input input-bordered flex-1" required>
                        <span class="opacity-60">.{{ parse_url(config('app.url'), PHP_URL_HOST) }}</span>
                    </div>

                    @error('subdomain')
                        <span class="text-sm text-error">{{ $message }}</span>
                        @if ($subdomainSuggestion)
                            <button type="button" wire:click="useSuggestedSubdomain" class="site-link-cta self-start text-sm text-primary">
                                Use {{ $subdomainSuggestion }} instead
                            </button>
                        @endif
                    @else
                        @if ($subdomain !== '')
                            <span class="text-sm text-success">{{ $subdomain }}.{{ parse_url(config('app.url'), PHP_URL_HOST) }} is free</span>
                        @endif
                    @enderror
                </div>

                <label class="flex flex-col gap-2">
                    <span class="font-semibold">One line about the business <span class="font-normal opacity-60">(optional)</span></span>
                    <input type="text" wire:model="tagline" class="input input-bordered w-full" placeholder="{{ $definition->demoProfile->tagline }}">
                    @error('tagline') <span class="text-sm text-error">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-2">
                    <span class="font-semibold">Town or city <span class="font-normal opacity-60">(optional)</span></span>
                    <input type="text" wire:model="city" class="input input-bordered w-full" autocomplete="address-level2" placeholder="{{ $definition->demoProfile->city }}">
                    @error('city') <span class="text-sm text-error">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-2">
                    <span class="font-semibold">Phone <span class="font-normal opacity-60">(optional)</span></span>
                    <input type="tel" wire:model="phone" class="input input-bordered w-full" autocomplete="tel">
                    @error('phone') <span class="text-sm text-error">{{ $message }}</span> @enderror
                </label>

                <button type="submit" class="btn btn-primary btn-lg self-start">Continue</button>
            </form>
        @elseif ($step === 2)
            <form wire:submit="next" class="flex flex-col gap-6">
                <p class="opacity-80">
                    These go straight onto your pages. Leave any of them blank — or skip the lot — and the
                    template&rsquo;s example content stays until you change it in the editor.
                </p>

                @foreach ($definition->extraFields as $field)
                    <label class="flex flex-col gap-2">
                        <span class="font-semibold">{{ $field->label }}</span>
                        @if ($field->multiline)
                            <textarea wire:model="answers.{{ $field->key }}" rows="3" class="textarea textarea-bordered w-full" placeholder="{{ $field->example }}"></textarea>
                        @else
                            <input type="text" wire:model="answers.{{ $field->key }}" class="input input-bordered w-full" placeholder="{{ $field->example }}">
                        @endif
                        @if ($field->help) <span class="text-sm opacity-60">{{ $field->help }}</span> @endif
                    </label>
                @endforeach

                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit" class="btn btn-primary btn-lg">Continue</button>
                    <button type="button" wire:click="skipDetails" class="site-link-cta text-primary">
                        Skip — use example content, edit later
                    </button>
                    <button type="button" wire:click="back" class="text-sm opacity-70 hover:opacity-100">Back</button>
                </div>
            </form>
        @elseif ($step === 3)
            <form wire:submit="submit" class="flex flex-col gap-6">
                <p class="opacity-80">Last step. This is the account you&rsquo;ll sign in with to edit your site.</p>

                <label class="flex flex-col gap-2">
                    <span class="font-semibold">Email</span>
                    <input type="email" wire:model="email" class="input input-bordered w-full" autocomplete="email" required>
                    @error('email') <span class="text-sm text-error">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-2">
                    <span class="font-semibold">Password</span>
                    <input type="password" wire:model="password" class="input input-bordered w-full" autocomplete="new-password" minlength="8" required>
                    @error('password') <span class="text-sm text-error">{{ $message }}</span> @enderror
                </label>

                {{-- The honeypot. Hidden from people, irresistible to a form
                     filler; a submission that fills it is silently dropped. --}}
                <div aria-hidden="true" class="hidden">
                    <label>Website<input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit" class="btn btn-primary btn-lg" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submit">Build my site</span>
                        <span wire:loading wire:target="submit">Building your site…</span>
                    </button>
                    <button type="button" wire:click="back" class="text-sm opacity-70 hover:opacity-100">Back</button>
                </div>
            </form>
        @else
            <div class="flex flex-col items-start gap-6">
                <p class="site-eyebrow text-primary">Your site is live</p>
                <h1 class="site-display font-heading">{{ $businessName }} is on the internet</h1>
                <p class="site-intro opacity-80">
                    It&rsquo;s published at <span class="font-semibold">{{ $siteUrl }}</span>. The pages are saved as
                    drafts so you can read them over before anyone else does — open the editor and hit publish when
                    you&rsquo;re happy. Photographs are still landing; give them a minute.
                </p>

                <div class="flex flex-wrap items-center gap-4">
                    <a href="{{ $claimUrl }}" class="btn btn-primary btn-lg">Open the editor</a>
                    <a href="{{ $siteUrl }}" target="_blank" rel="noopener" class="site-link-cta">View my site</a>
                </div>
            </div>
        @endif
    </div>
</x-site.section>
