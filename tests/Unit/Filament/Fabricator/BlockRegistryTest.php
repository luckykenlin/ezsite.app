<?php

declare(strict_types=1);

use App\Filament\Fabricator\BlockRegistry;
use App\Models\Business;
use App\Models\Location;
use App\Models\Tenant;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

it('enumerates every block contract in the vocabulary', function (): void {
    $vocabulary = BlockRegistry::vocabulary();

    expect($vocabulary)->toHaveKeys([
        'hero', 'heading', 'header', 'features', 'testimonials', 'gallery', 'cta', 'contact', 'footer',
    ])
        ->and($vocabulary['hero'])->toBe([
            'type' => 'hero',
            'variants' => ['centered-minimal', 'left-text-right-image', 'full-bleed-overlay'],
            'bind' => null,
            'icon' => 'o-sparkles',
            'fields' => ['eyebrow', 'heading', 'subheading', 'cta_label', 'cta_url', 'image_id', 'image_url'],
        ])
        ->and($vocabulary['heading']['variants'])->toBeEmpty()
        // Every block declares an editor icon.
        ->and(array_filter($vocabulary, fn (array $contract): bool => $contract['icon'] === null))->toBeEmpty()
        // Every block ships sample content, and its keys stay within the
        // block's declared fields (a typo here would silently drop content).
        ->and(array_filter($vocabulary, function (array $contract): bool {
            $class = FilamentFabricator::getPageBlockFromName($contract['type']);
            if ($class::sample() === []) {
                return true;
            }

            return array_diff(array_keys($class::sample()), $contract['fields']) !== [];
        }))->toBeEmpty()
        ->and($vocabulary['contact']['bind'])->toBe('location')
        ->and($vocabulary['header']['bind'])->toBe('business')
        ->and($vocabulary['footer']['bind'])->toBe('location')
        // The factual blocks above are the ONLY bound ones; every other block
        // is content-only and must declare no bind.
        ->and(array_keys(array_filter($vocabulary, fn (array $contract): bool => $contract['bind'] !== null)))
        ->toEqualCanonicalizing(['header', 'contact', 'footer']);
});

it('resolves a valid variant to its component', function (): void {
    expect(BlockRegistry::resolveComponent([
        'type' => 'hero',
        'data' => ['variant' => 'left-text-right-image'],
    ]))->toBe('filament-fabricator.page-blocks.hero.left-text-right-image');
});

it('falls back to the default variant when none is stored', function (): void {
    expect(BlockRegistry::resolveComponent([
        'type' => 'hero',
        'data' => ['heading' => 'Hi'],
    ]))->toBe('filament-fabricator.page-blocks.hero.centered-minimal');
});

it('rejects an unrecognised variant', function (): void {
    expect(BlockRegistry::resolveComponent([
        'type' => 'hero',
        'data' => ['variant' => 'does-not-exist'],
    ]))->toBeNull();
});

it('resolves a no-variant block to its bare component', function (): void {
    expect(BlockRegistry::resolveComponent([
        'type' => 'heading',
        'data' => ['content' => 'Hi'],
    ]))->toBe('filament-fabricator.page-blocks.heading');
});

it('returns null for an unknown or malformed type', function (array $block): void {
    expect(BlockRegistry::resolveComponent($block))->toBeNull();
})->with([
    'unknown type' => [['type' => 'nope', 'data' => []]],
    'missing type' => [['data' => ['heading' => 'x']]],
    'empty type' => [['type' => '', 'data' => []]],
]);

it('normalizes data: backfills the default variant', function (): void {
    expect(BlockRegistry::normalizeData([
        'type' => 'hero',
        'data' => ['heading' => 'Hi'],
    ]))->toBe(['heading' => 'Hi', 'variant' => 'centered-minimal']);
});

it('normalizes data: tolerates a non-array data payload', function (): void {
    expect(BlockRegistry::normalizeData([
        'type' => 'hero',
        'data' => 'oops',
    ]))->toBe(['variant' => 'centered-minimal']);
});

it('returns no bind attributes for a block without a bind declaration', function (): void {
    expect(BlockRegistry::bindAttributes(['type' => 'hero', 'data' => []]))->toBeEmpty()
        ->and(BlockRegistry::bindAttributes(['type' => 'heading', 'data' => []]))->toBeEmpty();
});

it('returns no bind attributes for an unknown or malformed type', function (array $block): void {
    expect(BlockRegistry::bindAttributes($block))->toBeEmpty();
})->with([
    'unknown type' => [['type' => 'ghost', 'data' => []]],
    'missing type' => [['data' => []]],
]);

it('injects the business into a Business-bound block', function (): void {
    $tenant = Tenant::factory()->create();
    $business = $this->createTenantBusiness($tenant, [], 0);

    $attributes = BlockRegistry::bindAttributes(['type' => 'header', 'data' => []]);

    expect($attributes)->toHaveKeys(['business'])
        ->and($attributes)->not->toHaveKey('location')
        ->and($attributes['business']->is(Business::query()->findOrFail($business->getKey())))->toBeTrue();
});

it('injects the business and primary location into a Location-bound block without a stored id', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 2);
    $primary = Location::query()->where('is_primary', true)->firstOrFail();

    $attributes = BlockRegistry::bindAttributes(['type' => 'contact', 'data' => []]);

    expect($attributes)->toHaveKeys(['business', 'location'])
        ->and($attributes['location']->is($primary))->toBeTrue();
});

it('injects the explicitly bound location, tolerating the string ids Filament selects dehydrate', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 2);
    $secondary = Location::query()->where('is_primary', false)->firstOrFail();

    $attributes = BlockRegistry::bindAttributes([
        'type' => 'contact',
        'data' => ['bind' => ['location_id' => (string) $secondary->id]],
    ]);

    expect($attributes['location']->is($secondary))->toBeTrue();
});

it('treats a malformed stored bind as unset and resolves the primary location', function (mixed $bind): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 2);
    $primary = Location::query()->where('is_primary', true)->firstOrFail();

    $attributes = BlockRegistry::bindAttributes([
        'type' => 'contact',
        'data' => ['bind' => $bind],
    ]);

    expect($attributes['location']->is($primary))->toBeTrue();
})->with([
    'non-array bind' => ['oops'],
    'non-numeric id' => [['location_id' => 'abc']],
    'missing id key' => [[]],
]);

it('returns null for a bound block when the tenant has no business', function (string $type): void {
    expect(BlockRegistry::bindAttributes(['type' => $type, 'data' => []]))->toBeNull();
})->with(['header', 'contact']);

it('returns null for a Location-bound block when the business has no locations', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 0);

    expect(BlockRegistry::bindAttributes(['type' => 'contact', 'data' => []]))->toBeNull();
});
