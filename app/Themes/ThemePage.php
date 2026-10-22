<?php

declare(strict_types=1);

namespace App\Themes;

use Filament\Support\Icons\Heroicon;

/**
 * One designed starting page a theme offers: what the site canvas's "Add a
 * page" picker shows as a card, and — when `$draftSlug` is set — a page the AI
 * site draft may produce.
 *
 * This replaced a global page-preset enum, and the relocation is the decision:
 * a page's blocks name layout VARIANTS, variants belong to the theme that draws
 * them ({@see ThemeBlock}), so a global page list was always one theme away
 * from naming a variant that theme has no view for. Keyed data on
 * {@see ThemeDefinition} rather than an enum per theme, for the same reason
 * {@see Scheme} is: a theme's pages are only ever enumerated through the theme.
 *
 * "Blank" is deliberately NOT a theme page. It has no blocks to design, so it
 * is the same in every theme; the picker renders it as a fixed first card.
 *
 * `$blocks` is in {@see \App\Ai\SiteDraftValidator} INPUT shape — flat
 * `variant`/`tone`/`spacing` siblings of `type`/`data`, an unprefixed
 * `image_query` inside `data` — for the same reason templates author in it: a
 * page that drifts from what the validator lets through renders through a path
 * nothing else uses. `ThemePageTest` runs every one through the validator.
 *
 * No slug for the picker: a created page's slug is derived from whatever title
 * the operator (or `$title`) supplies by
 * {@see \App\Actions\Pages\CreatePageFromName}. `$draftSlug` is a different
 * question — the closed menu the site draft may write to — and lives here so
 * a theme decides which supporting pages its drafts get.
 *
 * `$label`/`$description` are English source strings, translated where they
 * are SHOWN (`__()` in the picker) rather than where they are declared: a
 * theme definition is read where no translator exists — arch tests, dataset
 * collection — and translating at declaration would also freeze the copy to
 * whatever locale happened to build the definition. The draft prompt reads
 * `$description` untranslated on purpose; the model is briefed in English.
 */
final readonly class ThemePage
{
    /**
     * @param  string  $key  stable identifier, the picker's form value
     * @param  list<array<string, mixed>>  $blocks  SiteDraftValidator INPUT shape
     * @param  string|null  $draftSlug  e.g. `/about`; null when the AI site
     *                                  draft may not produce this page
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public Heroicon $icon,
        public string $title,
        public ?string $metaDescription,
        public array $blocks,
        public ?string $draftSlug = null,
    ) {
        //
    }
}
