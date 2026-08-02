<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Tenancy\RequiresTenantContext;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An enquiry a visitor left on the public tenant site — from the contact
 * block's form, an inline signup block, or the site-wide popup. RLS-only
 * isolation, like every other tenant-owned model — no global scope, no trait
 * beyond the write-context guard.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int|null $location_id
 * @property int|null $page_id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $message
 * @property LeadSource $source
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $utm_term
 * @property string|null $utm_content
 * @property string|null $referrer
 * @property string|null $landing_path
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

    /**
     * What to call this person in the inbox.
     *
     * The low-friction surfaces don't ask for a name, so falling back to the
     * email's local part ("jordan" from "jordan@example.com") gives the
     * operator something human to read instead of a blank cell — and a row
     * with no name at all is still a real lead, so it must render as
     * something.
     */
    public function displayName(): string
    {
        if (filled($this->name)) {
            return $this->name;
        }

        if (filled($this->email)) {
            return Str::before($this->email, '@');
        }

        return $this->phone ?? __('Anonymous');
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
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'read_at' => 'datetime',
        ];
    }
}
