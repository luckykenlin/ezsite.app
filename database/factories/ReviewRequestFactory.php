<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReviewRequestStatus;
use App\Models\Business;
use App\Models\Location;
use App\Models\ReviewRequest;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReviewRequest>
 */
final class ReviewRequestFactory extends Factory
{
    protected $model = ReviewRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'business_id' => fn (array $attributes): int => Business::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'location_id' => fn (array $attributes): int => Location::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'business_id' => $attributes['business_id'],
            ])->id,
            'channel' => 'link',
            'status' => ReviewRequestStatus::Queued,
            'token' => Str::random(12),
        ];
    }

    /**
     * A card somebody has already scanned.
     */
    public function clicked(): self
    {
        return $this->state(fn (): array => [
            'status' => ReviewRequestStatus::Clicked,
            'clicked_at' => now(),
        ]);
    }
}
