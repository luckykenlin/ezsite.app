<?php

declare(strict_types=1);

use App\Design\TypeStyle;

it('emits the full deterministic --type-* variable set for every style', function (TypeStyle $style): void {
    $variables = $style->variables();

    expect(array_keys($variables))->toBe([
        '--type-scale',
        '--type-display-scale',
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

    // Structural sanity per case: the body scale is a number close to 1, the
    // display scale multiplies it without inverting the hierarchy, weights are
    // stops the self-hosted fonts ship, cases are valid text-transform values,
    // and tracked sizes are rem lengths.
    expect((float) $variables['--type-scale'])->toBeGreaterThan(0.8)->toBeLessThan(1.3)
        // A display scale under 1 would set a hero headline smaller than the
        // section titles under it — a broken page, not a quiet one. The ceiling
        // is where a two-line headline stops fitting a phone.
        ->and((float) $variables['--type-display-scale'])->toBeGreaterThanOrEqual(0.9)->toBeLessThanOrEqual(1.5);

    foreach (['--type-display-weight', '--type-heading-weight', '--type-subheading-weight', '--type-eyebrow-weight'] as $weight) {
        // The floor is 300 rather than 500 because Serene sets a hairline
        // display face there on purpose. Which pairs may go under 500 is a
        // separate rule with its own guard in FontPairTest — a light weight on
        // a face that has no light cut snaps back up and loses the whole look.
        expect((int) $variables[$weight])->toBeGreaterThanOrEqual(300)->toBeLessThanOrEqual(800);
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
        '--type-display-scale' => '1',
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
