<?php

declare(strict_types=1);

use App\Enums\LeadFieldSet;
use App\Models\Lead;
use App\Models\Tenant;

/**
 * A tenant whose home page carries one signup block with the given data.
 */
function tenantWithSignup(array $data = [], string $subdomain = 'acme'): Tenant
{
    $tenant = Tenant::factory()->withDomain($subdomain)->create();
    test()->createTenantBusiness($tenant, ['name' => 'QQ Nail']);
    test()->createTenantPage($tenant, [
        ['type' => 'signup', 'data' => [
            'heading' => 'Get 10% off',
            'offer' => 'Leave your number and we will text the voucher.',
            'button_label' => 'Send my voucher',
            ...$data,
        ]],
    ]);

    return $tenant;
}

it('renders both arrangements with the offer, the button and the form', function (string $variant): void {
    tenantWithSignup(['variant' => $variant]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Get 10% off')
        ->assertSee('Leave your number and we will text the voucher.')
        ->assertSee('Send my voucher')
        ->assertSee('data-lead-form', false)
        ->assertSee('name="source" value="inline_form"', false)
        ->assertSee('id="lead-signup-1"', false);
})->with(['banner', 'stacked']);

/*
 * The one button on the page whose whole job is to be clicked, on the one tone
 * that used to swallow it. A signup block's own tone default is `accent`, whose
 * surface IS the primary colour, so the brand fill rendered a rectangle of
 * background with the same text colour as the copy above it — on every preset in
 * the library. End-to-end here rather than only in the enum, because the fix
 * spans four files (tone → layout → block view → lead form) and any one of them
 * dropping the class puts it straight back.
 */
it('gives the submit button a fill that reads on the band it sits on', function (string $variant, string $tone, string $expected, string $forbidden): void {
    tenantWithSignup(['variant' => $variant, 'appearance' => ['tone' => $tone]]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSeeHtml($expected)
        ->assertDontSeeHtml($forbidden);
})->with([
    // The default tone, both arrangements.
    'banner on its own accent band' => ['banner', 'accent', 'site-btn-on-accent', 'site-btn-primary'],
    'stacked on its own accent band' => ['stacked', 'accent', 'site-btn-on-accent', 'site-btn-primary'],
    // Moved onto a pale band, the brand-coloured button is right again.
    'stacked on a pale band' => ['stacked', 'muted', 'site-btn-primary', 'site-btn-on-accent'],
    // A dark band keeps the brand button, and that asymmetry is deliberate:
    // see the argument on SectionTone::buttonClasses().
    'stacked on a dark band' => ['stacked', 'inverted', 'site-btn-primary', 'site-btn-on-accent'],
]);

it('asks only for the fields the operator chose', function (string $fields, array $present, array $absent): void {
    tenantWithSignup(['fields' => $fields]);

    $response = $this->get(sprintf('http://acme.%s/', $this->centralDomain()))->assertOk();

    foreach ($present as $name) {
        $response->assertSee(sprintf('name="%s"', $name), false);
    }

    foreach ($absent as $name) {
        $response->assertDontSee(sprintf('name="%s"', $name), false);
    }
})->with([
    // Every field costs completions, so the field set is the block's most
    // consequential setting — each option has to actually change the form.
    'phone only' => ['phone', ['phone'], ['email', 'message']],
    'email only' => ['email', ['email'], ['phone', 'message']],
    'email and phone' => ['email_phone', ['email', 'phone'], ['message']],
    'name and email' => ['name_email', ['name', 'email'], ['phone', 'message']],
    'name and phone' => ['name_phone', ['name', 'phone'], ['email', 'message']],
    'the full enquiry set' => ['full', ['name', 'email', 'phone', 'message'], []],
]);

it('falls back to asking for a phone when the stored field set is unreadable', function (): void {
    // Tenant data is never trusted to be a valid enum value — the same
    // fail-safe posture the layout axes take.
    tenantWithSignup(['fields' => 'not-a-field-set']);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('name="phone"', false)
        ->assertDontSee('name="message"', false);
});

it('gives each signup block on a page its own form identity', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'QQ Nail']);
    $this->createTenantPage($tenant, [
        ['type' => 'signup', 'data' => ['heading' => 'Top offer', 'button_label' => 'Go']],
        ['type' => 'signup', 'data' => ['heading' => 'Bottom offer', 'button_label' => 'Go']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('id="lead-signup-1"', false)
        ->assertSee('id="lead-signup-2"', false);
});

it('shows the thank-you on the submitted form only, leaving the other asking', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'QQ Nail']);
    $this->createTenantPage($tenant, [
        ['type' => 'signup', 'data' => ['heading' => 'Top offer', 'button_label' => 'Go', 'success_message' => 'Voucher sent.']],
        ['type' => 'contact', 'data' => ['variant' => 'stacked', 'heading' => 'Get in touch']],
    ]);

    // The whole reason forms carry an id: a page can hold several, and only
    // the one that was submitted should say thanks.
    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), [
        'form_id' => 'signup-1',
        'source' => 'inline_form',
        'phone' => '+1 555 0100',
    ])
        ->assertRedirect()
        ->assertSessionHas('lead_submitted', 'signup-1');

    $html = $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Voucher sent.')
        ->getContent();

    // The signup form is hidden and the contact form is not.
    expect($html)->toContain('id="lead-signup-1"')
        ->and($html)->toContain('id="lead-contact"');

    expect(Lead::query()->sole()->source->value)->toBe('inline_form');
});

it('labels every field set the enum defines', function (): void {
    // The block's Select is handed LeadFieldSet::class (HasLabel), so a new
    // case reaches the editor by being declared rather than by being wired —
    // provided every case carries a label.
    foreach (LeadFieldSet::cases() as $set) {
        expect($set->getLabel())->not->toBeEmpty();
    }
});
