<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Testing\TestResponse;

/**
 * The offerings block's own view logic, almost all of it the `menu-card`
 * variant's: the single featured spotlight, the strict booleans an AI-written
 * flag must clear, the legend that only lists marks the menu uses, and the
 * course tabs that stay server-hidden so a visitor without JavaScript never
 * sees dead chrome. Variant→view routing itself is BlockRenderTest's job.
 */
function renderOfferings(array $data, string $subdomain = 'acme'): TestResponse
{
    $tenant = Tenant::factory()->withDomain($subdomain)->create();
    test()->createTenantPage($tenant, [['type' => 'offerings', 'data' => $data]]);

    return test()->get(sprintf('http://%s.%s/', $subdomain, test()->centralDomain()))->assertOk();
}

it('spotlights the first featured item without repeating it in its course', function (): void {
    $response = renderOfferings([
        'variant' => 'menu-card',
        'items' => [
            ['group' => 'Mains', 'name' => 'Peking Duck', 'price' => '$45', 'is_featured' => true],
            ['group' => 'Mains', 'name' => 'Mapo Tofu', 'price' => '$16'],
        ],
    ]);

    $response->assertSee('site-menu-feature', false)->assertSee('Mapo Tofu');

    // Once in the spotlight, and NOT a second time in the course grid.
    expect(mb_substr_count($response->getContent(), 'Peking Duck'))->toBe(1);
});

it('does not spotlight a featured flag that is not literally true', function (): void {
    // The likely AI mistake: "true" as a string. Strict === true must read it
    // as unflagged, leaving the dish in its course rather than promoting it.
    $response = renderOfferings([
        'variant' => 'menu-card',
        'items' => [
            ['group' => 'Mains', 'name' => 'Peking Duck', 'price' => '$45', 'is_featured' => 'true'],
        ],
    ]);

    $response->assertDontSee('site-menu-feature', false)->assertSee('Peking Duck');
});

it('marks dishes with the dietary flags they carry, in both variants', function (string $variant): void {
    renderOfferings([
        'variant' => $variant,
        'items' => [
            ['name' => 'Mapo Tofu', 'price' => '$16', 'spicy' => true, 'gluten_free' => true],
        ],
    ])
        ->assertSee('site-menu-flag--spicy', false)
        ->assertSee('site-menu-flag--gluten-free', false)
        ->assertDontSee('site-menu-flag--vegetarian', false);
})->with(['simple', 'menu-card']);

it('renders the legend only for the marks the menu actually uses', function (): void {
    renderOfferings([
        'variant' => 'menu-card',
        'items' => [
            ['name' => 'Scallion Pancakes', 'price' => '$6', 'vegetarian' => true],
        ],
    ])
        ->assertSee('site-menu-legend', false)
        ->assertSee('vegetarian')
        ->assertDontSee('gluten free');
});

it('renders no legend on a menu with no marks at all', function (): void {
    renderOfferings([
        'variant' => 'menu-card',
        'items' => [['name' => 'Wonton Soup', 'price' => '$10']],
    ])->assertDontSee('site-menu-legend', false);
});

it('ships the course tabs server-hidden, one per course plus All', function (): void {
    // JS (resources/js/site/menu-filter.ts) unhides them; without it the full
    // menu below is the degradation, so the bar must arrive `hidden`.
    renderOfferings([
        'variant' => 'menu-card',
        'items' => [
            ['group' => 'Dim sum', 'name' => 'Har Gow', 'price' => '$8'],
            ['group' => 'Soups', 'name' => 'Wonton Soup', 'price' => '$10'],
        ],
    ])
        ->assertSee('data-menu-tabs hidden', false)
        ->assertSee('data-menu-tab=""', false)
        ->assertSee('data-menu-tab="Dim sum"', false)
        ->assertSee('data-menu-tab="Soups"', false);
});

it('renders no tab bar when there is only one course to filter', function (): void {
    renderOfferings([
        'variant' => 'menu-card',
        'items' => [
            ['group' => 'Dim sum', 'name' => 'Har Gow', 'price' => '$8'],
            ['group' => 'Dim sum', 'name' => 'Siu Mai', 'price' => '$8'],
        ],
    ])->assertDontSee('data-menu-tabs', false);
});

it('tags the unlabelled course with an empty group so the filter can hide it', function (): void {
    // An item with no group renders in an anonymous section that belongs to
    // "All" only — the hook must exist for menu-filter.ts to reach it.
    renderOfferings([
        'variant' => 'menu-card',
        'items' => [
            ['name' => 'House Tea', 'price' => '$4'],
            ['group' => 'Dim sum', 'name' => 'Har Gow', 'price' => '$8'],
            ['group' => 'Soups', 'name' => 'Wonton Soup', 'price' => '$10'],
        ],
    ])->assertSee('data-menu-group=""', false);
});
