<?php

declare(strict_types=1);

use App\Actions\Templates\CreateClaimUrl;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\URL;

function claimUrlFor(Tenant $tenant, User $user): string
{
    return resolve(CreateClaimUrl::class)->handle($tenant, $user);
}

it('mints a signed link on the tenant s own host', function (): void {
    $tenant = Tenant::factory()->withDomain('jade-pearl')->create();
    $user = User::factory()->memberOf($tenant)->create();

    $url = claimUrlFor($tenant, $user);

    expect($url)->toStartWith('http://jade-pearl.'.$this->centralDomain().'/_claim/'.$user->getKey())
        ->and($url)->toContain('signature=');
});

it('signs someone in and drops them into the editor on their home page', function (): void {
    $tenant = Tenant::factory()->withDomain('jade-pearl')->create();
    $user = User::factory()->memberOf($tenant)->create();
    $home = $this->createTenantPage($tenant, [['type' => 'hero', 'data' => ['heading' => 'Hi']]]);

    $this->get(claimUrlFor($tenant, $user))
        ->assertRedirectContains('/'.$home->getKey().'/edit');

    expect(auth()->id())->toBe($user->getKey());
});

it('lands on the page list when the site somehow has no home page', function (): void {
    // Defensive rather than theoretical: provisioning and the claim link are
    // separate steps, and a person who arrives after a rolled-back draft
    // should still reach their panel rather than a 500.
    $tenant = Tenant::factory()->withDomain('jade-pearl')->create();
    $user = User::factory()->memberOf($tenant)->create();

    $this->get(claimUrlFor($tenant, $user))->assertRedirectContains('/admin/pages');

    expect(auth()->id())->toBe($user->getKey());
});

it('refuses an expired link', function (): void {
    $tenant = Tenant::factory()->withDomain('jade-pearl')->create();
    $user = User::factory()->memberOf($tenant)->create();

    $url = claimUrlFor($tenant, $user);

    $this->travel(16)->minutes();

    $this->get($url)->assertForbidden();

    expect(auth()->check())->toBeFalse();
});

it('refuses a tampered link', function (): void {
    $tenant = Tenant::factory()->withDomain('jade-pearl')->create();
    $user = User::factory()->memberOf($tenant)->create();
    $other = User::factory()->memberOf($tenant)->create();

    $url = str_replace('/_claim/'.$user->getKey(), '/_claim/'.$other->getKey(), claimUrlFor($tenant, $user));

    $this->get($url)->assertForbidden();

    expect(auth()->check())->toBeFalse();
});

it('refuses a validly signed link for someone who is not a member of this tenant', function (): void {
    // `tenant_user` carries `no-rls` so canAccessPanel() can read it from any
    // context — which means RLS is NOT what scopes this check, and the
    // controller has to make it explicitly.
    $tenant = Tenant::factory()->withDomain('jade-pearl')->create();
    $stranger = User::factory()->create();

    // Sign the stranger's id on this tenant's host: the signature is genuine,
    // the membership is not.
    $path = URL::temporarySignedRoute('site.claim', now()->addMinutes(15), ['user' => $stranger->getKey()], absolute: false);

    $this->get('http://jade-pearl.'.$this->centralDomain().$path)->assertNotFound();

    expect(auth()->check())->toBeFalse();
});

it('refuses a link minted for one tenant and spent on another', function (): void {
    $tenant = Tenant::factory()->withDomain('jade-pearl')->create();
    $other = Tenant::factory()->withDomain('other-shop')->create();
    $user = User::factory()->memberOf($tenant)->create();

    // Relative signing is what makes a link portable across hosts, which is
    // exactly why the membership check — not the signature — has to be what
    // stops it being spent on the wrong site.
    $url = str_replace('jade-pearl.', 'other-shop.', claimUrlFor($tenant, $user));

    $this->get($url)->assertNotFound();

    expect(auth()->check())->toBeFalse()
        ->and($other->domain?->domain)->toBe('other-shop');
});
