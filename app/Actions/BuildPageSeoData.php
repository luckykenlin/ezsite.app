<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Business;
use App\Models\Page;
use App\Site\BindResolver;
use App\Site\PublicUrl;
use App\Site\SeoFallbacks;
use RalphJSmit\Laravel\SEO\Schema\FaqPageSchema;
use RalphJSmit\Laravel\SEO\SchemaCollection;
use RalphJSmit\Laravel\SEO\Support\SEOData;

/**
 * Everything the <head> of a public tenant page needs, as the SEOData object
 * ralphjsmit/laravel-seo renders into title/description/canonical/robots/
 * OpenGraph/Twitter/JSON-LD tags.
 *
 * The page's own SEO columns win; anything left empty falls back through
 * {@see SeoFallbacks} to the tenant's Business, so a site is never shipped
 * with a blank description or share image.
 */
final readonly class BuildPageSeoData
{
    public function __construct(
        private BindResolver $bindResolver,
        private SeoFallbacks $fallbacks,
        private BuildLocalBusinessSchema $buildLocalBusinessSchema,
        private CollectPageFaqs $collectPageFaqs,
    ) {
        //
    }

    public function handle(Page $page): SEOData
    {
        $business = $this->bindResolver->business();
        $image = $this->fallbacks->image($business, $page->seo_image_media_id);

        return new SEOData(
            title: $this->fallbacks->title($page->seo_title ?? $page->title, $business),
            description: $this->fallbacks->description($page->seo_description, $business),
            image: $image,
            url: PublicUrl::to($page),
            enableTitleSuffix: false,
            modified_time: $page->updated_at,
            schema: $this->schema($page, $business, $image),
            site_name: $business?->name,
            robots: $page->is_indexable ? null : 'noindex, nofollow',
        );
    }

    /**
     * The page's JSON-LD nodes, or null when it has none to emit.
     *
     * Two nodes, scoped differently on purpose. LocalBusiness belongs to the
     * SITE, so only the home page carries it — sub-pages would duplicate the
     * node under a wrong URL. FAQPage describes the DOCUMENT, so it belongs to
     * whichever page actually holds the questions.
     *
     * They are also built differently, and that is deliberate rather than
     * inconsistent: LocalBusiness has no representation in the SEO package, so
     * {@see BuildLocalBusinessSchema} assembles the array itself; FAQPage does,
     * so the package keeps ownership of the schema.org shape and
     * {@see CollectPageFaqs} supplies only the question/answer pairs. One less
     * copy of a spec we would otherwise have to track.
     *
     * @return SchemaCollection<array-key>|null
     */
    private function schema(Page $page, ?Business $business, ?string $image): ?SchemaCollection
    {
        $localBusiness = $business instanceof Business && $page->isHome()
            ? $this->buildLocalBusinessSchema->handle($business, $this->bindResolver->location(null), $image)
            : [];

        $faqs = $this->collectPageFaqs->handle($page);

        if ($localBusiness === [] && $faqs === []) {
            return null;
        }

        $schema = SchemaCollection::make();

        if ($localBusiness !== []) {
            $schema->add(fn (): array => $localBusiness);
        }

        if ($faqs !== []) {
            $schema->addFaqPage(function (FaqPageSchema $faqPage) use ($faqs): FaqPageSchema {
                foreach ($faqs as $faq) {
                    $faqPage->addQuestion($faq['question'], $faq['answer']);
                }

                return $faqPage;
            });
        }

        return $schema;
    }
}
