<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Pages\UpdatePageBlock;
use App\Ai\PageDraft;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;
use App\Site\Blocks\LayoutAxis;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Sets how one section PRESENTS itself — every layout axis short of the
 * variant: the background it paints, its vertical room, content width, header
 * alignment, column count, item chrome and image crop. "Make that two
 * columns", "left-align the heading", "put the testimonials on a dark band",
 * "drop the cards".
 *
 * ONE restyle verb, deliberately: the model choosing between two overlapping
 * presentation tools costs more than one wider schema. Which axes a given
 * block accepts is the CONTRACT's decision ({@see BlockType::$axes}) — an
 * axis the type never declared comes back as a correction naming the real
 * ones, exactly like an invalid value.
 *
 * The third door onto a server-owned reserved key, alongside
 * {@see SetBlockVariant} (`variant`) and the panel's own bind picker (`bind`).
 * {@see \App\Ai\BlockDataSanitizer} strips `appearance` out of anything
 * {@see UpdateBlockContent} receives for the same reason it strips `variant`: a
 * routine copy edit must never silently restyle a section, and every restyle
 * must pass through one place that validates it.
 *
 * Writes into `data`'s existing `appearance` slot when there is one, rather than
 * rebuilding the array — key order is load-bearing, for the reason spelled out on
 * {@see SetBlockVariant}.
 */
final readonly class SetBlockAppearance implements Tool
{
    /**
     * The value that CLEARS an axis, handing it back to whatever the block's
     * layout was designed to do.
     *
     * Necessary rather than tidy: an unset axis is not the same as any
     * particular case (a testimonials block defaults to a shaded band, a hero to
     * the page background), so without this the model could apply an appearance
     * but never take it back — and the undo stack belongs to the operator, not
     * to the assistant.
     */
    private const string RESET = 'layout-default';

    public function __construct(
        private PageDraft $draft,
        private BlockVocabulary $vocabulary,
        private UpdatePageBlock $update,
    ) {
        //
    }

    public function description(): string
    {
        return 'Change how one section on the page looks without touching its words or its layout '
            .'variant: background, vertical space, content width, header alignment, column count, '
            .'item style (cards or plain), and image shape. Send only the axes you want to change. '
            .'Not every section has every axis — the correction will name the real ones. Use it to '
            .'give a page rhythm, not on every section at once.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $fields = [
            'key' => $schema->string()
                ->description("The block's key, as listed in the current page blocks.")
                ->required(),
        ];

        // One optional enum per axis, its guidance published straight off the
        // value enum — the same argument as BlockType::$description: names
        // alone leave the model choosing by vibe.
        foreach (LayoutAxis::cases() as $axis) {
            $extra = match ($axis) {
                LayoutAxis::Tone => ' Most sections should stay on the page background; a page where '
                    .'every section has its own colour looks worse than one where none do.',
                default => '',
            };

            $fields[$axis->value] = $schema->string()
                ->description(ucfirst(str_replace('_', ' ', $axis->value)).': '
                    .$this->describe($axis).'.'.$extra
                    .' Use "'.self::RESET.'" to hand it back to the layout\'s own default.')
                ->enum([...$axis->values(), self::RESET]);
        }

        return $fields;
    }

    public function handle(Request $request): string
    {
        $arguments = $request->toArray();
        $block = $this->draft->locate($arguments['key'] ?? null);

        if ($block === null) {
            return $this->draft->reply('There is no block with that key on this page.');
        }

        // Site chrome renders outside the section shell, so it has no appearance
        // to set. It should never reach a page draft at all — this is the same
        // belt-and-braces check AddPageBlock makes, for the same reason.
        if (! $this->vocabulary->isAddableToPage($block['type'])) {
            return $this->draft->reply(sprintf(
                "A %s block's appearance is not yours to set — it is part of the site frame, not a section of this page.",
                $block['type'],
            ));
        }

        $contract = $this->vocabulary->get($block['type']);
        $requested = [];

        foreach (LayoutAxis::cases() as $axis) {
            $value = $arguments[$axis->value] ?? null;

            if (! is_string($value)) {
                continue;
            }

            // An axis the type never declared: name the ones it has, so the
            // model's next call is a real one instead of a retry by vibe.
            if ($contract instanceof BlockType && ! $contract->supportsAxis($axis)) {
                return $this->draft->reply(sprintf(
                    "%s is not a layout axis of a %s block, so nothing changed. A %s block's axes are: %s.",
                    $axis->value,
                    $block['type'],
                    $block['type'],
                    implode(', ', array_map(
                        static fn (LayoutAxis $supported): string => $supported->value,
                        $contract->supportedAxes(),
                    )),
                ));
            }

            if ($value !== self::RESET && $axis->resolve($value) === null) {
                return $this->draft->reply(sprintf(
                    "'%s' is not a %s you can set, so nothing changed. The values are: %s.",
                    $value,
                    mb_strtolower($axis->label()),
                    implode(', ', $axis->values()),
                ));
            }

            $requested[$axis->value] = $value;
        }

        if ($requested === []) {
            return $this->draft->reply('Nothing changed: send at least one layout axis to set or reset.');
        }

        $data = $block['data'];
        $appearance = $this->apply($this->stored($data), $requested);

        if ($appearance === []) {
            unset($data[BlockShape::APPEARANCE_KEY]);
        } else {
            $data[BlockShape::APPEARANCE_KEY] = $appearance;
        }

        $this->draft->replace($this->update->handle($this->draft->blocks(), $block['key'], $data));

        return $this->draft->reply(sprintf(
            'Set the %s block to %s.',
            $block['type'],
            $appearance === []
                ? "its layout's own defaults"
                : $this->summarize($appearance),
        ));
    }

    /**
     * The block's stored appearance, narrowed to the axis keys that belong in
     * it — so a malformed or hand-edited value cannot survive an otherwise
     * valid edit.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stored(array $data): array
    {
        $stored = $data[BlockShape::APPEARANCE_KEY] ?? null;
        $stored = is_array($stored) ? $stored : [];

        $narrowed = [];

        foreach (LayoutAxis::cases() as $axis) {
            $value = $stored[$axis->value] ?? null;

            if (is_string($value)) {
                $narrowed[$axis->value] = $value;
            }
        }

        return $narrowed;
    }

    /**
     * Apply the requested axes over what is stored: a given value sets,
     * {@see RESET} clears, and an absent axis is left alone — so "make it
     * dark" does not quietly reset the columns someone chose. Keys re-emitted
     * in {@see LayoutAxis} order so repeated edits never reorder the JSON.
     *
     * @param  array<string, string>  $appearance
     * @param  array<string, string>  $requested
     * @return array<string, string>
     */
    private function apply(array $appearance, array $requested): array
    {
        foreach ($requested as $dimension => $value) {
            if ($value === self::RESET) {
                unset($appearance[$dimension]);

                continue;
            }

            $appearance[$dimension] = $value;
        }

        $ordered = [];

        foreach (LayoutAxis::cases() as $axis) {
            if (array_key_exists($axis->value, $appearance)) {
                $ordered[$axis->value] = $appearance[$axis->value];
            }
        }

        return $ordered;
    }

    /**
     * `value — when to use it` lines for a schema description. Publishing the
     * guidance the enum already carries is what stops the model choosing a
     * background by vibe, the same argument as `BlockType::$description`.
     */
    private function describe(LayoutAxis $axis): string
    {
        return implode('; ', array_map(
            static fn (object $case): string => $case->value.' is '.$case->description(),
            $axis->enumClass()::cases(),
        ));
    }

    /**
     * A stored appearance in words, for the tool's answer.
     *
     * @param  array<string, string>  $appearance
     */
    private function summarize(array $appearance): string
    {
        $parts = [];

        if (array_key_exists(BlockShape::TONE_KEY, $appearance)) {
            $parts[] = 'the '.$appearance[BlockShape::TONE_KEY].' background';
        }

        if (array_key_exists(BlockShape::SPACING_KEY, $appearance)) {
            $parts[] = $appearance[BlockShape::SPACING_KEY].' vertical spacing';
        }

        foreach (LayoutAxis::extended() as $axis) {
            if (array_key_exists($axis->value, $appearance)) {
                $parts[] = str_replace('_', ' ', $axis->value).' '.$appearance[$axis->value];
            }
        }

        return implode(', ', $parts);
    }
}
