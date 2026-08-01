<?php

declare(strict_types=1);

use App\Site\Blocks\SectionAlign;

it('gives every alignment a label, a description and both class treatments', function (SectionAlign $align): void {
    expect($align->label())->not->toBeEmpty()
        ->and($align->description())->not->toBeEmpty()
        // The intro always carries its reading measure and muted colour —
        // alignment travels WITH them, which is why this axis has two class
        // methods instead of one.
        ->and($align->introClasses())->toContain('site-intro')
        ->toContain('max-w-2xl')
        ->toContain('text-base-content/70')
        // classes() is the five-method contract's alias for the heading half.
        ->and($align->classes())->toBe($align->headingClasses());
})->with(SectionAlign::cases());

it('centres the heading and intro only for the centred case', function (): void {
    expect(SectionAlign::Center->headingClasses())->toBe('text-center')
        ->and(SectionAlign::Center->introClasses())->toContain('mx-auto')->toContain('text-center')
        ->and(SectionAlign::Start->headingClasses())->toBeEmpty()
        ->and(SectionAlign::Start->introClasses())->not->toContain('text-center');
});
