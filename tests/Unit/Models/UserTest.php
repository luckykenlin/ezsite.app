<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;

test('cannot access the tenant panel when no tenant has been resolved', function (): void {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Filament::getPanel('tenant')))->toBeFalse();
});

test('the memberOf scope finds the operators of one tenant and nobody else', function (): void {
    // Who runs a site is a membership question, so super admins are excluded on
    // purpose: they can open any panel, but they must not receive every tenant's
    // enquiry mail. Both callers (the panel bell and the mail job) depend on it.
    $tenant = Tenant::factory()->create();
    $members = User::factory()->count(2)->memberOf($tenant)->create();

    User::factory()->create();
    User::factory()->superAdmin()->create();
    User::factory()->memberOf(Tenant::factory()->create())->create();

    expect(User::query()->memberOf($tenant->id)->pluck('id')->sort()->values()->all())
        ->toBe($members->pluck('id')->sort()->values()->all());
});

test('to array', function (): void {
    $user = User::factory()->create()->refresh();

    expect(array_keys($user->toArray()))
        ->toBe([
            'id',
            'name',
            'email',
            'email_verified_at',
            'created_at',
            'updated_at',
            'is_super_admin',
        ]);
});
