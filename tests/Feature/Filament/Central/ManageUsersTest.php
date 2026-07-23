<?php

declare(strict_types=1);

use App\Filament\Resources\Tenants\Pages\ManageUsers;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

test('can list tenant users', function (): void {
    $tenant = Tenant::factory()->create();
    $users = User::factory()->count(3)->create();
    $tenant->users()->attach($users);

    Livewire::test(ManageUsers::class, ['record' => $tenant->getKey()])
        ->call('loadTable')
        ->assertCanSeeTableRecords($users);
});

test('can attach a user to a tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    Livewire::test(ManageUsers::class, ['record' => $tenant->getKey()])
        ->callAction(TestAction::make(AttachAction::class)->table(), ['recordId' => $user->id])
        ->assertHasNoFormErrors();

    expect($tenant->users()->whereKey($user->id)->exists())->toBeTrue();
});

test('can detach a user from a tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $tenant->users()->attach($user);

    Livewire::test(ManageUsers::class, ['record' => $tenant->getKey()])
        ->callAction(TestAction::make(DetachAction::class)->table($user));

    expect($tenant->users()->whereKey($user->id)->exists())->toBeFalse();
});
