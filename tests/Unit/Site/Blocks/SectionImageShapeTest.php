<?php

declare(strict_types=1);

use App\Site\Blocks\SectionImageShape;

it('gives every shape an aspect crop', function (SectionImageShape $shape): void {
    expect($shape->classes())->toContain('aspect-');
})->with(SectionImageShape::cases());

it('rounds only the circle — corner radius belongs to the site style', function (SectionImageShape $shape): void {
    // rounded-box/rounded-selector are the RadiusScale token's delivery
    // vehicles and stay view literals; the one shape that IS a rounding
    // carries it itself.
    if ($shape === SectionImageShape::Circle) {
        expect($shape->classes())->toBe('aspect-square rounded-full');

        return;
    }

    expect($shape->classes())->not->toContain('rounded');
})->with(SectionImageShape::cases());
