<?php

declare(strict_types=1);

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

/*
 * Narrowly about the AUTOMATIC fill, which is the whole subtlety: a type with no
 * media slot at all can never take one, and a variant designed around the
 * absence of a photograph must not be handed one on a guess — but neither is a
 * veto, because an operator choosing an image is expressing an intent and every
 * such variant renders a chosen image gracefully.
 */
it('refuses an unasked photograph only where the layout is built without one', function (): void {
    $photographic = new BlockType(
        type: 'hero',
        description: 'A test double.',
        variants: ['with-photo', 'type-only'],
        bind: null,
        icon: null,
        fields: ['heading', 'image_id'],
        sample: [],
        mediaField: 'image_id',
        imagelessVariants: ['type-only'],
    );

    expect($photographic->wantsAutoImage('with-photo'))->toBeTrue()
        ->and($photographic->wantsAutoImage('type-only'))->toBeFalse()
        // An unstored variant renders the type's default, which is the first
        // declared one — photographic here, so the fill still applies.
        ->and($photographic->wantsAutoImage(null))->toBeTrue()
        // A type with nowhere to put an image never wants one, whatever the
        // variant: there is no slot for the fill to land in.
        ->and(blockType()->wantsAutoImage('a'))->toBeFalse();
});
