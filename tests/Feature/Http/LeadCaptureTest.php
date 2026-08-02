<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\Location;
use App\Models\Page;
use App\Models\Tenant;

/**
 * Create a tenant with a business, a location and a published home page whose
 * contact block carries the enquiry form. Returns [tenant, page].
 *
 * @return array{0: Tenant, 1: Page}
 */
function tenantWithContactForm(string $subdomain = 'acme', array $contactData = []): array
{
    $tenant = Tenant::factory()->withDomain($subdomain)->create();
    test()->createTenantBusiness($tenant, ['name' => 'QQ Nail']);
    $page = test()->createTenantPage($tenant, [
        ['type' => 'contact', 'data' => ['variant' => 'stacked', 'heading' => 'Get in touch'] + $contactData],
    ]);

    return [$tenant, $page];
}

it('renders the enquiry form on the public contact block', function (): void {
    [$tenant, $page] = tenantWithContactForm();
    $location = $this->runInTenant($tenant, fn (): Location => Location::query()->firstOrFail());

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee(sprintf('action="http://acme.%s/_leads"', $this->centralDomain()), false)
        ->assertSee('name="_hp"', false)
        ->assertSee(sprintf('name="location_id" value="%d"', $location->id), false)
        ->assertSee(sprintf('name="page_id" value="%d"', $page->id), false)
        ->assertSee('Send message');
});

it('hides the form when the block turns it off', function (): void {
    tenantWithContactForm('acme', ['show_form' => false]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Get in touch')
        ->assertDontSee('name="_hp"', false);
});

it('captures a submitted enquiry for the tenant whose domain was posted to', function (): void {
    [$tenant, $page] = tenantWithContactForm();
    $other = Tenant::factory()->withDomain('other')->create();
    $location = $this->runInTenant($tenant, fn (): Location => Location::query()->firstOrFail());

    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), [
        'name' => 'Mei',
        'phone' => '+1 555 0100',
        'message' => 'Do you take walk-ins?',
        'location_id' => $location->id,
        'page_id' => $page->id,
        '_hp' => '',
    ])
        ->assertRedirect()
        ->assertSessionHas('lead_submitted', 'contact');

    $lead = Lead::query()->sole();

    expect($lead->tenant_id)->toBe($tenant->id)
        ->and($lead->name)->toBe('Mei')
        ->and($lead->location_id)->toBe($location->id)
        ->and($lead->page_id)->toBe($page->id);

    // RLS keeps the enquiry invisible to any other tenant.
    tenancy()->initialize($other);
    $visibleToOther = Lead::query()->count();
    tenancy()->end();

    expect($visibleToOther)->toBe(0);
});

it('shows the thank-you message after a submission', function (): void {
    tenantWithContactForm('acme', ['success_message' => 'Got it — we will call you back today.']);

    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), [
        'name' => 'Mei',
        'phone' => '+1 555 0100',
    ]);

    // Both states live in the DOM and are toggled with `hidden`, so the form
    // is still present after a submission — just not shown.
    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Got it — we will call you back today.')
        ->assertSee('data-lead-form', false)
        ->assertSee('hidden', false);
});

it('requires at least one way to reply, but not a name', function (): void {
    tenantWithContactForm();

    // Errors land in the submitting form's OWN bag, so a second form on the
    // page never shows them.
    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), [
        'name' => 'Mei',
        'message' => 'Call me',
    ])->assertSessionHasErrors(['email', 'phone'], errorBag: 'lead_contact');

    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), [
        'name' => 'Mei',
        'email' => 'not-an-email',
    ])->assertSessionHasErrors(['email'], errorBag: 'lead_contact');

    expect(Lead::query()->count())->toBe(0);

    // A name is optional — the low-friction surfaces never ask for one.
    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), [
        'email' => 'mei@example.com',
    ])->assertRedirect();

    expect(Lead::query()->sole()->name)->toBeNull();
});

it('silently drops a submission that filled the honeypot', function (): void {
    tenantWithContactForm();

    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), [
        'name' => 'Bot',
        'phone' => '+1 555 0100',
        '_hp' => 'http://spam.example',
    ])
        ->assertRedirect()
        ->assertSessionHas('lead_submitted', 'contact');

    expect(Lead::query()->count())->toBe(0);
});

it('ignores a stale location or page reference rather than failing the enquiry', function (): void {
    tenantWithContactForm();

    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), [
        'name' => 'Mei',
        'phone' => '+1 555 0100',
        'location_id' => 987654,
        'page_id' => 987654,
    ])->assertRedirect();

    $lead = Lead::query()->sole();

    expect($lead->location_id)->toBeNull()
        ->and($lead->page_id)->toBeNull();
});

it('rate limits a flood of submissions from one address', function (): void {
    tenantWithContactForm();

    foreach (range(1, 5) as $attempt) {
        $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), [
            'name' => 'Mei '.$attempt,
            'phone' => '+1 555 0100',
        ])->assertRedirect();
    }

    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), [
        'name' => 'Mei 6',
        'phone' => '+1 555 0100',
    ])->assertStatus(429);

    expect(Lead::query()->count())->toBe(5);
});
