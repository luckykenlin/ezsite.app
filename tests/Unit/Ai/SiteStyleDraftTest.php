<?php

declare(strict_types=1);

use App\Ai\SiteStyleDraft;
use App\Design\ColorPalette;
use App\Design\DesignTokens;
use App\Templates\SiteTemplate;
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
 * The shape is the contract with the editor's preview and SaveDesignSelection:
 * a selection (`scheme` + brand hexes), which both read through
 * TokenSelection::normalise(). No axes — every reader resolves the slug through
 * the current theme.
 */
it('serialises a picked scheme as its slug alone', function (): void {
    $draft = new SiteStyleDraft(DesignTokens::default());
    $draft->applyScheme(defaultScheme(DefaultTheme::PROFESSIONAL_MINIMAL));

    expect($draft->toArray())->toBe(['scheme' => DefaultTheme::PROFESSIONAL_MINIMAL]);
});

/*
 * Cross-turn continuity. A previous turn's staged-but-unapplied scheme rides
 * back in as the seed: "a bit darker" must refine what the operator is looking
 * at, not silently restart from the saved tokens.
 */
it('reads from a seeded preview and restyles on top of it', function (): void {
    $seeded = defaultScheme(DefaultTheme::BOLD_EDITORIAL);
    $draft = new SiteStyleDraft(DesignTokens::default(), $seeded);

    expect($draft->current())->toBe($seeded->tokens)
        ->and($draft->seededPreview())->toBeTrue()
        // The saved side is untouched by the seed: a new page created this
        // turn must still be laid out for the style the site actually has.
        ->and($draft->saved())->toEqual(DesignTokens::default());

    $draft->applyScheme(defaultScheme(DefaultTheme::WARM_CRAFT));

    expect($draft->current()->scheme)->toBe(DefaultTheme::WARM_CRAFT)
        ->and($draft->saved())->toEqual(DesignTokens::default());
});

/*
 * The regression this pins: a brand-only turn used to materialise the CURRENT
 * tokens as staged, so toArray() handed back the saved slug as if the
 * assistant had picked it — and every reader treats a staged scheme as
 * "replace the look with its bundle", wiping a template's overrides (here
 * DimSumHouse's Brand palette, the only palette the hex shows on).
 */
it('stages brand hexes without staging a scheme, leaving the look in force alone', function (): void {
    $saved = SiteTemplate::DimSumHouse->definition()->tokens();
    $draft = new SiteStyleDraft($saved);

    $draft->stageBrand(['brand_primary' => '#1a2b3c']);

    expect($draft->touched())->toBeTrue()
        ->and($draft->current())->toBe($saved)
        ->and($draft->toArray())->toBe(['brand_primary' => '#1a2b3c'])
        ->and($draft->brand())->toBe(['brand_primary' => '#1a2b3c']);
});

it('keeps a seeded scheme on the canvas through a brand-only follow-up', function (): void {
    // The editor's draft is REPLACED by toArray(), so a brand tweak on top of
    // last turn's unapplied scheme must hand that scheme back too.
    $draft = new SiteStyleDraft(DesignTokens::default(), defaultScheme(DefaultTheme::BOLD_EDITORIAL));

    $draft->stageBrand(['brand_primary' => '#1a2b3c']);

    expect($draft->toArray())->toBe(['scheme' => DefaultTheme::BOLD_EDITORIAL, 'brand_primary' => '#1a2b3c']);
});

it('layers brand hexes staged over seeded over saved', function (): void {
    $draft = new SiteStyleDraft(
        DesignTokens::default(),
        savedBrand: ['brand_primary' => '#111111', 'brand_secondary' => '#222222'],
        seededBrand: ['brand_primary' => '#333333'],
    );

    // Seeded hexes alone are an unapplied preview too.
    expect($draft->brand())->toBe(['brand_primary' => '#333333', 'brand_secondary' => '#222222'])
        ->and($draft->seededPreview())->toBeTrue();

    $draft->stageBrand(['brand_primary' => '#444444']);

    expect($draft->brand()['brand_primary'])->toBe('#444444')
        // The seeded hex is re-emitted (the editor draft is replaced whole),
        // the saved one is not (it is already on the row).
        ->and($draft->toArray())->toBe(['brand_primary' => '#444444']);
});

/*
 * The gate half of seeding: a turn that merely STARTED from the preview must
 * not hand it back — the editor already holds that draft, its Apply gate is
 * already up, and a re-staged copy would snapshot an edit nobody made.
 */
it('does not re-stage a seeded preview the turn left alone', function (): void {
    $draft = new SiteStyleDraft(DesignTokens::default(), defaultScheme(DefaultTheme::BOLD_EDITORIAL));

    expect($draft->touched())->toBeFalse()
        ->and($draft->toArray())->toBeNull();
});
