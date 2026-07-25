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

it('compiles stored tokens into the variable set, defaulting without them, and renders one scoped style tag', function (): void {
    $themed = Business::query()->findOrFail(themedBusiness(StylePreset::WarmCraft)->getKey());
    $bare = Business::query()->findOrFail(themedBusiness()->getKey());

    $variables = ThemeVariables::variables($themed);

    expect($variables['--color-primary'])->toBe('oklch(55% 0.12 40)') // WarmSand
        ->and($variables['--radius-box'])->toBe('1rem') // Lg
        ->and($variables['--spacing'])->toBe('0.28125rem') // Spacious
        ->and($variables['--font-heading'])->toContain('Playfair Display') // ElegantSerif
        ->and($variables['--font-sans'])->toContain('Source Sans 3');

    $defaults = ThemeVariables::variables($bare);

    expect($defaults['--color-primary'])->toBe('oklch(45% 0.24 277.023)')
        ->and($defaults['--radius-box'])->toBe('0.5rem')
        ->and($defaults['--spacing'])->toBe('0.25rem')
        ->and($defaults['--font-heading'])->toContain('Instrument Sans')
        ->and(ThemeVariables::style($themed)->toHtml())->toStartWith('<style data-site-theme>:root{')
        ->toEndWith('}</style>')
        ->toContain('--color-primary: oklch(55% 0.12 40);');
});
