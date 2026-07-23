<?php

declare(strict_types=1);

use App\Filament\Fabricator\BlockRegistry;

it('enumerates every block contract in the vocabulary', function (): void {
    $vocabulary = BlockRegistry::vocabulary();

    expect($vocabulary)->toHaveKeys(['hero', 'heading'])
        ->and($vocabulary['hero'])->toBe([
            'type' => 'hero',
            'variants' => ['centered-minimal', 'left-text-right-image', 'full-bleed-overlay'],
            'bind' => null,
            'fields' => ['eyebrow', 'heading', 'subheading', 'cta_label', 'cta_url', 'image_url'],
        ])
        ->and($vocabulary['heading']['variants'])->toBeEmpty();
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
