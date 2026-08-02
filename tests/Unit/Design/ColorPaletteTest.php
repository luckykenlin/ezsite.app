<?php

declare(strict_types=1);

use App\Design\ColorPalette;
use App\Models\Business;
use App\Models\Tenant;
use App\Tenancy\RunInTenant;

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

/**
 * The OKLCH lightness (0–100) of a palette value. Every curated value is
 * authored in `oklch(L% C H)` form, so a parse failure is itself a finding.
 */
function oklchLightness(string $color): float
{
    expect(preg_match('/^oklch\((\d+(?:\.\d+)?)%/', $color, $matches))->toBe(1, $color.' is not an oklch(L% …) value');

    return (float) $matches[1];
}

it('keeps every content color readable and the muted step visible', function (ColorPalette $palette): void {
    $colors = $palette->colors();

    // Readability: each background/content pair must be far apart in
    // lightness — the curated-palette analogue of Contrast::contentFor().
    // Two tiers: base and neutral carry body copy, so they need a wide gap;
    // primary/secondary/accent carry short bold button labels, where chroma
    // does part of the work and mid-tone surfaces are legitimate.
    foreach ([
        ['--color-base-100', '--color-base-content', 55],
        ['--color-neutral', '--color-neutral-content', 55],
        ['--color-primary', '--color-primary-content', 25],
        ['--color-secondary', '--color-secondary-content', 25],
        ['--color-accent', '--color-accent-content', 25],
    ] as [$background, $content, $minimum]) {
        expect(abs(oklchLightness($colors[$background]) - oklchLightness($colors[$content])))
            ->toBeGreaterThanOrEqual($minimum, $palette->value.': '.$background.' vs '.$content);
    }

    // The muted band: base-200 must sit a visible step from base-100 (this
    // used to be 2–3 L points, which rendered as dirty white), and the ramp
    // must be monotonic — away from base-100 in the palette's own direction.
    $base100 = oklchLightness($colors['--color-base-100']);
    $base200 = oklchLightness($colors['--color-base-200']);
    $base300 = oklchLightness($colors['--color-base-300']);

    expect(abs($base200 - $base100))->toBeGreaterThanOrEqual(3.5, $palette->value.': muted step');

    if ($palette->isDark()) {
        expect($base200)->toBeGreaterThan($base100)
            ->and($base300)->toBeGreaterThan($base200)
            // Inverted sections and the footer need their own surface.
            ->and(oklchLightness($colors['--color-neutral']))->toBeLessThan($base100);
    } else {
        expect($base200)->toBeLessThan($base100)
            ->and($base300)->toBeLessThan($base200);
    }
})->with(array_map(
    fn (ColorPalette $palette): array => [$palette],
    array_filter(ColorPalette::cases(), fn (ColorPalette $palette): bool => $palette !== ColorPalette::Brand),
));

it('marks exactly the near-black palettes dark', function (): void {
    $dark = array_values(array_filter(
        ColorPalette::cases(),
        fn (ColorPalette $palette): bool => $palette->isDark(),
    ));

    expect($dark)->toBe([ColorPalette::Midnight, ColorPalette::NoirGold]);
});

/*
 * The guide is the model's colour vocabulary — every palette needs one, and
 * the dark palettes must shout it: picking one repaints the whole site dark,
 * a mistake no adjective justifies on its own.
 */
it('gives every palette a colour guide, with the dark ones flagged in capitals', function (ColorPalette $palette): void {
    expect($palette->guide())->not->toBeEmpty()
        ->and($palette->isDark())->toBe(str_contains($palette->guide(), 'DARK'));
})->with(array_map(
    fn (ColorPalette $palette): array => [$palette],
    ColorPalette::cases(),
));

/*
 * The one public gate every brand-hex path shares — the Design form,
 * SetSiteStyle's hex arguments, TokenSelection::normalise() — and also the
 * CSS-injection guard, since these strings end up inside a <style> tag.
 */
it('validates and lower-cases a hex through the public gate', function (): void {
    expect(ColorPalette::validHex('#1A2B3C'))->toBe('#1a2b3c')
        ->and(ColorPalette::validHex('#123'))->toBeNull()
        ->and(ColorPalette::validHex('red; } body { display: none'))->toBeNull()
        ->and(ColorPalette::validHex(null))->toBeNull();
});

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
