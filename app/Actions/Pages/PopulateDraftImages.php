<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Actions\FindOrImportStockPhoto;
use App\Actions\Library\AdoptLibraryPhoto;
use App\Actions\Library\FindLibraryPhotos;
use App\Enums\PageStatus;
use App\Models\Business;
use App\Models\Media;
use App\Models\Page;
use App\Site\Blocks\BlockShape;
use App\StockPhotos\PhotoBudget;
use App\StockPhotos\PhotoOrientation;
use App\StockPhotos\StockPhotoProvider;

/**
 * Wire real stock photography into a freshly generated site draft: walk every
 * DRAFT page, spend the {@see BlockShape::IMAGE_QUERY_KEY} queries the model
 * proposed (falling back to queries derived from the business profile), and
 * fill the photo slots the draft agent structurally cannot — hero images,
 * gallery grids, offering and feature item photos.
 *
 * Every slot is filled from the SHARED photo library first and only then from
 * the provider ({@see findPhotos()}), so the catalogue makes each generation
 * after the first cheaper — and photo-bearing even when the provider is
 * unreachable.
 *
 * Runs as its own queued step ({@see \App\Jobs\PopulateDraftImagesJob}) after
 * the draft has already landed, so every failure mode here — provider down,
 * rate limit, timeout — degrades to exactly the pre-pipeline behavior: a
 * draft without photos, whose views all guard their image slots. The walk is
 * idempotent and re-runnable: `_image_query` keys are consumed and removed,
 * slots that already hold an image are skipped (protecting operator edits on
 * regeneration), and photos dedup on (provider, source id) in the shared
 * library.
 *
 * Deliberately UNFILLED: `team` and `testimonials` avatars — a stock face
 * posing as a real employee or a real reviewer is a fabricated fact in image
 * form — and `logos`, because a stock photo is never anyone's logo.
 */
final readonly class PopulateDraftImages
{
    /**
     * A fresh model-authored gallery arrives with alt/caption items or no
     * items at all; this is how many photographs an empty one earns.
     */
    private const int EMPTY_GALLERY_PHOTOS = 4;

    public function __construct(
        private StockPhotoProvider $provider,
        private FindOrImportStockPhoto $import,
        private FindLibraryPhotos $library,
        private AdoptLibraryPhoto $adopt,
    ) {
        //
    }

    public function handle(Business $business): void
    {
        $budget = PhotoBudget::fromConfig();

        // Home first (it is created first): the budget should be spent on the
        // page a visitor actually lands on before any supporting page.
        $pages = Page::query()->where('status', PageStatus::Draft)->orderBy('id')->get();

        foreach ($pages as $page) {
            $blocks = $page->blocks;
            $changed = false;

            foreach ($blocks as $index => $block) {
                if (! is_array($block) || ! is_string($block['type'] ?? null) || ! is_array($block['data'] ?? null)) {
                    continue;
                }

                $data = $this->fillBlock($block['type'], $block['data'], $business, $budget);

                if ($data !== $block['data']) {
                    $block['data'] = $data;
                    $blocks[$index] = $block;
                    $changed = true;
                }
            }

            if ($changed) {
                $page->update(['blocks' => $blocks]);
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function fillBlock(string $type, array $data, Business $business, PhotoBudget $budget): array
    {
        // The query is consumed whatever happens next — including for types
        // this action never fills — so a processed draft carries no transit
        // keys, and a re-run does not re-spend them.
        $query = is_string($data[BlockShape::IMAGE_QUERY_KEY] ?? null) ? $data[BlockShape::IMAGE_QUERY_KEY] : null;
        unset($data[BlockShape::IMAGE_QUERY_KEY]);

        return match ($type) {
            'hero' => $this->fillHero($data, $query ?? $this->fallbackQuery($business), $budget),
            'gallery' => $this->fillGallery($data, $query ?? $this->fallbackQuery($business), $budget),
            'offerings' => $this->fillItems($data, 'items', 'name', $business, $budget),
            'features' => $this->fillItems($data, 'features', 'title', $business, $budget),
            default => $data,
        };
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function fillHero(array $data, string $query, PhotoBudget $budget): array
    {
        if (filled($data['image_id'] ?? null) || filled($data['image_url'] ?? null)) {
            return $data;
        }

        $media = $this->findPhotos($query, 1, $budget)[0] ?? null;

        if ($media instanceof Media) {
            $data['image_id'] = $media->getKey();
        }

        return $data;
    }

    /**
     * One search fills the whole gallery with DISTINCT photos; items that
     * already carry an image keep it, and a model-authored item's alt text
     * outranks the provider's.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function fillGallery(array $data, string $query, PhotoBudget $budget): array
    {
        $images = is_array($data['images'] ?? null) ? array_values($data['images']) : [];

        if ($images === []) {
            $images = array_fill(0, self::EMPTY_GALLERY_PHOTOS, []);
        }

        // Keyed by image position, value narrowed to the item array — the
        // slots still waiting for a photograph.
        $needy = [];

        foreach ($images as $index => $item) {
            if (is_array($item) && blank($item['media_id'] ?? null) && blank($item['url'] ?? null)) {
                $needy[$index] = $item;
            }
        }

        if ($needy === []) {
            return $data;
        }

        $found = $this->findPhotos($query, count($needy), $budget);

        if ($found === []) {
            // Provider down or budget spent: do not persist the scaffold of
            // empty items an empty gallery was padded with.
            return $data;
        }

        $positions = array_keys($needy);

        foreach ($found as $offset => $media) {
            $index = $positions[$offset];
            $item = $needy[$index];
            $item['media_id'] = $media->getKey();

            if (blank($item['alt'] ?? null) && filled($media->alt)) {
                $item['alt'] = $media->alt;
            }

            $images[$index] = $item;
        }

        $data['images'] = $images;

        return $data;
    }

    /**
     * Per-item photos for priced offerings and feature rows, searched by the
     * item's own label plus the business category — items are the one slot
     * where the model's block-level query cannot help, because every item
     * needs a DIFFERENT photo.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function fillItems(array $data, string $listKey, string $labelKey, Business $business, PhotoBudget $budget): array
    {
        $items = is_array($data[$listKey] ?? null) ? array_values($data[$listKey]) : [];

        foreach ($items as $index => $item) {
            if (! is_array($item) || filled($item['image_id'] ?? null)) {
                continue;
            }

            $label = is_string($item[$labelKey] ?? null) ? mb_trim($item[$labelKey]) : '';

            if ($label === '') {
                continue;
            }

            $media = $this->findPhotos(mb_trim($label.' '.($business->category ?? '')), 1, $budget)[0] ?? null;

            if ($media instanceof Media) {
                $item['image_id'] = $media->getKey();
                $items[$index] = $item;
                $data[$listKey] = $items;
            }
        }

        return $data;
    }

    /**
     * Up to `$needed` distinct photos for one query: the shared library first,
     * the provider only for what the library could not cover.
     *
     * Library-first is the point of having a library. A photo another tenant's
     * site already imported costs no request, no download and nothing from the
     * budget, so the catalogue makes every generation after the first cheaper
     * and faster — and it still works when the provider is down or the budget
     * is spent, which is why the library pass runs BEFORE the `canSearch()`
     * guard.
     *
     * Over-fetches slightly on both sides so the in-run dedup still has enough
     * left to choose from when earlier sections already took the top results.
     *
     * @return list<Media>
     */
    private function findPhotos(string $query, int $needed, PhotoBudget $budget): array
    {
        $found = $this->takeFromLibrary($query, $needed, $budget);

        if (count($found) >= $needed || ! $budget->canSearch()) {
            return $found;
        }

        $budget->spendSearch();

        $missing = $needed - count($found);
        $photos = $budget->unused($this->provider->search($query, PhotoOrientation::Landscape, $missing + 3));

        foreach ($photos as $photo) {
            if (count($found) >= $needed || ! $budget->canTake()) {
                break;
            }

            $media = $this->import->handle($photo, $query);

            if ($media instanceof Media) {
                $budget->take($photo);
                $found[] = $media;
            }
        }

        return $found;
    }

    /**
     * The library pass: adopt matching photos into this tenant's media library.
     * Least-used first, which is {@see FindLibraryPhotos}'s ordering and the
     * reason reuse does not make every generated site look alike.
     *
     * @return list<Media>
     */
    private function takeFromLibrary(string $query, int $needed, PhotoBudget $budget): array
    {
        $candidates = $budget->unusedLibrary(
            $this->library->handle($query, PhotoOrientation::Landscape, $needed + 3),
        );

        $found = [];

        foreach ($candidates as $candidate) {
            if (count($found) >= $needed) {
                break;
            }

            $media = $this->adopt->handle($candidate);

            if ($media instanceof Media) {
                $budget->reuse($candidate);
                $found[] = $media;
            }
        }

        return $found;
    }

    private function fallbackQuery(Business $business): string
    {
        return $business->category ?? $business->name;
    }
}
