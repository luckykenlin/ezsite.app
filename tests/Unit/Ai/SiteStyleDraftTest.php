<?php

declare(strict_types=1);

use App\Ai\SiteStyleDraft;
use App\Design\ColorPalette;
use App\Design\DesignTokens;
use App\Themes\Definitions\DefaultTheme;

/*
 * The safety property this class exists for. A chat turn runs in a queue worker;
 * a tool that called ApplyScheme there would write immediately, and
 * ThemeVariables::style() reads SAVED tokens on every public render — so the
 * assistant would repaint a live website while its owner was still reading the
 * reply. Nothing here may touch the database.
 */
it('starts from the saved style and reports nothing staged', function (): void {
    $saved = defaultScheme(DefaultTheme::CALM_COASTAL)->tokens;

    $draft = new SiteStyleDraft($saved);

    expect($draft->current())->toBe($saved)
        ->and($draft->touched())->toBeFalse()
        // Null is what tells the editor there is nothing to preview or apply.
        ->and($draft->toArray())->toBeNull();
});

it('stages a scheme without disturbing the saved tokens', function (): void {
    $saved = DesignTokens::default();
    $draft = new SiteStyleDraft($saved);

    $draft->applyScheme(defaultScheme(DefaultTheme::WARM_CRAFT));

    expect($draft->current()->palette)->toBe(defaultScheme(DefaultTheme::WARM_CRAFT)->tokens->palette)
        ->and($draft->current()->scheme)->toBe(DefaultTheme::WARM_CRAFT)
        ->and($draft->touched())->toBeTrue()
        // The object it was constructed with is untouched — the "saved" side.
        ->and($saved->palette)->toBe(ColorPalette::Default);
});

/*
 * The shape is the contract with two consumers that were written before this
 * class: PageEditor::previewDesign() stages it, and SaveDesignSelection reads
 * the `scheme` out of it. If they matched only by luck there would be an
 * adapter somewhere on this path; there is not.
 */
it('serialises into the shape the editor previews and the writer consumes', function (): void {
    $draft = new SiteStyleDraft(DesignTokens::default());
    $draft->applyScheme(defaultScheme(DefaultTheme::PROFESSIONAL_MINIMAL));

    expect($draft->toArray())->toBe(defaultScheme(DefaultTheme::PROFESSIONAL_MINIMAL)->tokens->toArray());
});

/*
 * Cross-turn continuity. A previous turn's staged-but-unapplied style rides
 * back in as the seed: "a bit darker" must refine what the operator is looking
 * at, not silently restart from the saved tokens.
 */
it('reads from a seeded preview and restyles on top of it', function (): void {
    $seeded = defaultScheme(DefaultTheme::BOLD_EDITORIAL)->tokens;
    $draft = new SiteStyleDraft(DesignTokens::default(), $seeded);

    expect($draft->current())->toBe($seeded)
        ->and($draft->seededPreview())->toBeTrue()
        // The saved side is untouched by the seed: a new page created this
        // turn must still be laid out for the style the site actually has.
        ->and($draft->saved())->toEqual(DesignTokens::default());

    $draft->applyScheme(defaultScheme(DefaultTheme::WARM_CRAFT));

    expect($draft->current()->scheme)->toBe(DefaultTheme::WARM_CRAFT)
        ->and($draft->saved())->toEqual(DesignTokens::default());
});

/*
 * The brand layer: staged hexes ride inside the one draft array, the seeded
 * draft's hexes survive a later restyle, and the saved row's hexes count as the
 * effective brand without ever being re-emitted.
 */
it('carries staged brand hexes inside the draft array, on top of the look in force', function (): void {
    $draft = new SiteStyleDraft(defaultScheme(DefaultTheme::CALM_COASTAL)->tokens);

    $draft->stageBrand(['brand_primary' => '#1a2b3c']);

    // A brand-only turn still materialises a complete draft — the look it is
    // staged against — rather than a dirty flag with nothing behind it.
    expect($draft->touched())->toBeTrue()
        ->and($draft->toArray()['brand_primary'])->toBe('#1a2b3c')
        ->and($draft->toArray()['scheme'])->toBe(DefaultTheme::CALM_COASTAL)
        ->and($draft->brand())->toBe(['brand_primary' => '#1a2b3c']);
});

it('layers brand hexes staged over seeded over saved', function (): void {
    $draft = new SiteStyleDraft(
        DesignTokens::default(),
        defaultScheme(DefaultTheme::BOLD_EDITORIAL)->tokens,
        savedBrand: ['brand_primary' => '#111111', 'brand_secondary' => '#222222'],
        seededBrand: ['brand_primary' => '#333333'],
    );

    expect($draft->brand())->toBe(['brand_primary' => '#333333', 'brand_secondary' => '#222222']);

    $draft->stageBrand(['brand_primary' => '#444444']);

    expect($draft->brand()['brand_primary'])->toBe('#444444')
        // The seeded hex is re-emitted (the editor draft is replaced whole),
        // the saved one is not (it is already on the row).
        ->and($draft->toArray()['brand_primary'])->toBe('#444444')
        ->and($draft->toArray())->not->toHaveKey('brand_secondary');
});

/*
 * The gate half of seeding: a turn that merely STARTED from the preview must
 * not hand it back — the editor already holds that draft, its Apply gate is
 * already up, and a re-staged copy would snapshot an edit nobody made.
 */
it('does not re-stage a seeded preview the turn left alone', function (): void {
    $draft = new SiteStyleDraft(DesignTokens::default(), defaultScheme(DefaultTheme::BOLD_EDITORIAL)->tokens);

    expect($draft->touched())->toBeFalse()
        ->and($draft->toArray())->toBeNull();

    $draft->stageBrand(['brand_primary' => '#1a2b3c']);

    expect($draft->touched())->toBeTrue()
        ->and($draft->toArray())->not->toBeNull();
});
