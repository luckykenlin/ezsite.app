<?php

declare(strict_types=1);

use App\Site\Blocks\BlockShape;
use App\Site\Blocks\LayoutAxis;
use App\Site\Blocks\SectionSpacing;
use App\Site\Blocks\SectionTone;

/*
 * enumClass() is seven near-identical match arms, which is the shape a
 * copy-paste error survives: two axes pointing at ONE value enum would make the
 * inspector offer align's options under "Columns" and SetBlockAppearance accept
 * them there. Distinctness is the property that catches that.
 *
 * Deliberately not `expect($axis->values())->toBe($enum::values())` — that is a
 * tautology, because LayoutAxis::values() IS `$this->enumClass()::values()`.
 */
it('gives every axis its own value enum', function (): void {
    $enums = array_map(
        static fn (LayoutAxis $axis): string => $axis->enumClass(),
        LayoutAxis::cases(),
    );

    expect(array_unique($enums))->toHaveSameSize($enums);
});

/*
 * The axis VALUES are the storage keys inside data.appearance, so the two
 * pre-existing key constants must stay equal to their axis — a drift here
 * would silently split tone into two different keys.
 */
it('agrees with the BlockShape key constants on the storage key names', function (): void {
    expect(LayoutAxis::Tone->value)->toBe(BlockShape::TONE_KEY)
        ->and(LayoutAxis::Spacing->value)->toBe(BlockShape::SPACING_KEY);
});

it('resolves stored values through tryFrom and refuses everything else', function (): void {
    expect(LayoutAxis::Tone->resolve('inverted'))->toBe(SectionTone::Inverted)
        ->and(LayoutAxis::Spacing->resolve('tall'))->toBe(SectionSpacing::Tall)
        ->and(LayoutAxis::Columns->resolve('five'))->toBeNull()
        ->and(LayoutAxis::Width->resolve(['wide']))->toBeNull()
        ->and(LayoutAxis::Align->resolve(null))->toBeNull();
});

it('lists exactly the five parametric axes as the extension set', function (): void {
    expect(LayoutAxis::extended())->toBe([
        LayoutAxis::Width,
        LayoutAxis::Align,
        LayoutAxis::Columns,
        LayoutAxis::ItemStyle,
        LayoutAxis::ImageShape,
    ]);
});
