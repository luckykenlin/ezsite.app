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
 * @property Carbon|null $reserved_date
 * @property string|null $reserved_time
 * @property int|null $party_size
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

    public function isReservation(): bool
    {
        return $this->source === LeadSource::Reservation;
    }

    /**
     * The requested table in one line — "Sat 16 Aug, 19:00 · party of 4".
     *
     * The single formatter behind the inbox column, the infolist, the bell
     * notification and both emails, so the wording cannot drift between them.
     * Wall-clock values rendered verbatim: the stored date and time already
     * mean "at the restaurant" (see the leads migration), so no timezone
     * conversion belongs here.
     */
    public function reservationLine(): ?string
    {
        if ($this->reserved_date === null) {
            return null;
        }

        $line = $this->reserved_date->isoFormat('ddd D MMM');

        if (filled($this->reserved_time)) {
            $line .= ', '.mb_substr($this->reserved_time, 0, 5);
        }

        if ($this->party_size !== null) {
            $line .= ' · '.__('party of :count', ['count' => $this->party_size]);
        }

        return $line;
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
            'reserved_date' => 'date',
            'party_size' => 'integer',
        ];
    }
}
