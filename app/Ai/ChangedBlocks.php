<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * What one assistant turn did to a page's blocks, by comparing the draft it was
 * handed with the draft it returned.
 *
 * Two callers want this from opposite ends. {@see \App\Actions\Pages\ChatEditPage}
 * wants the COUNT, to record on the transcript row and to decide whether the turn
 * touched the page at all; the editor
 * ({@see \App\Filament\Tenant\Resources\PageResource\Concerns\InteractsWithPageChat::pollChatTurn()})
 * wants the KEYS, to point the canvas at what it should look at. Both used to be
 * one private method that only returned the number, so the keys had to be
 * rediscovered — the same diff written twice, free to disagree about what
 * "changed" means.
 *
 * The result cannot come from the worker, incidentally: it diffs against the
 * editor's CURRENT draft, which may have moved on since the turn was dispatched.
 */
final readonly class ChangedBlocks
{
    /**
     * The keys of every block the turn added or edited, in page order.
     *
     * Removals are deliberately absent even though they count as changes: there is
     * no element left on the canvas to point at. Callers that only need to know
     * something happened use {@see count()} instead.
     *
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $before
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $after
     * @return list<string>
     */
    public function keys(array $before, array $after): array
    {
        $keyed = array_column($before, null, 'key');
        $keys = [];

        foreach ($after as $block) {
            $previous = $keyed[$block['key']] ?? null;

            // Absent before: added. Present with different data: rewritten. A
            // block that only moved is neither — its content is what the operator
            // would be looking for, and it has not changed.
            if ($previous === null || $previous['data'] !== $block['data']) {
                $keys[] = $block['key'];
            }
        }

        return $keys;
    }

    /**
     * How many blocks the turn touched — added, edited, or removed.
     *
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $before
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $after
     */
    public function count(array $before, array $after): int
    {
        $beforeKeys = array_column($before, 'key');
        $afterKeys = array_column($after, 'key');

        $changed = count(array_diff($beforeKeys, $afterKeys))
            + count($this->keys($before, $after));

        // A pure reorder changes no block's content, but it is emphatically an
        // edit — counted as one so the turn is not recorded as having done
        // nothing.
        if ($changed === 0 && $beforeKeys !== $afterKeys) {
            return 1;
        }

        return $changed;
    }
}
