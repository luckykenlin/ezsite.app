<?php

declare(strict_types=1);

use App\Actions\RunInTenant;
use App\Design\ColorPalette;
use App\Models\Business;
use App\Models\Tenant;

const PALETTE_VARIABLES = [
    '--color-base-100', '--color-base-200', '--color-base-300', '--color-base-content',
    '--color-primary', '--color-primary-content',
    '--color-secondary', '--color-secondary-content',
    '--color-accent', '--color-accent-content',
    '--color-neutral', '--color-neutral-content',
];

function paletteBusiness(array $attributes): Business
{
    $tenant = Tenant::factory()->create();

    return resolve(RunInTenant::class)->handle(
        $tenant,
        fn (): Business => Business::factory()->create([...$attributes, 'tenant_id' => $tenant->id]),
    );
}

it('emits the full deterministic variable set for every palette', function (ColorPalette $palette): void {
    $colors = $palette->colors();

    expect(array_keys($colors))->toBe(PALETTE_VARIABLES)
        ->and($colors)->each->not->toBeEmpty();
})->with(array_map(
    fn (ColorPalette $palette): array => [$palette],
    array_filter(ColorPalette::cases(), fn (ColorPalette $palette): bool => $palette !== ColorPalette::Brand),
));

it('derives the brand palette from validated hex colors with contrast-picked content', function (): void {
    $business = paletteBusiness([
        'brand_primary' => '#1A2B3C', // dark blue → light content
        'brand_secondary' => '#F5E6D0', // light cream → dark content
        'brand_accent' => '#FF6B35',
    ]);

    $colors = ColorPalette::Brand->colors($business);

    expect(array_keys($colors))->toBe(PALETTE_VARIABLES)
        ->and($colors['--color-primary'])->toBe('#1a2b3c')
        ->and($colors['--color-primary-content'])->toBe('oklch(98% 0 0)')
        ->and($colors['--color-secondary'])->toBe('#f5e6d0')
        ->and($colors['--color-secondary-content'])->toBe('oklch(21% 0.006 285.885)')
        ->and($colors['--color-accent'])->toBe('#ff6b35');
});

it('falls back to the primary for missing secondary and accent colors', function (): void {
    $business = paletteBusiness([
        'brand_primary' => '#336699',
        'brand_secondary' => null,
        'brand_accent' => null,
    ]);

    $colors = ColorPalette::Brand->colors($business);

    expect($colors['--color-secondary'])->toBe('#336699')
        ->and($colors['--color-accent'])->toBe('#336699');
});

it('rejects anything that is not a six-digit hex and falls back to the default palette', function (?string $malicious): void {
    $business = paletteBusiness(['brand_primary' => $malicious]);

    // The regex is the CSS-injection guard: these strings would otherwise be
    // emitted inside a <style> tag.
    expect(ColorPalette::Brand->colors($business))->toBe(ColorPalette::Default->colors());
})->with([
    'css injection' => ['red;}</style><script>alert(1)</script>'],
    'shorthand hex' => ['#abc'],
    'not a color' => ['javascript:alert(1)'],
    'null' => [null],
]);

it('falls back to the default palette without a business at all', function (): void {
    expect(ColorPalette::Brand->colors())->toBe(ColorPalette::Default->colors());
});
