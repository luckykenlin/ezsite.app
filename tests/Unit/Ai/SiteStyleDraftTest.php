<?php

declare(strict_types=1);

use App\Ai\SiteStyleDraft;
use App\Design\ColorPalette;
use App\Design\DesignTokens;
use App\Design\RadiusScale;
use App\Design\StylePreset;
use App\Design\TokenKey;

/*
 * The safety property this class exists for. A chat turn runs in a queue worker;
 * a tool that called UpdateDesignTokens there would write immediately, and
 * ThemeVariables::style() reads SAVED tokens on every public render — so the
 * assistant would repaint a live website while its owner was still reading the
 * reply. Nothing here may touch the database.
 */
it('starts from the saved style and reports nothing staged', function (): void {
    $saved = StylePreset::CalmCoastal->tokens();

    $draft = new SiteStyleDraft($saved);

    expect($draft->current())->toBe($saved)
        ->and($draft->touched())->toBeFalse()
        // Null is what tells the editor there is nothing to preview or apply.
        ->and($draft->toArray())->toBeNull();
});

it('stages a preset without disturbing the saved tokens', function (): void {
    $saved = DesignTokens::default();
    $draft = new SiteStyleDraft($saved);

    $draft->applyPreset(StylePreset::WarmCraft);

    expect($draft->current()->palette)->toBe(StylePreset::WarmCraft->tokens()->palette)
        ->and($draft->current()->preset)->toBe(StylePreset::WarmCraft)
        ->and($draft->touched())->toBeTrue()
        // The object it was constructed with is untouched — the "saved" side.
        ->and($saved->palette)->toBe(ColorPalette::Default);
});

it('fine-tunes on top of what is already staged', function (): void {
    $draft = new SiteStyleDraft(DesignTokens::default());

    $draft->applyPreset(StylePreset::WarmCraft);
    $draft->apply([TokenKey::Radius->value => RadiusScale::None->value]);

    expect($draft->current()->radius)->toBe(RadiusScale::None)
        // Everything the preset chose survives the tweak.
        ->and($draft->current()->palette)->toBe(StylePreset::WarmCraft->tokens()->palette);
});

/*
 * Inherited from DesignTokens::with(), and it must stay inherited: the Design
 * modal and the assistant have to agree on when a combination stops being a
 * preset, or SaveDesignSelection would store an AI-tuned look as the untouched
 * preset it no longer is.
 */
it('detaches the preset marker once a token is tuned by hand', function (): void {
    $draft = new SiteStyleDraft(DesignTokens::default());

    $draft->applyPreset(StylePreset::BoldEditorial);
    $draft->apply([TokenKey::Palette->value => ColorPalette::Ocean->value]);

    expect($draft->current()->preset)->toBeNull();
});

it('leaves a token alone when its change is absent or unrecognised', function (mixed $value): void {
    $draft = new SiteStyleDraft(StylePreset::FreshModern->tokens());

    $draft->apply($value === null ? [] : [TokenKey::Palette->value => $value]);

    expect($draft->current()->palette)->toBe(StylePreset::FreshModern->tokens()->palette);
})->with([
    'absent' => [null],
    'unrecognised' => ['chartreuse'],
]);

/*
 * The shape is the contract with two consumers that were written before this
 * class: PageEditor::previewDesign() stages exactly these keys, and
 * SaveDesignSelection reads exactly these keys. If they matched only by luck
 * there would be an adapter somewhere on this path; there is not.
 */
it('serialises into the shape the editor previews and the writer consumes', function (): void {
    $draft = new SiteStyleDraft(DesignTokens::default());
    $draft->applyPreset(StylePreset::ProfessionalMinimal);

    expect($draft->toArray())->toBe(StylePreset::ProfessionalMinimal->tokens()->toArray());
});

/*
 * Cross-turn continuity. A previous turn's staged-but-unapplied style rides
 * back in as the seed: "a bit darker" must refine what the operator is looking
 * at, not silently restart from the saved tokens.
 */
it('reads from a seeded preview and fine-tunes on top of it', function (): void {
    $seeded = StylePreset::BoldEditorial->tokens();
    $draft = new SiteStyleDraft(DesignTokens::default(), $seeded);

    expect($draft->current())->toBe($seeded)
        ->and($draft->seededPreview())->toBeTrue()
        // The saved side is untouched by the seed: a new page created this
        // turn must still be laid out for the style the site actually has.
        ->and($draft->saved())->toEqual(DesignTokens::default());

    $draft->apply([TokenKey::Radius->value => RadiusScale::None->value]);

    expect($draft->current()->radius)->toBe(RadiusScale::None)
        ->and($draft->current()->palette)->toBe($seeded->palette);
});

/*
 * The brand layer: staged hexes ride inside the one draft array, the seeded
 * draft's hexes survive a later fine-tune, and the saved row's hexes count for
 * "is the brand palette legal" without ever being re-emitted.
 */
it('carries staged brand hexes inside the draft array', function (): void {
    $draft = new SiteStyleDraft(DesignTokens::default());

    $draft->stageBrand(['brand_primary' => '#1a2b3c']);
    $draft->apply([TokenKey::Palette->value => ColorPalette::Brand->value]);

    expect($draft->toArray()['brand_primary'])->toBe('#1a2b3c')
        ->and($draft->toArray()['palette'])->toBe(ColorPalette::Brand->value)
        ->and($draft->brand())->toBe(['brand_primary' => '#1a2b3c']);
});

it('layers brand hexes staged over seeded over saved', function (): void {
    $draft = new SiteStyleDraft(
        DesignTokens::default(),
        StylePreset::BoldEditorial->tokens(),
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
    $draft = new SiteStyleDraft(DesignTokens::default(), StylePreset::BoldEditorial->tokens());

    expect($draft->touched())->toBeFalse()
        ->and($draft->toArray())->toBeNull();

    $draft->apply([TokenKey::Radius->value => RadiusScale::None->value]);

    expect($draft->touched())->toBeTrue()
        ->and($draft->toArray())->not->toBeNull();
});
