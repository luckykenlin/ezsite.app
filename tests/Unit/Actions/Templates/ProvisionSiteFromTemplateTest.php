<?php

declare(strict_types=1);

use App\Actions\Templates\ProvisionSiteFromTemplate;
use App\Enums\PageStatus;
use App\Jobs\PopulateDraftImagesJob;
use App\Models\Business;
use App\Models\Location;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Templates\SignupDetails;
use App\Templates\SiteTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Exceptions\DomainOccupiedByOtherTenantException;

function signupDetails(array $overrides = []): SignupDetails
{
    return new SignupDetails(...[
        'businessName' => 'Jade Pearl',
        'subdomain' => 'jade-pearl',
        'email' => 'owner@jadepearl.test',
        'password' => 'a-good-password',
        ...$overrides,
    ]);
}

function provisionFromTemplate(SiteTemplate $template, ?SignupDetails $details = null): Tenant
{
    return resolve(ProvisionSiteFromTemplate::class)->handle($template, $details ?? signupDetails());
}

beforeEach(function (): void {
    Queue::fake();
});

it('builds an account, a tenant and a whole draft site for every template', function (SiteTemplate $template): void {
    $definition = $template->definition();
    $tenant = provisionFromTemplate($template);

    $user = User::query()->sole();

    expect($tenant->template)->toBe($template)
        // Attribution, not showcase: a real signup is never a demo site.
        ->and($tenant->is_demo)->toBeFalse()
        ->and($tenant->domain?->domain)->toBe('jade-pearl')
        ->and($user->email)->toBe('owner@jadepearl.test')
        ->and($tenant->users()->whereKey($user->getKey())->exists())->toBeTrue();

    $this->runInTenant($tenant, function () use ($definition): void {
        $business = Business::query()->sole();
        $pages = Page::query()->orderBy('id')->get();

        expect($business->name)->toBe('Jade Pearl')
            ->and($business->category)->toBe($definition->category)
            ->and($business->contact_email)->toBe('owner@jadepearl.test')
            ->and($business->design_tokens->toArray())->toBe($definition->tokens()->toArray())
            ->and(Location::query()->sole()->label)->toBe('Jade Pearl')
            // The template's street address belongs to its invented business;
            // blank beats wrong, and the location form is one click away.
            ->and(Location::query()->sole()->address_line1)->toBeNull()
            ->and($pages)->toHaveCount(count($definition->pages))
            // Drafts, unlike the demo sites: the owner has not read a word of
            // this copy yet, and publishing is their first deliberate act.
            ->and($pages->pluck('status')->unique()->all())->toBe([PageStatus::Draft]);

        // Not one template writes its demo business's name onto a real
        // customer's page, and no placeholder survives anywhere.
        $copy = (string) json_encode([$pages->pluck('title'), $pages->pluck('blocks')]);

        expect($copy)->not->toContain($definition->demoProfile->name)
            ->and(preg_match('/\{[a-z_]+\}/', $copy))->toBe(0)
            ->and(SiteSetting::query()->sole()->header)->not->toBeNull();
    });

    Queue::assertPushed(PopulateDraftImagesJob::class, fn (PopulateDraftImagesJob $job): bool => $job->tenantId === $tenant->id);
})->with(fn (): array => array_map(
    fn (SiteTemplate $template): array => [$template],
    SiteTemplate::cases(),
));

it('uses the wizard answers where the template left placeholders', function (): void {
    $template = SiteTemplate::ChineseRestaurant;
    $tenant = provisionFromTemplate($template, signupDetails([
        'tagline' => 'Sichuan cooking, no apologies',
        'city' => 'Portland',
        'phone' => '(415) 555-0199',
        'answers' => ['dish_one' => 'Mapo Tofu'],
    ]));

    $this->runInTenant($tenant, function () use ($template): void {
        $business = Business::query()->sole();
        $copy = json_encode(Page::query()->pluck('blocks'));

        expect($business->tagline)->toBe('Sichuan cooking, no apologies')
            ->and($business->contact_phone)->toBe('(415) 555-0199')
            ->and(Location::query()->sole()->city)->toBe('Portland')
            // The template's own city is written all over its copy — a hero
            // eyebrow, a footer note, an FAQ answer — so a signup that does
            // not ask for one ships a Portland pizzeria that says Providence.
            ->and($copy)->toContain('Portland')
            ->and($copy)->not->toContain($template->definition()->demoProfile->city)
            ->and($copy)->toContain('Mapo Tofu')
            ->and($copy)->toContain('Sichuan cooking, no apologies')
            // An unanswered industry field falls back to its own example, so
            // the menu section is never half empty.
            ->and($copy)->toContain($template->definition()->extraFields[2]->example);
    });
});

it('ships the example content when the wizard was skipped entirely', function (): void {
    $definition = SiteTemplate::MassageSpa->definition();
    $tenant = provisionFromTemplate(SiteTemplate::MassageSpa);

    $this->runInTenant($tenant, function () use ($definition): void {
        $copy = json_encode(Page::query()->pluck('blocks'));

        expect($copy)->toContain($definition->extraFields[0]->example)
            ->and(Business::query()->sole()->tagline)->toBe($definition->demoProfile->tagline);
    });
});

it('attaches an existing account instead of claiming it', function (): void {
    // The wizard IS registration, but it must never be able to take over an
    // address someone already holds — so an existing user keeps their
    // password and simply gains a second site.
    $existing = User::factory()->create(['email' => 'owner@jadepearl.test', 'password' => Hash::make('their-own-password')]);

    $tenant = provisionFromTemplate(SiteTemplate::PizzaShop);

    expect(User::query()->count())->toBe(1)
        ->and(Hash::check('their-own-password', $existing->refresh()->password))->toBeTrue()
        ->and(Hash::check('a-good-password', $existing->password))->toBeFalse()
        ->and($tenant->users()->whereKey($existing->getKey())->exists())->toBeTrue();
});

it('refuses a subdomain that is taken, and leaves nothing behind', function (): void {
    Tenant::factory()->withDomain('jade-pearl')->create();

    expect(fn (): Tenant => provisionFromTemplate(SiteTemplate::BurgerJoint))
        ->toThrow(ValidationException::class, 'already taken')
        // Validated before the first write, so there is no half-built tenant.
        ->and(Tenant::query()->count())->toBe(1)
        ->and(User::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

it('rolls the whole signup back when the host is lost to a race', function (): void {
    // Two people typing the same name at the same moment both pass
    // ValidateSubdomain. Whatever settles it — the package's own occupancy
    // guard here, the unique index on domains.domain if that were ever
    // relaxed — the loser's transaction must take the user row with it.
    // The other signup lands between this one's availability check and its
    // own insert. Written with the query builder rather than factories so it
    // fires no model events — a factory here would re-enter this very
    // listener and recurse.
    Tenant::created(function (Tenant $tenant): void {
        if (DB::table('domains')->where('domain', 'jade-pearl')->doesntExist()) {
            $rival = (string) Str::uuid();

            DB::table('tenants')->insert(['id' => $rival, 'name' => 'The other signup', 'email' => 'rival@example.test', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('domains')->insert(['tenant_id' => $rival, 'domain' => 'jade-pearl', 'created_at' => now(), 'updated_at' => now()]);
        }
    });

    expect(fn (): Tenant => provisionFromTemplate(SiteTemplate::BubbleTea))->toThrow(DomainOccupiedByOtherTenantException::class)
        ->and(User::query()->where('email', 'owner@jadepearl.test')->exists())->toBeFalse();

    Queue::assertNothingPushed();
});
