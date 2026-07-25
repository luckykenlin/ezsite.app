<?php

declare(strict_types=1);

use App\Actions\ApplyStylePreset;
use App\Design\ColorPalette;
use App\Design\StylePreset;
use App\Models\Business;
use App\Models\Tenant;

it('writes the preset token bundle onto the business', function (): void {
    $tenant = Tenant::factory()->create();
    $business = $this->runInTenant($tenant, fn (): Business => Business::factory()->create(['tenant_id' => $tenant->id]));

    $this->runInTenant($tenant, fn (): Business => resolve(ApplyStylePreset::class)
        ->handle($business, StylePreset::FreshModern));

    $tokens = Business::query()->findOrFail($business->getKey())->design_tokens;

    expect($tokens->preset)->toBe(StylePreset::FreshModern)
        ->and($tokens->palette)->toBe(ColorPalette::Forest);
});
