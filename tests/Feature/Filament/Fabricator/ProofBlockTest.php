<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Testing\TestResponse;

/**
 * View logic for the team block — the "proof" block that generates something
 * the operator never stores (initials) and refuses to render an incomplete
 * item. Cross-block behaviour that every type shares stays in BlockRenderTest.
 */
function renderProofBlock(string $type, array $data, string $subdomain = 'acme'): TestResponse
{
    $tenant = Tenant::factory()->withDomain($subdomain)->create();
    test()->createTenantBusiness($tenant, ['name' => 'Corner Cafe', 'logo_path' => null], 0);
    test()->createTenantPage($tenant, [['type' => $type, 'data' => $data]]);

    return test()->get(sprintf('http://%s.%s/', $subdomain, test()->centralDomain()))->assertOk();
}

/*
 * Initials rather than a broken image frame: a team block is worth adding before
 * anyone has uploaded headshots, and an empty <img> is worse than no <img>.
 */
it('falls back to an initial when a team member has no photo', function (): void {
    renderProofBlock('team', ['members' => [['name' => 'dana reed', 'role' => 'Head baker']]])
        // Upper-cased from a lower-case name, via mb_* so non-ASCII names work.
        ->assertSeeHtml('>D<')
        ->assertSee('Head baker')
        ->assertDontSeeHtml('<img');
});

it('renders a photo instead of initials when one resolves', function (): void {
    renderProofBlock('team', [
        'members' => [['name' => 'Dana Reed', 'avatar_url' => 'https://example.com/dana.jpg']],
    ])
        ->assertSee('https://example.com/dana.jpg')
        // Decorative: the name below is the accessible label, so an alt here
        // would be announced twice.
        ->assertSeeHtml('alt=""');
});

it('skips a team member with no name', function (): void {
    $response = renderProofBlock('team', [
        'members' => [['name' => 'Dana Reed'], ['role' => 'Nobody in particular']],
    ]);

    $response->assertSee('Dana Reed')->assertDontSee('Nobody in particular');
});
