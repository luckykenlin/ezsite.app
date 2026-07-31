<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\RequiresTenantContext;
use Carbon\CarbonImmutable;
use Database\Factories\PageRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One saved version of a page's blocks. RLS-only isolation, like every other
 * tenant-owned model — no global scope, no trait beyond the write-context guard.
 *
 * Never updated: a revision records what was saved at a moment, so the table has
 * `created_at` and no `updated_at`.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $page_id
 * @property int|null $user_id
 * @property list<array{type: string, data: array<string, mixed>}> $blocks
 * @property CarbonImmutable|null $created_at
 *
 * @method static PageRevisionFactory factory($count = null, $state = [])
 */
final class PageRevision extends Model
{
    /** @use HasFactory<PageRevisionFactory> */
    use HasFactory;

    use RequiresTenantContext;

    /**
     * A revision is only ever inserted, so Eloquent has no `updated_at` to
     * maintain and the table has no column for one.
     */
    public const ?string UPDATED_AT = null;

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
     * How many blocks this version holds — the one number that makes a history
     * list scannable ("12 blocks" next to "3 blocks" says what happened).
     */
    public function blockCount(): int
    {
        return count($this->blocks);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
