<?php

declare(strict_types=1);

use App\Design\RadiusScale;
use App\Design\SpacingDensity;

it('maps every radius step onto the full DaisyUI radius variable set', function (): void {
    $expected = [
        RadiusScale::None->value => ['0', '0', '0'],
        RadiusScale::Sm->value => ['0.25rem', '0.25rem', '0.25rem'],
        RadiusScale::Md->value => ['0.5rem', '0.25rem', '0.5rem'],
        RadiusScale::Lg->value => ['1rem', '0.5rem', '1rem'],
        RadiusScale::Full->value => ['2rem', '2rem', '2rem'],
    ];

    foreach (RadiusScale::cases() as $scale) {
        expect($scale->variables())->toBe([
            '--radius-selector' => $expected[$scale->value][0],
            '--radius-field' => $expected[$scale->value][1],
            '--radius-box' => $expected[$scale->value][2],
        ]);
    }
});

it('maps every density step onto the spacing and size variables', function (): void {
    $expected = [
        SpacingDensity::Compact->value => ['0.225rem', '0.21875rem'],
        SpacingDensity::Normal->value => ['0.25rem', '0.25rem'],
        SpacingDensity::Spacious->value => ['0.28125rem', '0.28125rem'],
    ];

    foreach (SpacingDensity::cases() as $density) {
        expect($density->variables())->toBe([
            '--spacing' => $expected[$density->value][0],
            '--size-field' => $expected[$density->value][1],
            '--size-selector' => $expected[$density->value][1],
        ]);
    }
});
