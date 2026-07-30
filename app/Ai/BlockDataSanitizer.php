<?php

declare(strict_types=1);

namespace App\Ai;

use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\Support\Facades\Log;

/**
 * The single rule for "what the AI is allowed to write into a block's data".
 *
 * Shared by both AI write paths — {@see SiteDraftValidator} (whole-site
 * generation) and {@see Tools\UpdateBlockContent} (the editor chat) —
 * so a field the model may not author is rejected identically in both. Unknown
 * types and fields are dropped and logged (matching the render layer's defensive
 * philosophy), the reserved keys are server-owned, and every leaf string is
 * de-tagged: block views render this content unescaped-free but on shared
 * tenant domains, so tags never survive the boundary.
 *
 * KNOWN LIMIT — the field whitelist is TOP-LEVEL ONLY. `BlockType::$fields`
 * is a flat list of {@see \Filament\Forms\Components\Field} names, so a `Repeater`
 * contributes just its own name (`features`) and never its `->schema()` children.
 * Inside a repeater item, therefore, keys are shape-limited (scalars only, one
 * level deep, each de-tagged) but NOT name-limited: an undeclared item key
 * survives. That is currently inert rather than exploitable — every repeater view
 * reads explicit keys (`$item['icon']`, `$link['url']`), nothing iterates
 * arbitrary keys, and a nested key cannot become an HTML attribute because it
 * sits inside a declared prop's array. Fixing it properly means teaching
 * `contract()` about nested shapes; until a real need appears, this comment is
 * the honest boundary.
 *
 * URL schemes are NOT this class's job: `strip_tags()` does nothing to
 * `javascript:`, so that is guarded at render time by
 * {@see \App\Filament\Fabricator\BlockRegistry::denyExecutableUrls()}, which covers panel- and
 * seeder-authored links too.
 *
 * The vocabulary arrives injected and is container-`scoped`, so a draft that
 * sanitizes twenty blocks walks the registered block classes once, not twenty
 * times — the memoization this class used to do itself.
 */
final readonly class BlockDataSanitizer
{
    public function __construct(private BlockVocabulary $vocabulary)
    {
        //
    }

    /**
     * Whitelist a block's data against its contract: unlisted and reserved keys
     * are stripped, leaf values sanitized, enumerated values normalized. An
     * unregistered type has no authorable fields, so everything is stripped.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(string $type, array $data): array
    {
        $fields = $this->vocabulary->get($type)->fields ?? [];
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (in_array($key, BlockShape::reservedKeys(), true)) {
                continue; // server-owned, silently stripped
            }

            if (! is_string($key) || ! in_array($key, $fields, true)) {
                Log::warning('ai_block.field_stripped', ['type' => $type, 'field' => $key]);

                continue;
            }

            $value = $this->value($value);

            if ($value !== null) {
                $sanitized[$key] = $value;
            }
        }

        return $this->normalize($type, $sanitized);
    }

    /**
     * Whether a block type exists in the vocabulary — the guard the chat tools
     * use before addressing a type by name.
     */
    public function knows(string $type): bool
    {
        return $this->vocabulary->has($type);
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
     * Scalars are de-tagged; lists of flat objects (repeater items) are
     * sanitized recursively one level down; anything else is dropped.
     */
    private function value(mixed $value): mixed
    {
        $scalar = $this->scalar($value);

        if ($scalar !== null) {
            return $scalar;
        }

        if (! is_array($value)) {
            return null;
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            $fields = [];

            foreach ($item as $field => $fieldValue) {
                $clean = is_string($field) ? $this->scalar($fieldValue) : null;

                if ($clean !== null) {
                    $fields[$field] = $clean;
                }
            }

            if ($fields !== []) {
                $items[] = $fields;
            }
        }

        return $items === [] ? null : $items;
    }

    /**
     * One leaf value: strings are de-tagged, other scalars pass through, and
     * anything else (arrays, objects, null) is "not a scalar" — the single
     * rule both the top level and repeater items apply.
     */
    private function scalar(mixed $value): string|int|float|bool|null
    {
        if (is_string($value)) {
            return strip_tags($value);
        }

        return is_int($value) || is_float($value) || is_bool($value) ? $value : null;
    }
}
