<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Actions\Channels\RenderShareCard;
use App\Enums\PostStatus;
use App\Models\Post;

/**
 * Takes an update live, or back to draft.
 *
 * Mirrors {@see \App\Actions\Pages\PublishPage} and exists for the same reason:
 * everything publishing comes to mean — stamping the moment it went live, the
 * share card, and eventually the fan-out to a connected Google Business Profile —
 * belongs in one place, so the table's row action and the composer's button can
 * never drift apart.
 *
 * `published_at` is stamped on the FIRST publish and then left alone. It is the
 * date the visitor reads and the date the feed sorts by, so an operator fixing a
 * typo six weeks later must not push their own announcement back to the top.
 */
final readonly class PublishPost
{
    public function __construct(private RenderShareCard $shareCard) {}

    public function handle(Post $post, bool $published = true): Post
    {
        $post->update([
            'status' => $published ? PostStatus::Published : PostStatus::Draft,
            'published_at' => $published ? ($post->published_at ?? now()) : $post->published_at,
            // Cut on the way out, not on the way in: an operator who changes the
            // photo three times while drafting should not leave three crops behind.
            // Returns null for an update with no photo, or one whose cover cannot be
            // read — a worse crop is a fine outcome, a failed publish is not.
            'share_card_media_id' => $published
                ? ($this->shareCard->handle($post) ?? $post->share_card_media_id)
                : $post->share_card_media_id,
        ]);

        return $post;
    }
}
