<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PostKind;
use App\Models\Business;
use App\Models\Post;
use App\Site\BindResolver;
use App\Site\MediaResolver;
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
 * fallback chains are deliberately identical, because a visitor should not be
 * able to tell which kind of page they landed on from the share card.
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
        private MediaResolver $mediaResolver,
    ) {
        //
    }

    public function handle(Post $post): SEOData
    {
        $business = $this->bindResolver->business();
        $image = $this->image($post, $business);

        return new SEOData(
            title: $this->title($post, $business),
            description: $this->description($post, $business),
            author: $post->author_name,
            image: $image,
            url: url($post->getUrl()),
            enableTitleSuffix: false,
            published_time: $post->published_at,
            modified_time: $post->updated_at,
            schema: $this->schema($post, $business, $image),
            type: 'article',
            site_name: $business?->name,
            robots: $post->is_indexable ? null : 'noindex, nofollow',
        );
    }

    private function title(Post $post, ?Business $business): string
    {
        $title = $post->seo_title ?? $post->title;

        return $business instanceof Business
            ? sprintf('%s - %s', $title, $business->name)
            : $title;
    }

    /**
     * The update's own line first, because it was written to be exactly this —
     * one sentence describing one announcement — then the tenant's stock
     * description, so a share card is never blank.
     */
    private function description(Post $post, ?Business $business): ?string
    {
        $description = $post->seo_description ?? $post->excerpt;

        if ($description === null && $business instanceof Business) {
            $description = $business->tagline ?? $business->description;
        }

        return $description === null ? null : Str::limit($description, 160);
    }

    /**
     * The share image, generated rendition first.
     *
     * {@see Channels\RenderShareCard} writes a 1200x630 crop
     * specifically so a scraper gets the aspect ratio it wants; the raw cover is
     * the fallback for an update whose card has not been rendered yet, and the
     * logo the fallback for one with no photo at all.
     */
    private function image(Post $post, ?Business $business): ?string
    {
        return $this->mediaResolver->url($post->share_card_media_id)
            ?? $this->mediaResolver->url($post->cover_media_id)
            ?? $business?->logoUrl();
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
                    __('Updates') => url('/'.Post::PATH_PREFIX),
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
            'url' => url($post->getUrl()),
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
