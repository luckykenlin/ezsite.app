<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Business;
use App\Models\Location;
use Spatie\OpeningHours\OpeningHours;

/**
 * The tenant's LocalBusiness JSON-LD, assembled from the same factual records
 * the bound blocks render (Business + its primary Location) — never from
 * authored block copy, so the structured data and the visible NAP can't drift.
 *
 * Emitted on the home page only ({@see BuildPageSeoData}): schema.org expects
 * one LocalBusiness node per business, anchored at the site root.
 */
final readonly class BuildLocalBusinessSchema
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Business $business, ?Location $location, ?string $image): array
    {
        // The bound location's own contact details win over the business-wide
        // ones, exactly as the contact block renders them.
        $phone = $location?->phone;
        $email = $location?->email;

        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $business->name,
            'url' => url('/'),
            'description' => $business->tagline ?? $business->description,
            'image' => $image,
            'telephone' => $phone ?? $business->contact_phone,
            'email' => $email ?? $business->contact_email,
        ], fn (mixed $value): bool => $value !== null);

        if (! $location instanceof Location) {
            return $schema;
        }

        return [
            ...$schema,
            ...array_filter([
                'address' => $this->address($location),
                'geo' => $this->geo($location),
                'openingHoursSpecification' => $this->openingHours($location->opening_hours),
            ], fn (array $value): bool => $value !== []),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function address(Location $location): array
    {
        $street = implode(', ', array_filter([$location->address_line1, $location->address_line2]));

        $address = array_filter([
            'streetAddress' => $street === '' ? null : $street,
            'addressLocality' => $location->city,
            'addressRegion' => $location->state,
            'postalCode' => $location->postal_code,
            'addressCountry' => $location->country,
        ], fn (?string $value): bool => $value !== null);

        return $address === [] ? [] : ['@type' => 'PostalAddress', ...$address];
    }

    /**
     * @return array<string, string>
     */
    private function geo(Location $location): array
    {
        if ($location->latitude === null || $location->longitude === null) {
            return [];
        }

        return [
            '@type' => 'GeoCoordinates',
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
        ];
    }

    /**
     * The `opening_hours` column already holds schema.org
     * OpeningHoursSpecification data (see App\Casts\OpeningHours), so spatie
     * hands us exactly the nodes JSON-LD wants — one per range, so a lunch
     * break stays two entries, plus any date exceptions.
     *
     * @return array<int|string, mixed>
     */
    private function openingHours(?OpeningHours $hours): array
    {
        return $hours?->asStructuredData() ?? [];
    }
}
