<?php

declare(strict_types=1);

namespace App\Ai;

use App\Design\StylePreset;
use App\Exceptions\SiteDraftInvalid;
use App\Filament\Fabricator\BlockRegistry;
use App\Filament\Fabricator\PageBlocks\Block;
use Illuminate\Support\Facades\Log;

/**
 * The server-side gate between the AI's structured output and persistence.
 * The JSON schema constrains shape loosely; this enforces the vocabulary:
 * unknown types and fields are dropped/stripped and logged (matching
 * BlockRegistry's defensive philosophy), reserved keys are server-owned,
 * strings are de-tagged, and a draft that survives with too little content
 * is rejected outright.
 */
final readonly class SiteDraftValidator
{
    private const int MIN_BLOCKS = 3;

    /**
     * @param  array<array-key, mixed>  $draft  the agent's decoded structured output
     * @return array{preset: StylePreset, title: string, blocks: list<array{type: string, data: array<string, mixed>}>}
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
            throw new SiteDraftInvalid(sprintf(
                'Draft not viable after sanitization: %d block(s) survived%s.',
                count($blocks),
                in_array('hero', array_column($blocks, 'type'), true) ? '' : ', no hero',
            ));
        }

        return ['preset' => $preset, 'title' => $title, 'blocks' => $blocks];
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
        $vocabulary = BlockRegistry::vocabulary();
        $sanitized = [];
        $hasHero = false;

        foreach ($blocks as $index => $block) {
            $type = is_array($block) && is_string($block['type'] ?? null) ? $block['type'] : null;

            if ($type === null || ! array_key_exists($type, $vocabulary) || in_array($type, ['header', 'footer'], true)) {
                Log::warning('site_draft.block_dropped', ['reason' => 'unknown_or_chrome_type', 'type' => $type, 'index' => $index]);

                continue;
            }

            if ($type === 'hero' && $hasHero) {
                Log::warning('site_draft.block_dropped', ['reason' => 'duplicate_hero', 'index' => $index]);

                continue;
            }

            $data = $this->data(
                is_array($block['data'] ?? null) ? $block['data'] : [],
                $vocabulary[$type]['fields'],
                $type,
            );

            $hasHero = $hasHero || $type === 'hero';
            $sanitized[] = ['type' => $type, 'data' => $this->normalize($type, $data)];
        }

        return $sanitized;
    }

    /**
     * Per-type value normalization for fields with enumerated values the
     * contract cannot express yet. Currently: heading levels — models often
     * write "2" or 2 instead of "h2"; unmappable values are dropped so the
     * view's default (h2) applies.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(string $type, array $data): array
    {
        if ($type !== 'heading' || ! array_key_exists('level', $data)) {
            return $data;
        }

        $raw = $data['level'];

        if (! is_string($raw) && ! is_int($raw)) {
            unset($data['level']);

            return $data;
        }

        $level = mb_strtolower((string) $raw);
        $level = preg_match('/^[1-6]$/', $level) === 1 ? 'h'.$level : $level;

        if (in_array($level, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            $data['level'] = $level;
        } else {
            unset($data['level']);
        }

        return $data;
    }

    /**
     * Whitelists data keys against the block contract, strips the reserved
     * (server-owned) keys, and sanitizes every leaf value.
     *
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function data(array $data, array $fields, string $type): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if ($key === Block::VARIANT_KEY || $key === Block::BIND_KEY) {
                continue; // server-owned, silently stripped
            }

            if (! is_string($key) || ! in_array($key, $fields, true)) {
                Log::warning('site_draft.field_stripped', ['type' => $type, 'field' => $key]);

                continue;
            }

            $value = $this->value($value);

            if ($value !== null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Scalars are de-tagged; lists of flat objects (repeater items) are
     * sanitized recursively one level down; anything else is dropped.
     */
    private function value(mixed $value): mixed
    {
        if (is_string($value)) {
            return strip_tags($value);
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            $items = [];

            foreach ($value as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $fields = [];

                foreach ($item as $field => $fieldValue) {
                    if (is_string($field) && is_string($fieldValue)) {
                        $fields[$field] = strip_tags($fieldValue);
                    } elseif (is_string($field) && (is_int($fieldValue) || is_float($fieldValue) || is_bool($fieldValue))) {
                        $fields[$field] = $fieldValue;
                    }
                }

                if ($fields !== []) {
                    $items[] = $fields;
                }
            }

            return $items === [] ? null : $items;
        }

        return null;
    }
}
