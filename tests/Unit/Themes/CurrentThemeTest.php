<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Themes\CurrentTheme;
use App\Themes\Theme;

test('outside a tenant context the current theme is the default', function (): void {
    expect(tenant())->toBeNull()
        ->and(resolve(CurrentTheme::class)->theme())->toBe(Theme::Default)
        ->and(resolve(CurrentTheme::class)->definition()->label)->toBe('Default');
});

test("inside a tenant context the current theme is the tenant's", function (): void {
    $tenant = Tenant::factory()->create(['theme' => Theme::Default]);

    tenancy()->initialize($tenant);

    expect(resolve(CurrentTheme::class)->theme())->toBe(Theme::Default);
});
