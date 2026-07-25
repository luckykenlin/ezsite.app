<?php

declare(strict_types=1);

use App\Actions\RunInTenant;
use App\Design\StylePreset;
use App\Design\ThemeVariables;
use App\Models\Business;
use App\Models\Tenant;

function themedBusiness(?StylePreset $preset = null): Business
{
    $tenant = Tenant::factory()->create();
    $factory = Business::factory();

    if ($preset instanceof StylePreset) {
        $factory = $factory->themed($preset);
    }

    return resolve(RunInTenant::class)->handle(
        $tenant,
        fn (): Business => $factory->create(['tenant_id' => $tenant->id]),
    );
}

it('compiles the full variable set from the business tokens', function (): void {
    $variables = ThemeVariables::variables(
        Business::query()->findOrFail(themedBusiness(StylePreset::WarmCraft)->getKey()),
    );

    expect($variables['--color-primary'])->toBe('oklch(55% 0.12 40)') // WarmSand
        ->and($variables['--radius-box'])->toBe('1rem') // Lg
        ->and($variables['--spacing'])->toBe('0.28125rem') // Spacious
        ->and($variables['--font-heading'])->toContain('Playfair Display') // ElegantSerif
        ->and($variables['--font-sans'])->toContain('Source Sans 3');
});

it('compiles default variables for a business without stored tokens', function (): void {
    $variables = ThemeVariables::variables(
        Business::query()->findOrFail(themedBusiness()->getKey()),
    );

    expect($variables['--color-primary'])->toBe('oklch(45% 0.24 277.023)')
        ->and($variables['--radius-box'])->toBe('0.5rem')
        ->and($variables['--spacing'])->toBe('0.25rem')
        ->and($variables['--font-heading'])->toContain('Instrument Sans');
});

it('renders a single scoped style tag', function (): void {
    $style = ThemeVariables::style(
        Business::query()->findOrFail(themedBusiness(StylePreset::BoldEditorial)->getKey()),
    )->toHtml();

    expect($style)->toStartWith('<style data-site-theme>:root{')
        ->toEndWith('}</style>')
        ->toContain('--radius-box: 0;')
        ->toContain('--color-primary: oklch(45% 0.15 320);'); // Plum
});
