<?php

declare(strict_types=1);

use App\Actions\SaveSiteCapture;
use App\Filament\Tenant\Support\OnboardingTaskUrls;
use App\Filament\Tenant\Widgets\OnboardingChecklist;
use App\Models\Business;
use App\Models\Location;
use App\Models\Page;
use App\Models\Tenant;
use App\Site\OnboardingTask;
use Livewire\Livewire;

/**
 * Bring a tenant all the way to "set up", so a test only has to undo the one
 * thing it is about. The dashboard's checklist is only interesting in the gap
 * between provisioning and a finished site, and that gap is what these cover.
 */
function finishSetup(Tenant $tenant): void
{
    $business = test()->createTenantBusiness($tenant, [
        'contact_phone' => '+1 555 0100',
        'logo_path' => 'logos/jade.png',
    ], locations: 0);

    // Everything through runInTenant, which restores the caller's context on the
    // way out. `createTenantPage()` would end tenancy outright, and these tests
    // run with it initialized by actingAsTenantPanelMember().
    test()->runInTenant($tenant, function () use ($tenant, $business): void {
        Location::factory()->create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'is_primary' => true,
            'address_line1' => '24 Baker Street',
        ]);

        Page::query()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Home',
            'slug' => '/',
            'layout' => 'main',
            'blocks' => [],
        ]);

        resolve(SaveSiteCapture::class)->handle(['enabled' => true, 'heading' => 'Get 10% off'], []);
    });
}

it('lists what is left with the reason for each, and links somewhere useful', function (): void {
    finishSetup($this->tenant);

    $this->runInTenant($this->tenant, fn (): bool => Business::query()->firstOrFail()->update(['logo_path' => null]));
    app()->forgetScopedInstances();

    Livewire::test(OnboardingChecklist::class)
        ->assertSee(OnboardingTask::Logo->label())
        ->assertSee(OnboardingTask::Logo->why())
        ->assertSee(OnboardingTaskUrls::for(OnboardingTask::Logo))
        // A finished task keeps its row but loses the lecture.
        ->assertSee(OnboardingTask::PublishSite->label())
        ->assertDontSee(OnboardingTask::PublishSite->why())
        ->assertSee('One thing left to do.');
});

it('hides itself once the owner has finished every task', function (): void {
    finishSetup($this->tenant);
    app()->forgetScopedInstances();

    expect(OnboardingChecklist::canView())->toBeFalse();
});

it('shows for a site that still has work outstanding', function (): void {
    expect(OnboardingChecklist::canView())->toBeTrue();
});

it('sends every task somewhere in this panel', function (OnboardingTask $task): void {
    // The `match` in OnboardingTaskUrls has no default arm, so a new task
    // without a destination throws here rather than rendering a dead row.
    expect(OnboardingTaskUrls::for($task))->toContain('/admin/');
})->with(fn (): array => array_map(
    fn (OnboardingTask $task): array => [$task],
    OnboardingTask::cases(),
));
