<?php

declare(strict_types=1);

use App\Actions\GenerateSiteDraft;
use App\Actions\RunInTenant;
use App\Ai\Agents\SiteDraftAgent;
use App\Design\StylePreset;
use App\Enums\PageStatus;
use App\Exceptions\SiteDraftInvalid;
use App\Models\Business;
use App\Models\Page;
use App\Models\Tenant;
use Laravel\Ai\Prompts\AgentPrompt;

function fakeDraftResponse(array $overrides = []): array
{
    return array_replace_recursive([
        'preset' => 'warm-craft',
        'rationale' => 'Earthy tones suit a bakery.',
        'pages' => [[
            'title' => 'Corner Cafe — Home',
            'slug' => '/',
            'blocks' => [
                ['type' => 'hero', 'data' => ['heading' => 'Welcome friends', 'variant' => 'full-bleed-overlay']],
                ['type' => 'features', 'data' => ['heading' => 'Why us', 'features' => [['title' => 'Handmade']]]],
                ['type' => 'contact', 'data' => ['heading' => 'Visit us', 'bind' => ['location_id' => 999]]],
            ],
        ]],
    ], $overrides);
}

function generateFor(Tenant $tenant): Page
{
    return resolve(RunInTenant::class)->handle(
        $tenant,
        fn (): Page => resolve(GenerateSiteDraft::class)->handle(Business::query()->firstOrFail()),
    );
}

it('persists a draft home page with preset-stamped variants and no AI-authored reserved keys', function (): void {
    SiteDraftAgent::fake([fakeDraftResponse()])->preventStrayPrompts();

    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe'], 1);

    generateFor($tenant);
    $page = Page::query()->sole();

    tenancy()->initialize($otherTenant);
    $visibleToOther = Page::query()->count();
    tenancy()->end();

    expect($page->tenant_id)->toBe($tenant->id)
        // RLS scopes the generated page to its own tenant.
        ->and($visibleToOther)->toBe(0)
        ->and($page->slug)->toBe('/')
        ->and($page->status)->toBe(PageStatus::Draft)
        ->and($page->title)->toBe('Corner Cafe — Home')
        // Variants come from the WarmCraft preset, not from the AI output.
        ->and($page->blocks[0]['data']['variant'])->toBe('left-text-right-image')
        ->and($page->blocks[1]['data']['variant'])->toBe('list')
        ->and($page->blocks[2]['data']['variant'])->toBe('split')
        // The AI-authored bind was stripped; omission = primary location.
        ->and($page->blocks[2]['data'])->not->toHaveKey('bind')
        ->and(Business::query()->sole()->design_tokens->preset)->toBe(StylePreset::WarmCraft);

    SiteDraftAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Corner Cafe'));
});

it('overwrites an existing draft in place on regeneration', function (): void {
    SiteDraftAgent::fake([
        fakeDraftResponse(),
        fakeDraftResponse(['pages' => [['title' => 'Second take']]]),
    ]);

    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);

    $first = generateFor($tenant);
    $second = generateFor($tenant);

    expect($second->getKey())->toBe($first->getKey())
        ->and(Page::query()->count())->toBe(1)
        ->and(Page::query()->sole()->title)->toBe('Second take');
});

it('stores the ai-written meta description on the draft page', function (): void {
    SiteDraftAgent::fake([
        fakeDraftResponse(['pages' => [['meta_description' => 'Fresh sourdough baked daily in Austin.']]]),
    ]);

    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);

    expect(generateFor($tenant)->seo_description)->toBe('Fresh sourdough baked daily in Austin.');
});

it('keeps an operator-written description when the model omits one', function (): void {
    SiteDraftAgent::fake([
        fakeDraftResponse(['pages' => [['meta_description' => 'Written by the model.']]]),
        fakeDraftResponse(),
    ]);

    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);

    generateFor($tenant);
    $regenerated = generateFor($tenant);

    expect($regenerated->seo_description)->toBe('Written by the model.');
});

it('refuses to touch a published home page', function (): void {
    SiteDraftAgent::fake([fakeDraftResponse()]);

    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['heading' => 'Live site']],
    ]);

    expect(fn (): Page => generateFor($tenant))
        ->toThrow(SiteDraftInvalid::class, 'already published')
        ->and(Page::query()->sole()->title)->toBe('Home'); // untouched
});

it('recovers from one drifted response by retrying', function (): void {
    SiteDraftAgent::fake([
        // First attempt drifts off-schema (an empty blocks list)...
        fakeDraftResponse(['pages' => [['blocks' => []]]]),
        // ...the retry lands.
        fakeDraftResponse(),
    ]);

    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);

    generateFor($tenant);

    expect(Page::query()->sole()->title)->toBe('Corner Cafe — Home');
});

it('rejects a response without structured output after the retry also fails', function (): void {
    SiteDraftAgent::fake([
        'plain text, not a structured draft',
        'still plain text on the retry',
    ]);

    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);

    expect(fn (): Page => generateFor($tenant))
        ->toThrow(SiteDraftInvalid::class, 'no structured output');
});
