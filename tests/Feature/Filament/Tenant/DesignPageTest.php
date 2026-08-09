<?php

declare(strict_types=1);

use App\Actions\UpdateDesignTokens;
use App\Design\ColorPalette;
use App\Design\FontPair;
use App\Design\RadiusScale;
use App\Design\StyleGroup;
use App\Design\StylePreset;
use App\Filament\Tenant\Pages\Design;
use App\Models\Business;
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
    Business::factory()->themed(StylePreset::WarmCraft)->create(['tenant_id' => $this->tenant->id]);

    $component = Livewire::test(Design::class)->call('stagePreset', 'bold-editorial');

    // The panel shows the staged look...
    expect($component->instance()->styleSelection()['palette'])->toBe('plum')
        ->and($component->instance()->hasStagedStyles())->toBeTrue()
        // ...while the site is still on the saved one. A stray click used to
        // restyle the whole live site with no undo.
        ->and(Business::query()->sole()->design_tokens->preset)->toBe(StylePreset::WarmCraft);

    $component->call('applySiteStyles')->assertNotified();

    $tokens = Business::query()->sole()->design_tokens;

    expect($tokens->preset)->toBe(StylePreset::BoldEditorial)
        ->and($tokens->palette)->toBe(ColorPalette::Plum)
        ->and($component->instance()->hasStagedStyles())->toBeFalse();
});

test('changing one token after a preset applies a custom combination, preset detached', function (): void {
    Business::factory()->themed(StylePreset::WarmCraft)->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(Design::class)
        ->call('stagePreset', 'bold-editorial')
        ->call('stageToken', 'palette', 'ocean')
        ->call('applySiteStyles');

    $tokens = Business::query()->sole()->design_tokens;

    expect($tokens->preset)->toBeNull()
        ->and($tokens->palette)->toBe(ColorPalette::Ocean)
        // The font the preset carried survives the change, pinned as itself.
        ->and($tokens->fontPair)->toBe(FontPair::Editorial);
});

test('applying individual tokens leaves the untouched ones alone', function (): void {
    Business::factory()->themed(StylePreset::WarmCraft)->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(Design::class)
        ->call('stageToken', 'palette', 'ocean')
        ->call('stageToken', 'font_pair', 'geometric')
        ->call('applySiteStyles');

    $tokens = Business::query()->sole()->design_tokens;

    expect($tokens->palette)->toBe(ColorPalette::Ocean)
        ->and($tokens->fontPair)->toBe(FontPair::Geometric)
        ->and($tokens->radius)->toBe(RadiusScale::Lg) // untouched from WarmCraft
        ->and($tokens->preset)->toBeNull();
});

test('applying brand colours updates the business columns', function (): void {
    $business = Business::factory()->create([
        'tenant_id' => $this->tenant->id,
        'brand_primary' => '#111111',
    ]);
    $this->runInTenant($this->tenant, fn (): Business => resolve(UpdateDesignTokens::class)
        ->handle($business, ['palette' => 'brand']));

    Livewire::test(Design::class)
        ->call('stageBrandColor', 'brand_primary', '#ff6b35')
        ->call('applySiteStyles');

    expect(Business::query()->sole()->brand_primary)->toBe('#ff6b35');
});

test('the panel opens on the stored tokens', function (): void {
    Business::factory()->themed(StylePreset::CalmCoastal)->create(['tenant_id' => $this->tenant->id]);

    $selection = Livewire::test(Design::class)->instance()->styleSelection();

    expect($selection['preset'])->toBe('calm-coastal')
        ->and($selection['palette'])->toBe('ocean')
        ->and($selection['radius'])->toBe('lg');
});

test('discarding a staged look returns the panel to the saved one', function (): void {
    Business::factory()->themed(StylePreset::WarmCraft)->create(['tenant_id' => $this->tenant->id]);

    $component = Livewire::test(Design::class)
        ->call('stagePreset', 'bold-editorial')
        ->call('resetSiteStyles');

    expect($component->instance()->hasStagedStyles())->toBeFalse()
        ->and($component->instance()->styleSelection()['preset'])->toBe('warm-craft');
});

test('every group is offered, each previewing what it changes', function (): void {
    // The replacement for a Radio of preset names with four colour dots: a look
    // is chosen by eye. Asserted through the palette only Bold Editorial has, so
    // the tile cannot be the current look repeated eight times.
    Business::factory()->themed(StylePreset::WarmCraft)->create(['tenant_id' => $this->tenant->id]);

    $component = Livewire::test(Design::class);

    foreach (StyleGroup::cases() as $group) {
        $component->assertSee($group->label());
    }

    $component->assertSeeHtml('data-facet="theme"')
        ->assertSeeHtml('data-facet="palette"')
        ->call('openStyleGroup', 'theme')
        ->assertSeeHtml(StylePreset::BoldEditorial->tokens()->palette->colors()['--color-primary']);
});
