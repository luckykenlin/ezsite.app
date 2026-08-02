<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PostKind;
use App\Models\Business;
use App\Models\Post;
use App\Site\BindResolver;
use App\Site\PublicUrl;
use App\Site\SeoFallbacks;
use Illuminate\Support\Str;
use RalphJSmit\Laravel\SEO\Schema\ArticleSchema;
use RalphJSmit\Laravel\SEO\Schema\BreadcrumbListSchema;
use RalphJSmit\Laravel\SEO\SchemaCollection;
use RalphJSmit\Laravel\SEO\Support\SEOData;

/**
 * Everything the <head> of a public update page needs.
 *
 * A SIBLING of {@see BuildPageSeoData} rather than a widening of it. That action
 * is typed to {@see \App\Models\Page} and asks it `isHome()`; putting a union in
 * front of one extra caller would drag `Page` into an abstraction it does not
 * need, and — worse — risk emitting the site-scoped LocalBusiness node on an
 * update, where it would duplicate the home page's under the wrong URL. The
 * fallback chains are identical because both actions read {@see SeoFallbacks}
 * — a visitor should not be able to tell which kind of page they landed on
 * from the share card.
 *
 * This is also where the two schemas `ralphjsmit/laravel-seo` has always shipped
 * finally get used: an update is an `Article`, it has a real position in a
 * `BreadcrumbList`, and — when it is an offer or an event with actual dates — it
 * is the one thing on a small-business site genuinely eligible for a rich result.
 */
final readonly class BuildPostSeoData
{
    public function __construct(
        private BindResolver $bindResolver,
        private SeoFallbacks $fallbacks,
    ) {
        //
    }

    public function handle(Post $post): SEOData
    {
        $business = $this->bindResolver->business();

        // The generated 1200x630 rendition first — RenderShareCard writes it
        // specifically so a scraper gets the aspect ratio it wants — then the
        // raw cover for an update whose card has not been rendered yet.
        $image = $this->fallbacks->image($business, $post->share_card_media_id, $post->cover_media_id);

        return new SEOData(
            title: $this->fallbacks->title($post->seo_title ?? $post->title, $business),
            description: $this->fallbacks->description($post->seo_description ?? $post->excerpt, $business),
            author: $post->author_name,
            image: $image,
            url: PublicUrl::to($post),
            enableTitleSuffix: false,
            published_time: $post->published_at,
            modified_time: $post->updated_at,
            schema: $this->schema($post, $business, $image),
            type: 'article',
            site_name: $business?->name,
            robots: $post->is_indexable ? null : 'noindex, nofollow',
        );
    }

    /**
     * @return SchemaCollection<array-key>
     */
    private function schema(Post $post, ?Business $business, ?string $image): SchemaCollection
    {
        $schema = SchemaCollection::make()
            ->addArticle(fn (ArticleSchema $article): ArticleSchema => $this->article($article, $post, $image))
            ->addBreadcrumbs(fn (BreadcrumbListSchema $breadcrumbs): BreadcrumbListSchema => $breadcrumbs
                ->prependBreadcrumbs([
                    ($business->name ?? __('Home')) => url('/'),
                    __('Updates') => PublicUrl::updatesIndex(),
                ]));

        $dated = $this->datedNode($post, $business, $image);

        // Hand-assembled, the way BuildLocalBusinessSchema assembles LocalBusiness
        // and for the same reason: the package represents neither Offer nor Event,
        // so we own that shape rather than pretending it owns it.
        return $dated === [] ? $schema : $schema->add(fn (): array => $dated);
    }

    private function article(ArticleSchema $article, Post $post, ?string $image): ArticleSchema
    {
        $article->headline = Str::limit($post->title, 110);
        $article->datePublished = $post->published_at;
        $article->dateModified = $post->updated_at;
        $article->image = $image;

        if (filled($post->author_name)) {
            $article->addAuthor($post->author_name);
        }

        return $article;
    }

    /**
     * The `Offer` or `Event` node, when the update is one and has a real window.
     *
     * Both require the dates to be worth emitting at all — an offer with no end
     * is a price, and an event with no date is an announcement — so a kind
     * without them contributes nothing rather than an incomplete node that a
     * validator would flag.
     *
     * @return array<string, mixed>
     */
    private function datedNode(Post $post, ?Business $business, ?string $image): array
    {
        if (! $post->kind->requiresDateRange() || $post->starts_at === null || $post->ends_at === null) {
            return [];
        }

        $node = [
            '@context' => 'https://schema.org',
            '@type' => $post->kind === PostKind::Offer ? 'Offer' : 'Event',
            'name' => $post->title,
            'url' => PublicUrl::to($post),
        ];

        if (filled($post->excerpt)) {
            $node['description'] = $post->excerpt;
        }

        if ($image !== null) {
            $node['image'] = $image;
        }

        if ($post->kind === PostKind::Offer) {
            $node['availabilityStarts'] = $post->starts_at->toAtomString();
            $node['availabilityEnds'] = $post->ends_at->toAtomString();
        } else {
            $node['startDate'] = $post->starts_at->toAtomString();
            $node['endDate'] = $post->ends_at->toAtomString();
        }

        if ($business instanceof Business) {
            $node[$post->kind === PostKind::Offer ? 'offeredBy' : 'organizer'] = [
                '@type' => 'LocalBusiness',
                'name' => $business->name,
                'url' => url('/'),
            ];
        }

        return $node;
    }
}
