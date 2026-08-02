<?php

declare(strict_types=1);

namespace App\Livewire\Central;

use App\Actions\Templates\CreateClaimUrl;
use App\Actions\Templates\ProvisionSiteFromTemplate;
use App\Actions\Templates\ValidateSubdomain;
use App\Models\User;
use App\Templates\SignupDetails;
use App\Templates\SiteTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The guided form that turns a template into somebody's website.
 *
 * Full-page Livewire, and the codebase's first Livewire component outside
 * Filament. The reasons are specific: `/livewire/update` works on the central
 * domain, the subdomain field has to answer "is that free?" while someone
 * types (which plain Blade cannot do), and a Filament schema outside a panel
 * drags panel styling onto the marketing surface.
 *
 * Three steps, and the middle one is skippable in one click — the templates
 * are complete on their own, so skipping still yields a good site. That is the
 * whole reason the industry questions exist as a separate step rather than
 * appended to step one: a person who does not want to type their menu should
 * be able to say so, not abandon.
 *
 * State lives in the component, not in a table. There is no `applications`
 * row in v1: a half-finished wizard is worth nothing to anyone, and the only
 * durable artefact is the site itself.
 */
#[Layout('components.central.layout')]
final class ApplyTemplate extends Component
{
    #[Locked]
    public SiteTemplate $template;

    #[Locked]
    public int $step = 1;

    public string $businessName = '';

    public string $subdomain = '';

    public string $tagline = '';

    public string $city = '';

    public string $phone = '';

    public string $email = '';

    public string $password = '';

    /** @var array<string, string> */
    public array $answers = [];

    /**
     * The honeypot. Named like a field a form-filling bot wants and hidden
     * from people; anything in it means the submitter is not one.
     */
    public string $website = '';

    /**
     * Where the finished site is, once it exists — the success screen's two
     * buttons, and the marker that the wizard is done.
     */
    #[Locked]
    public ?string $siteUrl = null;

    #[Locked]
    public ?string $claimUrl = null;

    /**
     * The free variant offered when the typed subdomain is taken, so the fix
     * is one click rather than another round of guessing.
     */
    #[Locked]
    public ?string $subdomainSuggestion = null;

    /**
     * Whether the person has taken the address over from the auto-derived
     * one. A public locked property rather than a private field because only
     * public state survives a Livewire round trip — a private one would reset
     * every keystroke and the prefill would never stop overwriting them.
     */
    #[Locked]
    public bool $subdomainEdited = false;

    public function mount(SiteTemplate $template): void
    {
        $this->template = $template;

        foreach ($template->definition()->extraFields as $field) {
            $this->answers[$field->key] = '';
        }
    }

    /**
     * Prefill the address from the business name until the person edits it
     * themselves — after that it is theirs, and re-deriving it would fight
     * whatever they typed.
     */
    public function updatedBusinessName(string $value): void
    {
        if (! $this->subdomainEdited) {
            $this->subdomain = Str::slug($value);
            $this->checkSubdomain();
        }
    }

    public function updatedSubdomain(): void
    {
        $this->subdomainEdited = true;
        $this->checkSubdomain();
    }

    public function useSuggestedSubdomain(): void
    {
        if ($this->subdomainSuggestion !== null) {
            $this->subdomain = $this->subdomainSuggestion;
            $this->checkSubdomain();
        }
    }

    public function next(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'businessName' => ['required', 'string', 'max:120'],
                'tagline' => ['nullable', 'string', 'max:160'],
                'city' => ['nullable', 'string', 'max:120'],
                'phone' => ['nullable', 'string', 'max:40'],
            ]);

            $this->subdomain = resolve(ValidateSubdomain::class)->handle($this->subdomain);
        }

        $this->step++;
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    /**
     * Skip the industry questions: clear whatever was half-typed and move on.
     * The example content then fills every placeholder — see
     * {@see \App\Actions\Templates\FillTemplatePlaceholders}.
     */
    public function skipDetails(): void
    {
        $this->answers = array_map(static fn (): string => '', $this->answers);
        $this->step = 3;
    }

    public function submit(CreateClaimUrl $createClaimUrl, ProvisionSiteFromTemplate $provision): void
    {
        // A bot that filled the hidden field gets the same silent nothing a
        // rate-limited one does: no error to learn from.
        if ($this->website !== '') {
            return;
        }

        $this->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $this->assertNotRateLimited();

        $details = new SignupDetails(
            businessName: $this->businessName,
            subdomain: $this->subdomain,
            email: $this->email,
            password: $this->password,
            tagline: $this->tagline,
            city: $this->city,
            phone: $this->phone,
            answers: array_filter($this->answers, static fn (string $answer): bool => mb_trim($answer) !== ''),
        );

        $tenant = $provision->handle($this->template, $details);

        RateLimiter::hit($this->rateLimiterKey(), Config::integer('templates.signup.decay_minutes') * 60);

        $user = User::query()->where('email', $this->email)->sole();

        $this->siteUrl = $tenant->domain?->getUrl();
        $this->claimUrl = $createClaimUrl->handle($tenant, $user);
        $this->password = '';
        $this->step = 4;
    }

    public function render(): View
    {
        return view('livewire.central.apply-template', [
            'definition' => $this->template->definition(),
        ]);
    }

    /**
     * Live availability. Errors are shown against the field and the free
     * variant is offered alongside; a valid one clears both.
     */
    private function checkSubdomain(): void
    {
        $this->resetErrorBag('subdomain');
        $this->subdomainSuggestion = null;

        if ($this->subdomain === '') {
            return;
        }

        try {
            $this->subdomain = resolve(ValidateSubdomain::class)->handle($this->subdomain);
        } catch (ValidationException $validationException) {
            $this->addError('subdomain', $validationException->validator->errors()->first('subdomain'));
            $this->subdomainSuggestion = resolve(ValidateSubdomain::class)->suggest($this->subdomain);
        }
    }

    /**
     * Instant provisioning with no email verification and no manual approval
     * is the product promise; this is the only friction standing in for all of
     * it. Keyed on IP and email together, so one address cannot be spread
     * across a proxy pool and one connection cannot spread across addresses.
     *
     * @throws ValidationException
     */
    private function assertNotRateLimited(): void
    {
        if (RateLimiter::tooManyAttempts($this->rateLimiterKey(), Config::integer('templates.signup.max_attempts'))) {
            throw ValidationException::withMessages([
                'email' => __('Too many sites created from this connection. Try again in :minutes minutes.', [
                    'minutes' => (int) ceil(RateLimiter::availableIn($this->rateLimiterKey()) / 60),
                ]),
            ]);
        }
    }

    private function rateLimiterKey(): string
    {
        return 'template-signup:'.sha1(request()->ip().'|'.mb_strtolower($this->email));
    }
}
