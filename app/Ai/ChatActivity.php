<?php

declare(strict_types=1);

namespace App\Ai;

use Illuminate\Support\Str;

/**
 * "Here is what the assistant is doing right now", in one line, built from a tool
 * call as the provider announces it.
 *
 * The editor chat is tool-driven: the prose answer is written LAST, after every
 * edit has already been made ({@see Agents\PageEditorAgent}). So a request
 * like "rewrite all the copy" streams no text at all for most of its runtime —
 * it is a dozen silent tool calls, and the operator watched a blinking cursor for
 * a minute with no way to tell a working turn from a hung one. These lines are
 * what fills that silence.
 *
 * Deliberately coarse. It names the block and the verb, not the arguments: the
 * content the model is writing is about to appear on the canvas anyway, and a
 * progress line that quotes it is both noisy and wrong the moment the model
 * revises itself. An unknown tool gets NO line rather than a generic one — a made
 * up "working…" is worse than the cursor it replaces.
 */
final readonly class ChatActivity
{
    /**
     * The line for one tool call, or null when the tool has nothing worth saying.
     *
     * @param  array<array-key, mixed>  $arguments  the tool's arguments as the MODEL
     *                                              sent them — neither the keys nor
     *                                              the values are guaranteed, so
     *                                              every read here is defensive
     */
    public function forToolCall(PageDraft $draft, string $tool, array $arguments): ?string
    {
        // Resolved against the draft as it stands when the call is ANNOUNCED,
        // which is before the tool runs — so a block being removed still has a
        // name here. A key the model invented resolves to null and the line
        // degrades to its indefinite form rather than naming a block that is not
        // there.
        $block = $this->blockLabel($draft, $arguments['key'] ?? null);

        $added = $arguments['type'] ?? null;

        // Substituted with str_replace rather than through `__()`'s own
        // replacements: the literal keys are what lets a translation scanner find
        // them. `__()` is typed `string|array` either way — a key CAN resolve to a
        // whole translation group — so the result is narrowed below rather than
        // assumed; a line that somehow came back as an array is no line at all.
        $line = match ($tool) {
            'UpdateBlockContent' => $block === null
                ? __('Rewriting a block…')
                : str_replace(':block', $block, __('Rewriting the :block block…')),
            'AddBlock' => is_string($added)
                ? str_replace(':block', Str::headline($added), __('Adding a :block block…'))
                : __('Adding a block…'),
            'RemoveBlock' => $block === null
                ? __('Removing a block…')
                : str_replace(':block', $block, __('Removing the :block block…')),
            'ReorderBlocks' => __('Reordering the page…'),
            'SetBlockVariant' => $block === null
                ? __('Changing a block layout…')
                : str_replace(':block', $block, __('Changing the :block layout…')),
            // Named without the style it is switching to: the argument is a
            // preset SLUG the operator has never seen, and by the time this
            // renders the canvas is about to show them the answer anyway.
            'SetSiteStyle' => __('Restyling the site…'),
            default => null,
        };

        return is_string($line) ? $line : null;
    }

    /**
     * What the operator calls the block with this key — the same `Str::headline`
     * name the inspector and the canvas label it with, so a progress line and the
     * page agree.
     */
    private function blockLabel(PageDraft $draft, mixed $key): ?string
    {
        if (! is_string($key)) {
            return null;
        }

        $block = $draft->find($key);

        return $block === null ? null : Str::headline($block['type']);
    }
}
