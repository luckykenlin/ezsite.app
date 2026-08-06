<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
 * Before this existed, a forgotten panel password was unrecoverable without
 * staff intervention: both panels offered a login and nothing else, and the
 * template signup flow deliberately skips email verification, so a typo in the
 * signup address produced an account nobody could ever get back into.
 *
 * The tenant-panel half also pins a piece of Filament behaviour this app leans
 * on: it only mails the link to a user its `canAccessPanel()` accepts. That is
 * what stops one tenant's domain from being used to probe for, or issue reset
 * links to, another tenant's operators.
 */

test('both panels expose a password reset page', function (string $host): void {
    Tenant::factory()->withDomain('acme')->create();

    $this->get(sprintf('http://%s/admin/password-reset/request', $host))->assertOk();
})->with([
    'the tenant panel' => [fn (): string => 'acme.'.test()->centralDomain()],
    'the central panel' => [fn (): string => test()->centralDomain()],
]);

test('a member of the site gets a reset link for it', function (): void {
    Notification::fake();

    $tenant = Tenant::factory()->withDomain('acme')->create();
    $member = User::factory()->memberOf($tenant)->create();

    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('tenant'));

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $member->email])
        ->call('request')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($member, ResetPassword::class);
});

test('an operator of another site gets no link from this one', function (): void {
    Notification::fake();

    $tenant = Tenant::factory()->withDomain('acme')->create();
    $outsider = User::factory()->create();

    tenancy()->initialize($tenant);
    Filament::setCurrentPanel(Filament::getPanel('tenant'));

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $outsider->email])
        ->call('request')
        ->assertHasNoFormErrors();

    // Silently nothing, and the page still confirms — an error here would turn
    // the form into an account-enumeration oracle.
    Notification::assertNothingSentTo($outsider);
});

test('the central panel only issues links to super admins', function (): void {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $operator = User::factory()->memberOf($tenant)->create();
    $superAdmin = User::factory()->superAdmin()->create();

    Filament::setCurrentPanel(Filament::getPanel('central'));

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $operator->email])
        ->call('request');

    Notification::assertNothingSentTo($operator);

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $superAdmin->email])
        ->call('request');

    Notification::assertSentTo($superAdmin, ResetPassword::class);
});
