<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TeamMember;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMember>
 */
final class TeamMemberFactory extends Factory
{
    protected $model = TeamMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->firstName();

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'role' => fake()->randomElement(['Owner', 'Senior nail technician', 'Nail technician', 'Nail artist']),
            'bio' => fake()->sentence(14),
            'booking_url' => null,
            'instagram_handle' => mb_strtolower($name).'.nails',
            'sort' => 0,
        ];
    }

    /**
     * A technician who takes bookings on a page of their own.
     */
    public function withBookingUrl(): self
    {
        return $this->state(fn (): array => [
            'booking_url' => 'https://booksy.com/en-us/'.fake()->numberBetween(100000, 999999).'_'.fake()->slug(2),
        ]);
    }
}
