<?php

declare(strict_types=1);

use App\Enums\BindType;
use App\Enums\ChromeSlot;
use App\Filament\Fabricator\BlockRegistry;
use App\Models\Business;
use App\Models\Location;
use App\Models\Tenant;
use App\Site\Blocks\BlockIntent;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;

it('enumerates every block contract in the vocabulary', function (): void {
    $vocabulary = resolve(BlockVocabulary::class)->all();

    expect($vocabulary)->toHaveKeys([
        'hero', 'heading', 'header', 'features', 'testimonials', 'gallery', 'cta', 'contact', 'signup', 'footer',
    ])
        ->and($vocabulary['signup']->variants)->toBe(['banner', 'stacked'])
        ->and($vocabulary['signup']->bind)->toBeNull()
        ->and($vocabulary['signup']->intent)->toBe(BlockIntent::Convert)
        ->and($vocabulary['hero']->variants)->toBe(['centered-minimal', 'left-text-right-image', 'full-bleed-overlay', 'full-viewport-quiet'])
        ->and($vocabulary['hero']->bind)->toBeNull()
        ->and($vocabulary['hero']->icon)->toBe('o-sparkles')
        ->and($vocabulary['hero']->fields)
        ->toBe(['eyebrow', 'heading', 'subheading', 'cta_label', 'cta_url', 'image_id', 'image_url'])
        ->and($vocabulary['heading']->variants)->toBeEmpty()
        // Every block declares an editor icon.
        ->and(array_filter($vocabulary, fn (BlockType $contract): bool => $contract->icon === null))->toBeEmpty()
        // …and says what it is FOR. Without it the assistant separates
        // look-alike containers (features / testimonials / offerings are all
        // "heading + repeater of titled items") by name alone and quietly picks
        // wrong — a failure that shows up nowhere else, which is why it is
        // asserted rather than reviewed.
        ->and(array_keys(array_filter($vocabulary, fn (BlockType $contract): bool => $contract->description === '')))->toBeEmpty()
        // Every block ships sample content, and its keys stay within the
        // block's declared fields (a typo here would silently drop content).
        ->and(array_filter(
            $vocabulary,
            fn (BlockType $contract): bool => $contract->sample === []
                || array_diff(array_keys($contract->sample), $contract->fields) !== [],
        ))->toBeEmpty()
        ->and($vocabulary['contact']->bind)->toBe(BindType::Location)
        ->and($vocabulary['header']->bind)->toBe(BindType::Business)
        ->and($vocabulary['footer']->bind)->toBe(BindType::Location)
        // The factual blocks above are the ONLY bound ones; every other block
        // is content-only and must declare no bind.
        ->and(array_keys(array_filter($vocabulary, fn (BlockType $contract): bool => $contract->bind instanceof BindType)))
        ->toEqualCanonicalizing(['header', 'contact', 'footer']);
});

/*
 * The library groups by intent, and a page type without one is silently
 * dropped from the modal — a block that quietly cannot be added, with no
 * failure anywhere else. Chrome is the opposite: it is never in the library,
 * so an intent there would suggest a grouping that does not exist.
 */
it('gives every page block a library intent, and chrome none', function (): void {
    $vocabulary = resolve(BlockVocabulary::class);

    expect(array_keys(array_filter(
        $vocabulary->pageTypes(),
        fn (BlockType $contract): bool => ! $contract->intent instanceof BlockIntent,
    )))->toBeEmpty()
        ->and($vocabulary->get('header')?->intent)->toBeNull()
        ->and($vocabulary->get('footer')?->intent)->toBeNull();
});

it('separates the page-level types from site chrome', function (): void {
    $vocabulary = resolve(BlockVocabulary::class);

    expect($vocabulary->pageTypeNames())
        ->not->toContain(...ChromeSlot::values())
        ->and(array_keys($vocabulary->all()))->toContain(...ChromeSlot::values())
        ->and($vocabulary->get('header')?->isChrome())->toBeTrue()
        ->and($vocabulary->get('hero')?->isChrome())->toBeFalse()
        ->and($vocabulary->isAddableToPage('hero'))->toBeTrue()
        ->and($vocabulary->isAddableToPage('header'))->toBeFalse()
        // An unknown type is not addable either, and does not blow up.
        ->and($vocabulary->isAddableToPage('carousel'))->toBeFalse()
        ->and($vocabulary->has('carousel'))->toBeFalse()
        ->and($vocabulary->get('carousel'))->toBeNull();
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

/*
 * The media-slot contract SetBlockImage places through, derived from each
 * block's own form schema (a CuratorPicker top-level or one repeater deep) so
 * a future block gets it for free. Pinned per type because a derivation bug
 * here silently strips the assistant of image placement on that type.
 */
it('derives where every image-bearing type keeps its media ids', function (): void {
    $contracts = BlockRegistry::contracts();

    $slots = collect($contracts)
        ->filter(fn ($type): bool => $type->acceptsMedia())
        ->map(fn ($type): array => [$type->mediaField, $type->itemsField, $type->itemMediaField])
        ->all();

    expect($slots)->toBe([
        'cta' => ['image_id', null, null],
        'features' => [null, 'features', 'image_id'],
        'gallery' => [null, 'images', 'media_id'],
        'hero' => ['image_id', null, null],
        'logos' => [null, 'logos', 'media_id'],
        'offerings' => [null, 'items', 'image_id'],
        'team' => [null, 'members', 'avatar_media_id'],
        'testimonials' => [null, 'testimonials', 'avatar_media_id'],
    ]);

    // And a type with no picker anywhere reports no slot at all.
    expect($contracts['heading']->acceptsMedia())->toBeFalse()
        ->and($contracts['heading']->mediaField)->toBeNull()
        ->and($contracts['heading']->itemMediaField)->toBeNull();
});
