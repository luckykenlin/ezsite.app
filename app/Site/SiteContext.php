<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\Page;

/**
 * What the assistant needs to know about the SITE, as opposed to the page in
 * front of it: which other pages exist to link to.
 *
 * Only that, deliberately. The prompt's other sections already own the rest —
 * {@see \App\Ai\Prompts\PageEditPrompt} states the business facts as the
 * authoritative "these are the only facts you may state" block, and the style
 * section states the current look next to the styles that can replace it. An
 * earlier version of this digest restated both a few hundred tokens earlier,
 * which spends tokens to give the model two versions of one fact and invites it
 * to treat the shorter one as the fact list.
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
    /** @var list<array{slug: string, status: string}>|null */
    private ?array $pages = null;

    /**
     * Every page's address and status, memoized.
     *
     * Lazy and only the two columns the prompt prints: this is one small select
     * per turn, and it fixes a real gap — the vocabulary section tells the model
     * to write relative links like "/contact" without ever telling it which
     * pages exist.
     *
     * @return list<array{slug: string, status: string}>
     */
    public function pages(): array
    {
        return $this->pages ??= array_values(Page::query()
            ->orderBy('slug')
            ->get(['slug', 'status'])
            ->map(static fn (Page $page): array => [
                'slug' => (string) $page->slug,
                'status' => $page->isDraft() ? 'draft' : 'published',
            ])
            ->all());
    }

    /**
     * The site block for the prompt, or null when there is nothing to say — a
     * tenant whose only page is the open one contributes no heading for the
     * model to reason about rather than an empty one.
     */
    public function digest(): ?string
    {
        $pages = $this->pages();

        if ($pages === []) {
            return null;
        }

        return "## This site\nPages: ".implode(', ', array_map(
            static fn (array $page): string => $page['slug'].' ('.$page['status'].')',
            $pages,
        ));
    }
}
