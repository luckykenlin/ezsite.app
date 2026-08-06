<?php

declare(strict_types=1);

use App\Http\Middleware\RememberLeadAttribution;
use App\Models\Lead;
use App\Models\Tenant;

/**
 * A tenant whose home page carries the contact block's enquiry form.
 */
function tenantWithAttributedForm(string $subdomain = 'acme'): Tenant
{
    $tenant = Tenant::factory()->withDomain($subdomain)->create();
    test()->createTenantBusiness($tenant, ['name' => 'QQ Nail']);
    test()->createTenantPage($tenant, [
        ['type' => 'contact', 'data' => ['variant' => 'stacked', 'heading' => 'Get in touch']],
    ]);

    return $tenant;
}

it('carries the landing campaign through to a lead submitted on a later page', function (): void {
    tenantWithAttributedForm();
    $host = sprintf('http://acme.%s', $this->centralDomain());

    // The visitor lands from an ad...
    $this->get($host.'/?utm_source=google&utm_medium=cpc&utm_campaign=spring', [
        'referer' => 'https://www.google.com/search',
    ])->assertOk();

    // ...browses, losing the query string...
    $this->get($host.'/')->assertOk();

    // ...and only then enquires.
    $this->post($host.'/_leads', ['name' => 'Mei', 'phone' => '+1 555 0100'])
        ->assertRedirect();

    $lead = Lead::query()->sole();

    expect($lead->utm_source)->toBe('google')
        ->and($lead->utm_medium)->toBe('cpc')
        ->and($lead->utm_campaign)->toBe('spring')
        ->and($lead->utm_term)->toBeNull()
        ->and($lead->referrer)->toBe('https://www.google.com/search')
        ->and($lead->landing_path)->toBe('/?utm_source=google&utm_medium=cpc&utm_campaign=spring');
});

it('keeps the first touch when the visitor returns by another route', function (): void {
    tenantWithAttributedForm();
    $host = sprintf('http://acme.%s', $this->centralDomain());

    $this->get($host.'/?utm_source=google')->assertOk();
    // A later visit with a different campaign must not overwrite the one that
    // actually earned the visitor.
    $this->get($host.'/?utm_source=facebook')->assertOk();

    $this->post($host.'/_leads', ['name' => 'Mei', 'phone' => '+1 555 0100']);

    expect(Lead::query()->sole()->utm_source)->toBe('google');
});

it('treats an internal referrer as no referrer', function (): void {
    tenantWithAttributedForm();
    $host = sprintf('http://acme.%s', $this->centralDomain());

    $this->get($host.'/', ['referer' => $host.'/services'])->assertOk();

    $this->post($host.'/_leads', ['name' => 'Mei', 'phone' => '+1 555 0100']);

    $lead = Lead::query()->sole();

    expect($lead->referrer)->toBeNull()
        ->and($lead->landing_path)->toBe('/');
});

it('records nothing for a visitor who arrived with no campaign at all', function (): void {
    tenantWithAttributedForm();
    $host = sprintf('http://acme.%s', $this->centralDomain());

    $this->get($host.'/')->assertOk();
    $this->post($host.'/_leads', ['name' => 'Mei', 'phone' => '+1 555 0100']);

    $lead = Lead::query()->sole();

    expect($lead->utm_source)->toBeNull()
        ->and($lead->referrer)->toBeNull()
        ->and($lead->landing_path)->toBe('/');
});

it('does not let the enquiry POST become the landing page', function (): void {
    tenantWithAttributedForm();
    $host = sprintf('http://acme.%s', $this->centralDomain());

    // No GET first: the POST itself must not seed attribution, or every
    // direct-traffic lead would claim it landed on /_leads.
    $this->post($host.'/_leads', ['name' => 'Mei', 'phone' => '+1 555 0100']);

    expect(Lead::query()->sole()->landing_path)->toBeNull();
});

it('does not let an internal endpoint claim the landing slot', function (): void {
    tenantWithAttributedForm();
    $host = sprintf('http://acme.%s', $this->centralDomain());

    // Underscore-prefixed paths are the app's own plumbing (previews, the
    // editor stream, the enquiry endpoint). Whatever they return, none of
    // them is where a visitor arrived.
    $this->get($host.'/_leads');

    expect(session()->has(RememberLeadAttribution::SESSION_KEY))->toBeFalse();

    // A real page still seeds it.
    $this->get($host.'/')->assertOk();

    expect(session()->has(RememberLeadAttribution::SESSION_KEY))->toBeTrue();
});

it('truncates an overlong campaign value rather than failing the insert', function (): void {
    tenantWithAttributedForm();
    $host = sprintf('http://acme.%s', $this->centralDomain());

    $this->get($host.'/?utm_source='.str_repeat('a', 400))->assertOk();
    $this->post($host.'/_leads', ['name' => 'Mei', 'phone' => '+1 555 0100']);

    expect((string) Lead::query()->sole()->utm_source)->toHaveLength(255);
});

it('ignores a campaign value passed as an array', function (): void {
    tenantWithAttributedForm();
    $host = sprintf('http://acme.%s', $this->centralDomain());

    $this->get($host.'/?utm_source[]=google&utm_medium=%20')->assertOk();
    $this->post($host.'/_leads', ['name' => 'Mei', 'phone' => '+1 555 0100']);

    $lead = Lead::query()->sole();

    expect($lead->utm_source)->toBeNull()
        ->and($lead->utm_medium)->toBeNull();
});

it('narrows a forged session blob before anything can reach the insert', function (): void {
    // The session is untrusted input: an old format or a tampered payload may
    // hold keys that are not lead columns, or arrays where strings belong.
    // read() is the single gate — CaptureLead spreads its result verbatim.
    tenantWithAttributedForm();
    $host = sprintf('http://acme.%s', $this->centralDomain());

    $this->withSession([
        RememberLeadAttribution::SESSION_KEY => [
            'utm_source' => 'google',
            'utm_medium' => ['an', 'array'],
            'not_a_column' => 'ignored',
        ],
    ])->post($host.'/_leads', ['name' => 'Mei', 'phone' => '+1 555 0100'])
        ->assertRedirect();

    $lead = Lead::query()->sole();

    expect($lead->utm_source)->toBe('google')
        ->and($lead->utm_medium)->toBeNull()
        ->and($lead->landing_path)->toBeNull();
});
