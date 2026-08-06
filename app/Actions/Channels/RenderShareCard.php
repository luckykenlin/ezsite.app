<?php

declare(strict_types=1);

namespace App\Actions\Channels;

use App\Actions\StoreMedia;
use App\Images\OptimizedImage;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * The 1200x630 image a social platform shows when somebody shares an update.
 *
 * This is the best free growth lever a WEBSITE BUILDER specifically has, and the
 * reason is worth stating: we own the `<head>` of every tenant page. Facebook's
 * share dialog ignores `quote`, `description` and `picture` entirely — caption
 * prefill is dead — but it renders the card from the target page's OpenGraph tags,
 * which are ours. So a plain `sharer.php` link with no API, no OAuth, no app
 * review and no per-post fee still produces a designed, correctly-cropped card,
 * and it stays correct forever even after the sharer rewrites their own words.
 * `x.com/intent/post` does the same.
 *
 * One rendition, cover-cropped, NO TEXT LOCKUP. Drawing text with GD needs a
 * bundled TTF per {@see \App\Design\FontPair} and `resources/fonts/` does not
 * exist — that is a font-licensing subproject, and GD cannot read an SVG logo
 * either. The crop alone unlocks the lever, which is the whole point; type on top
 * is a later, separately-scoped nicety.
 *
 * The one image path in this app that deliberately BYPASSES
 * {@see \App\Actions\OptimizeImage}'s WebP gate. Every social scraper accepts
 * JPEG and only some accept WebP, and Google Business Profile post media is
 * JPG/PNG only — so the format that is right for the site is wrong here. Same
 * contract as that action otherwise: GD (the driver present everywhere), and it
 * NEVER THROWS. A card it cannot render returns null and the update falls back to
 * its raw cover, which is a worse crop rather than a broken page.
 */
final readonly class RenderShareCard
{
    /**
     * OpenGraph's canonical ratio, and what Facebook, X and LinkedIn all crop to.
     */
    private const int WIDTH = 1200;

    private const int HEIGHT = 630;

    /**
     * 82 is where a photographic JPEG stops visibly improving. A share card is
     * decoration on somebody else's page, not the photograph itself.
     */
    private const int QUALITY = 82;

    public function __construct(private StoreMedia $storeMedia)
    {
        //
    }

    /**
     * Renders the card for an update and returns the media id, or null.
     *
     * Idempotent by cover: re-rendering the same cover returns the existing card
     * rather than filling the library with near-identical crops, which matters
     * because publishing is a verb an operator presses more than once.
     */
    public function handle(Post $post): ?int
    {
        if ($post->cover_media_id === null) {
            return null;
        }

        $cover = Media::query()->find($post->cover_media_id);

        if ($cover === null) {
            return null;
        }

        $existing = $this->existingCard($post, $cover);

        if ($existing !== null) {
            return $existing;
        }

        $source = Storage::disk($cover->disk)->get($cover->path);

        if ($source === null) {
            // The media row outlived its file. Degrade to "no card" rather than
            // write one pointing at nothing.
            Log::warning('share_card.source_missing', ['post_id' => $post->id, 'media_id' => $cover->id]);

            return null;
        }

        $encoded = $this->encode($source);

        return $encoded === null ? null : $this->store($post, $cover, $encoded);
    }

    /**
     * A card already rendered from this exact cover.
     *
     * Keyed on the cover rather than on the post, because changing the photo is the
     * only edit that invalidates a card — retitling an update does not, since there
     * is no text on it.
     */
    private function existingCard(Post $post, Media $cover): ?int
    {
        if ($post->share_card_media_id === null) {
            return null;
        }

        $card = Media::query()->find($post->share_card_media_id);

        return $card !== null && $card->title === $this->fingerprint($cover) ? (int) $card->id : null;
    }

    /**
     * Which cover a card was cut from, stored in `title` because Curator's schema
     * has no column of our own to put it in and `alt` belongs to the operator.
     */
    private function fingerprint(Media $cover): string
    {
        return 'share-card:'.$cover->id;
    }

    private function encode(string $source): ?string
    {
        try {
            return (string) ImageManager::gd()
                ->read($source)
                // cover() scales AND crops to the exact frame, centre-weighted —
                // the whole job. `scaleDown` would letterbox, and a card with bars
                // reads as a broken image in a feed.
                ->cover(self::WIDTH, self::HEIGHT)
                ->toJpeg(self::QUALITY);
        } catch (Throwable $throwable) {
            Log::warning('share_card.render_failed', ['reason' => $throwable->getMessage()]);

            return null;
        }
    }

    /**
     * StoreMedia's public visibility is load-bearing here: a scraper fetches
     * this URL unauthenticated, and a Google Business Profile post accepts a
     * `sourceUrl` only — there is no multipart upload into a local post.
     */
    private function store(Post $post, Media $cover, string $encoded): ?int
    {
        try {
            $card = $this->storeMedia->handle(
                new OptimizedImage($encoded, self::WIDTH, self::HEIGHT, 'jpg', 'image/jpeg'),
                directory: 'share-cards',
                prefix: 'share',
                extra: ['alt' => $post->title, 'title' => $this->fingerprint($cover)],
            );
        } catch (Throwable $throwable) {
            // The never-throws contract above outranks StoreMedia's fail-loud
            // default: a card that cannot be written is just no card.
            Log::warning('share_card.store_failed', ['post_id' => $post->id, 'reason' => $throwable->getMessage()]);

            return null;
        }

        return (int) $card->id;
    }
}
