<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Agents\SiteDraftAgent;
use App\Ai\Prompts\SiteDraftPrompt;
use App\Ai\SiteDraftValidator;
use App\Design\StylePreset;
use App\Exceptions\SiteDraftRefused;
use App\Exceptions\SiteDraftUnusable;
use App\Models\Business;
use App\Models\Location;
use App\Models\Page;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * The AI half of the draft pipeline: prompt → validate → hand to
 * {@see ApplySiteDraft}. Runs inside tenant context (the RequiresTenantContext
 * guards on Page/Business enforce it — dispatch out-of-band callers through
 * GenerateSiteDraftJob/RunInTenant).
 *
 * The AI chooses a preset, a block sequence, per-section layout variants and
 * appearances, and copy; the validator lets only enum-checked layout choices
 * through. Persistence — the preset back-fill, the page upserts and the
 * navigation stamp — lives in `ApplySiteDraft`, which the hand-curated
 * templates share, so a generated site and a template site land identically.
 * What stays here is what only the AI path has: the provider call, the one
 * retry, and the refusal to overwrite a published home page.
 */
final readonly class GenerateSiteDraft
{
    public function __construct(
        private SiteDraftValidator $validator,
        private ApplyStylePreset $applyStylePreset,
        private BlockVocabulary $vocabulary,
        private ApplySiteDraft $applySiteDraft,
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

        // One transaction around both halves: a site whose tokens changed but
        // whose pages did not land is a visibly broken restyle.
        return DB::transaction(function () use ($business, $draft): Page {
            $this->applyStylePreset->handle($business, $draft['preset']);

            return $this->applySiteDraft->handle($business, $draft);
        });
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
