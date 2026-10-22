<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\RequiresTenantContext;
use Carbon\CarbonImmutable;
use Database\Factories\TeamMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person a customer may ask for by name — a technician at a nail salon.
 * Clients follow a tech, not a salon, so the team is first-class data with a
 * photo, a personal booking link and an Instagram handle, rather than a
 * paragraph in an About block.
 *
 * Deliberately NOT a `users` row: the people on the website are rarely the
 * people with a login, and adding a staff account must not change the team
 * page.
 *
 * RLS-only isolation, writes guarded by {@see RequiresTenantContext}.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $role
 * @property string|null $bio
 * @property int|null $media_id
 * @property string|null $booking_url
 * @property string|null $instagram_handle
 * @property int $sort
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static TeamMemberFactory factory($count = null, $state = [])
 */
final class TeamMember extends Model
{
    /** @use HasFactory<TeamMemberFactory> */
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
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * Operator order, `id` as the tiebreak.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }
}
