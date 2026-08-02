<?php

declare(strict_types=1);

namespace App\Site;

/**
 * Hands each capture form on a page a distinct, render-stable id.
 *
 * Several forms can now share a page, and each needs its own identity for
 * three things that would otherwise collide: the `#lead-…` anchor a redirect
 * lands on, the session flag that decides which form shows its thank-you, and
 * the validation error bag. Without it, submitting the popup would paint
 * "Thanks!" on the contact form too.
 *
 * A counter rather than the block's own key, because that key is TRANSIENT —
 * `AddPageBlock` stamps a uuid for canvas selection, and it is never persisted,
 * so the live site has nothing stable to read. Position is what a live render
 * does have, and the sequence is identical on every render of the same page,
 * which is all the redirect needs to find its way back.
 *
 * Scoped, not singleton: a queue worker rendering two tenants' pages must not
 * carry a counter between them.
 */
final class LeadFormIds
{
    /**
     * @var array<string, int>
     */
    private array $counts = [];

    /**
     * The next id for a surface, e.g. `signup-1`, `signup-2`.
     *
     * The site-wide surfaces pass their own fixed id instead — there is only
     * ever one popup, and `contact` is an anchor with links pointing at it.
     */
    public function next(string $prefix): string
    {
        $this->counts[$prefix] = ($this->counts[$prefix] ?? 0) + 1;

        return $prefix.'-'.$this->counts[$prefix];
    }
}
