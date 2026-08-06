<?php

declare(strict_types=1);

namespace App\Site;

/**
 * An update's plain-text body, as the public page renders it.
 *
 * Text formatting, not model state — which is why it lives here and not on
 * {@see \App\Models\Post}. The view renders one escaped `<p>` per paragraph
 * rather than `nl2br` over the whole column, which keeps the markup
 * structural and keeps `{!! !!}` off a page built from tenant-authored text.
 */
final readonly class PostBody
{
    public function __construct(private ?string $body)
    {
        //
    }

    /**
     * The body split into paragraphs on blank lines.
     *
     * @return list<string>
     */
    public function paragraphs(): array
    {
        if ($this->body === null) {
            return [];
        }

        $paragraphs = preg_split('/\R{2,}/', mb_trim($this->body)) ?: [];

        return array_values(array_filter(
            array_map(mb_trim(...), $paragraphs),
            static fn (string $paragraph): bool => $paragraph !== '',
        ));
    }
}
