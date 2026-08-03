<?php

declare(strict_types=1);

use App\Design\MotionStyle;

it('emits the full deterministic --reveal-* set for every style', function (MotionStyle $motion): void {
    $variables = $motion->variables();

    expect(array_keys($variables))->toBe([
        '--reveal-opacity',
        '--reveal-shift',
        '--reveal-duration',
    ])
        ->and($variables['--reveal-shift'])->toMatch('/^(0px|\d+(\.\d+)?rem)$/')
        ->and($variables['--reveal-duration'])->toEndWith('ms');
})->with(MotionStyle::cases());

/*
 * The zero-regression contract, and the reason motion is a token at all:
 * `reveal.ts` marks the section shell on EVERY tenant site, so `still` has to
 * make the shared CSS a no-op rather than a shorter animation. A visitor on a
 * site that never chose a motion style must see the same pixels as before the
 * feature existed — which means a fully opaque, unmoved, untransitioned section.
 */
it('makes the still style a no-op rather than a fast animation', function (): void {
    expect(MotionStyle::Still->variables())->toBe([
        '--reveal-opacity' => '1',
        '--reveal-shift' => '0px',
        '--reveal-duration' => '0ms',
    ]);
});

it('hides and shifts a revealing section, by an amount smaller than the section', function (): void {
    $reveal = MotionStyle::Reveal->variables();

    expect($reveal['--reveal-opacity'])->toBe('0')
        // A section that travels further than its own first line of text reads
        // as a slide show rather than as a page settling.
        ->and((float) $reveal['--reveal-shift'])->toBeGreaterThan(0)->toBeLessThanOrEqual(3)
        ->and((int) $reveal['--reveal-duration'])->toBeGreaterThan(0);
});
