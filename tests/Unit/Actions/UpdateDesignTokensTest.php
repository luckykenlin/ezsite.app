<?php

declare(strict_types=1);

use App\Actions\UpdateDesignTokens;
use App\Design\ColorPalette;
use App\Design\FontPair;
use App\Design\RadiusScale;
use App\Design\SpacingDensity;
use App\Models\Business;
use App\Models\Tenant;
use App\Tenancy\RunInTenant;
use App\Themes\Definitions\DefaultTheme;

function tokenBusiness(): array
{
    $tenant = Tenant::factory()->create();
    $business = resolve(RunInTenant::class)->handle(
        $tenant,
        fn (): Business => Business::factory()->themed(DefaultTheme::WARM_CRAFT)->create(['tenant_id' => $tenant->id]),
    );

    return [$tenant, Business::query()->findOrFail($business->getKey())];
}

it('merges partial changes and detaches the preset', function (): void {
    [$tenant, $business] = tokenBusiness();

    $this->runInTenant($tenant, fn (): Business => resolve(UpdateDesignTokens::class)
        ->handle($business, ['palette' => 'ocean', 'radius' => 'none']));

    $tokens = Business::query()->findOrFail($business->getKey())->design_tokens;

    expect($tokens->palette)->toBe(ColorPalette::Ocean)
        ->and($tokens->radius)->toBe(RadiusScale::None)
        ->and($tokens->fontPair)->toBe(FontPair::WarmEditorial) // untouched (WarmCraft)
        ->and($tokens->density)->toBe(SpacingDensity::Spacious) // untouched (WarmCraft)
        ->and($tokens->scheme)->toBeNull();
});

it('throws loudly on an unknown token key', function (): void {
    [$tenant, $business] = tokenBusiness();

    expect(fn (): Business => resolve(UpdateDesignTokens::class)->handle($business, ['scheme' => 'warm-craft']))
        ->toThrow(InvalidArgumentException::class, 'Unknown design token key(s): scheme');
});

it('throws loudly on an invalid token value', function (): void {
    [$tenant, $business] = tokenBusiness();

    expect(fn (): Business => resolve(UpdateDesignTokens::class)->handle($business, ['palette' => 'hot-pink']))
        ->toThrow(InvalidArgumentException::class, 'Invalid value [hot-pink] for design token [palette]');
});
