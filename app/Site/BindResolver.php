<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\Business;
use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Request-scoped cache of the tenant's factual records for block bind
 * resolution.
 *
 * A full page render — body blocks and site chrome alike — resolves every
 * bound block from at most two queries: one for the Business, one for all
 * Locations. Both are memoized here and the container binds the resolver as
 * scoped (see AppServiceProvider), so the cache lives exactly one request.
 * Stateful by design, which is why it sits beside the registry instead of in
 * app/Actions (Actions are final readonly single-handle classes).
 */
final class BindResolver
{
    private ?Business $business = null;

    private bool $businessLoaded = false;

    /** @var Collection<int, Location>|null */
    private ?Collection $locations = null;

    public function business(): ?Business
    {
        if (! $this->businessLoaded) {
            $this->business = Business::query()->first();
            $this->businessLoaded = true;
        }

        return $this->business;
    }

    /**
     * Every location of the tenant, primary first.
     *
     * @return Collection<int, Location>
     */
    public function locations(): Collection
    {
        return $this->locations ??= Location::query()
            ->primaryFirst()
            ->get();
    }

    /**
     * The location a block is bound to: the primary when $id is null, the
     * matching location when it still exists, or the primary as a logged
     * fallback when the stored id has gone stale (e.g. the location was
     * deleted after the block was configured).
     */
    public function location(?int $id): ?Location
    {
        $locations = $this->locations();

        if ($id === null) {
            return $locations->first();
        }

        $location = $locations->firstWhere('id', $id);

        if ($location === null) {
            Log::warning('fabricator.bind_fallback', [
                'reason' => 'stale_location_id',
                'location_id' => $id,
            ]);

            return $locations->first();
        }

        return $location;
    }
}
