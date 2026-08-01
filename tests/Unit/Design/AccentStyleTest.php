<?php

declare(strict_types=1);

use App\Design\AccentStyle;

it('paints the accent surface from palette variables only', function (AccentStyle $accent): void {
    $variables = $accent->variables();

    expect(array_keys($variables))->toBe(['--accent-surface'])
        // Palette-derived, never authored: every surface builds on the
        // primary variable, so it follows whatever palette the site uses and
        // no raw colour can enter through this enum.
        ->and($variables['--accent-surface'])->toContain('var(--color-primary)')
        ->and($accent->description())->not->toBeEmpty();
})->with(AccentStyle::cases());

it('keeps flat as a plain surface and the others as gradients', function (): void {
    expect(AccentStyle::Flat->variables()['--accent-surface'])->toBe('var(--color-primary)')
        ->and(AccentStyle::Gradient->variables()['--accent-surface'])->toStartWith('linear-gradient(')
        ->and(AccentStyle::Sheen->variables()['--accent-surface'])->toStartWith('linear-gradient(');
});
