<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Testing\TestResponse;

/**
 * The pricing block's own view logic: the newline-separated feature lines, the
 * single-highlight rule, and the column cap.
 *
 * Its shape carries the two decisions worth guarding. `features` is a STRING
 * split on newlines rather than a nested repeater — a `plans → features` tree
 * would be the first block to nest two levels deep, which is exactly what makes
 * `BlockDataSanitizer`'s top-level-only whitelist "inert rather than
 * exploitable". And `is_featured` is honoured for at most one plan, because two
 * recommended columns recommend nothing.
 */
function renderPricing(array $data, string $subdomain = 'acme'): TestResponse
{
    $tenant = Tenant::factory()->withDomain($subdomain)->create();
    test()->createTenantBusiness($tenant, ['name' => 'Corner Cafe', 'logo_path' => null], 0);
    test()->createTenantPage($tenant, [['type' => 'pricing', 'data' => $data]]);

    return test()->get(sprintf('http://%s.%s/', $subdomain, test()->centralDomain()))->assertOk();
}

it('splits the included lines on newlines and drops the blank ones', function (): void {
    renderPricing([
        'plans' => [[
            'name' => 'Standard',
            // Mixed line endings and a stray blank line — what a paste from a
            // document actually looks like.
            'features' => "Weekly visit\r\nSame-day callout\n\n  Cancel anytime  ",
        ]],
    ])
        ->assertSee('Weekly visit')
        ->assertSee('Same-day callout')
        // Trimmed, so leading spaces from a paste do not indent the bullet.
        ->assertSeeHtml('<span>Cancel anytime</span>')
        // Three lines, not four: the blank one renders no bullet at all.
        ->assertSeeInOrder(['Weekly visit', 'Same-day callout', 'Cancel anytime']);
});

it('renders one bullet per non-blank line, however many newlines separate them', function (): void {
    $response = renderPricing(['plans' => [['name' => 'Standard', 'features' => "One\n\n\nTwo"]]]);

    expect(mb_substr_count($response->getContent(), 'aria-hidden="true">✓'))->toBe(2);
});

it('highlights only the first plan marked as recommended', function (): void {
    $response = renderPricing([
        'plans' => [
            ['name' => 'Starter'],
            ['name' => 'Standard', 'is_featured' => true],
            // An operator who toggles a second without clearing the first: the
            // likely mistake, and the view has to pick one.
            ['name' => 'Premium', 'is_featured' => true],
        ],
    ]);

    $response->assertSee('Starter')->assertSee('Standard')->assertSee('Premium');

    expect(mb_substr_count($response->getContent(), 'badge badge-primary'))->toBe(1)
        ->and(mb_substr_count($response->getContent(), 'ring-2 ring-primary'))->toBe(1);
});

it('renders no highlight at all when no plan claims one', function (): void {
    $response = renderPricing(['plans' => [['name' => 'Starter'], ['name' => 'Standard']]]);

    expect(mb_substr_count($response->getContent(), 'badge badge-primary'))->toBe(0)
        // Every button is the outline treatment when nothing is recommended.
        ->and(mb_substr_count($response->getContent(), 'btn-outline'))->toBe(0);
});

it('caps the column count so a fourth plan does not become a sliver', function (): void {
    $plans = array_map(static fn (int $i): array => ['name' => 'Plan '.$i], range(1, 4));

    renderPricing(['plans' => $plans])
        ->assertSee('Plan 4')
        // Three columns maximum; the fourth wraps onto a second row.
        ->assertSeeHtml('lg:grid-cols-3');
});

it('drops an unnamed plan before counting columns, so the real one keeps full width', function (): void {
    // A half-finished entry the operator is still typing. Skipping it in the loop
    // but counting it for the grid is the bug this pins: one card rendering at
    // half width in a two-column grid.
    $response = renderPricing(['plans' => [['name' => 'Standard'], ['price' => '$0']]]);

    $response->assertSee('Standard')
        ->assertDontSeeHtml('sm:grid-cols-2')
        ->assertDontSeeHtml('lg:grid-cols-3');

    expect(mb_substr_count($response->getContent(), 'class="card-body"'))->toBe(1);
});
