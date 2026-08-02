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
 */
final readonly class TemplateField
{
    public function __construct(
        public string $key,
        public string $label,
        public string $example,
        public ?string $help = null,
        public bool $multiline = false,
    ) {
        //
    }
}
