<?php

declare(strict_types=1);

namespace App\Ai;

use App\Design\StylePreset;
use App\Exceptions\SiteDraftUnusable;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\Support\Facades\Log;

/**
 * The server-side gate between the AI's structured output and persistence.
 * The JSON schema constrains shape loosely; this enforces the page-level
 * rules: a recognized preset, a usable title and meta description, and enough
 * surviving blocks to be worth publishing. Per-block field whitelisting is
 * {@see BlockDataSanitizer}'s job — the same rules the editor chat writes
 * through.
 */
final readonly class SiteDraftValidator
{
    private const int MIN_BLOCKS = 3;

    /**
     * Google truncates search snippets around 160 characters; the column
     * itself is unbounded text.
     */
    private const int MAX_META_DESCRIPTION = 160;

    public function __construct(
        private BlockDataSanitizer $sanitizer,
        private BlockVocabulary $vocabulary,
    ) {
        //
    }

    /**
     * @param  array<array-key, mixed>  $draft  the agent's decoded structured output
     * @return array{preset: StylePreset, title: string, metaDescription: string|null, blocks: list<array{type: string, data: array<string, mixed>}>}
     */
    public function handle(array $draft, string $fallbackTitle): array
    {
        $preset = $this->preset($draft);
        $page = $this->page($draft);

        $title = is_string($page['title'] ?? null) && mb_trim($page['title']) !== ''
            ? strip_tags(mb_trim($page['title']))
            : $fallbackTitle;

        $blocks = $this->blocks(is_array($page['blocks'] ?? null) ? $page['blocks'] : []);

        if (count($blocks) < self::MIN_BLOCKS || ! in_array('hero', array_column($blocks, 'type'), true)) {
            throw new SiteDraftUnusable(sprintf(
                'Draft not viable after sanitization: %d block(s) survived%s.',
                count($blocks),
                in_array('hero', array_column($blocks, 'type'), true) ? '' : ', no hero',
            ));
        }

        return [
            'preset' => $preset,
            'title' => $title,
            'metaDescription' => $this->metaDescription($page),
            'blocks' => $blocks,
        ];
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
     * @param  array<array-key, mixed>  $draft
     * @return array<array-key, mixed>
     */
    private function page(array $draft): array
    {
        $pages = is_array($draft['pages'] ?? null) ? array_values($draft['pages']) : [];
        $page = $pages[0] ?? null;

        return is_array($page) ? $page : [];
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

            $data = $this->sanitizer->handle(
                $type,
                is_array($block['data'] ?? null) ? $block['data'] : [],
            );

            $hasHero = $hasHero || $type === 'hero';
            $sanitized[] = ['type' => $type, 'data' => $data];
        }

        return $sanitized;
    }
}
