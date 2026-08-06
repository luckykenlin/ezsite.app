<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Pages\StampPresetDefaults;
use App\Ai\SiteDraftValidator;
use App\Design\StylePreset;
use App\Enums\PageStatus;
use App\Exceptions\SiteDraftUnusable;
use App\Models\Business;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Land a validated site draft: upsert every page, back-fill the preset's
 * layout defaults, and stamp the header navigation. One transaction, no
 * provider call anywhere near it.
 *
 * Extracted from {@see GenerateSiteDraft}, whose persistence half this used to
 * be, because a hand-curated template ({@see \App\Templates\SiteTemplate}) has
 * to land through exactly the same door as an AI-composed one: same validator
 * output shape, same preset fill, same navigation stamp. Two persistence paths
 * would mean a template that renders differently from a generated site, which
 * is the one difference the product cannot afford — templates ARE the design
 * system's showcase.
 *
 * The input is {@see SiteDraftValidator}'s OUTPUT: a resolved
 * {@see StylePreset} plus pages whose blocks have already been whitelisted.
 * Nothing here re-validates, so never hand it raw model output.
 *
 * What deliberately did NOT move: applying the preset's tokens to the
 * business. `GenerateSiteDraft` still does that (inside its own transaction,
 * so the pair stays atomic) and the template paths write their own merged
 * preset + override tokens — a bare {@see ApplyStylePreset} here would clobber
 * the overrides that make two WarmCraft sites look like two restaurants.
 *
 * Runs in tenant context (the RequiresTenantContext guards on Page and
 * SiteSetting enforce it).
 */
final readonly class ApplySiteDraft
{
    public function __construct(
        private StampPresetDefaults $stampPresetDefaults,
        private SaveSiteChrome $saveSiteChrome,
    ) {
        //
    }

    /**
     * @param  array{preset: StylePreset, pages: non-empty-list<array{slug: string, title: string, metaDescription: string|null, blocks: list<array{type: string, data: array<string, mixed>}>}>}  $draft
     * @param  bool  $overwritePublished  whether a PUBLISHED extra page may be
     *                                    replaced — false for anything a real
     *                                    operator owns, true for demo tenants,
     *                                    which are re-seeded on every deploy
     */
    public function handle(Business $business, array $draft, bool $overwritePublished = false): Page
    {
        return DB::transaction(function () use ($business, $draft, $overwritePublished): Page {
            $home = null;
            $navigable = [];

            foreach ($draft['pages'] as $page) {
                $saved = $this->upsertPage($business, $page, $draft['preset'], $overwritePublished);

                if (! $saved instanceof Page) {
                    continue;
                }

                $navigable[] = ['slug' => $page['slug'], 'title' => $page['title']];

                if ($page['slug'] === '/') {
                    $home = $saved;
                }
            }

            // The validator guarantees a home page survives, and upsertPage()
            // only ever skips non-home slugs — so this cannot fire. Belt for
            // those two braces, since the alternative is returning null into
            // a signature every caller trusts.
            throw_unless($home instanceof Page, SiteDraftUnusable::class, 'The draft landed no home page.');

            $this->stampNavigation($navigable);

            return $home;
        });
    }

    /**
     * Land one page: update the existing draft at its slug, create a missing
     * one, or — for a PUBLISHED extra page — skip it entirely. The live
     * /about someone wrote by hand outranks anything a regeneration composes;
     * the home page's collision is the CALLER's to police (GenerateSiteDraft
     * refuses outright, demo seeding overwrites on purpose).
     *
     * @param  array{slug: string, title: string, metaDescription: string|null, blocks: list<array{type: string, data: array<string, mixed>}>}  $page
     */
    private function upsertPage(Business $business, array $page, StylePreset $preset, bool $overwritePublished): ?Page
    {
        $existing = Page::query()->where('slug', $page['slug'])->whereNull('parent_id')->first();

        if ($existing !== null && ! $existing->isDraft() && $page['slug'] !== '/' && ! $overwritePublished) {
            Log::info('site_draft.page_skipped', ['slug' => $page['slug'], 'reason' => 'already_published']);

            return null;
        }

        $blocks = $this->stampPresetDefaults->fill($page['blocks'], $preset);

        // A regenerated draft replaces the copy, but never wipes a
        // description the operator wrote when the model omitted one.
        $seo = $page['metaDescription'] === null ? [] : ['seo_description' => $page['metaDescription']];

        if ($existing !== null) {
            $existing->update([
                'title' => $page['title'],
                'blocks' => $blocks,
                'status' => PageStatus::Draft,
                ...$seo,
            ]);

            return $existing;
        }

        return Page::query()->create([
            'tenant_id' => $business->tenant_id,
            'title' => $page['title'],
            'slug' => $page['slug'],
            'layout' => 'main',
            'blocks' => $blocks,
            'status' => PageStatus::Draft,
            ...$seo,
        ]);
    }

    /**
     * Stamp the header navigation with links to the landed pages — the
     * "pages, navigation, copy in under a minute" half of the first-run
     * moment. ONLY when the tenant has no stored header: a navigation someone
     * already shaped by hand outranks a generated one, and null-header tenants
     * are exactly the ones being onboarded here.
     *
     * @param  list<array{slug: string, title: string}>  $pages
     */
    private function stampNavigation(array $pages): void
    {
        $settings = SiteSetting::query()->first();

        if ($settings?->header !== null) {
            return;
        }

        $links = array_map(static fn (array $page): array => [
            'label' => $page['slug'] === '/' ? __('Home') : $page['title'],
            'url' => $page['slug'],
        ], $pages);

        $this->saveSiteChrome->handle(
            [['type' => 'header', 'data' => ['nav_links' => $links]]],
            $settings?->footer,
        );
    }
}
