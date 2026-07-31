<?php

declare(strict_types=1);

use App\Enums\BindType;
use App\Site\Blocks\BlockType;

function blockType(string $type = 'hero', array $variants = ['a', 'b', 'c']): BlockType
{
    return new BlockType(
        type: $type,
        description: 'A test double.',
        variants: $variants,
        bind: null,
        icon: null,
        fields: ['heading'],
        sample: ['heading' => 'Hi'],
    );
}

it('treats the first declared variant as the default', function (): void {
    // The single definition of a fallback that used to be re-derived in four
    // places, so "first declared wins" is worth pinning explicitly.
    expect(blockType()->defaultVariant())->toBe('a')
        ->and(blockType(variants: ['only'])->defaultVariant())->toBe('only')
        ->and(blockType(variants: [])->defaultVariant())->toBeNull();
});

it('knows which types are site chrome', function (string $type, bool $isChrome): void {
    expect(blockType($type)->isChrome())->toBe($isChrome);
})->with([
    'header' => ['header', true],
    'footer' => ['footer', true],
    'hero' => ['hero', false],
    'heading' => ['heading', false],
    'an unknown type is not chrome' => ['carousel', false],
]);

it('resolves a stored variant, falling back only when one was never chosen', function (mixed $stored, ?string $expected): void {
    expect(blockType()->resolveVariant($stored))->toBe($expected);
})->with([
    'a known variant is kept' => ['b', 'b'],
    'the default variant is kept' => ['a', 'a'],
    // Absent means "never chosen" -> fall back. Present-but-wrong means the
    // stored data is bad, and null tells the caller to refuse to render rather
    // than silently show a different layout than the operator picked.
    'null falls back to the default' => [null, 'a'],
    'an empty string falls back to the default' => ['', 'a'],
    'a non-string falls back to the default' => [42, 'a'],
    'an unrecognised variant is refused' => ['nope', null],
]);

it('has no variant to resolve when the type declares none', function (): void {
    // A variant-less block renders one view; anything stored under the variant
    // key is meaningless rather than wrong.
    $type = blockType(variants: []);

    expect($type->resolveVariant(null))->toBeNull()
        ->and($type->resolveVariant('anything'))->toBeNull();
});

it('carries the bind target as an enum rather than a string', function (): void {
    // The contract used to expose `bind` as a nullable string, so every consumer
    // re-derived the enum (or compared strings and got it subtly wrong).
    $bound = new BlockType(
        type: 'contact',
        description: 'A test double.',
        variants: [],
        bind: BindType::Location,
        icon: 'o-map-pin',
        fields: [],
        sample: [],
    );

    expect($bound->bind)->toBe(BindType::Location)
        ->and(blockType()->bind)->toBeNull();
});
