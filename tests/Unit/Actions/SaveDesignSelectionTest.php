<?php

declare(strict_types=1);

use App\Actions\SaveDesignSelection;
use App\Models\Business;
use App\Models\Tenant;
use App\Templates\SiteTemplate;
use App\Tenancy\RunInTenant;
use App\Themes\Definitions\DefaultTheme;
use Illuminate\Support\Facades\DB;

/**
 * @return array{Tenant, Business}
 */
function designSelectionBusiness(): array
{
    $tenant = Tenant::factory()->create();
    $business = resolve(RunInTenant::class)->handle(
        $tenant,
        fn (): Business => Business::factory()->themed(DefaultTheme::WARM_CRAFT)->create(['tenant_id' => $tenant->id]),
    );

    return [$tenant, Business::query()->findOrFail($business->getKey())];
}

/*
 * The single-write-path property, extended to the brand hexes: the Design page
 * and the chat rail both pass their selection through here, so this is where a
 * staged brand colour becomes a businesses-row colour.
 */
it('persists valid brand hexes and keeps the rest of the row for absent or junk ones', function (): void {
    [$tenant, $business] = designSelectionBusiness();

    $accentBefore = $business->brand_accent;

    $this->runInTenant($tenant, function () use ($business): void {
        $business->update(['brand_primary' => '#111111', 'brand_secondary' => '#222222']);

        resolve(SaveDesignSelection::class)->handle($business, [
            'scheme' => DefaultTheme::WARM_CRAFT,
            'brand_primary' => '#1A2B3C',
            // Junk is ignored, not written: this string would land inside a
            // <style> tag on the public site.
            'brand_secondary' => 'red; } body { display: none',
        ]);
    });

    $stored = Business::query()->findOrFail($business->getKey());

    expect($stored->brand_primary)->toBe('#1a2b3c')
        ->and($stored->brand_secondary)->toBe('#222222')
        // Never named in the selection at all — untouched.
        ->and($stored->brand_accent)->toBe($accentBefore);
});

it('saves a scheme the current theme offers as the whole look', function (): void {
    [$tenant, $business] = designSelectionBusiness();

    $this->runInTenant($tenant, fn (): Business => resolve(SaveDesignSelection::class)
        ->handle($business, ['scheme' => DefaultTheme::BOLD_EDITORIAL]));

    $tokens = Business::query()->findOrFail($business->getKey())->design_tokens;

    expect($tokens->toArray())->toBe(defaultScheme(DefaultTheme::BOLD_EDITORIAL)->tokens->toArray());
});

/*
 * A slug the current theme does not offer — another theme's, a retired one,
 * one the browser invented — is not a scheme here. Ignored rather than stored
 * or substituted, and the brand hexes in the same selection still land.
 */
it('ignores a scheme the current theme does not offer, saving the brand hexes regardless', function (): void {
    [$tenant, $business] = designSelectionBusiness();

    $this->runInTenant($tenant, fn (): Business => resolve(SaveDesignSelection::class)
        ->handle($business, ['scheme' => 'no-such-scheme', 'brand_primary' => '#1a2b3c']));

    $stored = Business::query()->findOrFail($business->getKey());

    expect($stored->design_tokens->scheme)->toBe(DefaultTheme::WARM_CRAFT)
        ->and($stored->brand_primary)->toBe('#1a2b3c');
});

it('leaves the tokens untouched when the selection names no scheme', function (): void {
    [$tenant, $business] = designSelectionBusiness();

    $before = $business->design_tokens->toArray();

    $returned = $this->runInTenant($tenant, fn (): Business => resolve(SaveDesignSelection::class)
        ->handle($business, ['scheme' => null, 'palette' => 'ocean']));

    expect($returned->is($business))->toBeTrue()
        // The stray axis in the selection is not a write path: nothing may
        // change one axis independently of a scheme.
        ->and(Business::query()->findOrFail($business->getKey())->design_tokens->toArray())->toBe($before);
});

/*
 * What a brand edit hands over: hexes and no scheme. On a template-provisioned
 * look (DimSumHouse — WarmCraft's marker on the Brand palette, a combination
 * no scheme's bare bundle matches) the stored tokens must not move by a byte;
 * re-applying the scheme here is exactly how the override used to be lost.
 */
it('leaves overridden tokens byte-identical when the selection is brand hexes only', function (): void {
    $tenant = Tenant::factory()->create();
    $business = resolve(RunInTenant::class)->handle($tenant, fn (): Business => Business::factory()->create([
        'tenant_id' => $tenant->id,
        'design_tokens' => SiteTemplate::DimSumHouse->definition()->tokens(),
    ]));

    $stored = fn (): mixed => DB::table('businesses')->where('id', $business->getKey())->value('design_tokens');
    $before = $stored();

    $this->runInTenant($tenant, fn (): Business => resolve(SaveDesignSelection::class)
        ->handle($business, ['scheme' => null, 'brand_primary' => '#1a2b3c']));

    expect($stored())->toBe($before)
        ->and(Business::query()->findOrFail($business->getKey())->brand_primary)->toBe('#1a2b3c');
});
