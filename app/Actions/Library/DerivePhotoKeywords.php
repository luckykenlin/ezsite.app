<?php

declare(strict_types=1);

namespace App\Actions\Library;

use App\Enums\PhotoCategory;

/**
 * Turn what we know about an incoming photo — the query that found it and the
 * provider's own alt text — into the two classifications the shared library is
 * filtered by: a tag list and a category.
 *
 * The `keywords` blob that {@see \App\Models\LibraryPhoto::scopeMatching()}
 * actually searches is deliberately NOT built here. It is a derived column,
 * recomputed by the model on every save, so that a curator fixing a photo's
 * description in the central panel also fixes what it is findable by. Building
 * it here as well would mean two spellings of the same rule.
 *
 * Deliberately NOT an AI call. Captioning every import would put a model in
 * the middle of the photo pipeline, which currently degrades gracefully to
 * "no photos" on any failure; a keyword split cannot fail. The provider's alt
 * text is already human-written, and the search query is literally what
 * someone was looking for when this photo answered — between them there is
 * enough signal, and the central panel can improve any row by hand.
 */
final readonly class DerivePhotoKeywords
{
    /**
     * Words carrying no search signal, stripped from tags so a photo is not
     * tagged "with" or "front".
     *
     * @var list<string>
     */
    private const array STOP_WORDS = [
        'the', 'and', 'for', 'with', 'from', 'that', 'this', 'near', 'onto', 'into',
        'over', 'under', 'front', 'back', 'photo', 'image', 'picture', 'shot', 'view',
        'some', 'more', 'very', 'other', 'their', 'there', 'here', 'while', 'during',
    ];

    /**
     * @return array{tags: list<string>, category: PhotoCategory|null}
     */
    public function handle(?string $searchQuery, ?string $alt, ?string $description = null): array
    {
        $source = mb_trim(implode(' ', array_filter([$searchQuery, $alt, $description])));

        return [
            'tags' => $this->tags($source),
            'category' => PhotoCategory::guess($source),
        ];
    }

    /**
     * @return list<string>
     */
    private function tags(string $source): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($source)) ?: [];

        $tags = array_filter(
            $words,
            fn (string $word): bool => mb_strlen($word) > 2 && ! in_array($word, self::STOP_WORDS, true),
        );

        return array_values(array_unique($tags));
    }
}
