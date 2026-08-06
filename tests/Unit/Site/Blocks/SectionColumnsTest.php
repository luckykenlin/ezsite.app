<?php

declare(strict_types=1);

use App\Site\Blocks\SectionColumns;

it('degrades every column count responsively down to one on phones', function (SectionColumns $columns): void {
    expect($columns->classes())->toStartWith('grid-cols-1');
})->with(SectionColumns::cases());

it('passes multi-column layouts through two columns on the way up', function (): void {
    // A 3- or 4-column grid that jumps straight from 1 to N leaves tablets
    // with a single skinny column; the sm step is the difference.
    expect(SectionColumns::Three->classes())->toContain('sm:grid-cols-2')->toContain('lg:grid-cols-3')
        ->and(SectionColumns::Four->classes())->toContain('sm:grid-cols-2')->toContain('lg:grid-cols-4')
        ->and(SectionColumns::Two->classes())->toBe('grid-cols-1 sm:grid-cols-2')
        // One is what lets a list-style variant merge into its grid sibling.
        ->and(SectionColumns::One->classes())->toBe('grid-cols-1');
});
