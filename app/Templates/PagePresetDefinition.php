<?php

declare(strict_types=1);

namespace App\Templates;

/**
 * One page preset's designed content: the single-page analogue of a
 * {@see TemplateDefinition} page entry, for the site canvas's "Add a page"
 * picker rather than whole-site provisioning.
 *
 * `$blocks` is in {@see \App\Ai\SiteDraftValidator} INPUT shape — flat
 * `variant`/`tone`/`spacing` siblings of `type`/`data`, an unprefixed
 * `image_query` inside `data` — for the same reason templates author in it: a
 * preset that drifts from what the validator lets through is a preset that
 * renders through a path nothing else uses. `PagePresetTest` runs every one
 * through the validator and fails on a single dropped block.
 *
 * No slug: unlike a template page, a preset never dictates where it lands —
 * {@see \App\Actions\Pages\CreatePageFromName} derives the slug from whatever
 * title the operator (or this definition) supplies.
 */
final readonly class PagePresetDefinition
{
    /**
     * @param  list<array<string, mixed>>  $blocks  SiteDraftValidator INPUT shape;
     *                                              empty only for the blank preset
     */
    public function __construct(
        public string $title,
        public ?string $metaDescription,
        public array $blocks,
    ) {
        //
    }
}
