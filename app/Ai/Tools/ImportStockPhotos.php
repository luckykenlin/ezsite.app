<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Library\FindOrImportLibraryPhoto;
use App\Ai\PhotoAnnouncement;
use App\Models\LibraryPhoto;
use App\StockPhotos\PhotoOrientation;
use App\StockPhotos\StockPhotoProvider;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Import NEW photographs from the stock provider when the shared library has
 * nothing that fits — the escape hatch behind {@see SearchPhotoLibrary}.
 *
 * The order matters and is the agent's decision to make, which is why these are
 * two tools and not one with a flag: the model can see whether the library
 * results actually answer the request, and only it can judge that. The
 * instructions ask for library-first; this tool is what "no, none of those
 * work" leads to.
 *
 * Imports land in the SHARED library, not just this tenant's, so a photo
 * fetched for one site is free for every site afterwards — the catalogue grows
 * through normal use. Then they are adopted and announced as media ids exactly
 * like a library search result.
 *
 * Inert rather than absent when no provider key is configured: the container
 * binds {@see \App\StockPhotos\NullProvider}, whose search returns nothing, so
 * the tool reports that it found nothing instead of erroring.
 */
final readonly class ImportStockPhotos implements Tool
{
    private const int MAX_RESULTS = 4;

    public function __construct(
        private StockPhotoProvider $provider,
        private FindOrImportLibraryPhoto $import,
        private PhotoAnnouncement $announcement,
    ) {
        //
    }

    public function description(): string
    {
        return 'Import new photographs from the stock photo provider, for when the shared photo library '
            .'has nothing that fits — search the library first. Describe the subject in English words. '
            .'Imported photos join the shared library and are added to the media library, and are returned '
            .'with their media ids for the set block image tool. Never use this for a photo of the actual '
            .'business, its real staff, or its logo: a stock photo posing as those is a fabricated fact.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('English words describing the subject of the photo to import.')
                ->required(),
            'orientation' => $schema->string()
                ->description('The shape the slot needs: landscape for a wide hero or gallery image, portrait for a tall one, square for a grid tile.')
                ->enum(array_column(PhotoOrientation::cases(), 'value')),
            'count' => $schema->integer()
                ->description('How many photos to import, 1 to '.self::MAX_RESULTS.'.'),
        ];
    }

    public function handle(Request $request): string
    {
        $arguments = $request->toArray();
        $query = $arguments['query'] ?? null;

        if (! is_string($query) || mb_trim($query) === '') {
            return 'No search words were given, so nothing was imported. Describe the subject of the photo you want.';
        }

        $count = $arguments['count'] ?? null;
        $wanted = is_int($count) ? max(1, min($count, self::MAX_RESULTS)) : 1;

        $orientation = is_string($arguments['orientation'] ?? null)
            ? PhotoOrientation::tryFrom($arguments['orientation'])
            : null;

        $results = $this->provider->search($query, $orientation ?? PhotoOrientation::Landscape, $wanted);
        $photos = [];

        foreach ($results as $result) {
            if (count($photos) >= $wanted) {
                break;
            }

            $photo = $this->import->handle($result, $query);

            if ($photo instanceof LibraryPhoto) {
                $photos[] = $photo;
            }
        }

        return $this->announcement->handle(
            $photos,
            sprintf(
                'No photos could be imported for "%s" — the provider returned nothing or is unavailable. '
                .'Tell the operator, and offer a change you can make without a photo.',
                $query,
            ),
        );
    }
}
