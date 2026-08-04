<?php

declare(strict_types=1);

use App\Livewire\Central\ApplyTemplate;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\User;
use App\Templates\SiteTemplate;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function wizard(SiteTemplate $template = SiteTemplate::ChineseRestaurant): Testable
{
    return Livewire::test(ApplyTemplate::class, ['template' => $template]);
}

beforeEach(function (): void {
    Queue::fake();
});

it('derives the address from the business name until someone edits it', function (): void {
    wizard()
        ->set('businessName', 'Jade Pearl')
        ->assertSet('subdomain', 'jade-pearl')
        ->set('businessName', 'Jade Pearl Kitchen')
        ->assertSet('subdomain', 'jade-pearl-kitchen')
        // Once the address is theirs, the name stops overwriting it.
        ->set('subdomain', 'jade')
        ->set('businessName', 'Something Else Entirely')
        ->assertSet('subdomain', 'jade');
});

it('says so while you type when an address is taken, and offers the nearest free one', function (): void {
    Tenant::factory()->withDomain('jade-pearl')->create();

    wizard()
        ->set('subdomain', 'jade-pearl')
        ->assertHasErrors('subdomain')
        ->assertSet('subdomainSuggestion', 'jade-pearl-2')
        ->call('useSuggestedSubdomain')
        ->assertSet('subdomain', 'jade-pearl-2')
        ->assertHasNoErrors('subdomain');
});

it('will not move past step one without a name and a free address', function (): void {
    wizard()
        ->call('next')
        ->assertHasErrors('businessName')
        ->assertSet('step', 1);

    Tenant::factory()->withDomain('jade-pearl')->create();

    wizard()
        ->set('businessName', 'Jade Pearl')
        ->call('next')
        ->assertHasErrors('subdomain')
        ->assertSet('step', 1);
});

it('walks the three steps and builds the site', function (): void {
    $template = SiteTemplate::ChineseRestaurant;

    $component = wizard($template)
        ->set('businessName', 'Jade Pearl')
        ->set('tagline', 'Sichuan cooking, no apologies')
        ->set('city', 'Portland')
        ->call('next')
        ->assertSet('step', 2)
        ->set('answers.dish_one', 'Mapo Tofu')
        ->call('next')
        ->assertSet('step', 3)
        ->set('email', 'owner@jadepearl.test')
        ->set('password', 'a-good-password')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('step', 4)
        // The password is not carried around in component state afterwards.
        ->assertSet('password', '');

    $tenant = Tenant::query()->sole();

    expect($tenant->template)->toBe($template)
        ->and($component->get('siteUrl'))->toBe($tenant->domain?->getUrl())
        ->and($component->get('claimUrl'))->toContain('/_claim/')
        ->and(User::query()->sole()->email)->toBe('owner@jadepearl.test');

    $this->runInTenant($tenant, function (): void {
        expect(json_encode(Page::query()->pluck('blocks')))
            ->toContain('Mapo Tofu')
            ->toContain('Portland');
    });
});

it('skips straight past the industry questions and still ships a full site', function (): void {
    $definition = SiteTemplate::NailSalon->definition();

    wizard(SiteTemplate::NailSalon)
        ->set('businessName', 'The Gilded Tip')
        ->call('next')
        ->set('answers.'.$definition->extraFields[0]->key, 'half-typed, then abandoned')
        ->call('skipDetails')
        ->assertSet('step', 3)
        ->set('email', 'owner@gildedtip.test')
        ->set('password', 'a-good-password')
        ->call('submit')
        ->assertSet('step', 4);

    $tenant = Tenant::query()->sole();

    $this->runInTenant($tenant, function () use ($definition): void {
        $copy = (string) json_encode(Page::query()->pluck('blocks'));

        // Skipping means the example content, not the half-typed answer.
        expect($copy)->toContain($definition->extraFields[0]->example)
            ->not->toContain('half-typed');
    });
});

it('lets someone go back and change an earlier answer', function (): void {
    wizard()
        ->set('businessName', 'Jade Pearl')
        ->call('next')
        ->call('back')
        ->assertSet('step', 1)
        // And never below the first step.
        ->call('back')
        ->assertSet('step', 1);
});

it('validates the account step', function (): void {
    wizard()
        ->set('businessName', 'Jade Pearl')
        ->call('next')
        ->call('next')
        ->set('email', 'not-an-email')
        ->set('password', 'short')
        ->call('submit')
        ->assertHasErrors(['email' => 'email', 'password' => 'min'])
        ->assertSet('step', 3);

    expect(Tenant::query()->count())->toBe(0);
});

it('silently drops a submission that filled the honeypot', function (): void {
    // No error to learn from: a bot that is told which field gave it away
    // simply stops filling that field.
    wizard()
        ->set('businessName', 'Jade Pearl')
        ->call('next')
        ->call('next')
        ->set('email', 'bot@example.test')
        ->set('password', 'a-good-password')
        ->set('website', 'http://spam.example')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('step', 3);

    expect(Tenant::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('stops after the configured number of sites from one connection', function (): void {
    // Instant provisioning with no email verification is the promise; this is
    // the only friction standing in for all of it.
    config(['templates.signup.max_attempts' => 1]);

    $submit = fn (string $name, string $email): Testable => wizard()
        ->set('businessName', $name)
        ->call('next')
        ->call('next')
        ->set('email', $email)
        ->set('password', 'a-good-password')
        ->call('submit');

    $submit('Jade Pearl', 'owner@jadepearl.test')->assertSet('step', 4);
    $submit('Jade Palace', 'owner@jadepearl.test')->assertHasErrors('email');

    expect(Tenant::query()->count())->toBe(1);
});

it('counts the limit per email, so one person s cap is not everyone s', function (): void {
    config(['templates.signup.max_attempts' => 1]);

    wizard()
        ->set('businessName', 'Jade Pearl')
        ->call('next')
        ->call('next')
        ->set('email', 'owner@jadepearl.test')
        ->set('password', 'a-good-password')
        ->call('submit')
        ->assertSet('step', 4);

    wizard()
        ->set('businessName', 'Ora Studio')
        ->call('next')
        ->call('next')
        ->set('email', 'someone@else.test')
        ->set('password', 'a-good-password')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('step', 4);

    expect(Tenant::query()->count())->toBe(2);
});

it('says nothing about an address that has been cleared', function (): void {
    // Emptying the field is not a mistake, it is a mid-edit state — showing
    // "use 3 to 63 letters" the instant someone selects-all-and-deletes would
    // shout at them for typing.
    Tenant::factory()->withDomain('jade-pearl')->create();

    wizard()
        ->set('subdomain', 'jade-pearl')
        ->assertHasErrors('subdomain')
        ->set('subdomain', '')
        ->assertHasNoErrors('subdomain')
        ->assertSet('subdomainSuggestion', null);
});

it('keeps every link in the wizard in the language it was opened in', function (): void {
    // The regression this exists for: /livewire/update is a route of its own —
    // universal, shared with the tenant panels — so the central SetLocale
    // middleware never runs on it and URL::defaults falls back to the
    // application-wide default. This view calls route('central.templates.show')
    // on EVERY re-render, so without ApplyTemplate::booted() an English
    // visitor's back-link turns Chinese on their first keystroke.
    App::setLocale('en');

    wizard(SiteTemplate::PizzaShop)
        ->assertSet('locale', 'en')
        // A round trip: the update request is where the locale is lost.
        ->set('businessName', 'Alley Slice')
        ->assertSee(route('central.templates.show', ['locale' => 'en', 'template' => SiteTemplate::PizzaShop]))
        ->assertDontSee(route('central.templates.show', ['locale' => 'zh', 'template' => SiteTemplate::PizzaShop]));
});

it('stays in Chinese across a round trip', function (): void {
    App::setLocale('zh');

    wizard(SiteTemplate::PizzaShop)
        ->assertSet('locale', 'zh')
        ->set('businessName', '巷口披萨')
        ->assertSee(trans('marketing.wizard.business_name', locale: 'zh'))
        ->assertSee(route('central.templates.show', ['locale' => 'zh', 'template' => SiteTemplate::PizzaShop]))
        ->assertDontSee('What is the business called?');
});
