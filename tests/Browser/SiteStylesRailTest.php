<?php

declare(strict_types=1);

use App\Design\FontPair;
use App\Design\StylePreset;
use App\Filament\Tenant\Resources\PageResource;
use App\Models\Business;
use App\Models\Tenant;
use App\Models\User;

/**
 * A tenant with a business profile (the rail's precondition) and a home page,
 * plus the editor URL for it.
 */
function stylesRailUrl(): string
{
    $tenant = Tenant::factory()->withDomain('styles')->create();

    test()->actingAs(User::factory()->memberOf($tenant)->create());

    $page = test()->createTenantPage($tenant, [
        ['type' => 'heading', 'data' => ['content' => 'First block', 'level' => 'h1']],
    ]);

    test()->runInTenant($tenant, fn (): Business => Business::factory()
        ->themed(StylePreset::ProfessionalMinimal)
        ->create(['tenant_id' => $tenant->id]));

    tenancy()->initialize($tenant);

    return test()->tenantUrl($tenant, PageResource::getUrl('edit', ['record' => $page], isAbsolute: false, panel: 'tenant'));
}

/*
 * The one claim about this rail that no PHP test can make. Every server-side
 * test can prove the markup names Cormorant Garamond; only a browser can say
 * whether the face was actually fetched and is available to paint with. If it
 * is not, every Fonts specimen renders in the admin theme's own sans — six
 * identical rows — which is precisely the dropdown this rail replaced.
 *
 * It is also the check on the delivery mechanism: FontStylesheet links one
 * cacheable stylesheet instead of inlining ~111KB per Livewire round trip, and
 * a link that 404s or never loads fails exactly here and nowhere else.
 */
it('loads the real font faces the specimens are drawn in', function (): void {
    // The rail is open by default (the URL's tenant has a Business).
    $browser = visit(stylesRailUrl())
        ->click('Fonts')
        ->assertSee('Heading')
        // The stylesheet is a <link>, so its faces register on load rather than
        // with the markup. script() does not await, so the wait is here.
        ->wait(1);

    $families = $browser->script('Array.from(document.fonts).map((face) => face.family)');

    // Every family a pair can resolve to, registered from one linked sheet. An
    // empty set is what a 404 or a wrong build path looks like.
    expect($families)->toContain('Cormorant Garamond', 'Fraunces', 'Instrument Sans');

    $browser->assertNoJavaScriptErrors();
});

/*
 * The specimens' whole premise: an option is drawn with the tokens it would
 * apply, not with the ones already in force. Asserted through computed style,
 * because that is the layer the operator actually sees — the inline custom
 * properties could be present and still be overridden by the panel's own CSS.
 */
it('draws each font option in its own family, not the current one', function (): void {
    $families = visit(stylesRailUrl())
        ->click('Fonts')
        // Only the drilled-in panel has a way back, so this pins the state the
        // specimens below belong to. Without it the script races the Livewire
        // update and reads the single specimen on the group CARD instead.
        ->assertSee('All styles')
        ->script(<<<'JS'
            Array.from(document.querySelectorAll('.pe-specimen[data-facet="font_pair"] .pe-specimen-heading'))
                .map((el) => getComputedStyle(el).fontFamily)
        JS);

    // One specimen per pair, and they must not all resolve to the same stack.
    expect($families)->toHaveSameSize(FontPair::cases())
        ->and(array_unique($families))->not->toHaveCount(1);
});

/*
 * Staging repaints the canvas. The Livewire suite asserts the draft and the
 * cached preview payload; only here does the iframe actually reload and show
 * the new theme.
 */
it('repaints the canvas when a swatch is clicked, without saving', function (): void {
    $browser = visit(stylesRailUrl())
        ->click('Themes')
        // The enum's own label, not a copy of it: a literal that drifts does
        // not fail here, it hangs — Playwright waits for a match forever.
        ->click(StylePreset::NightLounge->label())
        // The footer appearing proves the click staged; the canvas catching up
        // is a separate, asynchronous step — the iframe reloads on its own.
        ->assertSee('Apply to site')
        ->wait(2);

    // The preview document is re-rendered with the staged tokens, so the
    // canvas's own theme variables change — proof the round trip reached it.
    $palette = $browser->script(<<<'JS'
        getComputedStyle(
            document.querySelector('.pe-canvas-frame iframe').contentDocument.documentElement
        ).getPropertyValue('--color-base-100').trim()
    JS);

    expect($palette)->toBe(StylePreset::NightLounge->tokens()->palette->colors()['--color-base-100'])
        ->and(Business::query()->sole()->design_tokens->preset)->toBe(StylePreset::ProfessionalMinimal);

    $browser->assertNoJavaScriptErrors();
});
