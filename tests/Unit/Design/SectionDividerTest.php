<?php

declare(strict_types=1);

use App\Design\SectionDivider;

it('emits the display and clip pair for every divider', function (SectionDivider $divider): void {
    $variables = $divider->variables();

    expect(array_keys($variables))->toBe(['--divider-display', '--divider-clip'])
        ->and($variables['--divider-display'])->toBeIn(['none', 'block']);

    // A shaped divider must actually clip; only None may be a no-op. The
    // clip value is authored geometry — enum-guaranteed, never tenant data —
    // so it only ever contains the shape functions the seam rules expect.
    if ($divider === SectionDivider::None) {
        expect($variables['--divider-display'])->toBe('none');
    } else {
        expect($variables['--divider-display'])->toBe('block')
            ->and($variables['--divider-clip'])->toMatch('/^(polygon|ellipse)\(/');
    }
})->with(SectionDivider::cases());
