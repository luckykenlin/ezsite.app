<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChatRole;
use App\Models\Page;
use App\Models\PageChatMessage;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageChatMessage>
 */
final class PageChatMessageFactory extends Factory
{
    protected $model = PageChatMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'page_id' => fn (array $attributes): int => Page::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'role' => ChatRole::User,
            'content' => fake()->sentence(),
        ];
    }

    /**
     * A reply from the assistant that edited the page.
     */
    public function assistant(int $changedBlocks = 1): self
    {
        return $this->state(fn (): array => [
            'role' => ChatRole::Assistant,
            'content' => fake()->sentence(),
            'changed_blocks' => $changedBlocks,
        ]);
    }
}
