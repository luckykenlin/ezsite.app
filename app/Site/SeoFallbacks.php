<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\Business;
use Illuminate\Support\Str;

/**
 * The tenant-wide fallback chains behind every public page's <head>.
 *
 * {@see \App\Actions\BuildPageSeoData}, {@see \App\Actions\BuildPostSeoData}
 * and {@see \App\Actions\BuildPostIndexSeoData} stay separate classes on
 * purpose — each owns its surface's schema and inputs — but their fallbacks
 * are REQUIRED to be identical: a visitor should not be able to tell which
 * kind of page they landed on from the share card. Before this existed, each
 * action carried its own copy of the chains, and the requirement was only as
 * strong as the last copy-paste.
 */
final readonly class SeoFallbacks
{
    public function __construct(private MediaResolver $mediaResolver)
    {
        //
    }

    /**
     * `{page title} - {business name}`, or the bare title on a tenant with no
     * business row yet. The suffix is applied here, per tenant, which is why
     * every caller sets `enableTitleSuffix: false` — config/seo.php is a
     * central singleton and holds no tenant value.
     */
    public function title(string $title, ?Business $business): string
    {
        return $business instanceof Business
            ? sprintf('%s - %s', $title, $business->name)
            : $title;
    }

    /**
     * The surface's own line first — it was written to describe exactly that
     * document — then the tenant's stock description, so a share card is
     * never blank.
     */
    public function description(?string $own, ?Business $business): ?string
    {
        $description = $own;

        if ($description === null && $business instanceof Business) {
            $description = $business->tagline ?? $business->description;
        }

        return $description === null ? null : Str::limit($description, 160);
    }

    /**
     * The share image: the surface's own picks in order, then the tenant's
     * logo (which already resolves media-then-legacy-upload).
     */
    public function image(?Business $business, ?int ...$mediaIds): ?string
    {
        foreach ($mediaIds as $mediaId) {
            $url = $this->mediaResolver->url($mediaId);

            if ($url !== null) {
                return $url;
            }
        }

        return $business?->logoUrl();
    }
}
