<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Templates\SiteTemplate;
use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Support\Facades\Artisan;

it('seeds a super admin and every demo site', function (): void {
    // Regression guard: `WithoutModelEvents` on the seeder muted the `creating`
    // listener that fills a tenant's UUID key, so every demo site died on a
    // null id. Assert the keys, not just the count.
    $this->artisan('db:seed')->assertSuccessful();

    expect(User::query()->where('is_super_admin', true)->count())->toBe(1)
        ->and(Tenant::query()->demo()->count())->toBe(count(SiteTemplate::cases()))
        ->and(Tenant::query()->demo()->pluck('id')->all())->each->toBeString();
});

it('keeps db:seed pointed at laravel own seed command', function (): void {
    // Regression guard: tenancy's `tenants:seed` inherits Laravel's `db:seed`
    // signature, so it silently took the `db:seed` name over — and then failed
    // on its own missing `--tenants` option. See
    // TenancyServiceProvider::reclaimDbSeedCommand().
    expect(Artisan::all()['db:seed'])->toBeInstanceOf(SeedCommand::class);
});
