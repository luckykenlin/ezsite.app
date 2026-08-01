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
use App\Site\Blocks\BlockVocabulary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * The AI draft pipeline: prompt → validate → persist. Runs inside tenant
 * context (the RequiresTenantContext guards on Page/Business enforce it —
 * dispatch out-of-band callers through GenerateSiteDraftJob/RunInTenant).
 *
 * The AI only chose a preset, block sequence and copy; this action stamps
 * each block's layout variant from the preset (never the model's choice),
 * applies the preset tokens, and upserts the home page as a draft. An
 * already-published home page is never overwritten.
 */
final readonly class GenerateSiteDraft
{
    public function __construct(
        private SiteDraftValidator $validator,
        private ApplyStylePreset $applyStylePreset,
        private BlockVocabulary $vocabulary,
        private StampPresetDefaults $stampPresetDefaults,
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

        $existing = Page::query()->where('slug', '/')->whereNull('parent_id')->first();

        throw_if(
            $existing !== null && ! $existing->isDraft(),
            SiteDraftRefused::class,
            'The home page is already published; refusing to overwrite it.',
        );

        $blocks = $this->stampPresetDefaults->handle($draft['blocks'], $draft['preset']);

        return DB::transaction(function () use ($business, $existing, $draft, $blocks): Page {
            $this->applyStylePreset->handle($business, $draft['preset']);

            // A regenerated draft replaces the copy, but never wipes a
            // description the operator wrote when the model omitted one.
            $seo = $draft['metaDescription'] === null ? [] : ['seo_description' => $draft['metaDescription']];

            if ($existing !== null) {
                $existing->update([
                    'title' => $draft['title'],
                    'blocks' => $blocks,
                    'status' => PageStatus::Draft,
                    ...$seo,
                ]);

                return $existing;
            }

            return Page::query()->create([
                'tenant_id' => $business->tenant_id,
                'title' => $draft['title'],
                'slug' => '/',
                'layout' => 'main',
                'blocks' => $blocks,
                'status' => PageStatus::Draft,
                ...$seo,
            ]);
        });
    }

    /**
     * @return array{preset: StylePreset, title: string, metaDescription: string|null, blocks: list<array{type: string, data: array<string, mixed>}>}
     */
    private function requestDraft(Business $business): array
    {
        $locations = Location::query()->primaryFirst()->get();

        $response = new SiteDraftAgent($this->vocabulary)->prompt(
            (string) new SiteDraftPrompt($business, $locations, $this->vocabulary->all()),
        );

        throw_unless(
            $response instanceof StructuredAgentResponse,
            SiteDraftUnusable::class,
            'The agent returned no structured output.',
        );

        return $this->validator->handle($response->toArray(), $business->name);
    }
}
