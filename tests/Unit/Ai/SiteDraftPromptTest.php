<?php

declare(strict_types=1);

use App\Ai\Prompts\SiteDraftPrompt;
use App\Models\Business;
use App\Models\Location;
use App\Models\Tenant;
use App\Site\Blocks\BlockVocabulary;

it('assembles the profile, locations, vocabulary, presets and language sections', function (): void {
    $tenant = Tenant::factory()->create();
    $business = $this->createTenantBusiness($tenant, [
        'name' => 'Corner Cafe',
        'category' => 'cafe',
        'description' => 'Neighborhood espresso bar.',
        'locale' => 'zh_TW',
    ], 0);
    $this->runInTenant($tenant, fn (): Location => Location::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'is_primary' => true,
        'label' => 'Main Street',
        'city' => 'Fremont',
    ]));

    $prompt = (string) new SiteDraftPrompt(
        Business::query()->findOrFail($business->getKey()),
        Location::query()->get(),
        resolve(BlockVocabulary::class)->all(),
    );

    expect($prompt)->toContain('Name: Corner Cafe')
        ->toContain('Neighborhood espresso bar.')
        ->toContain('Main Street (primary)')
        ->toContain('Fremont')
        ->toContain('"hero"') // vocabulary JSON
        // Not a copy snapshot: this is the second of three guards on "the AI never
        // authors chrome" (schema enum in SiteDraftAgentTest, server-side drop in
        // SiteDraftValidatorTest). Rewording the instruction should be deliberate.
        ->toContain('do NOT include them')
        ->toContain('warm-craft') // preset menu
        ->toContain('vibes:')
        ->toContain('Write all user-visible copy in: zh_TW');
});

it('degrades gracefully when the business has no locations and no locale', function (): void {
    $tenant = Tenant::factory()->create();
    $business = $this->createTenantBusiness($tenant, ['locale' => null], 0);

    $prompt = (string) new SiteDraftPrompt(
        Business::query()->findOrFail($business->getKey()),
        Location::query()->get(),
        resolve(BlockVocabulary::class)->all(),
    );

    expect($prompt)->toContain('None recorded yet.')
        ->toContain('Write all user-visible copy in: en');
});
