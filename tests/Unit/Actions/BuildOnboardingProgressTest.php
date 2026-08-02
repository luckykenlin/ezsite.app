<?php

declare(strict_types=1);

use App\Actions\BuildOnboardingProgress;
use App\Actions\SaveSiteCapture;
use App\Actions\Templates\ProvisionSiteFromTemplate;
use App\Enums\PageStatus;
use App\Models\Business;
use App\Models\Location;
use App\Models\Tenant;
use App\Site\OnboardingProgress;
use App\Site\OnboardingTask;
use App\Templates\SignupDetails;
use App\Templates\SiteTemplate;
use Illuminate\Support\Facades\Queue;

/**
 * Build the progress inside the tenant's RLS context, from a clean container:
 * SiteCapture memoizes the settings row, so a test that writes settings between
 * two calls would otherwise read the first answer twice.
 */
function onboardingFor(Tenant $tenant): OnboardingProgress
{
    return test()->runInTenant($tenant, function (): OnboardingProgress {
        app()->forgetScopedInstances();

        return resolve(BuildOnboardingProgress::class)->handle();
    });
}

it('reports every task outstanding for a site a real signup just produced', function (): void {
    Queue::fake();

    $tenant = resolve(ProvisionSiteFromTemplate::class)->handle(
        SiteTemplate::NailSalon,
        new SignupDetails(
            businessName: 'Jade Nails',
            subdomain: 'jade-nails',
            email: 'owner@jadenails.test',
            password: 'a-good-password',
        ),
    );

    // Each of these is something the pipeline deliberately does NOT do on the
    // owner's behalf: pages stay Draft so they review before going live, the
    // template's street address belonged to an invented business, the phone fell
    // back to that business's number because the wizard's field is optional, and
    // no logo or capture surface exists until somebody adds one. Which is to say
    // a brand-new site owes all five — and until this list existed, nothing in
    // the product said so.
    expect(onboardingFor($tenant)->remaining())->toBe(OnboardingTask::cases());
});

it('counts the site as live as soon as one page is published', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantPage($tenant, []);

    expect(onboardingFor($tenant)->isLive())->toBeTrue();
});

it('does not count a draft page as being live', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);

    $this->runInTenant($tenant, fn (): bool => $page->update(['status' => PageStatus::Draft]));

    expect(onboardingFor($tenant)->isLive())->toBeFalse();
});

it('accepts the address once the primary location has a street', function (?string $street, bool $done): void {
    $tenant = Tenant::factory()->create();
    $business = $this->createTenantBusiness($tenant, locations: 0);

    $this->runInTenant($tenant, fn (): Location => Location::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'is_primary' => true,
        'address_line1' => $street,
    ]));

    expect(onboardingFor($tenant)->isDone(OnboardingTask::SiteAddress))->toBe($done);
})->with([
    'provisioning leaves it blank' => [null, false],
    'the owner filled it in' => ['24 Baker Street', true],
]);

it('accepts the phone number once it is not the template example any more', function (): void {
    // The sharp case this task exists for: skip the wizard's optional phone
    // field and the site goes live advertising the template demo's number.
    $tenant = Tenant::factory()->create(['template' => SiteTemplate::NailSalon]);
    $example = SiteTemplate::NailSalon->definition()->demoProfile->phone;

    $this->createTenantBusiness($tenant, ['contact_phone' => $example]);

    expect(onboardingFor($tenant)->isDone(OnboardingTask::PhoneNumber))->toBeFalse();

    $this->runInTenant($tenant, fn (): bool => Business::query()->firstOrFail()->update(['contact_phone' => '+1 555 0100']));

    expect(onboardingFor($tenant)->isDone(OnboardingTask::PhoneNumber))->toBeTrue();
});

it('has no example number to warn about when no template was used', function (): void {
    // A hand-built site, or one the assistant drafted: nothing was inherited, so
    // whatever number it carries is its own.
    $tenant = Tenant::factory()->create(['template' => null]);
    $this->createTenantBusiness($tenant, ['contact_phone' => '+1 555 0100']);

    expect(onboardingFor($tenant)->isDone(OnboardingTask::PhoneNumber))->toBeTrue();
});

it('leaves the phone outstanding for a tenant with no business profile yet', function (): void {
    $tenant = Tenant::factory()->create();

    expect(onboardingFor($tenant)->isDone(OnboardingTask::PhoneNumber))->toBeFalse();
});

it('accepts the logo once one is attached', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, ['logo_path' => null]);

    expect(onboardingFor($tenant)->isDone(OnboardingTask::Logo))->toBeFalse();

    $this->runInTenant($tenant, fn (): bool => Business::query()->firstOrFail()->update(['logo_path' => 'logos/jade.png']));

    expect(onboardingFor($tenant)->isDone(OnboardingTask::Logo))->toBeTrue();
});

it('accepts either capture surface on its own', function (array $popup, array $callBar): void {
    $tenant = Tenant::factory()->create();

    expect(onboardingFor($tenant)->isDone(OnboardingTask::CaptureSurface))->toBeFalse();

    $this->runInTenant($tenant, fn () => resolve(SaveSiteCapture::class)->handle($popup, $callBar));

    expect(onboardingFor($tenant)->isDone(OnboardingTask::CaptureSurface))->toBeTrue();
})->with([
    'the offer popup' => [['enabled' => true, 'heading' => 'Get 10% off'], []],
    'the mobile call bar' => [[], ['enabled' => true]],
]);

it('sees only the current tenant, whatever another site has finished', function (): void {
    // RLS is the only thing scoping these reads, so a neighbour's finished
    // profile must never tick this owner's boxes.
    $neighbour = Tenant::factory()->create();
    $this->createTenantBusiness($neighbour, ['logo_path' => 'logos/other.png']);
    $this->createTenantPage($neighbour, []);

    $tenant = Tenant::factory()->create();

    expect(onboardingFor($tenant)->remaining())->toBe(OnboardingTask::cases());
});

it('reports a fully finished site as complete', function (): void {
    $tenant = Tenant::factory()->create(['template' => null]);
    $business = $this->createTenantBusiness($tenant, [
        'contact_phone' => '+1 555 0100',
        'logo_path' => 'logos/jade.png',
    ], locations: 0);

    $this->createTenantPage($tenant, []);

    $this->runInTenant($tenant, function () use ($tenant, $business): void {
        Location::factory()->create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'is_primary' => true,
            'address_line1' => '24 Baker Street',
        ]);

        resolve(SaveSiteCapture::class)->handle(['enabled' => true, 'heading' => 'Get 10% off'], []);
    });

    expect(onboardingFor($tenant)->isComplete())->toBeTrue();
});
