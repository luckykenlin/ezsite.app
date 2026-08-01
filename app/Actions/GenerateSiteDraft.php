<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Pages\StampPresetDefaults;
use App\Ai\Agents\SiteDraftAgent;
use App\Ai\Prompts\SiteDraftPrompt;
use App\Ai\SiteDraftValidator;
use App\Design\StylePreset;
use App\Enums\PageStatus;
use App\Exceptions\SiteDraftRefused;
use App\Exceptions\SiteDraftUnusable;
use App\Models\Business;
use App\Models\Location;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * The AI draft pipeline: prompt → validate → persist. Runs inside tenant
 * context (the RequiresTenantContext guards on Page/Business enforce it —
 * dispatch out-of-band callers through GenerateSiteDraftJob/RunInTenant).
 *
 * The AI chooses a preset, a block sequence, per-section layout variants and
 * appearances, and copy; the validator lets only enum-checked layout choices
 * through, and {@see StampPresetDefaults::fill()} back-fills the preset's
 * defaults for whatever the model omitted (position-aware, so even a silent
 * model gets an alternating rhythm). This action applies the preset tokens
 * and upserts the home page as a draft. An already-published home page is
 * never overwritten.
 */
final readonly class GenerateSiteDraft
{
    public function __construct(
        private SiteDraftValidator $validator,
        private ApplyStylePreset $applyStylePreset,
        private BlockVocabulary $vocabulary,
        private StampPresetDefaults $stampPresetDefaults,
        private SaveSiteChrome $saveSiteChrome,
    ) {
        //
    }

    public function handle(Business $business): Page
    {
        try {
            $draft = $this->requestDraft($business);
        } catch (SiteDraftUnusable $siteDraftUnusable) {
            // One fresh attempt. Logged, because this used to be silent: a
            // tenant whose generation always needs two provider calls (a sparse
            // profile, say) looked identical to one that always needs one, and
            // the second call costs ~90 seconds.
            Log::warning('site_draft.retrying', [
                'tenant_id' => tenant('id'),
                'reason' => $siteDraftUnusable->getMessage(),
            ]);

            $draft = $this->requestDraft($business);
        }

        $existingHome = Page::query()->where('slug', '/')->whereNull('parent_id')->first();

        // The home page keeps its hard guard: a published home is the live
        // front door, and regenerating over it is refused outright. Published
        // EXTRA pages are skipped per page instead — see upsertPage().
        throw_if(
            $existingHome !== null && ! $existingHome->isDraft(),
            SiteDraftRefused::class,
            'The home page is already published; refusing to overwrite it.',
        );

        return DB::transaction(function () use ($business, $draft): Page {
            $this->applyStylePreset->handle($business, $draft['preset']);

            $home = null;
            $navigable = [];

            foreach ($draft['pages'] as $page) {
                $saved = $this->upsertPage($business, $page, $draft['preset']);

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
     * Land one generated page: update the existing draft at its slug, create
     * a missing one, or — for a PUBLISHED extra page — skip it entirely. The
     * live /about someone wrote by hand outranks anything a regeneration
     * composes; only the home page's collision is loud (see handle()).
     *
     * @param  array{slug: string, title: string, metaDescription: string|null, blocks: list<array{type: string, data: array<string, mixed>}>}  $page
     */
    private function upsertPage(Business $business, array $page, StylePreset $preset): ?Page
    {
        $existing = Page::query()->where('slug', $page['slug'])->whereNull('parent_id')->first();

        if ($existing !== null && ! $existing->isDraft() && $page['slug'] !== '/') {
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
     * Stamp the header navigation with links to the generated pages — the
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

    /**
     * @return array{preset: StylePreset, pages: non-empty-list<array{slug: string, title: string, metaDescription: string|null, blocks: list<array{type: string, data: array<string, mixed>}>}>}
     */
    private function requestDraft(Business $business): array
    {
        $locations = Location::query()->primaryFirst()->get();

        // Same failover chain as the chat turns (ai.failover): a one-shot
        // generation is the worst place to lose a whole attempt to a provider
        // that refused to answer — the operator waits minutes either way.
        $response = new SiteDraftAgent($this->vocabulary)->prompt(
            (string) new SiteDraftPrompt($business, $locations, $this->vocabulary->all()),
            provider: config()->array('ai.failover'),
        );

        throw_unless(
            $response instanceof StructuredAgentResponse,
            SiteDraftUnusable::class,
            'The agent returned no structured output.',
        );

        $payload = $response->toArray();
        $draft = $this->validator->handle($payload, $business->name);

        // The schema demands one sentence of design reasoning; nothing
        // in-product reads it yet, but a greppable line per landed draft is
        // what makes the prompt tunable — without it, "the model reasons
        // badly" and "the model reasons well and we override it" look alike.
        if (is_string($payload['rationale'] ?? null)) {
            Log::info('site_draft.rationale', ['tenant_id' => tenant('id'), 'rationale' => $payload['rationale']]);
        }

        return $draft;
    }
}
