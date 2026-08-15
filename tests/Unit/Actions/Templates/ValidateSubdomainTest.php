<?php

declare(strict_types=1);

use App\Actions\Templates\ValidateSubdomain;
use App\Models\Tenant;
use App\Templates\SiteTemplate;
use Illuminate\Validation\ValidationException;

function validateSubdomain(string $subdomain): string
{
    return resolve(ValidateSubdomain::class)->handle($subdomain);
}

it('normalizes a typed name into a dns label', function (): void {
    expect(validateSubdomain('Corner Café!'))->toBe('corner-cafe')
        ->and(validateSubdomain('  Jade  Pearl  '))->toBe('jade-pearl')
        ->and(validateSubdomain('ORA-Studio'))->toBe('ora-studio');
});

it('rejects a name that cannot be a host', function (string $subdomain): void {
    expect(fn (): string => validateSubdomain($subdomain))
        ->toThrow(ValidationException::class, '3 to 63 letters');
})->with([
    'empty' => [''],
    'punctuation only' => ['!!!'],
    'too short' => ['ab'],
    'too long' => [str_repeat('a', 64)],
]);

it('keys the error on the field the wizard is showing', function (): void {
    // The wizard renders the message under the subdomain input; a differently
    // keyed error would validate correctly and display nowhere.
    try {
        resolve(ValidateSubdomain::class)->handle('www', 'site_address');
    } catch (ValidationException $validationException) {
        expect(array_keys($validationException->errors()))->toBe(['site_address']);

        return;
    }

    $this->fail('The reserved subdomain was accepted.');
});

it('refuses the hosts the product and its infrastructure need', function (string $subdomain): void {
    expect(fn (): string => validateSubdomain($subdomain))
        ->toThrow(ValidationException::class, 'reserved');
})->with(['www', 'mail', 'admin', 'api', 'ezsite']);

it('refuses the demo namespace, so no signup can shadow a gallery site', function (): void {
    expect(fn (): string => validateSubdomain(SiteTemplate::NailSalon->demoSubdomain()))
        ->toThrow(ValidationException::class, 'reserved')
        // The whole prefix, not just the names in use — a new
        // template must not have to be added to a list to be safe.
        ->and(fn (): string => validateSubdomain('demo-anything'))
        ->toThrow(ValidationException::class, 'reserved');
});

it('refuses a subdomain another tenant already answers on', function (): void {
    Tenant::factory()->withDomain('acme')->create();

    expect(fn (): string => validateSubdomain('acme'))
        ->toThrow(ValidationException::class, 'already taken');
});

it('refuses a label already qualified beneath an existing host', function (): void {
    // `domains.domain` holds either a bare label or a full host, so the same
    // site can be stored two ways and a bare-label-only check would hand out
    // a subdomain that resolves to somebody else.
    Tenant::factory()->withDomain('acme.ezsite.test')->create();

    expect(fn (): string => validateSubdomain('acme'))
        ->toThrow(ValidationException::class, 'already taken');
});

it('suggests the nearest free variant when the first choice is gone', function (): void {
    Tenant::factory()->withDomain('corner-cafe')->create();
    Tenant::factory()->withDomain('corner-cafe-2')->create();

    expect(resolve(ValidateSubdomain::class)->suggest('Corner Cafe'))->toBe('corner-cafe-3')
        // A free name suggests itself, so the wizard can call this
        // unconditionally.
        ->and(resolve(ValidateSubdomain::class)->suggest('Jade Pearl'))->toBe('jade-pearl');
});

it('suggests nothing for a name no variant can rescue', function (): void {
    // Every variant of a two-letter name is still too short, and a reserved
    // word stays reserved however it is numbered until the suffix makes it
    // long enough — so the wizard has to cope with "no suggestion".
    expect(resolve(ValidateSubdomain::class)->suggest('!!'))->toBeNull();
});
