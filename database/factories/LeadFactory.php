<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
final class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'message' => fake()->sentence(),
            'source' => LeadSource::ContactForm,
            'status' => LeadStatus::New,
            'ip_address' => fake()->ipv4(),
        ];
    }

    /**
     * A lead from one of the low-friction surfaces: an email and nothing else.
     */
    public function anonymous(): self
    {
        return $this->state(fn (): array => [
            'name' => null,
            'phone' => null,
            'message' => null,
            'source' => LeadSource::Popup,
        ]);
    }

    /**
     * A table-booking request: the three reservation facts plus the
     * name-and-phone pair the reservation form always collects.
     */
    public function reservation(): self
    {
        return $this->state(fn (): array => [
            'source' => LeadSource::Reservation,
            'reserved_date' => now()->addDays(3)->toDateString(),
            'reserved_time' => '19:00',
            'party_size' => 4,
        ]);
    }

    /**
     * A lead that arrived carrying first-touch campaign data.
     */
    public function attributed(): self
    {
        return $this->state(fn (): array => [
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'spring-offer',
            'referrer' => 'https://www.google.com/',
            'landing_path' => '/?utm_source=google',
        ]);
    }

    /**
     * A lead the operator has already seen.
     */
    public function read(): self
    {
        return $this->state(fn (): array => [
            'status' => LeadStatus::Read,
            'read_at' => now(),
        ]);
    }

    /**
     * A lead filed out of the inbox.
     */
    public function archived(): self
    {
        return $this->state(fn (): array => ['status' => LeadStatus::Archived]);
    }
}
