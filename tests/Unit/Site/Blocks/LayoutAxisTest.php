<?php

declare(strict_types=1);

use App\Site\Blocks\BlockShape;
use App\Site\Blocks\LayoutAxis;
use App\Site\Blocks\SectionSpacing;
use App\Site\Blocks\SectionTone;

it('maps every axis to a value enum with the shared five-method contract', function (LayoutAxis $axis): void {
    $enum = $axis->enumClass();

    expect(enum_exists($enum))->toBeTrue()
        ->and($axis->label())->not->toBeEmpty()
        ->and($axis->values())->toBe($enum::values())
        ->and($axis->values())->not->toBeEmpty();

    foreach ($enum::cases() as $case) {
        expect($case->label())->not->toBeEmpty()
            ->and($case->description())->not->toBeEmpty();
    }
})->with(LayoutAxis::cases());

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
