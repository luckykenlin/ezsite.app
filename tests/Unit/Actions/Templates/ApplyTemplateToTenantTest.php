<?php

declare(strict_types=1);

use App\Actions\Templates\ApplyTemplateToTenant;
use App\Models\Business;
use App\Models\Location;
use App\Models\Page;
use App\Models\Tenant;
use App\Templates\SiteTemplate;
use App\Themes\Theme;

it('rolls the whole site back when a step throws mid-way', function (): void {
    // The action owns the transaction precisely so a caller cannot forget it:
    // before the extraction, the demo path ran un-wrapped and a throw here
    // left a half-built site behind. The listener stands in for any real
    // failure inside the draft step — every action in the chain is final, so
    // the failure is injected at the model layer rather than mocked.
    $tenant = Tenant::factory()->create();

    Page::creating(function (): void {
        throw new RuntimeException('draft failed');
    });

    $this->runInTenant($tenant, function () use ($tenant): void {
        expect(fn (): Business => resolve(ApplyTemplateToTenant::class)->handle($tenant, SiteTemplate::NailSalon->definition()))
            ->toThrow(RuntimeException::class, 'draft failed')
            ->and(Business::query()->count())->toBe(0)
            ->and(Location::query()->count())->toBe(0);
    });
});

/*
 * Both provisioners build this action in the CENTRAL context, where the
 * current theme is the default one. A template on a vertical theme must still
 * land with its own layouts and its theme-exclusive blocks — validated against
 * the theme the tenant renders through, not the one current where the action
 * happened to be constructed.
 */
it('validates the draft against the provisioned tenant\'s theme, wherever the action was built', function (): void {
    $tenant = Tenant::factory()->create(['theme' => Theme::NailLuxe]);
    $action = resolve(ApplyTemplateToTenant::class);

    $this->runInTenant($tenant, fn (): Business => $action->handle($tenant, SiteTemplate::NailSalon->definition()));

    $home = Page::query()->where('slug', '/')->firstOrFail();
    $blocks = $home->draft ?? $home->blocks;

    expect(array_column($blocks, 'type'))->toContain('credentials')
        ->and($blocks[0]['data']['variant'])->toBe('arch');
});
