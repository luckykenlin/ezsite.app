<?php

declare(strict_types=1);

namespace App\Site;

/**
 * Hands each capture form on a page a distinct, render-stable id — and owns
 * every name derived from one.
 *
 * Several forms can now share a page, and each needs its own identity for
 * three things that would otherwise collide: the `#lead-…` anchor a redirect
 * lands on, the session flag that decides which form shows its thank-you, and
 * the validation error bag. Without it, submitting the popup would paint
 * "Thanks!" on the contact form too.
 *
 * The id round-trips through visitor-controlled markup — rendered into the
 * form, posted back, then used in an anchor and a bag name that Blade does
 * not escape — so the producer (this class), the parser
 * ({@see \App\Http\Requests\StoreLeadRequest}) and the consumer views all go
 * through the statics here rather than each spelling the rule themselves.
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
     * The id used when a form posts without one — a hand-rolled or cached
     * older form. It matches the anchor the contact block has always used, so
     * the pre-form-id behaviour still works.
     */
    public const string DEFAULT = 'contact';

    /**
     * The session flash key naming which form should show its thank-you.
     */
    public const string SUBMITTED_SESSION_KEY = 'lead_submitted';

    /**
     * @var array<string, int>
     */
    private array $counts = [];

    /**
     * A posted form id, reduced to the character set the anchor and the bag
     * name are built from — both end up in markup, and a bag name is not
     * escaped by Blade.
     */
    public static function sanitize(?string $formId): string
    {
        $formId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $formId) ?? '';

        return $formId === '' ? self::DEFAULT : mb_substr($formId, 0, 64);
    }

    public static function errorBag(string $formId): string
    {
        return 'lead_'.$formId;
    }

    public static function anchor(string $formId): string
    {
        return 'lead-'.$formId;
    }

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
