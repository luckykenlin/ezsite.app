<?php

declare(strict_types=1);

use App\Site\Blocks\SectionItemStyle;
use App\Site\Blocks\SectionTone;

it('gives every style a label, a description and a card verdict', function (SectionItemStyle $style): void {
    expect($style->label())->not->toBeEmpty()
        ->and($style->description())->not->toBeEmpty()
        ->and($style->isCard())->toBe($style !== SectionItemStyle::Plain);
})->with(SectionItemStyle::cases());

/*
 * The tone-aware half is the axis's reason to exist: a card surface chosen
 * without looking at the band behind it is how cards vanish. Every tone must
 * yield a card surface DIFFERENT from the tone's own background.
 */
it('lifts a card off every band it can sit on', function (SectionTone $tone): void {
    $card = SectionItemStyle::Card->classes($tone);

    expect($card)->toStartWith('card')
        ->and($tone->itemSurface())->not->toBeEmpty();

    if ($tone->classes() !== '' && $tone !== SectionTone::Accent) {
        // The card's surface never repeats the band's own background class.
        $band = explode(' ', $tone->classes())[0];

        expect($card)->not->toContain($band);
    }
})->with(SectionTone::cases());

it('re-asserts a readable foreground on the dark and accent bands', function (): void {
    // The section paints light-on-dark text; a light card must flip it back.
    expect(SectionTone::Inverted->itemSurface())->toContain('text-base-content')
        ->and(SectionTone::Accent->itemSurface())->toContain('text-base-content')
        ->and(SectionTone::Muted->itemSurface())->toBe('bg-base-100')
        ->and(SectionTone::Base->itemSurface())->toBe('bg-base-200');
});

it('paints nothing for plain and a thin border for outline', function (): void {
    expect(SectionItemStyle::Plain->classes(SectionTone::Base))->toBe('')
        ->and(SectionItemStyle::Outline->classes(SectionTone::Base))->toBe('card border border-base-300');
});
