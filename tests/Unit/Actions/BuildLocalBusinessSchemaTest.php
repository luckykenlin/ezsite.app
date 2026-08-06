<?php

declare(strict_types=1);

use App\Actions\BuildLocalBusinessSchema;
use App\Models\Business;
use App\Models\Location;
use App\Models\Tenant;
use Spatie\OpeningHours\OpeningHours;

/**
 * Build the schema for a business (and optional location) of a fresh tenant.
 *
 * @param  array<string, mixed>  $businessAttributes
 * @param  array<string, mixed>|null  $locationAttributes  null = no location at all
 * @return array<string, mixed>
 */
function localBusinessSchema(array $businessAttributes = [], ?array $locationAttributes = []): array
{
    $tenant = Tenant::factory()->create();

    return test()->runInTenant($tenant, function () use ($tenant, $businessAttributes, $locationAttributes): array {
        $business = Business::factory()->create(['tenant_id' => $tenant->id] + $businessAttributes);

        $location = $locationAttributes === null ? null : Location::factory()->create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            ...$locationAttributes,
        ]);

        return resolve(BuildLocalBusinessSchema::class)->handle($business, $location, 'https://cdn.test/logo.png');
    });
}

it('describes the business and its location', function (): void {
    $schema = localBusinessSchema(
        ['name' => 'QQ Nail', 'tagline' => 'Nails done right', 'contact_phone' => '+1 555 0000'],
        [
            'address_line1' => '12 Main St',
            'address_line2' => 'Suite 3',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country' => 'US',
            'phone' => '+1 555 0100',
            'email' => 'hi@qqnail.test',
            'latitude' => '30.2672000',
            'longitude' => '-97.7431000',
            'timezone' => 'America/Chicago',
            'opening_hours' => OpeningHours::create(['monday' => ['09:00-12:00', '13:00-17:00']]),
        ],
    );

    expect($schema['@type'])->toBe('LocalBusiness')
        ->and($schema['name'])->toBe('QQ Nail')
        ->and($schema['description'])->toBe('Nails done right')
        ->and($schema['image'])->toBe('https://cdn.test/logo.png')
        // The location's own contact details win over the business-wide ones.
        ->and($schema['telephone'])->toBe('+1 555 0100')
        ->and($schema['email'])->toBe('hi@qqnail.test')
        ->and($schema['address'])->toBe([
            '@type' => 'PostalAddress',
            'streetAddress' => '12 Main St, Suite 3',
            'addressLocality' => 'Austin',
            'addressRegion' => 'TX',
            'postalCode' => '78701',
            'addressCountry' => 'US',
        ])
        ->and($schema['geo'])->toBe([
            '@type' => 'GeoCoordinates',
            'latitude' => '30.2672000',
            'longitude' => '-97.7431000',
        ])
        // One entry per range, so a lunch break is not one wrong span.
        ->and($schema['openingHoursSpecification'])->toBe([
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'Monday', 'opens' => '09:00', 'closes' => '12:00'],
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'Monday', 'opens' => '13:00', 'closes' => '17:00'],
        ]);
});

it('falls back to the business contact details when the location has none', function (): void {
    $schema = localBusinessSchema(
        ['contact_phone' => '+1 555 0000', 'contact_email' => 'hq@qqnail.test'],
        ['phone' => null, 'email' => null],
    );

    expect($schema['telephone'])->toBe('+1 555 0000')
        ->and($schema['email'])->toBe('hq@qqnail.test');
});

it('omits the location nodes for a business with no location yet', function (): void {
    $schema = localBusinessSchema(['contact_phone' => '+1 555 0000'], null);

    expect($schema)->not->toHaveKeys(['address', 'geo', 'openingHoursSpecification'])
        ->and($schema['telephone'])->toBe('+1 555 0000');
});

it('omits geo, address and hours the location does not have', function (): void {
    $schema = localBusinessSchema([], [
        'address_line1' => null,
        'address_line2' => null,
        'city' => null,
        'state' => null,
        'postal_code' => null,
        'country' => null,
        'latitude' => null,
        'longitude' => null,
        'opening_hours' => null,
    ]);

    expect($schema)->not->toHaveKeys(['address', 'geo', 'openingHoursSpecification']);
});

it('drops the description and contact keys a sparse profile cannot fill', function (): void {
    $schema = localBusinessSchema([
        'tagline' => null,
        'description' => null,
        'contact_phone' => null,
        'contact_email' => null,
    ], ['phone' => null, 'email' => null]);

    expect($schema)->not->toHaveKeys(['description', 'telephone', 'email'])
        ->and($schema['@context'])->toBe('https://schema.org');
});
