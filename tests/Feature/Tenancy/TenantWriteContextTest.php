<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\CreatePostForTenant;

test('the guard rejects writing an RLS-scoped model in central context', function (): void {
    $tenant = Tenant::factory()->create();

    // No tenancy initialized: tenant() is null, so the write would land under the
    // BYPASSRLS central connection with no scope — the guard must fail loud.
    expect(fn () => Post::query()->create(['tenant_id' => $tenant->id, 'title' => 'Orphan']))
        ->toThrow(RuntimeException::class);
});

test('RunInTenant writes land under the target tenant and are isolated from others', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $postId = $this->runInTenant($tenantA, fn (): int => Post::query()->create([
        'tenant_id' => $tenantA->id,
        'title' => 'A-only',
    ])->getKey());

    $visibleToA = $this->runInTenant($tenantA, fn (): bool => Post::query()->whereKey($postId)->exists());
    $visibleToB = $this->runInTenant($tenantB, fn (): bool => Post::query()->whereKey($postId)->exists());

    expect($visibleToA)->toBeTrue()
        ->and($visibleToB)->toBeFalse();
});

test('RunInTenant reverts the context even when the callback throws', function (): void {
    $tenant = Tenant::factory()->create();

    expect(fn () => $this->runInTenant($tenant, function (): void {
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class, 'boom')
        ->and(tenant())->toBeNull();
});

test('RunInTenant restores the previous tenant context when nested', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $this->runInTenant($tenantA, function () use ($tenantA, $tenantB): void {
        expect(tenant()->getTenantKey())->toBe($tenantA->getTenantKey());

        $this->runInTenant($tenantB, function () use ($tenantB): void {
            expect(tenant()->getTenantKey())->toBe($tenantB->getTenantKey());
        });

        // Nested revert restores A, not central.
        expect(tenant()->getTenantKey())->toBe($tenantA->getTenantKey());
    });

    expect(tenant())->toBeNull();
});

test('RunInTenant runs the callback with the RLS session variable set to the tenant', function (): void {
    $tenant = Tenant::factory()->create();

    $active = $this->runInTenant($tenant, fn (): ?string => DB::scalar(
        "SELECT current_setting('".config('tenancy.rls.session_variable_name')."', true)"
    ));

    expect($active)->toBe((string) $tenant->getTenantKey());
});

test('a TenantAware job dispatched from central writes to the target tenant', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    // Central context, as a cron/webhook dispatch would be.
    expect(tenant())->toBeNull();

    dispatch_sync(new CreatePostForTenant($tenantA->id, 'from-job'));

    // Context is restored after the job, and the row is scoped to tenant A only.
    expect(tenant())->toBeNull();

    $countA = $this->runInTenant($tenantA, fn (): int => Post::query()->count());
    $countB = $this->runInTenant($tenantB, fn (): int => Post::query()->count());

    expect($countA)->toBe(1)
        ->and($countB)->toBe(0);
});
