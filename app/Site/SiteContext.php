<?php

declare(strict_types=1);

namespace App\Site;

use App\Design\DesignTokens;
use App\Models\Business;
use App\Models\Page;

/**
 * What the assistant needs to know about the SITE, as opposed to the page in
 * front of it: who the business is, what the current look is, and what other
 * pages exist to link to.
 *
 * Scoped in the container beside {@see BindResolver}, {@see SiteChrome} and
 * {@see Blocks\BlockVocabulary}, for the reason those are: memoization that
 * lasts exactly one request, so a queue worker handling two tenants' turns
 * cannot serve one of them the other's site.
 *
 * Deliberately does NOT know the open page's blocks. {@see \App\Ai\PageDraft}
 * is the single source for what is open, and it is rebuilt every turn precisely
 * because the operator hand-edits between messages; a site-scoped object
 * caching page state would be a staleness bug waiting to be written.
 *
 * No `IndustryProfile` here either. The obvious version — restaurant expects a
 * menu, a reservation CTA, reviews — cannot say anything actionable against the
 * current block vocabulary, and an assistant that offers to add a section the
 * product has no block for is worse than one that stays quiet.
 * {@see \App\Design\StylePreset::vibes()} already does industry-noun matching
 * for the first-draft path.
 */
final class SiteContext
{
    /** @var list<array{slug: string, title: string, status: string}>|null */
    private ?array $pages = null;

    public function __construct(private readonly BindResolver $bind)
    {
        //
    }

    public function business(): ?Business
    {
        return $this->bind->business();
    }

    /**
     * The site's SAVED tokens. A chat turn reads its own staged style from
     * {@see \App\Ai\SiteStyleDraft} instead, which starts from this.
     */
    public function tokens(): DesignTokens
    {
        $business = $this->business();

        return $business instanceof Business ? $business->design_tokens : DesignTokens::default();
    }

    /**
     * Every page's address, title and status, memoized.
     *
     * Lazy and only four columns: this is one small select per turn, and it
     * fixes a real gap — the prompt tells the model to write relative links
     * like "/contact" without ever telling it which pages exist.
     *
     * @return list<array{slug: string, title: string, status: string}>
     */
    public function pages(): array
    {
        return $this->pages ??= array_values(Page::query()
            ->orderBy('slug')
            ->get(['id', 'slug', 'title', 'status'])
            ->map(static fn (Page $page): array => [
                'slug' => (string) $page->slug,
                'title' => (string) $page->title,
                'status' => $page->isDraft() ? 'draft' : 'published',
            ])
            ->all());
    }

    /**
     * The always-in-prompt site block. Every line is omitted when it has
     * nothing to say, so a tenant with no business profile contributes no
     * empty headings for the model to reason about.
     */
    public function digest(): string
    {
        $business = $this->business();

        $lines = array_filter([
            $business instanceof Business ? 'Business: '.$this->businessLine($business) : null,
            'Style: '.$this->tokens()->describe(),
            $this->pagesLine(),
        ]);

        return "## This site\n".implode("\n", $lines);
    }

    private function businessLine(Business $business): string
    {
        return $business->category === null
            ? $business->name
            : $business->name.' — '.$business->category;
    }

    private function pagesLine(): ?string
    {
        $pages = $this->pages();

        if ($pages === []) {
            return null;
        }

        return 'Pages: '.implode(', ', array_map(
            static fn (array $page): string => $page['slug'].' ('.$page['status'].')',
            $pages,
        ));
    }
}
