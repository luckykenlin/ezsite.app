<?php

declare(strict_types=1);

use App\Actions\Pages\BuildPresetPageBlocks;
use App\Design\StylePreset;
use App\Models\Location;
use App\Models\Tenant;
use App\Site\Blocks\BlockShape;
use App\Templates\PagePreset;

/**
 * @return array{title: string, metaDescription: string|null, blocks: list<array{type: string, data: array<string, mixed>}>}
 */
function buildPresetFor(Tenant $tenant, PagePreset $preset): array
{
    return test()->runInTenant($tenant, fn (): array => resolve(BuildPresetPageBlocks::class)->handle($preset));
}

it('personalizes the copy from the business and its primary location', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [
        'name' => 'The Lacquer Room',
        'tagline' => 'Hand-painted nails, one chair at a time',
    ]);
    $this->runInTenant($tenant, fn () => Location::query()->firstOrFail()->update([
        'city' => 'Savannah',
        'phone' => '(912) 555-0184',
    ]));

    $built = buildPresetFor($tenant, PagePreset::Home);

    expect($built['title'])->toBe('Home')
        ->and($built['metaDescription'])->toBe('The Lacquer Room — Hand-painted nails, one chair at a time. Based in Savannah.')
        ->and($built['blocks'][0]['data']['eyebrow'])->toBe('Savannah')
        ->and($built['blocks'][0]['data']['heading'])->toBe('Hand-painted nails, one chair at a time')
        ->and($built['blocks'][1]['data']['heading'])->toBe('Why The Lacquer Room');

    // The closing CTA dials the location's phone, not a placeholder.
    $cta = end($built['blocks']);

    expect($cta['data']['cta_label'])->toBe('Call (912) 555-0184')
        ->and($cta['data']['cta_url'])->toBe('tel:(912) 555-0184');
});

it('falls back from the location to the business for phone and email', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, ['contact_phone' => '(555) 010-0000']);
    $this->runInTenant($tenant, fn () => Location::query()->firstOrFail()->update([
        'phone' => null,
        'email' => null,
    ]));

    $built = buildPresetFor($tenant, PagePreset::Home);

    expect(end($built['blocks'])['data']['cta_label'])->toBe('Call (555) 010-0000');
});

it('answers every token with neutral replace-me copy when no rows exist', function (): void {
    $tenant = Tenant::factory()->create();

    $built = buildPresetFor($tenant, PagePreset::Home);

    expect($built['blocks'][0]['data']['eyebrow'])->toBe('your area')
        ->and($built['blocks'][0]['data']['heading'])->toBe('A line that says what you do')
        ->and($built['metaDescription'])->toBe('Your business — A line that says what you do. Based in your area.')
        ->and(end($built['blocks'])['data']['cta_label'])->toBe('Call (000) 000-0000');
});

it('re-lays the blocks to the site style preset without losing authored variants', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [
        'design_tokens' => StylePreset::QuietLuxe->tokens(),
    ]);

    $built = buildPresetFor($tenant, PagePreset::Home);

    // fill(), not handle(): the preset's designed hero variant survives the
    // site style, which only speaks where the author was silent.
    expect($built['blocks'][0]['data'][BlockShape::VARIANT_KEY])->toBe('full-bleed-overlay');

    // The back-fill half is visible on appearance: the preset picker authors
    // no tones, so any appearance on a middle block came from the site style.
    $appearances = array_filter(array_map(
        static fn (array $block): mixed => $block['data'][BlockShape::APPEARANCE_KEY] ?? null,
        $built['blocks'],
    ));

    expect($appearances)->not->toBeEmpty();
});

it('leaves the blocks unstamped on a custom palette with no preset', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant); // default design_tokens: preset null

    $built = buildPresetFor($tenant, PagePreset::Home);

    $appearances = array_filter(array_map(
        static fn (array $block): mixed => $block['data'][BlockShape::APPEARANCE_KEY] ?? null,
        $built['blocks'],
    ));

    expect($appearances)->toBeEmpty()
        ->and($built['blocks'][0]['data'][BlockShape::VARIANT_KEY])->toBe('full-bleed-overlay');
});

it('builds Blank as an empty shell with no meta description', function (): void {
    // CreatePresetPage short-circuits Blank before reaching this action, but
    // the pipeline still answers for it honestly — the thumbnail route asks
    // by enum, and a preset with nothing to say must not invent a meta.
    $tenant = Tenant::factory()->create();

    $built = buildPresetFor($tenant, PagePreset::Blank);

    expect($built['title'])->toBe('Untitled page')
        ->and($built['metaDescription'])->toBeNull()
        ->and($built['blocks'])->toBeEmpty();
});

it('carries the image queries through under the reserved transit key', function (): void {
    config(['stock-photos.enabled' => true]);

    $tenant = Tenant::factory()->create();

    $built = buildPresetFor($tenant, PagePreset::Home);

    expect($built['blocks'][0]['data'][BlockShape::IMAGE_QUERY_KEY])->toBe('welcoming small business interior');
});
