<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatRole;
use App\Tenancy\RequiresTenantContext;
use Database\Factories\PageChatMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a page's editor-chat transcript. RLS-only isolation, like every
 * other tenant-owned model — no global scope, no trait beyond the write-context
 * guard.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $page_id
 * @property int|null $user_id
 * @property ChatRole $role
 * @property string $content
 * @property int|null $changed_blocks
 * @property bool $failed
 * @property array<int, array{type: string, data: array<string, mixed>}>|null $blocks_before
 * @property list<string>|null $activity
 *
 * @method static PageChatMessageFactory factory($count = null, $state = [])
 */
final class PageChatMessage extends Model
{
    /** @use HasFactory<PageChatMessageFactory> */
    use HasFactory;

    use RequiresTenantContext;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this turn actually changed the page — drives the "edited the
     * page" marker in the chat, so an answer that only explained something
     * doesn't look like it touched anything.
     */
    public function changedThePage(): bool
    {
        return ($this->changed_blocks ?? 0) > 0;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ChatRole::class,
            'failed' => 'boolean',
            'blocks_before' => 'array',
            'activity' => 'array',
        ];
    }
}
