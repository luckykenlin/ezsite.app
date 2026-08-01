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
        // The vocabulary is structured now: purpose + intent + fields + layouts,
        // so the model can pick a block AND its layout by purpose, not by name.
        ->toContain('"purpose"')
        ->toContain('"layouts"')
        ->toContain('"introduce"')
        // Chrome never appears in the vocabulary the model composes from…
        ->not->toContain('"header"')
        ->not->toContain('"footer"')
        // Not a copy snapshot: this is the second of three guards on "the AI never
        // authors chrome" (schema enum in SiteDraftAgentTest, server-side drop in
        // SiteDraftValidatorTest). Rewording the instruction should be deliberate.
        ->toContain('do NOT include them')
        // Two fidelity rules earned by real DeepSeek output: icon fields came
        // back as words ("scissors") and prose came back heading-only.
        ->toContain('ONE emoji character')
        ->toContain('always include paragraphs')
        // The design guidance teaches rhythm from the enums' own descriptions.
        ->toContain('## Design guidance')
        ->toContain('no rhythm at all')
        // Two prompt bugs must stay dead: the copyable example sequence that a
        // low-temperature model reproduced verbatim on every draft, and the
        // instruction to pad sparse profiles with neutral filler copy.
        ->not->toContain('features, gallery, testimonials, contact, cta')
        ->not->toContain('STILL compose the full page')
        ->toContain('warm-craft') // preset menu
        ->toContain('vibes:')
        ->toContain('Write all user-visible copy in: zh_TW');
});

it('teaches the image-query rule only when the stock-photo pipeline can act on it', function (): void {
    $tenant = Tenant::factory()->create();
    $business = $this->createTenantBusiness($tenant, [], 0);

    $build = fn (): string => (string) new SiteDraftPrompt(
        Business::query()->findOrFail($business->getKey()),
        Location::query()->get(),
        resolve(BlockVocabulary::class)->all(),
    );

    // Disabled (the default): asking for a query that would be silently
    // discarded wastes tokens and trust.
    expect($build())->not->toContain('image_query');

    config()->set('stock-photos.enabled', true);

    expect($build())->toContain('image_query')
        ->toContain('ENGLISH');
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
