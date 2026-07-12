<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;

test('cannot access the tenant panel when no tenant has been resolved', function (): void {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Filament::getPanel('tenant')))->toBeFalse();
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
