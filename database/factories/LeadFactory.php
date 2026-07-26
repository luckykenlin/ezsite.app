<?php

declare(strict_types=1);

namespace Database\Factories;

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
            'source' => 'contact_form',
            'status' => LeadStatus::New,
            'ip_address' => fake()->ipv4(),
        ];
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
