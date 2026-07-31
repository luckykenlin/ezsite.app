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
