<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

test('can list users', function (): void {
    $users = User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->call('loadTable')
        ->assertCanSeeTableRecords($users);
});

test('can create a user', function (): void {
    Livewire::test(ListUsers::class)
        ->callAction(CreateAction::class, [
            'name' => 'Test User',
            'email' => 'test-user@example.com',
            'password' => 'password',
        ])
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'test-user@example.com')->firstOrFail();

    expect(Hash::check('password', $user->password))->toBeTrue();
});

test('can update a user without changing the password, through an edit form prefilled with their email', function (): void {
    $user = User::factory()->create();
    $originalPassword = $user->password;

    Livewire::test(ListUsers::class)
        ->mountAction(TestAction::make(EditAction::class)->table($user))
        ->assertSchemaStateSet(['email' => $user->email])
        ->setActionData(['name' => 'Updated Name', 'password' => null])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->not->toBeEmpty()
        ->and($user->password)->toBe($originalPassword);
});

test('can delete a user', function (): void {
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make(DeleteAction::class)->table($user));

    $this->assertModelMissing($user);
});

test('cannot delete their own account', function (): void {
    $currentUser = User::factory()->create();
    $this->actingAs($currentUser);

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make(DeleteAction::class)->table($currentUser));

    $this->assertModelExists($currentUser);
});

test('cannot bulk delete their own account', function (): void {
    $currentUser = User::factory()->create();
    $this->actingAs($currentUser);
    $otherUser = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->selectTableRecords([$currentUser, $otherUser])
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk());

    $this->assertModelExists($currentUser);
    $this->assertModelMissing($otherUser);
});
