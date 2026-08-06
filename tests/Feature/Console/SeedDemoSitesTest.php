<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Templates\SiteTemplate;

it('builds every demo site by default', function (): void {
    $this->artisan('demo:seed', ['--skip-photos' => true])
        ->assertSuccessful();

    expect(Tenant::query()->demo()->count())->toBe(count(SiteTemplate::cases()))
        ->and(Tenant::query()->demo()->pluck('template')->all())
        ->toEqualCanonicalizing(SiteTemplate::cases());
});

it('builds only the templates named on the command line', function (): void {
    $this->artisan('demo:seed', ['template' => ['nail-salon', 'bubble-tea'], '--skip-photos' => true])
        ->assertSuccessful();

    expect(Tenant::query()->demo()->pluck('template')->all())
        ->toEqualCanonicalizing([SiteTemplate::NailSalon, SiteTemplate::BubbleTea]);
});

it('names the valid slugs when asked for a template that does not exist', function (): void {
    // A silent no-op would cost more than the argument saves, so an unknown
    // slug fails loudly and lists what it could have been.
    $this->artisan('demo:seed', ['template' => ['sushi-bar'], '--skip-photos' => true])
        ->expectsOutputToContain('Unknown template "sushi-bar"')
        ->expectsOutputToContain('chinese-restaurant')
        ->assertFailed();

    expect(Tenant::query()->count())->toBe(0);
});

it('keeps going when one template fails, and reports failure at the end', function (): void {
    // The sites are independent: a gallery missing one card beats a gallery
    // missing eight. The forced failure is a real tenant already holding the
    // demo profile's email address, which `tenants.email` refuses to duplicate.
    Tenant::factory()->create(['email' => SiteTemplate::NailSalon->definition()->demoProfile->email]);

    $this->artisan('demo:seed', [
        'template' => ['nail-salon', 'bubble-tea'],
        '--skip-photos' => true,
    ])->assertFailed();

    expect(Tenant::query()->demo()->pluck('template')->all())->toBe([SiteTemplate::BubbleTea]);
});
