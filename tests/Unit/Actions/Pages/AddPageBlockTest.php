<?php

declare(strict_types=1);

use App\Actions\Pages\AddPageBlock;
use App\Enums\ChromeSlot;

it('appends a block with its default variant and sample content pre-filled', function (): void {
    $result = resolve(AddPageBlock::class)->handle([], 'hero');

    expect($result['blocks'])->toHaveCount(1)
        ->and($result['blocks'][0]['key'])->toBe($result['key'])
        ->and($result['blocks'][0]['type'])->toBe('hero')
        // Literals, not `['variant' => Hero::defaultVariant()] + Hero::sample()`:
        // that restates the action, and would stay green if the registry stopped
        // propagating sample at all — both sides would go empty together.
        ->and($result['blocks'][0]['data']['variant'])->toBe('centered-minimal')
        ->and(array_keys($result['blocks'][0]['data']))
        ->toBe(['variant', 'eyebrow', 'heading', 'subheading', 'cta_label', 'cta_url']);
});

it('appends a variant-less block with its sample content', function (): void {
    $result = resolve(AddPageBlock::class)->handle([], 'heading');

    // No variant key at all, and the sample lands whole.
    expect($result['blocks'][0]['data'])->toBe(['content' => 'Your section heading', 'level' => 'h2']);
});

it('inserts at an explicit position and clamps out-of-range positions', function (int $position, int $expectedIndex): void {
    $blocks = [
        ['key' => 'a', 'type' => 'heading', 'data' => []],
        ['key' => 'b', 'type' => 'heading', 'data' => []],
    ];

    $result = resolve(AddPageBlock::class)->handle($blocks, 'cta', $position);

    expect(array_column($result['blocks'], 'type'))->toHaveCount(3)
        ->and($result['blocks'][$expectedIndex]['type'])->toBe('cta')
        ->and($result['blocks'][$expectedIndex]['key'])->toBe($result['key'])
        // Keys stay unique: canvas selection addresses blocks by key.
        ->and($result['key'])->not->toBeIn(['a', 'b']);
})->with([
    'at the start' => [0, 0],
    'in the middle' => [1, 1],
    'past the end clamps to the end' => [9, 2],
    'negative clamps to the start' => [-3, 0],
]);

it('throws loudly on a block type that cannot go in a page', function (string $type): void {
    // Site chrome is a registered Block and would otherwise pass the
    // is_subclass_of check, landing a second header inside one page's body.
    expect(fn (): array => resolve(AddPageBlock::class)->handle([], $type))
        ->toThrow(InvalidArgumentException::class, sprintf('Block type [%s] cannot be added to a page.', $type));
})->with([
    'an unknown type' => ['carousel'],
    ...array_map(static fn (string $slot): array => [$slot], ChromeSlot::values()),
]);
