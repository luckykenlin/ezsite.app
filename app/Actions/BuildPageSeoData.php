<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Business;
use App\Models\Page;
use App\Site\BindResolver;
use App\Site\MediaResolver;
use Illuminate\Support\Str;
use RalphJSmit\Laravel\SEO\SchemaCollection;
use RalphJSmit\Laravel\SEO\Support\SEOData;

/**
 * Everything the <head> of a public tenant page needs, as the SEOData object
 * ralphjsmit/laravel-seo renders into title/description/canonical/robots/
 * OpenGraph/Twitter/JSON-LD tags.
 *
 * The page's own SEO columns win; anything left empty falls back to the
 * tenant's Business, so a site is never shipped with a blank description or
 * share image. config/seo.php holds NO tenant-specific value (it is a central
 * singleton) — every per-tenant value is set here, including the title suffix,
 * which is why enableTitleSuffix is off.
 */
final readonly class BuildPageSeoData
{
    public function __construct(
        private BindResolver $bindResolver,
        private MediaResolver $mediaResolver,
        private BuildLocalBusinessSchema $buildLocalBusinessSchema,
    ) {
        //
    }

    public function handle(Page $page): SEOData
    {
        $business = $this->bindResolver->business();
        $image = $this->image($page, $business);

        return new SEOData(
            title: $this->title($page, $business),
            description: $this->description($page, $business),
            image: $image,
            url: url($page->getUrl()),
            enableTitleSuffix: false,
            modified_time: $page->updated_at,
            schema: $this->schema($page, $business, $image),
            site_name: $business?->name,
            robots: $page->is_indexable ? null : 'noindex, nofollow',
        );
    }

    private function title(Page $page, ?Business $business): string
    {
        $title = $page->seo_title ?? $page->title;

        return $business instanceof Business
            ? sprintf('%s - %s', $title, $business->name)
            : $title;
    }

    private function description(Page $page, ?Business $business): ?string
    {
        $description = $page->seo_description;

        if ($description === null && $business instanceof Business) {
            $description = $business->tagline ?? $business->description;
        }

        return $description === null ? null : Str::limit($description, 160);
    }

    /**
     * The share image: the page's own pick, else the tenant's logo (which
     * already resolves media-then-legacy-upload).
     */
    private function image(Page $page, ?Business $business): ?string
    {
        return $this->mediaResolver->url($page->seo_image_media_id)
            ?? $business?->logoUrl();
    }

    /**
     * LocalBusiness belongs to the site as a whole, so only the home page
     * carries it; sub-pages would duplicate the node under a wrong URL.
     *
     * @return SchemaCollection<array-key>|null
     */
    private function schema(Page $page, ?Business $business, ?string $image): ?SchemaCollection
    {
        if (! $business instanceof Business || ! $page->isHome()) {
            return null;
        }

        $schema = $this->buildLocalBusinessSchema->handle(
            $business,
            $this->bindResolver->location(null),
            $image,
        );

        return SchemaCollection::make()->add(fn (): array => $schema);
    }
}
