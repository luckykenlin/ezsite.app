<?php

declare(strict_types=1);

namespace App\StockPhotos;

use App\Models\LibraryPhoto;

/**
 * One site-population run's spending money: how many provider searches and
 * how many photo imports it may still make, plus which photos it has already
 * used — the in-run dedup that stops one photograph appearing on every
 * section of a page.
 *
 * Mutable on purpose (the one mutable class in this namespace): it IS the
 * running state of a walk over a site's pages, threaded through
 * {@see \App\Actions\Pages\PopulateDraftImages}'s fill methods so the action
 * itself can stay `readonly` like every other action.
 *
 * Cross-run dedup is a different mechanism and deliberately not here: the
 * (provider, source id) pair on the SHARED library row lets
 * {@see \App\Actions\Library\FindOrImportLibraryPhoto} reuse a download across
 * regenerations and across tenants, whereas this set only prevents in-run
 * repetition.
 */
final class PhotoBudget
{
    /**
     * @var array<string, true>
     */
    private array $used = [];

    public function __construct(private int $searches, private int $photos)
    {
        //
    }

    public static function fromConfig(): self
    {
        return new self(
            config()->integer('stock-photos.max_searches_per_site'),
            config()->integer('stock-photos.max_photos_per_site'),
        );
    }

    public function canSearch(): bool
    {
        return $this->searches > 0 && $this->photos > 0;
    }

    public function spendSearch(): void
    {
        $this->searches--;
    }

    public function canTake(): bool
    {
        return $this->photos > 0;
    }

    public function take(StockPhoto $photo): void
    {
        $this->photos--;
        $this->used[$photo->provider.':'.$photo->sourceId] = true;
    }

    /**
     * Register a photo reused out of the shared library.
     *
     * Deliberately does NOT decrement either counter, unlike {@see take()}:
     * this budget is provider SPEND, and a photo another site already imported
     * costs no request and no download. It shares the same `used` key space
     * though — without that, one photograph could land on a page twice, once
     * from the library and once from a fresh provider search that returned the
     * same result.
     */
    public function reuse(LibraryPhoto $photo): void
    {
        $this->used[$this->libraryKey($photo)] = true;
    }

    /**
     * The given results minus every photo this run already used.
     *
     * @param  list<StockPhoto>  $photos
     * @return list<StockPhoto>
     */
    public function unused(array $photos): array
    {
        return array_values(array_filter(
            $photos,
            fn (StockPhoto $photo): bool => ! isset($this->used[$photo->provider.':'.$photo->sourceId]),
        ));
    }

    /**
     * The same filter for library rows.
     *
     * @param  list<LibraryPhoto>  $photos
     * @return list<LibraryPhoto>
     */
    public function unusedLibrary(array $photos): array
    {
        return array_values(array_filter(
            $photos,
            fn (LibraryPhoto $photo): bool => ! isset($this->used[$this->libraryKey($photo)]),
        ));
    }

    /**
     * A library row's key in the shared `used` space. Falls back to the row id
     * for a photo with no provider source id (nothing imports those today, but
     * a null source id must not collide every such photo into one key).
     */
    private function libraryKey(LibraryPhoto $photo): string
    {
        return $photo->provider.':'.($photo->source_id ?? 'library-'.$photo->id);
    }
}
