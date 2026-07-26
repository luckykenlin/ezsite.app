<?php

declare(strict_types=1);

namespace App\Filament\Fabricator;

use App\Models\Media;

/**
 * Request-scoped media id → public URL lookups for block rendering, batched
 * so a whole page render costs one query (same rationale as
 * {@see BindResolver}). RLS scopes the lookups to the current tenant, so a
 * cross-tenant id in stored block data simply resolves to null — the render
 * loop's existing defensive fallbacks take it from there.
 */
final class MediaResolver
{
    /**
     * @var array<int, string|null>
     */
    private array $urls = [];

    /**
     * Batch-load the given ids (unknown ones memoized as null so a dangling
     * reference never re-queries).
     *
     * @param  list<mixed>  $ids
     */
    public function preload(array $ids): void
    {
        $missing = [];

        foreach ($ids as $id) {
            $id = $this->normalizeId($id);

            if ($id !== null && ! array_key_exists($id, $this->urls)) {
                $missing[] = $id;
                $this->urls[$id] = null;
            }
        }

        if ($missing === []) {
            return;
        }

        Media::query()
            ->whereIn('id', $missing)
            ->get()
            ->each(function (Media $media): void {
                $this->urls[(int) $media->id] = $media->url;
            });
    }

    public function url(mixed $id): ?string
    {
        $id = $this->normalizeId($id);

        if ($id === null) {
            return null;
        }

        $this->preload([$id]);

        return $this->urls[$id];
    }

    /**
     * Ids arrive as ints from storage, digit strings from Filament
     * dehydration, or — for the block currently being edited — the
     * CuratorPicker's RAW form state (a uuid-keyed array of media item
     * arrays), which the editor's live preview substitutes uncommitted.
     * Anything else is treated as unset (same tolerance as
     * {@see BlockRegistry::boundLocationId()}).
     */
    private function normalizeId(mixed $id): ?int
    {
        if (is_array($id)) {
            $first = reset($id);
            $id = is_array($first) ? ($first['id'] ?? null) : $first;
        }

        if (is_int($id)) {
            return $id;
        }

        return is_string($id) && ctype_digit($id) ? (int) $id : null;
    }
}
