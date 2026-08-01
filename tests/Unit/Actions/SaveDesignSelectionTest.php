<?php

declare(strict_types=1);

use App\Actions\SaveDesignSelection;
use App\Design\ColorPalette;
use App\Design\FontPair;
use App\Design\RadiusScale;
use App\Design\SpacingDensity;
use App\Design\StylePreset;
use App\Design\TokenKey;
use App\Models\Business;
use App\Models\Tenant;
use App\Tenancy\RunInTenant;

/**
 * @return array{Tenant, Business}
 */
function designSelectionBusiness(): array
{
    $tenant = Tenant::factory()->create();
    $business = resolve(RunInTenant::class)->handle(
        $tenant,
        fn (): Business => Business::factory()->themed(StylePreset::WarmCraft)->create(['tenant_id' => $tenant->id]),
    );

    return [$tenant, Business::query()->findOrFail($business->getKey())];
}

/**
 * @return array<string, string>
 */
function selectionFor(StylePreset $preset): array
{
    // The fixture mirrors what the Design form actually posts: one field per
    // TokenKey, so a token added to the system is submitted here too instead
    // of silently "diverging" and detaching every preset.
    $tokens = $preset->tokens();
    $selection = ['preset' => $preset->value];

    foreach (TokenKey::cases() as $key) {
        $selection[$key->value] = $key->valueOn($tokens);
    }

    return $selection;
}

it('saves an untouched preset as that preset', function (): void {
    [$tenant, $business] = designSelectionBusiness();

    $this->runInTenant($tenant, fn (): Business => resolve(SaveDesignSelection::class)
        ->handle($business, selectionFor(StylePreset::BoldEditorial)));

    $tokens = Business::query()->findOrFail($business->getKey())->design_tokens;

    expect($tokens->preset)->toBe(StylePreset::BoldEditorial)
        ->and($tokens->palette)->toBe(StylePreset::BoldEditorial->tokens()->palette);
});

it('detaches the preset as soon as one fine-tune field diverges', function (): void {
    [$tenant, $business] = designSelectionBusiness();

    // BoldEditorial's own radius is None, so Full is a genuine divergence.
    $selection = [...selectionFor(StylePreset::BoldEditorial), 'radius' => RadiusScale::Full->value];

    $this->runInTenant($tenant, fn (): Business => resolve(SaveDesignSelection::class)
        ->handle($business, $selection));

    $tokens = Business::query()->findOrFail($business->getKey())->design_tokens;

    expect($tokens->preset)->toBeNull()
        ->and($tokens->radius)->toBe(RadiusScale::Full)
        ->and($tokens->palette)->toBe(StylePreset::BoldEditorial->tokens()->palette);
});

it('treats a cleared preset as a custom combination', function (): void {
    [$tenant, $business] = designSelectionBusiness();

    $selection = [...selectionFor(StylePreset::BoldEditorial), 'preset' => null];

    $this->runInTenant($tenant, fn (): Business => resolve(SaveDesignSelection::class)
        ->handle($business, $selection));

    $tokens = Business::query()->findOrFail($business->getKey())->design_tokens;

    expect($tokens->preset)->toBeNull()
        ->and($tokens->fontPair)->toBe(StylePreset::BoldEditorial->tokens()->fontPair);
});

it('leaves a token untouched when the form omits its field', function (): void {
    [$tenant, $business] = designSelectionBusiness();

    // Only the palette was supplied — the Brand-colors style of conditionally
    // hidden fields must not be written as null.
    $this->runInTenant($tenant, fn (): Business => resolve(SaveDesignSelection::class)
        ->handle($business, ['palette' => ColorPalette::Ocean->value]));

    $tokens = Business::query()->findOrFail($business->getKey())->design_tokens;

    expect($tokens->palette)->toBe(ColorPalette::Ocean)
        ->and($tokens->fontPair)->toBe(FontPair::ElegantSerif) // untouched (WarmCraft)
        ->and($tokens->density)->toBe(SpacingDensity::Spacious); // untouched (WarmCraft)
});
