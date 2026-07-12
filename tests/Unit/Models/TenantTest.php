<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

test('users relation returns attached users', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user);

    expect($tenant->users()->whereKey($user->id)->exists())->toBeTrue();
});

test('to array', function (): void {
    $tenant = Tenant::factory()->create();
    $tenant = Tenant::query()->findOrFail($tenant->getKey());

    expect(array_keys($tenant->toArray()))
        ->toBe([
            'id',
            'name',
            'email',
            'created_at',
            'updated_at',
            'data',
        ]);
});
