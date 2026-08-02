<?php

declare(strict_types=1);

use App\Design\TypeStyle;

it('emits the full deterministic --type-* variable set for every style', function (TypeStyle $style): void {
    $variables = $style->variables();

    expect(array_keys($variables))->toBe([
        '--type-scale',
        '--type-display-weight',
        '--type-display-tracking',
        '--type-heading-weight',
        '--type-heading-tracking',
        '--type-heading-case',
        '--type-subheading-weight',
        '--type-eyebrow-size',
        '--type-eyebrow-weight',
        '--type-eyebrow-case',
        '--type-eyebrow-tracking',
        '--type-intro-size',
    ]);

    // Structural sanity per case: the scale is a number close to 1, weights
    // are the 500–800 stops the self-hosted fonts ship, cases are valid
    // text-transform values, and tracked sizes are rem lengths.
    expect((float) $variables['--type-scale'])->toBeGreaterThan(0.8)->toBeLessThan(1.3);

    foreach (['--type-display-weight', '--type-heading-weight', '--type-subheading-weight', '--type-eyebrow-weight'] as $weight) {
        expect((int) $variables[$weight])->toBeGreaterThanOrEqual(500)->toBeLessThanOrEqual(800);
    }

    foreach (['--type-heading-case', '--type-eyebrow-case'] as $case) {
        expect($variables[$case])->toBeIn(['none', 'uppercase', 'lowercase', 'capitalize']);
    }

    foreach (['--type-eyebrow-size', '--type-intro-size'] as $size) {
        expect($variables[$size])->toEndWith('rem');
    }
})->with(TypeStyle::cases());

it('classic emits exactly the site.css fallback values, so an untouched site cannot change', function (): void {
    // The zero-regression contract: every `.site-*` declaration in
    // resources/css/site.css falls back to these values when no theme tag is
    // present, so Classic-with-variables and no-variables-at-all must render
    // the same pixels. Change either side only together.
    expect(TypeStyle::Classic->variables())->toBe([
        '--type-scale' => '1',
        '--type-display-weight' => '700',
        '--type-display-tracking' => '-0.025em',
        '--type-heading-weight' => '700',
        '--type-heading-tracking' => '-0.025em',
        '--type-heading-case' => 'none',
        '--type-subheading-weight' => '600',
        '--type-eyebrow-size' => '0.875rem',
        '--type-eyebrow-weight' => '600',
        '--type-eyebrow-case' => 'uppercase',
        '--type-eyebrow-tracking' => '0.1em',
        '--type-intro-size' => '1.125rem',
    ]);
});
