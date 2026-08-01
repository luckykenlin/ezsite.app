<?php

declare(strict_types=1);

namespace App\StockPhotos;

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
 * (provider, source id) pair on the media record lets
 * {@see \App\Actions\FindOrImportStockPhoto} reuse files across
 * regenerations, whereas this set only prevents in-run repetition.
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
}
