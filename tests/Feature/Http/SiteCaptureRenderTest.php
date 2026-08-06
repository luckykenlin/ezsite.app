<?php

declare(strict_types=1);

use App\Actions\Pages\CachePageEditorPreview;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\RunInTenant;

/**
 * A tenant with a home page and the given `site_settings.capture` payload.
 */
function tenantWithCapture(array $capture, array $locationAttributes = []): Tenant
{
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $business = test()->createTenantBusiness($tenant, [
        'name' => 'QQ Nail',
        'contact_phone' => '+1 555 000 1111',
    ], 0);

    test()->runInTenant($tenant, fn (): Location => Location::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'is_primary' => true,
        ...$locationAttributes,
    ]));

    test()->runInTenant($tenant, fn (): SiteSetting => SiteSetting::query()->create([
        'tenant_id' => $tenant->id,
        'capture' => $capture,
    ]));

    test()->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    return $tenant;
}

/**
 * The popup, switched on with sensible copy.
 */
function enabledPopup(array $overrides = []): array
{
    return ['popup' => [
        'enabled' => true,
        'heading' => 'Get 10% off',
        'offer' => 'Join the list and we will email your voucher.',
        'fields' => 'email',
        'button_label' => 'Send it',
        'trigger' => 'delay',
        'trigger_value' => 8,
        'frequency_days' => 14,
        ...$overrides,
    ]];
}

it('renders the popup as a dialog carrying its trigger config', function (): void {
    tenantWithCapture(enabledPopup());

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('data-site-popup', false)
        ->assertSee('data-popup-trigger="delay"', false)
        ->assertSee('data-popup-value="8"', false)
        ->assertSee('data-popup-frequency="14"', false)
        ->assertSee('Get 10% off')
        ->assertSee('Send it')
        ->assertSee('id="lead-popup"', false)
        ->assertSee('name="source" value="popup"', false);
});

it('leaves the popup out entirely when it is switched off', function (): void {
    tenantWithCapture(enabledPopup(['enabled' => false]));

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee('data-site-popup', false)
        ->assertDontSee('Get 10% off');
});

it('refuses to render a popup with nothing to say', function (): void {
    // Switched on but never written: an empty modal is worse than none, so
    // the heading is what actually gates it.
    tenantWithCapture(enabledPopup(['heading' => '  ']));

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee('data-site-popup', false);
});

it('never puts the popup or the call bar on the editor canvas', function (): void {
    // A modal the operator cannot dismiss, and a fixed bar over the block they
    // are editing, would both be bugs. The canvas also gets the marker that
    // tells resources/js/site.ts to stand down.
    $tenant = tenantWithCapture([
        ...enabledPopup(),
        'call_bar' => ['enabled' => true],
    ]);
    $this->actingAsThroughSession(User::factory()->create());

    $page = $this->runInTenant($tenant, fn (): Page => Page::query()->firstOrFail());

    resolve(RunInTenant::class)->handle(
        $tenant,
        fn () => resolve(CachePageEditorPreview::class)->handle($page, [
            ['key' => 'k1', 'type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Draft']],
        ], 'valid-token'),
    );

    $this->get(sprintf('http://acme.%s/_editor/preview?token=valid-token', $this->centralDomain()))
        ->assertOk()
        ->assertSee('data-editor-canvas', false)
        ->assertDontSee('data-site-popup', false)
        ->assertDontSee('data-call-bar', false);
});

it('captures a popup submission as its own source', function (): void {
    tenantWithCapture(enabledPopup());

    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), [
        'form_id' => 'popup',
        'source' => 'popup',
        'email' => 'mei@example.com',
    ])
        ->assertRedirect()
        ->assertSessionHas('lead_submitted', 'popup');

    $lead = Lead::query()->sole();

    expect($lead->source->value)->toBe('popup')
        ->and($lead->email)->toBe('mei@example.com')
        ->and($lead->name)->toBeNull();
});

it('answers an enhanced submit with JSON instead of a redirect', function (): void {
    // What lets the popup succeed without a reload — a redirect would close it.
    tenantWithCapture(enabledPopup());

    $this->postJson(sprintf('http://acme.%s/_leads', $this->centralDomain()), [
        'form_id' => 'popup',
        'source' => 'popup',
        'email' => 'mei@example.com',
    ])
        ->assertOk()
        ->assertExactJson(['ok' => true, 'form_id' => 'popup']);

    expect(Lead::query()->count())->toBe(1);
});

it('answers an invalid enhanced submit with field errors', function (): void {
    tenantWithCapture(enabledPopup());

    $this->postJson(sprintf('http://acme.%s/_leads', $this->centralDomain()), [
        'form_id' => 'popup',
        'source' => 'popup',
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => ['email', 'phone']]);

    expect(Lead::query()->count())->toBe(0);
});

it('renders the call bar with the location phone and a spacer', function (): void {
    tenantWithCapture(
        ['call_bar' => ['enabled' => true, 'label' => 'Call the salon']],
        ['phone' => '+1 555 222 3333'],
    );

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('href="tel:+1 555 222 3333"', false)
        ->assertSee('Call the salon')
        // The bar is fixed, so the page owes the footer its height back.
        ->assertSee('h-16 sm:hidden', false);
});

it('falls back to the business phone when the location has none', function (): void {
    tenantWithCapture(
        ['call_bar' => ['enabled' => true]],
        ['phone' => null],
    );

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('href="tel:+1 555 000 1111"', false)
        ->assertSee('Call now');
});

it('offers the popup button from the call bar only when there is a popup', function (bool $withPopup): void {
    // Asked for identically both times; the difference is whether there is
    // anything for the button to open. A button that does nothing is worse
    // than no button.
    tenantWithCapture([
        ...($withPopup ? enabledPopup() : []),
        'call_bar' => ['enabled' => true, 'show_popup_button' => true],
    ]);

    $response = $this->get(sprintf('http://acme.%s/', $this->centralDomain()))->assertOk();

    $withPopup
        ? $response->assertSee('data-call-bar-popup', false)
        : $response->assertDontSee('data-call-bar-popup', false);
})->with([
    'with a popup to open' => true,
    'with no popup at all' => false,
]);

it('renders no call bar for a business with no phone number', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $business = $this->createTenantBusiness($tenant, ['contact_phone' => null], 0);
    $this->runInTenant($tenant, fn (): Location => Location::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'is_primary' => true,
        'phone' => null,
    ]));
    $this->runInTenant($tenant, fn (): SiteSetting => SiteSetting::query()->create([
        'tenant_id' => $tenant->id,
        'capture' => ['call_bar' => ['enabled' => true]],
    ]));
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee('data-call-bar-popup', false)
        ->assertDontSee('href="tel:', false);
});
