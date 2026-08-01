<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Pages\CreatePageFromName;
use App\Actions\Pages\StampPresetDefaults;
use App\Design\StylePreset;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Adds a new page to the site, as a DRAFT.
 *
 * The first verb that writes to the database rather than staging, and the
 * justification is narrow enough to state exactly: a page cannot be staged. Blocks,
 * a style and the chrome all have a "current value" a draft can shadow, but a page
 * either exists or it does not — there is no in-memory form of "there will be a
 * Services page" for the operator to review. So this writes, and every other
 * property of the design exists to make that write safe:
 *
 *  - It is always a DRAFT ({@see CreatePageFromName} hard-codes the status), so it
 *    is invisible to the public, absent from `sitemap.xml` and unreachable by URL.
 *    Nothing about the live site changes.
 *  - It is REVERSIBLE, which is the asymmetry that makes creating allowed where
 *    deleting is not: an unwanted draft page is removed with one click from the
 *    site canvas, whereas a deleted page takes its content with it.
 *  - It does NOT navigate. The operator stays where they are, keeping whatever is
 *    unsaved on the open page — including anything this same turn just wrote. The
 *    new page appears as a card on the site canvas, and the reply says so.
 *
 * Publishing is deliberately NOT offered, here or anywhere: it is the one action
 * that makes content publicly visible and indexable, and an assistant that both
 * writes copy and approves its release is reviewing its own work. The operator
 * publishes from the editor in one click.
 *
 * The optional `sections` skeleton is what stops this producing a blank page the
 * assistant then cannot fill — the block tools all address the OPEN page, so a new
 * page is out of their reach until the operator switches to it. Each requested
 * section arrives with its sample copy and, when the site is on a preset, that
 * preset's own layouts and section rhythm — so the new page looks like it belongs
 * to the site before anyone has typed a word into it.
 */
final readonly class CreatePage implements Tool
{
    public function __construct(
        private CreatePageFromName $create,
        private BlockVocabulary $vocabulary,
        private StampPresetDefaults $stamp,
        private ?StylePreset $preset,
    ) {
        //
    }

    public function description(): string
    {
        return 'Add a new page to the site. It is created as a hidden draft with placeholder copy, '
            .'so nothing on the live site changes and the operator opens it from the site canvas when '
            .'they are ready. You cannot delete or publish a page.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description("The page's name, as it should appear in the site's navigation, e.g. \"Services\". The web address is derived from it.")
                ->required(),
            'sections' => $schema->array()
                ->items($schema->string()->enum($this->vocabulary->pageTypeNames()))
                ->description('The sections to start the page with, top to bottom — usually a hero, one or two '
                    ."middle sections and a call to action. They arrive with placeholder copy in the site's own "
                    .'style. Omit only if the operator asked for an empty page.'),
        ];
    }

    public function handle(Request $request): string
    {
        $arguments = $request->toArray();
        $title = $arguments['title'] ?? null;
        $title = is_string($title) ? mb_trim($title) : '';

        if ($title === '') {
            return 'A new page needs a name — ask the operator what to call it.';
        }

        $sections = $arguments['sections'] ?? null;
        $sections = is_array($sections) ? array_values(array_filter($sections, is_string(...))) : [];

        ['blocks' => $blocks, 'unknown' => $unknown] = $this->skeleton($sections);

        // Nothing at all rather than a page with the good sections: a half-built
        // page is harder for the operator to make sense of than none, and the
        // model can simply call again with a corrected list.
        if ($unknown !== []) {
            return sprintf(
                'Nothing was created: %s %s not a section you can add. Pick from: %s.',
                implode(', ', $unknown),
                count($unknown) === 1 ? 'is' : 'are',
                implode(', ', $this->vocabulary->pageTypeNames()),
            );
        }

        $page = $this->create->handle($title);

        if ($blocks !== []) {
            $page->update(['blocks' => $blocks]);
        }

        return sprintf(
            'Created "%s" as a hidden draft at /%s%s. It is not on the live site: the operator opens it '
            ."from the site canvas, and publishes it themselves when it is ready — you cannot publish or delete a page.\n\n"
            .'Tell them where it is and what is on it. The tools you have address the page currently open, '
            .'so you cannot write copy into this one until they switch to it.',
            $page->title,
            $page->slug,
            $sections === [] ? ' with no sections yet' : ', with '.implode(', ', $sections),
        );
    }

    /**
     * The requested sections as stored blocks — sample copy, then the site's own
     * layouts and section rhythm when it is on a preset — alongside any name that
     * is not a section at all.
     *
     * Validating and building in ONE pass, deliberately: checking the names
     * against the vocabulary first and then looking each one up again meant the
     * second lookup's miss branch could never run, which is unreachable code
     * pretending to be defensiveness. Here the lookup IS the check, so an unknown
     * type and site chrome (known, but never page content — the same boundary
     * {@see \App\Actions\Pages\AddPageBlock} enforces) come out through one path.
     *
     * Sample content rather than empty blocks for the same reason `AddPageBlock`
     * seeds it — an empty required field would fail the block's own validation the
     * first time the operator opened the page — and the copy reads as an obvious
     * placeholder that says to replace it.
     *
     * @param  list<string>  $sections
     * @return array{blocks: list<array{type: string, data: array<string, mixed>}>, unknown: list<string>}
     */
    private function skeleton(array $sections): array
    {
        $blocks = [];
        $unknown = [];

        foreach ($sections as $type) {
            $contract = $this->vocabulary->get($type);

            if (! $contract instanceof BlockType || $contract->isChrome()) {
                $unknown[] = $type;

                continue;
            }

            $data = $contract->sample;
            $variant = $contract->defaultVariant();

            // The default variant first, so key order matches what AddPageBlock
            // writes and a later Save cannot manufacture a revision out of a
            // reordered array — see RecordPageRevision's `===` comparison.
            if ($variant !== null) {
                $data = [BlockShape::VARIANT_KEY => $variant] + $data;
            }

            $blocks[] = ['type' => $type, 'data' => $data];
        }

        // A site on a custom palette has no preset to follow, and the defaults
        // stamped above are then exactly right.
        return [
            'blocks' => $this->preset instanceof StylePreset
                ? $this->stamp->handle($blocks, $this->preset)
                : $blocks,
            'unknown' => $unknown,
        ];
    }
}
