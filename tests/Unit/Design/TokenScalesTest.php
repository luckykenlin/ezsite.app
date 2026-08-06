<?php

declare(strict_types=1);

use App\Design\RadiusScale;
use App\Design\SpacingDensity;

it('emits the full DaisyUI radius variable set, growing strictly across the scale', function (): void {
    $boxRadii = [];

    foreach (RadiusScale::cases() as $scale) {
        $variables = $scale->variables();

        expect(array_keys($variables))->toBe(['--radius-selector', '--radius-field', '--radius-box'])
            ->and($variables)->each->toMatch('/^(0|\d+(\.\d+)?rem)$/');

        $boxRadii[] = (float) $variables['--radius-box'];
    }

    // None means genuinely square corners, and the steps form an ordered
    // scale — each one's box radius strictly grows.
    expect(RadiusScale::None->variables())->toBe(['--radius-selector' => '0', '--radius-field' => '0', '--radius-box' => '0'])
        ->and($boxRadii)->toBe(collect($boxRadii)->unique()->sort()->values()->all());
});

it('keeps control sizes in step with spacing and orders the densities around the Tailwind default', function (): void {
    $spacings = [];

    foreach (SpacingDensity::cases() as $density) {
        $variables = $density->variables();

        expect(array_keys($variables))->toBe(['--spacing', '--size-field', '--size-selector'])
            ->and($variables['--size-field'])->toBe($variables['--size-selector'])
            ->and($variables['--spacing'])->toMatch('/^\d+(\.\d+)?rem$/');

        $spacings[$density->value] = (float) $variables['--spacing'];
    }

    // The ±~10% design rule (see the enum docblock): Compact trims and
    // Spacious widens around Normal's Tailwind root default of 0.25rem.
    expect($spacings[SpacingDensity::Normal->value])->toBe(0.25)
        ->and($spacings[SpacingDensity::Compact->value])->toBeLessThan($spacings[SpacingDensity::Normal->value])
        ->and($spacings[SpacingDensity::Spacious->value])->toBeGreaterThan($spacings[SpacingDensity::Normal->value]);
});
