{{--
    The public enquiry form, shared by every contact block variant. Posts to
    the tenant's own `leads.store` route (see StoreLeadController); success and
    validation errors come back through the session, so no JavaScript is
    involved and the form works in the page editor's canvas too (where the
    canvas swallows clicks, so it can't be submitted by accident).

    `_hp` is the honeypot: hidden from humans, irresistible to bots.
--}}
@props([
    'location' => null,
    'page' => null,
    'successMessage' => null,
])

<div id="contact" class="w-full max-w-xl">
    @if (session('lead_submitted'))
        <div role="status" class="alert alert-success">
            <span>{{ $successMessage ?: __('Thanks — we got your message and will be in touch.') }}</span>
        </div>
    @else
        <form method="POST" action="{{ route('leads.store') }}" class="space-y-4">
            @csrf

            @if ($location)
                <input type="hidden" name="location_id" value="{{ $location->id }}">
            @endif

            @if ($page && $page->id)
                <input type="hidden" name="page_id" value="{{ $page->id }}">
            @endif

            <div class="hidden" aria-hidden="true">
                <label for="lead-hp">{{ __('Leave this field empty') }}</label>
                <input id="lead-hp" type="text" name="_hp" value="" tabindex="-1" autocomplete="off">
            </div>

            <label class="floating-label">
                <span>{{ __('Your name') }}</span>
                <input
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    maxlength="120"
                    autocomplete="name"
                    placeholder="{{ __('Your name') }}"
                    class="input input-bordered w-full @error('name') input-error @enderror"
                >
            </label>
            @error('name')
                <p class="text-sm text-error">{{ $message }}</p>
            @enderror

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="floating-label">
                        <span>{{ __('Phone') }}</span>
                        <input
                            type="tel"
                            name="phone"
                            value="{{ old('phone') }}"
                            maxlength="40"
                            autocomplete="tel"
                            placeholder="{{ __('Phone') }}"
                            class="input input-bordered w-full @error('phone') input-error @enderror"
                        >
                    </label>
                    @error('phone')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="floating-label">
                        <span>{{ __('Email') }}</span>
                        <input
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            maxlength="255"
                            autocomplete="email"
                            placeholder="{{ __('Email') }}"
                            class="input input-bordered w-full @error('email') input-error @enderror"
                        >
                    </label>
                    @error('email')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <label class="floating-label">
                <span>{{ __('How can we help?') }}</span>
                <textarea
                    name="message"
                    rows="4"
                    maxlength="2000"
                    placeholder="{{ __('How can we help?') }}"
                    class="textarea textarea-bordered w-full @error('message') textarea-error @enderror"
                >{{ old('message') }}</textarea>
            </label>
            @error('message')
                <p class="text-sm text-error">{{ $message }}</p>
            @enderror

            <button type="submit" class="btn btn-primary">{{ __('Send message') }}</button>
        </form>
    @endif
</div>
