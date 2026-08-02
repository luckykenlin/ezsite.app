<?php

declare(strict_types=1);

namespace App\Actions\Posts;

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
    public function handle(Post $post, bool $published = true): Post
    {
        $post->update([
            'status' => $published ? PostStatus::Published : PostStatus::Draft,
            'published_at' => $published ? ($post->published_at ?? now()) : $post->published_at,
        ]);

        return $post;
    }
}
