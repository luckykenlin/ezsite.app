<?php

declare(strict_types=1);

use App\Design\ColorPalette;
use App\Design\StylePreset;
use App\Design\ThemeVariables;
use App\Models\Business;
use App\Models\Tenant;
use App\Tenancy\RunInTenant;

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

    $variables = ThemeVariables::variablesFor($themed->design_tokens, $themed);

    expect($variables['--color-primary'])->toBe('oklch(55% 0.12 40)') // WarmSand
        ->and($variables['--radius-box'])->toBe('1rem') // Lg
        ->and($variables['--spacing'])->toBe('0.28125rem') // Spacious
        ->and($variables['--font-heading'])->toContain('Newsreader') // WarmEditorial
        ->and($variables['--font-sans'])->toContain('Figtree');

    $defaults = ThemeVariables::variablesFor($bare->design_tokens, $bare);

    expect($defaults['--color-primary'])->toBe('oklch(45% 0.24 277.023)')
        ->and($defaults['--radius-box'])->toBe('0.5rem')
        ->and($defaults['--spacing'])->toBe('0.25rem')
        ->and($defaults['--font-heading'])->toContain('Schibsted Grotesk')
        // The TypeStyle contract rides in the same set (Classic here).
        ->and($defaults['--type-scale'])->toBe('1')
        ->and($defaults['--type-heading-weight'])->toBe('700')
        ->and(ThemeVariables::style($themed)->toHtml())->toStartWith('<style data-site-theme>:root{')
        ->toEndWith('}</style>')
        ->toContain('--color-primary: oklch(55% 0.12 40);');
});

it('renders the same declarations as an attribute body, for one element instead of a document', function (): void {
    $business = Business::query()->findOrFail(themedBusiness(StylePreset::WarmCraft)->getKey());
    $tokens = $business->design_tokens;

    $inline = ThemeVariables::inline($tokens, $business);

    // The last assertion is the one that matters: it pins the document form to
    // this body, so re-forking the declaration loop into styleFor() fails here.
    expect($inline)->toContain('--color-primary: oklch(55% 0.12 40);')
        ->not->toContain('<style')
        ->not->toContain(':root')
        ->and(ThemeVariables::styleFor($tokens, $business)->toHtml())->toContain($inline);
});

it('tells the browser the palette scheme, so native UI follows a dark site', function (): void {
    $business = Business::query()->findOrFail(themedBusiness()->getKey());

    $light = ThemeVariables::variablesFor($business->design_tokens, $business);
    $dark = ThemeVariables::variablesFor(
        $business->design_tokens->with(palette: ColorPalette::Midnight),
        $business,
    );

    expect($light['color-scheme'])->toBe('light')
        ->and($dark['color-scheme'])->toBe('dark')
        ->and(ThemeVariables::styleFor($business->design_tokens->with(palette: ColorPalette::Midnight), $business)->toHtml())
        ->toContain('color-scheme: dark;');
});
