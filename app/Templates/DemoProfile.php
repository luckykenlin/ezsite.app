<?php

declare(strict_types=1);

namespace App\Templates;

/**
 * The invented business behind a template's demo site: the Business row and
 * the single Location that the bind-capable blocks (`contact`, the header and
 * footer) resolve against.
 *
 * It doubles as the fallback for every unanswered placeholder, which is why
 * the fields mirror the wizard's step-1 questions exactly. A visitor who skips
 * the guided form gets this profile's copy — complete, publishable and
 * obviously replaceable — rather than a page with `{city}` printed on it.
 */
final readonly class DemoProfile
{
    /**
     * The default `$openingHours` is an ordinary six-day trading week — right
     * for most of the library, and wrong for none of it in a way a visitor
     * would notice. A template with real hours passes its own.
     *
     * @param  array{monday?: list<string>, tuesday?: list<string>, wednesday?: list<string>, thursday?: list<string>, friday?: list<string>, saturday?: list<string>, sunday?: list<string>}  $openingHours
     */
    public function __construct(
        public string $name,
        public string $tagline,
        public string $description,
        public string $city,
        public string $phone,
        public string $email,
        public string $addressLine1,
        public string $state,
        public string $postalCode,
        public string $country = 'US',
        public string $timezone = 'America/Los_Angeles',
        public array $openingHours = [
            'monday' => ['11:00-21:00'],
            'tuesday' => ['11:00-21:00'],
            'wednesday' => ['11:00-21:00'],
            'thursday' => ['11:00-21:00'],
            'friday' => ['11:00-22:00'],
            'saturday' => ['11:00-22:00'],
            'sunday' => [],
        ],
    ) {
        //
    }
}
