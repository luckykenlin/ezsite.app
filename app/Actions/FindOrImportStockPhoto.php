<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Library\AdoptLibraryPhoto;
use App\Actions\Library\FindOrImportLibraryPhoto;
use App\Models\LibraryPhoto;
use App\Models\Media;
use App\StockPhotos\StockPhoto;

/**
 * Get one provider photo into the current tenant's media library, via the
 * shared library.
 *
 * Two steps, each interesting on its own:
 * {@see FindOrImportLibraryPhoto} downloads it into the cross-tenant catalogue
 * at most once ever, and {@see AdoptLibraryPhoto} gives THIS tenant its own
 * media row for it. So a photo any tenant has already imported costs no HTTP
 * and no rate-limit token, and re-running a generation costs neither either.
 *
 * Kept as a named action with its original `handle(StockPhoto): ?Media`
 * signature rather than being dissolved into its two halves, because that is
 * the verb {@see Pages\PopulateDraftImages} and the chat's photo
 * tools actually want: "make this search result usable on this tenant's page".
 * Callers that want only the catalogue half (the `library:import` command) use
 * FindOrImportLibraryPhoto directly, and only from central context.
 *
 * Null on any failure, never an exception — the caller ships the draft without
 * that photo, which is exactly the pre-pipeline behavior.
 */
final readonly class FindOrImportStockPhoto
{
    public function __construct(
        private FindOrImportLibraryPhoto $import,
        private AdoptLibraryPhoto $adopt,
    ) {
        //
    }

    public function handle(StockPhoto $photo, ?string $searchQuery = null): ?Media
    {
        $libraryPhoto = $this->import->handle($photo, $searchQuery);

        if (! $libraryPhoto instanceof LibraryPhoto) {
            return null;
        }

        return $this->adopt->handle($libraryPhoto);
    }
}
