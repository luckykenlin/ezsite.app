<?php

declare(strict_types=1);

use App\Site\Blocks\SectionAlign;

it('gives every alignment both class treatments', function (SectionAlign $align): void {
    // The intro always carries its reading measure and muted colour —
    // alignment travels WITH them, which is why this axis has two class
    // methods instead of one.
    //
    // `site-dim`, never a `text-base-content` literal: the intro has to read on
    // an accent or inverted band too, and those surfaces set their own `color`
    // for it to dim from.
    expect($align->introClasses())->toContain('site-intro')
        ->toContain('max-w-2xl')
        ->toContain('site-dim')
        ->not->toContain('text-base-content')
        // classes() is the five-method contract's alias for the heading half.
        ->and($align->classes())->toBe($align->headingClasses());
})->with(SectionAlign::cases());

it('centres the heading and intro only for the centred case', function (): void {
    expect(SectionAlign::Center->headingClasses())->toBe('text-center')
        ->and(SectionAlign::Center->introClasses())->toContain('mx-auto')->toContain('text-center')
        ->and(SectionAlign::Start->headingClasses())->toBeEmpty()
        ->and(SectionAlign::Start->introClasses())->not->toContain('text-center');
});
