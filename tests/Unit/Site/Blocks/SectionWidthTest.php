<?php

declare(strict_types=1);

use App\Site\Blocks\SectionWidth;

it('gives every width a max-width class, a label and an AI description', function (SectionWidth $width): void {
    expect($width->classes())->toStartWith('max-w-')
        ->and($width->label())->not->toBeEmpty()
        ->and($width->description())->not->toBeEmpty();
})->with(SectionWidth::cases());

it('widens monotonically from narrow to wide', function (): void {
    // The values are an ordered scale; a re-mapping that breaks the order
    // would make "wider" mean "narrower" in every chat instruction.
    expect(SectionWidth::Narrow->classes())->toBe('max-w-3xl')
        ->and(SectionWidth::Normal->classes())->toBe('max-w-5xl')
        ->and(SectionWidth::Wide->classes())->toBe('max-w-7xl');
});

it('offers the labelled options in scale order', function (): void {
    expect(array_keys(SectionWidth::options()))->toBe(SectionWidth::values())
        ->and(SectionWidth::values())->toBe(['narrow', 'normal', 'wide']);
});
