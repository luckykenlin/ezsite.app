<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\RequiresTenantContext;
use App\Enums\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An enquiry a visitor left on the public tenant site (the contact block's
 * form). RLS-only isolation, like every other tenant-owned model — no global
 * scope, no trait beyond the write-context guard.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int|null $location_id
 * @property int|null $page_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $message
 * @property string $source
 * @property LeadStatus $status
 * @property Carbon|null $read_at
 * @property string|null $ip_address
 *
 * @method static LeadFactory factory($count = null, $state = [])
 */
final class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
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
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * The single best line to reach this person by, phone first — the channel
     * small-business operators actually use.
     */
    public function contactLine(): ?string
    {
        return $this->phone ?? $this->email;
    }

    public function isUnread(): bool
    {
        return $this->status === LeadStatus::New;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'read_at' => 'datetime',
        ];
    }
}
