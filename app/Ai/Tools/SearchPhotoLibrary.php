<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Library\FindLibraryPhotos;
use App\Ai\PhotoAnnouncement;
use App\Enums\PhotoCategory;
use App\StockPhotos\PhotoOrientation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Find a photograph in the shared library — the app's own accumulating
 * catalogue of licensed stock photography, shared by every site.
 *
 * The first thing the agent should reach for when a request needs a photo,
 * because a library hit costs no provider request and no download: the
 * photograph is already on disk. {@see ImportStockPhotos} is the fallback when
 * nothing here fits, and the agent's instructions say so in that order.
 *
 * Matching photos are adopted into the tenant's own media library on the way
 * out ({@see PhotoAnnouncement}), so the ids this returns are ordinary media
 * ids that {@see SetBlockImage} accepts unchanged.
 *
 * One verb, like the rest of the roster: this finds photos, it does not place
 * them. Placing is SetBlockImage, and switching a block to its photographic
 * layout is {@see SetBlockVariant}.
 */
final readonly class SearchPhotoLibrary implements Tool
{
    /**
     * Enough for the model to choose between without turning a tool result
     * into a catalogue dump — and a hard bound on how many photos one search
     * adopts into the tenant's library.
     */
    private const int MAX_RESULTS = 6;

    public function __construct(
        private FindLibraryPhotos $find,
        private PhotoAnnouncement $announcement,
    ) {
        //
    }

    public function description(): string
    {
        return 'Search the shared photo library for a photograph to use on the page — always try this '
            .'before importing a new photo, because these are already downloaded. Describe the subject '
            .'in English words ("sunlit cafe interior", "hairdresser at work"). Matching photos are added '
            .'to the media library and returned with their media ids, which you place with the set block '
            .'image tool. Ask for the orientation the slot needs, and for a dark photo when text will sit '
            .'on top of it.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('English words describing the subject of the photo you want.')
                ->required(),
            'orientation' => $schema->string()
                ->description('The shape the slot needs: landscape for a wide hero or gallery image, portrait for a tall one, square for an avatar or grid tile.')
                ->enum(array_column(PhotoOrientation::cases(), 'value')),
            'category' => $schema->string()
                ->description('Narrow to one subject category, when the request is clearly about one.')
                ->enum(array_column(PhotoCategory::cases(), 'value')),
            'prefer_dark' => $schema->boolean()
                ->description('True to only return photos dark enough to carry overlaid text — use this for a full-bleed hero or cta background.'),
            'count' => $schema->integer()
                ->description('How many photos to return, 1 to '.self::MAX_RESULTS.'. Ask for several when filling a gallery.'),
        ];
    }

    public function handle(Request $request): string
    {
        $arguments = $request->toArray();
        $query = $arguments['query'] ?? null;

        if (! is_string($query) || mb_trim($query) === '') {
            return 'No search words were given, so nothing was searched. Describe the subject of the photo you want.';
        }

        $count = $arguments['count'] ?? null;
        $preferDark = $arguments['prefer_dark'] ?? null;

        $photos = $this->find->handle(
            $query,
            $this->orientation($arguments['orientation'] ?? null),
            is_int($count) ? max(1, min($count, self::MAX_RESULTS)) : 1,
            $this->category($arguments['category'] ?? null),
            // Only ever narrows to dark photos: "prefer light" is not a request
            // anyone makes, and treating false as "light only" would silently
            // hide usable photos.
            $preferDark === true ? true : null,
        );

        return $this->announcement->handle(
            $photos,
            sprintf(
                'The shared photo library has nothing matching "%s"%s. Import a new photo with the import '
                .'stock photos tool, or tell the operator what to upload.',
                $query,
                $preferDark === true ? ' that is dark enough for overlaid text' : '',
            ),
        );
    }

    private function orientation(mixed $value): ?PhotoOrientation
    {
        return is_string($value) ? PhotoOrientation::tryFrom($value) : null;
    }

    private function category(mixed $value): ?PhotoCategory
    {
        return is_string($value) ? PhotoCategory::tryFrom($value) : null;
    }
}
