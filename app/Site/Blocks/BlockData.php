<?php

declare(strict_types=1);

namespace App\Site\Blocks;

/**
 * The two normalisations a block's `data` goes through on its way to storage.
 *
 * Both were private statics on the page editor, which made them look like editor
 * details. They are not — they define the persisted JSON shape, so the AI writer,
 * the cache layer and anything else that produces block data has to agree with
 * them. {@see \App\Filament\Fabricator\BlockRegistry::normalizeData()} is the
 * mirror operation on the way back out.
 */
final class BlockData
{
    /**
     * Force string keys.
     *
     * Numeric-looking field names arrive as ints from Livewire's JSON round trip
     * (`{"0": …}` decodes to `[0 => …]`), and `json_encode` would then emit a JSON
     * ARRAY instead of an object, silently changing the stored shape.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    public static function stringKeyed(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /**
     * Recursively drop nulls AND empty arrays, re-indexing lists.
     *
     * Named `pruned` rather than the old `withoutNulls`, because dropping empty
     * arrays is the half that surprises people and the old name denied it.
     *
     * This is load-bearing in two places, not just cosmetic:
     *  - The persisted shape stays minimal, so an untouched field never appears in
     *    the JSON and committing an unedited block compares identical to its
     *    stored form — which is what lets the editor skip a canvas reload when the
     *    operator merely clicks around.
     *  - It is why inspecting an empty chrome slot never marks chrome dirty: the
     *    default entry prunes to the same thing it started as.
     *
     * The trade-off is real and deliberate: an operator who empties a repeater or
     * clears a field removes the key rather than storing an empty value. Every
     * consumer treats absent and empty identically, so nothing downstream can tell
     * the difference.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public static function pruned(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = self::pruned($value);
            }

            if ($value !== null && $value !== []) {
                $result[$key] = $value;
            }
        }

        return array_is_list($values) ? array_values($result) : $result;
    }

    /**
     * Both, in the order a commit applies them — prune first so a field whose
     * value became null disappears, then guarantee string keys on what survives.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    public static function committed(array $values): array
    {
        return self::stringKeyed(self::pruned($values));
    }
}
