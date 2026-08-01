<?php

declare(strict_types=1);

namespace App\Ai;

use App\Design\StylePreset;
use App\Exceptions\SiteDraftUnusable;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;
use App\Site\Blocks\LayoutAxis;
use App\Site\Blocks\SectionLayout;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The server-side gate between the AI's structured output and persistence.
 * The JSON schema constrains shape loosely; this enforces the page-level
 * rules: a recognized preset, a usable title and meta description, and enough
 * surviving blocks to be worth publishing. Per-block field whitelisting is
 * {@see BlockDataSanitizer}'s job — the same rules the editor chat writes
 * through.
 *
 * This is also the draft path's one validating door onto the server-owned
 * layout keys, the counterpart of {@see Tools\SetBlockVariant} and
 * {@see Tools\SetBlockAppearance} on the chat path: the model offers
 * `variant`/`tone`/`spacing` as flat siblings of `type`/`data`, each choice is
 * checked against its own block's enums, and only a valid value is written
 * into `data` — the sanitizer keeps stripping those keys out of `data` itself,
 * so a value that did not pass through here cannot exist.
 */
final readonly class SiteDraftValidator
{
    /**
     * The slugs a generated site may use beyond the home page. A fixed menu
     * rather than model-invented paths: these are the pages a small-business
     * brochure site actually has, and a closed set keeps slugs collision-free
     * and linkable before anything exists.
     */
    public const array EXTRA_SLUGS = ['/about', '/services', '/contact'];

    private const int MIN_BLOCKS = 3;

    /**
     * Google truncates search snippets around 160 characters; the column
     * itself is unbounded text.
     */
    private const int MAX_META_DESCRIPTION = 160;

    /**
     * The UNPREFIXED name under which the model proposes a stock-photo search
     * query inside a block's `data`. Harvested here (popped BEFORE the
     * sanitizer runs, so the field-stripped warning never fires for it) and
     * re-attached under the reserved {@see BlockShape::IMAGE_QUERY_KEY} for
     * {@see \App\Actions\Pages\PopulateDraftImages} to consume.
     */
    private const string IMAGE_QUERY_FIELD = 'image_query';

    private const int MAX_IMAGE_QUERY = 80;

    public function __construct(
        private BlockDataSanitizer $sanitizer,
        private BlockVocabulary $vocabulary,
    ) {
        //
    }

    /**
     * Two tiers of strictness, deliberately: the HOME page is the product
     * moment, so a home that does not survive sanitization fails the whole
     * draft (retryable, {@see SiteDraftUnusable}); an extra page that does not
     * survive is simply dropped with a log — a thin /about must not cost the
     * operator the home page the model already composed.
     *
     * @param  array<array-key, mixed>  $draft  the agent's decoded structured output
     * @return array{preset: StylePreset, pages: non-empty-list<array{slug: string, title: string, metaDescription: string|null, blocks: list<array{type: string, data: array<string, mixed>}>}>}
     */
    public function handle(array $draft, string $fallbackTitle): array
    {
        $preset = $this->preset($draft);
        $rawPages = is_array($draft['pages'] ?? null) ? array_values($draft['pages']) : [];

        // Home held apart from the extras: it re-assembles as [$home, ...] so
        // the result ALWAYS leads with home — the order navigation and the
        // site canvas show — whatever order the model answered in.
        $home = null;
        $extras = [];
        $seen = [];

        foreach ($rawPages as $index => $page) {
            if (! is_array($page)) {
                Log::warning('site_draft.page_dropped', ['reason' => 'not_a_page', 'index' => $index]);

                continue;
            }

            $slug = is_string($page['slug'] ?? null) ? $page['slug'] : null;

            if ($slug !== '/' && ! in_array($slug, self::EXTRA_SLUGS, true)) {
                Log::warning('site_draft.page_dropped', ['reason' => 'unknown_slug', 'slug' => $slug, 'index' => $index]);

                continue;
            }

            if (in_array($slug, $seen, true)) {
                Log::warning('site_draft.page_dropped', ['reason' => 'duplicate_slug', 'slug' => $slug, 'index' => $index]);

                continue;
            }

            $blocks = $this->blocks(is_array($page['blocks'] ?? null) ? $page['blocks'] : []);

            if ($slug === '/') {
                // The home page carries the old whole-draft bar: enough blocks
                // AND a hero, or the generation is not worth landing at all.
                if (count($blocks) < self::MIN_BLOCKS || ! in_array('hero', array_column($blocks, 'type'), true)) {
                    throw new SiteDraftUnusable(sprintf(
                        'Draft not viable after sanitization: %d block(s) survived%s.',
                        count($blocks),
                        in_array('hero', array_column($blocks, 'type'), true) ? '' : ', no hero',
                    ));
                }
            } elseif (count($blocks) < self::MIN_BLOCKS) {
                Log::warning('site_draft.page_dropped', ['reason' => 'too_few_blocks', 'slug' => $slug, 'count' => count($blocks)]);

                continue;
            }

            $seen[] = $slug;
            $validated = [
                'slug' => $slug,
                'title' => $this->title($page, $slug === '/' ? $fallbackTitle : Str::headline(mb_trim($slug, '/'))),
                'metaDescription' => $this->metaDescription($page),
                'blocks' => $blocks,
            ];

            if ($slug === '/') {
                $home = $validated;
            } else {
                $extras[] = $validated;
            }
        }

        // No home page is the one multi-page failure that cannot be shrugged
        // off — there is nothing to land.
        throw_if(
            $home === null,
            SiteDraftUnusable::class,
            'The draft contains no home page.',
        );

        return ['preset' => $preset, 'pages' => [$home, ...$extras]];
    }

    /**
     * @param  array<array-key, mixed>  $page
     */
    private function title(array $page, string $fallback): string
    {
        return is_string($page['title'] ?? null) && mb_trim($page['title']) !== ''
            ? strip_tags(mb_trim($page['title']))
            : $fallback;
    }

    /**
     * The search-result summary. Optional on the way in: a provider that
     * drops it (prompt-enforced structured output does) still yields a usable
     * draft — the page then falls back to the business tagline at render time.
     *
     * @param  array<array-key, mixed>  $page
     */
    private function metaDescription(array $page): ?string
    {
        if (! is_string($page['meta_description'] ?? null)) {
            return null;
        }

        $description = mb_trim(strip_tags($page['meta_description']));

        return $description === '' ? null : mb_substr($description, 0, self::MAX_META_DESCRIPTION);
    }

    /**
     * @param  array<array-key, mixed>  $draft
     */
    private function preset(array $draft): StylePreset
    {
        $preset = is_string($draft['preset'] ?? null) ? StylePreset::tryFrom($draft['preset']) : null;

        if ($preset === null) {
            Log::warning('site_draft.preset_fallback', ['stored' => $draft['preset'] ?? null]);

            return StylePreset::cases()[0];
        }

        return $preset;
    }

    /**
     * @param  array<array-key, mixed>  $blocks
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function blocks(array $blocks): array
    {
        $pageTypes = $this->vocabulary->pageTypes();
        $sanitized = [];
        $hasHero = false;

        foreach ($blocks as $index => $block) {
            $type = is_array($block) && is_string($block['type'] ?? null) ? $block['type'] : null;

            if ($type === null || ! array_key_exists($type, $pageTypes)) {
                Log::warning('site_draft.block_dropped', ['reason' => 'unknown_or_chrome_type', 'type' => $type, 'index' => $index]);

                continue;
            }

            if ($type === 'hero' && $hasHero) {
                Log::warning('site_draft.block_dropped', ['reason' => 'duplicate_hero', 'index' => $index]);

                continue;
            }

            $raw = is_array($block['data'] ?? null) ? $block['data'] : [];
            $imageQuery = $raw[self::IMAGE_QUERY_FIELD] ?? null;
            unset($raw[self::IMAGE_QUERY_FIELD]);

            $data = $this->sanitizer->handle($type, $raw);

            if (config()->boolean('stock-photos.enabled') && is_string($imageQuery) && mb_trim($imageQuery) !== '') {
                $data[BlockShape::IMAGE_QUERY_KEY] = mb_substr(strip_tags(mb_trim($imageQuery)), 0, self::MAX_IMAGE_QUERY);
            }

            // The model's layout choices ride as flat siblings of type/data —
            // with a salvage read from inside `data`, where a drifting
            // provider habitually puts them (the sanitizer just stripped them
            // there). Block level wins. Only validated values are written; an
            // invalid one falls through to the preset fill, never worse than
            // the stamped output this path used to produce.
            $variant = $this->variant($pageTypes[$type], $block[BlockShape::VARIANT_KEY] ?? $raw[BlockShape::VARIANT_KEY] ?? null, $index);

            if ($variant !== null) {
                $data[BlockShape::VARIANT_KEY] = $variant;
            }

            $stored = is_array($raw[BlockShape::APPEARANCE_KEY] ?? null) ? $raw[BlockShape::APPEARANCE_KEY] : [];
            $axes = [];

            foreach (LayoutAxis::cases() as $axis) {
                $axes[$axis->value] = $this->axis(
                    $pageTypes[$type],
                    $axis,
                    $block[$axis->value] ?? $stored[$axis->value] ?? null,
                    $index,
                );
            }

            $appearance = SectionLayout::store($axes);

            if ($appearance !== null) {
                $data[BlockShape::APPEARANCE_KEY] = $appearance;
            }

            $hasHero = $hasHero || $type === 'hero';
            $sanitized[] = ['type' => $type, 'data' => $data];
        }

        return $sanitized;
    }

    /**
     * A validated layout choice, or null. Present-but-invalid logs with the
     * offered value — silent-drop-to-preset would make "the model chose
     * garbage every time" look identical to "the model chose nothing", and
     * that frequency is exactly the DeepSeek-fidelity telemetry to watch.
     */
    private function variant(BlockType $type, mixed $raw, int $index): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        if (in_array($raw, $type->variants, true)) {
            return $raw;
        }

        Log::info('site_draft.layout_dropped', ['dimension' => BlockShape::VARIANT_KEY, 'type' => $type->type, 'value' => $raw, 'index' => $index]);

        return null;
    }

    /**
     * A validated axis value, or null. Present-but-invalid logs with the
     * offered value, and so does an axis the type never declared — both are
     * the model-fidelity telemetry to watch, and neither may reach a class
     * attribute.
     */
    private function axis(BlockType $type, LayoutAxis $axis, mixed $raw, int $index): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        if ($axis->resolve($raw) === null) {
            Log::info('site_draft.layout_dropped', ['dimension' => $axis->value, 'type' => $type->type, 'value' => $raw, 'index' => $index]);

            return null;
        }

        if (! $type->supportsAxis($axis)) {
            Log::info('site_draft.layout_dropped', ['dimension' => $axis->value, 'type' => $type->type, 'value' => $raw, 'index' => $index, 'reason' => 'axis_not_applicable']);

            return null;
        }

        return $raw;
    }
}
