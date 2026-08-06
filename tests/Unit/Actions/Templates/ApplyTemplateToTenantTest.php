<?php

declare(strict_types=1);

use App\Actions\Templates\ApplyTemplateToTenant;
use App\Models\Business;
use App\Models\Location;
use App\Models\Page;
use App\Models\Tenant;
use App\Templates\SiteTemplate;

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
