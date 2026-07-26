<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Agents\SiteDraftAgent;
use App\Ai\Prompts\SiteDraftPrompt;
use App\Ai\SiteDraftValidator;
use App\Design\StylePreset;
use App\Enums\PageStatus;
use App\Exceptions\SiteDraftInvalid;
use App\Filament\Fabricator\BlockRegistry;
use App\Filament\Fabricator\PageBlocks\Block;
use App\Models\Business;
use App\Models\Location;
use App\Models\Page;
use Illuminate\Support\Facades\DB;
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
    ) {
        //
    }

    public function handle(Business $business): Page
    {
        try {
            $draft = $this->requestDraft($business);
        } catch (SiteDraftInvalid) {
            // Providers whose structured output is prompt-enforced (DeepSeek)
            // occasionally drift off-schema; one fresh attempt usually lands.
            // A second failure propagates to the caller.
            $draft = $this->requestDraft($business);
        }

        $existing = Page::query()->where('slug', '/')->whereNull('parent_id')->first();

        throw_if(
            $existing !== null && ! $existing->isDraft(),
            SiteDraftInvalid::class,
            'The home page is already published; refusing to overwrite it.',
        );

        $blocks = $this->stampVariants($draft['blocks'], $draft['preset']);

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
        $locations = Location::query()->orderByDesc('is_primary')->orderBy('id')->get();

        $response = new SiteDraftAgent()->prompt(
            (string) new SiteDraftPrompt($business, $locations, BlockRegistry::vocabulary()),
        );

        throw_unless(
            $response instanceof StructuredAgentResponse,
            SiteDraftInvalid::class,
            'The agent returned no structured output.',
        );

        return $this->validator->handle($response->toArray(), $business->name);
    }

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function stampVariants(array $blocks, StylePreset $preset): array
    {
        $vocabulary = BlockRegistry::vocabulary();
        $defaults = $preset->blockVariantDefaults();

        return array_map(function (array $block) use ($vocabulary, $defaults): array {
            $variants = $vocabulary[$block['type']]['variants'];

            if ($variants !== []) {
                $preferred = $defaults[$block['type']] ?? null;

                // Falls back to the block's first variant if the preset has no
                // (valid) default for this type — e.g. a block added after the
                // preset was authored.
                $block['data'][Block::VARIANT_KEY] = in_array($preferred, $variants, true) ? $preferred : $variants[0];
            }

            return $block;
        }, $blocks);
    }
}
