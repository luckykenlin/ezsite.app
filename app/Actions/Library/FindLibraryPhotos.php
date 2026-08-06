<?php

declare(strict_types=1);

namespace App\Actions\Library;

use App\Enums\PhotoCategory;
use App\Models\LibraryPhoto;
use App\StockPhotos\PhotoOrientation;

/**
 * Search the shared library. This is the read side of the whole feature: the
 * draft pipeline consults it before spending a provider search, and the chat
 * agent's photo tool is a thin wrapper over it.
 *
 * The ordering is the interesting part. Results come back LEAST-USED FIRST,
 * because a shared library is otherwise a machine for making every generated
 * site look the same — the exact failure the design overhaul was fought over.
 * Ascending `usage_count` spreads demand across the catalogue instead of
 * concentrating it on whatever matched first, and a photo already on twenty
 * sites sinks below one on none.
 *
 * Orientation is a hard filter, not a preference: a portrait photo in a
 * full-bleed hero is a visibly broken page, so it is better to fall through to
 * the provider than to return the wrong shape.
 */
final readonly class FindLibraryPhotos
{
    /**
     * @return list<LibraryPhoto>
     */
    public function handle(
        string $query,
        ?PhotoOrientation $orientation = null,
        int $count = 1,
        ?PhotoCategory $category = null,
        ?bool $isDark = null,
    ): array {
        if ($count < 1) {
            return [];
        }

        $photos = LibraryPhoto::query()
            ->published()
            ->matching($query)
            ->when($orientation instanceof PhotoOrientation, fn ($builder) => $builder->where('orientation', $orientation))
            ->when($category instanceof PhotoCategory, fn ($builder) => $builder->where('category', $category))
            ->when($isDark !== null, fn ($builder) => $builder->where('is_dark', $isDark))
            ->orderBy('usage_count')
            ->orderByDesc('id')
            ->limit($count)
            ->get()
            ->all();

        return array_values($photos);
    }
}
