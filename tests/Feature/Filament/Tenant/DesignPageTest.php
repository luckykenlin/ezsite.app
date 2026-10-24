<?php

declare(strict_types=1);

use App\Design\ColorPalette;
use App\Design\DesignTokens;
use App\Filament\Tenant\Pages\Design;
use App\Models\Business;
use App\Templates\SiteTemplate;
use App\Themes\Definitions\DefaultTheme;
use App\Themes\Theme;
use Livewire\Livewire;

/*
 * The settings-page half of the Site Styles panel. Its behaviour is
 * App\Filament\Tenant\Concerns\EditsSiteStyles, shared with the page editor's
 * rail, so what is worth asserting here is the part that ISN'T shared: this
 * surface stages into its own property and has no canvas, and it is the only way
 * in for a tenant whose site has no pages yet.
 */

test('the page is inaccessible until a business profile exists', function (): void {
    expect(Design::canAccess())->toBeFalse();

    Business::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(Design::canAccess())->toBeTrue();
});

test('a staged look waits for apply, and nothing reaches the site before it', function (): void {
    Business::factory()->themed(DefaultTheme::WARM_CRAFT)->create(['tenant_id' => $this->tenant->id]);

    $component = Livewire::test(Design::class)->call('stageScheme', 'bold-editorial');

    // The panel shows the staged look — resolved through the theme, since the
    // staged selection itself is only the slug...
    expect($component->instance()->activeScheme())->toBe('bold-editorial')
        ->and($component->instance()->styleTokens()->palette)->toBe(ColorPalette::Plum)
        ->and($component->instance()->hasStagedStyles())->toBeTrue()
        // ...while the site is still on the saved one. A stray click used to
        // restyle the whole live site with no undo.
        ->and(Business::query()->sole()->design_tokens->scheme)->toBe(DefaultTheme::WARM_CRAFT);

    $component->call('applySiteStyles')->assertNotified();

    $tokens = Business::query()->sole()->design_tokens;

    expect($tokens->scheme)->toBe(DefaultTheme::BOLD_EDITORIAL)
        ->and($tokens->palette)->toBe(ColorPalette::Plum)
        ->and($component->instance()->hasStagedStyles())->toBeFalse();
});

test('applying brand colours updates the business columns', function (): void {
    Business::factory()->create([
        'tenant_id' => $this->tenant->id,
        'brand_primary' => '#111111',
        'design_tokens' => DesignTokens::fromArray([...DesignTokens::default()->toArray(), 'palette' => 'brand']),
    ]);

    Livewire::test(Design::class)
        ->call('stageBrandColor', 'brand_primary', '#ff6b35')
        ->call('applySiteStyles');

    expect(Business::query()->sole()->brand_primary)->toBe('#ff6b35');
});

test('the panel opens on the stored tokens, as the site actually has them', function (): void {
    // Not re-resolved from the slug: a template may provision a scheme with
    // its own axis overrides, and the panel must show that look, not the
    // scheme's pure bundle.
    $provisioned = DesignTokens::fromArray([...defaultScheme(DefaultTheme::CALM_COASTAL)->tokens->toArray(), 'radius' => 'none']);
    Business::factory()->create(['tenant_id' => $this->tenant->id, 'design_tokens' => $provisioned]);

    $instance = Livewire::test(Design::class)->instance();

    expect($instance->activeScheme())->toBe('calm-coastal')
        ->and($instance->styleSelection())->toBe([])
        ->and($instance->styleTokens()->toArray())->toBe($provisioned->toArray());
});

/*
 * A brand edit is a colour change, not a scheme pick. It used to stage the
 * saved slug alongside the hex, which this surface's Save then re-applied as
 * the scheme's bare bundle — so editing a DimSumHouse site's red threw away the
 * Brand palette the red was for.
 */
test('a brand edit keeps a template s overridden look, staged and saved', function (): void {
    $provisioned = SiteTemplate::DimSumHouse->definition()->tokens();
    Business::factory()->create(['tenant_id' => $this->tenant->id, 'design_tokens' => $provisioned]);

    $component = Livewire::test(Design::class)->call('stageBrandColor', 'brand_primary', '#ff6b35');

    expect($component->instance()->styleSelection())->toBe(['scheme' => null, 'brand_primary' => '#ff6b35'])
        ->and($component->instance()->styleTokens()->toArray())->toBe($provisioned->toArray())
        ->and($component->instance()->activeScheme())->toBe(DefaultTheme::WARM_CRAFT);

    $component->call('applySiteStyles');

    expect(Business::query()->sole()->design_tokens->toArray())->toBe($provisioned->toArray())
        ->and(Business::query()->sole()->brand_primary)->toBe('#ff6b35');
});

test('discarding a staged look returns the panel to the saved one', function (): void {
    Business::factory()->themed(DefaultTheme::WARM_CRAFT)->create(['tenant_id' => $this->tenant->id]);

    $component = Livewire::test(Design::class)
        ->call('stageScheme', 'bold-editorial')
        ->call('resetSiteStyles');

    expect($component->instance()->hasStagedStyles())->toBeFalse()
        ->and($component->instance()->activeScheme())->toBe('warm-craft');
});

test('every scheme of the current theme is offered at the top level, each drawn as itself', function (): void {
    // One level, no group to open first. Asserted through the palette only
    // Bold Editorial has, so the tile cannot be the current look repeated.
    Business::factory()->themed(DefaultTheme::WARM_CRAFT)->create(['tenant_id' => $this->tenant->id]);

    $component = Livewire::test(Design::class);

    foreach (Theme::Default->definition()->schemes as $slug => $scheme) {
        $component->assertSee($scheme->label)
            ->assertSeeHtml("stageScheme('{$slug}')");
    }

    $component->assertSeeHtml(defaultScheme(DefaultTheme::BOLD_EDITORIAL)->tokens->palette->colors()['--color-primary'])
        ->assertDontSee('All styles');
});
