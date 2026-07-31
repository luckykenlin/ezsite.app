<?php

declare(strict_types=1);

use App\Ai\ChangedBlocks;

/*
 * What a turn did to the page, read two ways: the count goes on the transcript
 * row and decides whether the editor's state is disturbed at all, the keys tell
 * the canvas which blocks to point at once the answer lands.
 */

function block(string $key, array $data = []): array
{
    return ['key' => $key, 'type' => 'hero', 'data' => $data];
}

function changed(): ChangedBlocks
{
    return resolve(ChangedBlocks::class);
}

it('names the blocks an edit added or rewrote, in page order', function (): void {
    $before = [block('k1', ['heading' => 'Old']), block('k2', ['heading' => 'Kept'])];
    $after = [block('k1', ['heading' => 'New']), block('k2', ['heading' => 'Kept']), block('k3', ['heading' => 'Added'])];

    expect(changed()->keys($before, $after))->toBe(['k1', 'k3'])
        ->and(changed()->count($before, $after))->toBe(2);
});

it('has no key to point at for a removal, but still counts it', function (): void {
    // There is nothing left on the canvas to highlight — which is exactly why the
    // count and the keys are separate answers rather than one list.
    $before = [block('k1'), block('k2')];
    $after = [block('k1')];

    expect(changed()->keys($before, $after))->toBeEmpty()
        ->and(changed()->count($before, $after))->toBe(1);
});

it('counts a pure reorder as an edit but highlights nothing', function (): void {
    // No block's content changed, so there is nothing to review inside one — but
    // the page is emphatically different, and a turn recorded as having changed
    // nothing would draw no "review and Save" badge.
    $before = [block('k1', ['heading' => 'A']), block('k2', ['heading' => 'B'])];
    $after = [block('k2', ['heading' => 'B']), block('k1', ['heading' => 'A'])];

    expect(changed()->count($before, $after))->toBe(1)
        ->and(changed()->keys($before, $after))->toBeEmpty();
});

it('reports nothing for a turn that only answered a question', function (): void {
    $blocks = [block('k1', ['heading' => 'Welcome'])];

    expect(changed()->count($blocks, $blocks))->toBe(0)
        ->and(changed()->keys($blocks, $blocks))->toBeEmpty();
});
