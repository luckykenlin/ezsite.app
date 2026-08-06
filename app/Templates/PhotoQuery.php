<?php

declare(strict_types=1);

namespace App\Templates;

use App\Enums\PhotoCategory;
use App\StockPhotos\PhotoOrientation;

/**
 * One search a template wants pre-warmed in the shared photo library before
 * its demo site is seeded.
 *
 * Pre-warming is what makes template application feel instant: the wizard's
 * image pass draws from the library first
 * ({@see \App\Actions\Pages\PopulateDraftImages::takeFromLibrary()}), so a
 * query that already landed during `demo:seed` costs the applying user no
 * provider request at all.
 *
 * The {@see $category} is advisory — the library guesses its own from the
 * query and alt text ({@see PhotoCategory::guess()}) — and is recorded here so
 * a template says out loud what kind of picture it is asking for.
 */
final readonly class PhotoQuery
{
    public function __construct(
        public string $query,
        public PhotoOrientation $orientation = PhotoOrientation::Landscape,
        public ?PhotoCategory $category = null,
        public int $count = 4,
    ) {
        //
    }
}
