<?php

declare(strict_types=1);

namespace App\Ai;

use App\Actions\Library\AdoptLibraryPhoto;
use App\Models\LibraryPhoto;
use App\Models\Media;

/**
 * Turn shared-library search results into something the chat agent can act on:
 * adopt each photo into the current tenant's media library, then describe the
 * results as media ids.
 *
 * The adoption is why this exists. Both photo tools
 * ({@see Tools\SearchPhotoLibrary},
 * {@see Tools\ImportStockPhotos}) must hand back ids that
 * {@see Tools\SetBlockImage} accepts, and that tool takes a media id it
 * validates through RLS — as it should, since a block never references a
 * library row. So "offering" a photo to the model necessarily means giving the
 * tenant its own copy first. Adoption is idempotent and metadata-only for a
 * photo the tenant already has, so offering the same photo twice costs nothing,
 * and the operator sees the photos the assistant showed them in their own media
 * panel afterwards.
 *
 * Shared between the two tools rather than duplicated in each, because the
 * announcement wording is a contract with the model — the agent's instructions
 * tell it to use "media ids announced in this conversation", and two spellings
 * of that would eventually disagree.
 */
final readonly class PhotoAnnouncement
{
    public function __construct(private AdoptLibraryPhoto $adopt) {}

    /**
     * @param  list<LibraryPhoto>  $photos
     */
    public function handle(array $photos, string $emptyMessage): string
    {
        $lines = [];

        foreach ($photos as $photo) {
            $media = $this->adopt->handle($photo);

            if ($media instanceof Media) {
                $lines[] = $this->line((int) $media->id, $photo);
            }
        }

        if ($lines === []) {
            return $emptyMessage;
        }

        return "These photos are now in the media library — place one with the set block image tool:\n"
            .implode("\n", $lines);
    }

    /**
     * One offered photo, in the shape the model reads: the media id first,
     * because that is the only part it has to reproduce exactly, then what the
     * photo shows and the facts that decide whether it fits the slot — shape
     * for the layout, and darkness for whether text can sit on top of it.
     */
    private function line(int $mediaId, LibraryPhoto $photo): string
    {
        $facts = array_filter([
            $photo->orientation?->value,
            sprintf('%dx%d', $photo->width, $photo->height),
            $photo->category?->value,
            $photo->dominant_color,
            $photo->is_dark ? 'dark enough for overlaid text' : 'too light for overlaid text',
        ]);

        return sprintf(
            '- media id %d — %s (%s)',
            $mediaId,
            $photo->alt !== null && $photo->alt !== '' ? $photo->alt : 'no description',
            implode(', ', $facts),
        );
    }
}
