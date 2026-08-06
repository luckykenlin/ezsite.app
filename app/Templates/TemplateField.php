<?php

declare(strict_types=1);

namespace App\Templates;

/**
 * One industry-specific question the apply wizard asks on step 2 — "what is
 * your signature dish", "what does a gel manicure cost" — and the placeholder
 * its answer fills.
 *
 * The {@see $key} is the placeholder token: a field keyed `dish_one` replaces
 * every `{dish_one}` in the template's page copy
 * ({@see \App\Actions\Templates\FillTemplatePlaceholders}). That is the whole
 * contract, and it is why {@see $example} is required rather than optional:
 * the wizard's "skip — use example content" path and the demo site both fall
 * back to it, so a field with no example would leave a literal `{dish_one}`
 * on a live page.
 *
 * The question and its hint are NOT here. They are marketing copy in two
 * languages and live in `lang/{locale}/marketing.php`, reached through
 * {@see SiteTemplate::fieldLabel()} — a field alone cannot resolve them,
 * because the same key asks a different question in a different template.
 * {@see $example} stays a literal: it is seeded into a tenant's pages as
 * content, not shown as chrome.
 */
final readonly class TemplateField
{
    public function __construct(
        public string $key,
        public string $example,
        public bool $multiline = false,
    ) {
        //
    }
}
