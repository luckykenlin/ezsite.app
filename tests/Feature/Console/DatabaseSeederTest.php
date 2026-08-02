<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Templates\SiteTemplate;

it('seeds a super admin and every demo site', function (): void {
    // Regression guard: `WithoutModelEvents` on the seeder muted the `creating`
    // listener that fills a tenant's UUID key, so every demo site died on a
    // null id. Assert the keys, not just the count.
    $this->artisan('db:seed')->assertSuccessful();

    expect(User::query()->where('is_super_admin', true)->count())->toBe(1)
        ->and(Tenant::query()->demo()->count())->toBe(count(SiteTemplate::cases()))
        ->and(Tenant::query()->demo()->pluck('id')->all())->each->toBeString();
});
